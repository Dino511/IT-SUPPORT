<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\DeviceRequest;
use App\Models\DeviceType;
use App\Models\Employee;
use App\Support\CompanyScope;
use App\Support\Inventory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Requests filed by admins on behalf of employees, and their handling.
 *
 * pending --approve--> approved --complete--> completed
 *    |--reject--> rejected
 *    \--cancel--> cancelled
 *
 * Approving a repair/damage/lost report updates the device status right away;
 * completing applies the final outcome (assign, return to stock, retire...).
 * An admin cannot handle a request for their own employee record; the super
 * admin can.
 *
 * Routes: admin/requests/* (both roles).
 */
class RequestController extends Controller
{
    /**
     * Request queue, narrowed to the selected company. Shows open requests
     * (pending + approved) unless ?status= is given (empty = all). Sorted by
     * status, then priority (high first), then newest.
     */
    public function index(Request $request)
    {
        $requests = CompanyScope::applyVia(DeviceRequest::with('employee.company', 'device', 'handler', 'requester'), 'employee')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s), fn ($q) => $request->has('status') ? $q : $q->whereIn('status', ['pending', 'approved']))
            ->when($request->type, fn ($q, $t) => $q->where('request_type', $t))
            ->when($request->q, function ($query, $q) {
                $query->where(fn ($w) => $w->where('reference_no', 'like', "%{$q}%")
                    ->orWhereHas('employee', fn ($e) => $e->where('first_name', 'like', "%{$q}%")
                        ->orWhere('last_name', 'like', "%{$q}%")->orWhere('employee_no', 'like', "%{$q}%")));
            })
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END")
            ->orderByRaw("CASE priority WHEN 'high' THEN 0 WHEN 'normal' THEN 1 ELSE 2 END")
            ->latest()
            ->paginate(20)->withQueryString();

        return view('admin.requests.index', compact('requests'));
    }

    /**
     * "New Request" form. Accepts ?employee=, ?device= and ?type= so other pages
     * can link straight to a pre-filled form (e.g. "Repair" on an employee's device).
     * Also passes the data for the device dropdown and the employee snapshot panel.
     */
    public function create(Request $request)
    {
        // employees of the selected company (plus one passed in from a link)
        $employees = Employee::where('is_active', true)->with('devices', 'company')
            ->when(CompanyScope::id(), fn ($q, $id) => $q->where(fn ($w) => $w->where('company_id', $id)->orWhere('id', $request->integer('employee'))))
            ->orderBy('last_name')->orderBy('first_name')->get();

        return view('admin.requests.form', [
            'employees' => $employees,
            // employee id => devices they hold, for the device dropdown
            'devicesByEmployee' => $employees->mapWithKeys(fn ($e) => [$e->id => $e->devices->map(fn ($d) => [
                'id' => $d->id, 'label' => "{$d->asset_tag} - {$d->type_label} {$d->name}", 'status' => $d->status_label,
            ])->values()]),
            // shown in the employee snapshot panel
            'employeeInfo' => $employees->mapWithKeys(fn ($e) => [$e->id => [
                'name' => $e->full_name, 'no' => $e->employee_no, 'position' => $e->position,
                'department' => $e->department, 'company' => $e->company?->name, 'email' => $e->email, 'contact' => $e->contact_no,
            ]]),
            'types' => DeviceType::active(),
            'selected' => [
                'employee_id' => $request->query('employee'),
                'device_id' => $request->query('device'),
                'request_type' => $request->query('type', 'new_device'),
            ],
        ]);
    }

    /**
     * File a request (status "pending"). A new_device request needs a device
     * type; every other type needs one or more devices the employee currently
     * holds (device_ids[], or a single device_id). Each device becomes its own
     * request, since each gets its own approval and outcome, and only one open
     * request per device is allowed.
     */
    public function store(Request $request)
    {
        if (! $request->has('device_ids') && $request->filled('device_id')) {
            $request->merge(['device_ids' => [$request->input('device_id')]]);
        }

        $data = $request->validate([
            'employee_id' => ['required', Rule::exists('employees', 'id')->where('is_active', true)],
            'request_type' => ['required', Rule::in(array_keys(DeviceRequest::TYPES))],
            'device_type' => ['nullable', 'required_if:request_type,new_device', Rule::exists('device_types', 'code')->where('is_active', true)],
            'device_ids' => ['nullable', 'required_unless:request_type,new_device', 'array'],
            'device_ids.*' => ['integer', 'distinct', Rule::exists('devices', 'id')->where('current_employee_id', $request->integer('employee_id'))],
            'priority' => ['required', Rule::in(array_keys(DeviceRequest::PRIORITIES))],
            'reason' => ['required', 'string', 'max:2000'],
            'incident_date' => ['nullable', 'date', 'before_or_equal:now'],
        ], [
            'device_ids.required_unless' => 'Select the device this request is about.',
            'device_ids.*.exists' => 'A selected device is not assigned to this employee.',
        ]);
        $deviceIds = $data['request_type'] === 'new_device' ? [null] : array_map('intval', $data['device_ids']);
        unset($data['device_ids']);

        if ($data['request_type'] !== 'new_device') {
            $data['device_type'] = null;
            $busy = Device::whereIn('id', $deviceIds)
                ->whereHas('requests', fn ($q) => $q->whereIn('status', ['pending', 'approved']))->pluck('asset_tag');
            if ($busy->isNotEmpty()) {
                return back()->withInput()->withErrors(['device_ids' => 'There is already an open request for '.$busy->implode(', ').'.']);
            }
        }

        $filed = DB::transaction(fn () => collect($deviceIds)->map(function ($deviceId) use ($data) {
            $deviceRequest = DeviceRequest::create(['device_id' => $deviceId] + $data + ['requested_by' => auth()->id(), 'status' => 'pending']);
            $employee = $deviceRequest->employee;

            AuditLog::record('request.created',
                auth()->user()->name." filed {$deviceRequest->reference_no} ({$deviceRequest->type_label}) for {$employee->full_name} [{$employee->employee_no}]"
                    .($deviceRequest->device ? " - {$deviceRequest->device->asset_tag}" : " - wants a {$deviceRequest->device_type_label}"),
                ['employee' => $employee, 'device' => $deviceRequest->device, 'request' => $deviceRequest]);

            return $deviceRequest;
        }));

        if ($filed->count() === 1) {
            return redirect()->route('admin.requests.show', $filed->first())->with('success', "Request {$filed->first()->reference_no} filed.");
        }

        return redirect()->route('admin.requests.index')
            ->with('success', "{$filed->count()} requests filed, one per device: ".$filed->pluck('reference_no')->implode(', ').'.');
    }

    /** Cancel a pending request (any admin may cancel). Logged. */
    public function cancel(DeviceRequest $deviceRequest)
    {
        $this->ensureStatus($deviceRequest, ['pending']);

        DB::transaction(function () use ($deviceRequest) {
            $deviceRequest->update(['status' => 'cancelled']);
            AuditLog::record('request.cancelled', "{$deviceRequest->reference_no} cancelled by ".auth()->user()->name,
                ['employee' => $deviceRequest->employee, 'device' => $deviceRequest->device, 'request' => $deviceRequest]);
        });

        return back()->with('success', "{$deviceRequest->reference_no} cancelled.");
    }

    /**
     * Request page with the actions allowed in its current status. The list of
     * available devices (for new device / replacement) only holds units of the
     * type the request is about, with the employee's own company first.
     */
    public function show(DeviceRequest $deviceRequest)
    {
        $deviceRequest->load('employee.company', 'device.currentEmployee', 'device.company', 'requester', 'handler', 'logs.user');
        $wantedType = $this->wantedType($deviceRequest);

        return view('admin.requests.show', [
            'req' => $deviceRequest,
            'isOwnRequest' => $this->isOwnRequest($deviceRequest),
            'wantedType' => $wantedType,
            // units owned by the employee's company first
            'availableDevices' => Device::with('company')->where('status', 'available')
                ->when($wantedType, fn ($query, $type) => $query->where('type', $type))
                ->orderByRaw('CASE WHEN company_id = ? THEN 0 ELSE 1 END', [$deviceRequest->employee->company_id ?? 0])
                ->orderBy('type')->orderBy('asset_tag')->get(),
        ]);
    }

    /**
     * Approve a pending request. Reports change the device immediately:
     *   repair  -> device "in_repair" (employee keeps it on record)
     *   damaged -> device "damaged"
     *   lost    -> device "lost" and the employee's assignment is closed
     * new_device and return requests change nothing until they are completed.
     */
    public function approve(Request $request, DeviceRequest $deviceRequest)
    {
        $this->ensureStatus($deviceRequest, ['pending']);
        $this->ensureNotOwnRequest($deviceRequest);
        $remarks = $request->validate(['admin_remarks' => ['nullable', 'string', 'max:2000']])['admin_remarks'] ?? null;

        DB::transaction(function () use ($deviceRequest, $remarks) {
            $device = $deviceRequest->device;
            match ($deviceRequest->request_type) {
                'repair' => Inventory::setStatus($device, 'in_repair', 'Sent for repair', $deviceRequest),
                'damaged' => Inventory::setStatus($device, 'damaged', 'Reported damaged', $deviceRequest),
                'lost' => $device->current_employee_id
                    ? Inventory::unassign($device, 'lost', ['remarks' => 'Reported lost'], $deviceRequest)
                    : Inventory::setStatus($device, 'lost', 'Reported lost', $deviceRequest),
                default => null,
            };

            $this->handle($deviceRequest, 'approved', $remarks);
        });

        return back()->with('success', "{$deviceRequest->reference_no} approved.");
    }

    /** Reject a pending request; a reason (admin_remarks) is required. */
    public function reject(Request $request, DeviceRequest $deviceRequest)
    {
        $this->ensureStatus($deviceRequest, ['pending']);
        $this->ensureNotOwnRequest($deviceRequest);
        $remarks = $request->validate(['admin_remarks' => ['required', 'string', 'max:2000']])['admin_remarks'];

        DB::transaction(fn () => $this->handle($deviceRequest, 'rejected', $remarks));

        return back()->with('success', "{$deviceRequest->reference_no} rejected.");
    }

    /**
     * Complete an approved request by applying its outcome:
     *   new_device             -> assign the chosen available device (device_id required)
     *   return                 -> take the device back to stock with its condition
     *   repair / damaged / lost -> device_status decides: "assigned" (fixed, back to the
     *                             employee), available, damaged, lost or retired;
     *                             optionally assign a replacement device (device_id)
     * The device handed out must be of the requested type (see wantedType()).
     */
    public function complete(Request $request, DeviceRequest $deviceRequest)
    {
        $this->ensureStatus($deviceRequest, ['approved']);
        $this->ensureNotOwnRequest($deviceRequest);
        $type = $deviceRequest->request_type;
        $wantedType = $this->wantedType($deviceRequest);

        $data = $request->validate([
            'device_id' => ['bail', $type === 'new_device' ? 'required' : 'nullable', Rule::exists('devices', 'id')->where('status', 'available'),
                function ($attribute, $value, $fail) use ($wantedType) {
                    if ($wantedType && Device::whereKey($value)->value('type') !== $wantedType) {
                        $fail('Choose a device of the requested type ('.(DeviceType::names()[$wantedType] ?? $wantedType).').');
                    }
                }],
            'device_status' => [in_array($type, ['repair', 'damaged', 'lost']) ? 'required' : 'nullable',
                Rule::in(['assigned', 'available', 'damaged', 'lost', 'retired'])],
            'condition' => ['nullable', Rule::in(array_keys(Device::CONDITIONS))],
            'admin_remarks' => ['nullable', 'string', 'max:2000'],
        ], ['device_id.exists' => 'The selected device is no longer available.']);

        DB::transaction(function () use ($deviceRequest, $data, $type) {
            $device = $deviceRequest->device;
            $employee = $deviceRequest->employee;

            if ($type === 'return') {
                Inventory::unassign($device, 'available', ['condition' => $data['condition'] ?? null, 'remarks' => $data['admin_remarks'] ?? null], $deviceRequest);
            }

            if (in_array($type, ['repair', 'damaged', 'lost'])) {
                $status = $data['device_status'];
                if ($status === 'assigned') {
                    if ($device->current_employee_id !== $employee->id) {
                        throw ValidationException::withMessages(['device_status' => 'The device is no longer with this employee; choose another outcome.']);
                    }
                    Inventory::setStatus($device, 'assigned', 'Returned to employee after '.$deviceRequest->type_label, $deviceRequest);
                } elseif ($device->current_employee_id) {
                    Inventory::unassign($device, $status, ['condition' => $data['condition'] ?? null, 'remarks' => $data['admin_remarks'] ?? null], $deviceRequest);
                } elseif ($device->status !== $status) {
                    Inventory::setStatus($device, $status, $data['admin_remarks'] ?? null, $deviceRequest);
                }
            }

            // new device, or a replacement unit for repair/damaged/lost
            if (! empty($data['device_id'])) {
                // lock the row so two admins can't hand out the same unit at once
                $assigned = Device::lockForUpdate()->findOrFail($data['device_id']);
                Inventory::assign($assigned, $employee, ['remarks' => $type === 'new_device' ? null : 'Replacement unit'], $deviceRequest);
                if ($type === 'new_device') {
                    $deviceRequest->device_id = $assigned->id;
                }
            }

            $this->handle($deviceRequest, 'completed', $data['admin_remarks'] ?? null);
        });

        return back()->with('success', "{$deviceRequest->reference_no} completed.");
    }

    /**
     * The device type a request may be given: the type asked for on a new_device
     * request, or the reported device's type for a replacement (a broken laptop
     * is replaced by a laptop). Null when unknown, which allows any type.
     */
    private function wantedType(DeviceRequest $deviceRequest): ?string
    {
        return $deviceRequest->device_type ?? $deviceRequest->device?->type;
    }

    /** Guard: the action is only valid in the given statuses (e.g. approve needs "pending"). */
    private function ensureStatus(DeviceRequest $deviceRequest, array $allowed): void
    {
        if (! in_array($deviceRequest->status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => "Request is already {$deviceRequest->status_label}."]);
        }
    }

    /**
     * True when an Admin is looking at a request for their own employee record
     * (segregation of duties). Always false for the Super Admin.
     */
    private function isOwnRequest(DeviceRequest $deviceRequest): bool
    {
        $user = auth()->user();

        return ! $user->isSuperAdmin() && $user->employee_id && $user->employee_id === $deviceRequest->employee_id;
    }

    /** Guard used by approve / reject / complete: blocks handling your own request. */
    private function ensureNotOwnRequest(DeviceRequest $deviceRequest): void
    {
        if ($this->isOwnRequest($deviceRequest)) {
            throw ValidationException::withMessages(['status' => 'You cannot process a request for your own devices. Another admin or the Super Admin must handle it.']);
        }
    }

    /**
     * Move the request to $status, stamp who handled it and when, keep the
     * remarks, and log "request.{status}".
     */
    private function handle(DeviceRequest $deviceRequest, string $status, ?string $remarks): void
    {
        $deviceRequest->fill([
            'status' => $status,
            'handled_by' => auth()->id(),
            'handled_at' => now(),
            'admin_remarks' => $remarks ?? $deviceRequest->admin_remarks,
        ])->save();

        AuditLog::record("request.{$status}",
            "{$deviceRequest->reference_no} ({$deviceRequest->type_label}) {$status} by ".auth()->user()->name
                .($remarks ? ". Remarks: {$remarks}" : ''),
            ['employee' => $deviceRequest->employee, 'device' => $deviceRequest->device, 'request' => $deviceRequest]);
    }
}
