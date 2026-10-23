<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\Employee;
use App\Models\Lookup;
use App\Support\CompanyScope;
use App\Support\Inventory;
use App\Support\Lookups;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Devices: list, log new, view, edit, plus the hand-over actions
 * (assign / return / change status) and the purchase order (PO) file.
 *
 * Routes: admin/devices/* (both roles). All state changes go through
 * App\Support\Inventory inside a transaction and are written to the audit log.
 */
class DeviceController extends Controller
{
    /** Device columns backed by a master data list. */
    private const LOOKUP_FIELDS = ['brand', 'processor', 'memory', 'storage', 'operating_system', 'color', 'supplier'];

    /**
     * Device list, narrowed to the selected company. Filters: q (asset tag,
     * brand, model, serial, IMEI), type and status.
     */
    public function index(Request $request)
    {
        $devices = CompanyScope::apply(Device::with('currentEmployee', 'company'))
            ->when($request->q, function ($query, $q) {
                $query->where(fn ($w) => $w->where('asset_tag', 'like', "%{$q}%")
                    ->orWhere('brand', 'like', "%{$q}%")
                    ->orWhere('model', 'like', "%{$q}%")
                    ->orWhere('serial_number', 'like', "%{$q}%")
                    ->orWhere('imei', 'like', "%{$q}%"));
            })
            ->when($request->type, fn ($query, $type) => $query->where('type', $type))
            ->when($request->status, fn ($query, $status) => $query->where('status', $status))
            ->orderBy('asset_tag')
            ->paginate(20)->withQueryString();

        return view('admin.devices.index', ['devices' => $devices, 'types' => DeviceType::names()]);
    }

    /** "Log New Device" form; the company defaults to the one being viewed. */
    public function create()
    {
        return $this->form(new Device(['status' => 'available', 'condition' => 'new', 'company_id' => CompanyScope::id()]));
    }

    /**
     * Save a new device: normalise dropdown values (super admin's new values join
     * the master data), generate the asset tag from the type prefix, log it, and
     * store the PO file if one was uploaded. New devices start as "available".
     */
    public function store(Request $request)
    {
        $data = $this->validated($request);

        $device = DB::transaction(function () use ($data, $request) {
            $data = $this->rememberLookups($data);
            $data['asset_tag'] = DeviceType::generateAssetTag($data['type']);

            $device = Device::create($data + ['created_by' => auth()->id(), 'status' => 'available']);
            AuditLog::record('device.created', "Logged new {$device->type_label}: {$device->asset_tag} ({$device->name}) for {$device->company->name}"
                .($device->serial_number ? ", S/N {$device->serial_number}" : ''), ['device' => $device]);
            $this->savePoFile($request, $device);

            return $device;
        });

        return redirect()->route('admin.devices.show', $device)->with('success', "Device added to inventory as {$device->asset_tag}.");
    }

    /** Device page: specs, holder, purchase/PO, hand-over history, requests and audit trail. */
    public function show(Device $device)
    {
        $device->load(['company', 'currentEmployee.company', 'creator', 'assignments' => fn ($q) => $q->latest('assigned_at'),
            'assignments.employee', 'assignments.assignedBy', 'assignments.returnedTo',
            'requests' => fn ($q) => $q->latest(), 'requests.employee']);

        return view('admin.devices.show', [
            'device' => $device,
            'logs' => $device->logs()->with('user')->latest('created_at')->get(),
            // for the assign dialog: the device's own company is listed first
            'employeesByCompany' => Employee::with('company')->where('is_active', true)
                ->orderBy('last_name')->orderBy('first_name')->get()
                ->groupBy(fn ($e) => $e->company?->name ?? 'No company')
                ->sortBy(fn ($group, $name) => ($name === $device->company?->name ? '0' : '1').$name),
        ]);
    }

    public function edit(Device $device)
    {
        return $this->form($device);
    }

    /**
     * Save edits. Changing the type re-generates the asset tag (it follows the
     * type prefix). Changed fields are recorded old -> new in the audit log.
     * The PO file can be replaced (new upload) or removed (remove_po_file).
     */
    public function update(Request $request, Device $device)
    {
        $data = $this->validated($request, $device);

        DB::transaction(function () use ($device, $data, $request) {
            $data = $this->rememberLookups($data);
            // the asset tag follows the type prefix, so a type change re-tags the device
            if ($data['type'] !== $device->type) {
                $data['asset_tag'] = DeviceType::generateAssetTag($data['type']);
            }

            // capture old -> new values before saving, formatted for the audit log
            $device->fill($data);
            $changes = collect($device->getDirty())
                ->mapWithKeys(fn ($new, $key) => [$key => [$device->getOriginal($key), $new]])
                ->map(fn ($pair) => array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : (is_array($v) ? json_encode($v) : $v), $pair))
                ->all();
            if (isset($changes['company_id'])) { // log company names, not ids
                $names = Company::whereIn('id', array_filter($changes['company_id']))->pluck('name', 'id');
                $changes['company'] = array_map(fn ($id) => $names[$id] ?? null, $changes['company_id']);
                unset($changes['company_id']);
            }
            $device->save();

            if ($changes) {
                AuditLog::record('device.updated', "Updated {$device->asset_tag}: ".implode(', ', array_keys($changes)),
                    ['device' => $device, 'changes' => $changes]);
            }

            if ($request->boolean('remove_po_file') && ! $request->hasFile('po_file')) {
                $this->deletePoFile($device);
            }
            $this->savePoFile($request, $device);
        });

        return redirect()->route('admin.devices.show', $device)->with('success', 'Device updated.');
    }

    /**
     * View the purchase order file in the browser (PDF/images) or download it.
     * Files live on the private "local" disk (storage/app/private/po/{device id}),
     * so this signed-in route is the only way to reach them.
     */
    public function poFile(Device $device)
    {
        abort_unless($device->po_file_path && Storage::disk('local')->exists($device->po_file_path), 404, 'No PO file for this device.');

        return Storage::disk('local')->response($device->po_file_path, $device->po_file_name);
    }

    /** Store an uploaded PO file (field "po_file"), replacing any previous one. Logged. */
    private function savePoFile(Request $request, Device $device): void
    {
        if (! $request->hasFile('po_file')) {
            return;
        }

        $file = $request->file('po_file');
        $old = $device->po_file_name;
        $this->deletePoFile($device, log: false);

        $device->update([
            'po_file_path' => $file->store("po/{$device->id}", 'local'),
            'po_file_name' => $file->getClientOriginalName(),
        ]);
        AuditLog::record('device.po_uploaded', "PO file \"{$device->po_file_name}\" uploaded for {$device->asset_tag}"
            .($old ? " (replaced \"{$old}\")" : ''), ['device' => $device]);
    }

    /** Delete the device's PO file from disk and clear the columns ($log = false when replacing). */
    private function deletePoFile(Device $device, bool $log = true): void
    {
        if (! $device->po_file_path) {
            return;
        }

        Storage::disk('local')->delete($device->po_file_path);
        $name = $device->po_file_name;
        $device->update(['po_file_path' => null, 'po_file_name' => null]);
        if ($log) {
            AuditLog::record('device.po_removed', "PO file \"{$name}\" removed from {$device->asset_tag}", ['device' => $device]);
        }
    }

    /**
     * "Assign" dialog: give an available device to an active employee (any
     * company; other companies are logged as a loan). See Inventory::assign.
     */
    public function assign(Request $request, Device $device)
    {
        $data = $request->validate([
            'employee_id' => ['required', Rule::exists('employees', 'id')->where('is_active', true)],
            'assigned_at' => ['nullable', 'date'],
            'condition' => ['nullable', Rule::in(array_keys(Device::CONDITIONS))],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(fn () => Inventory::assign($device, Employee::findOrFail($data['employee_id']), $data));

        return back()->with('success', 'Device assigned.');
    }

    /**
     * "Return" dialog: take the device back from its holder and choose the
     * status it returns to (available, in repair, damaged, lost, retired).
     */
    public function return(Request $request, Device $device)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['available', 'in_repair', 'damaged', 'lost', 'retired'])],
            'condition' => ['nullable', Rule::in(array_keys(Device::CONDITIONS))],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        abort_unless($device->current_employee_id, 422, 'Device is not assigned.');
        DB::transaction(fn () => Inventory::unassign($device, $data['status'], $data));

        return back()->with('success', 'Device returned.');
    }

    /** "Change Status" dialog: set the status without changing who holds the device. */
    public function status(Request $request, Device $device)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Device::STATUSES))],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        // assigned is only reachable through the assign action; unassigned
        // devices cannot be "assigned" by a bare status change.
        if ($data['status'] === 'assigned' && ! $device->current_employee_id) {
            return back()->with('error', 'Use "Assign" to give the device to an employee.');
        }
        if ($data['status'] === 'available' && $device->current_employee_id) {
            return back()->with('error', 'Use "Return" to take the device back from the employee first.');
        }

        DB::transaction(fn () => Inventory::setStatus($device, $data['status'], $data['remarks'] ?? null));

        return back()->with('success', 'Status updated.');
    }

    /**
     * Shared create/edit form. Dropdown options come from the master data;
     * inactive companies/types are offered only if the device already uses them.
     */
    private function form(Device $device)
    {
        $options = collect(self::LOOKUP_FIELDS)->mapWithKeys(fn ($f) => [$f => Lookup::values($f)])->all();

        return view('admin.devices.form', [
            'device' => $device,
            'companies' => Company::where('is_active', true)->orWhere('id', $device->company_id)->orderBy('name')->get(),
            'types' => DeviceType::where('is_active', true)->orWhere('code', $device->type)->orderBy('name')->get(),
            'options' => $options,
            'modelsByBrand' => Lookup::modelsByBrand(),
            'specNames' => Lookup::values('spec_name'),
        ]);
    }

    /**
     * Validate the device form. Dropdown fields use Lookups::rule (admins must
     * pick existing options). The extra spec rows arrive as parallel arrays
     * spec_keys[] / spec_values[] and are folded into the `specs` JSON map.
     * The PO upload is validated here but stored separately (savePoFile).
     *
     * @return array attributes ready for Device::fill()
     */
    private function validated(Request $request, ?Device $device = null): array
    {
        $types = DeviceType::where('is_active', true)->pluck('code')->push($device?->type)->filter()->all();
        $lookup = fn (string $field, int $max) => ['nullable', 'string', "max:{$max}", Lookups::rule($field, $device?->{$field})];

        $data = $request->validate([
            'company_id' => ['required', Rule::exists('companies', 'id')->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $device?->company_id))],
            'po_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx', 'max:10240'],
            'type' => ['required', Rule::in($types)],
            'brand' => ['required', 'string', 'max:100', Lookups::rule('brand', $device?->brand)],
            'model' => ['required', 'string', 'max:150', Lookups::rule('model', $device?->brand === $request->input('brand') ? $device?->model : null, $request->input('brand'))],
            'serial_number' => ['nullable', 'string', 'max:100', Rule::unique('devices')->ignore($device)],
            'imei' => ['nullable', 'string', 'max:50'],
            'processor' => $lookup('processor', 150),
            'memory' => $lookup('memory', 50),
            'storage' => $lookup('storage', 50),
            'operating_system' => $lookup('operating_system', 100),
            'color' => $lookup('color', 50),
            'supplier' => $lookup('supplier', 150),
            'purchase_date' => ['nullable', 'date'],
            'purchase_cost' => ['nullable', 'numeric', 'min:0'],
            'warranty_until' => ['nullable', 'date'],
            'condition' => ['required', Rule::in(array_keys(Device::CONDITIONS))],
            'notes' => ['nullable', 'string', 'max:2000'],
            'spec_keys' => ['array'],
            'spec_keys.*' => ['nullable', 'string', 'max:100', Lookups::rule('spec_name', array_keys($device?->specs ?? []))],
            'spec_values' => ['array'],
            'spec_values.*' => ['nullable', 'string', 'max:255'],
        ], [], ['po_file' => 'PO file', 'company_id' => 'company']);

        $specs = [];
        foreach ($data['spec_keys'] ?? [] as $i => $key) {
            if (filled($key)) {
                $specs[trim($key)] = $data['spec_values'][$i] ?? '';
            }
        }
        unset($data['spec_keys'], $data['spec_values'], $data['po_file']);
        $data['specs'] = $specs ?: null;

        return $data;
    }

    /** Normalise dropdown values; the super admin's new values join the master data. */
    private function rememberLookups(array $data): array
    {
        foreach (self::LOOKUP_FIELDS as $field) {
            $data[$field] = Lookups::remember($field, $data[$field] ?? null);
        }
        $data['model'] = Lookups::remember('model', $data['model'], $data['brand']);

        if ($data['specs']) {
            $data['specs'] = collect($data['specs'])
                ->mapWithKeys(fn ($value, $key) => [Lookups::remember('spec_name', $key) => $value])->all();
        }

        return $data;
    }
}
