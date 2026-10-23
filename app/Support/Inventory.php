<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Device;
use App\Models\DeviceAssignment;
use App\Models\DeviceRequest;
use App\Models\Employee;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Device lifecycle operations - the only place that moves a device between
 * holders and statuses. Used by DeviceController (manual assign / return /
 * status change) and RequestController (approving and completing requests).
 *
 * Every operation writes an audit log entry. Callers must wrap calls in
 * DB::transaction() so the device, its assignment and the log stay consistent.
 */
class Inventory
{
    /**
     * Hand a device to an employee: opens a DeviceAssignment and marks the
     * device "assigned". Assigning to someone outside the owning company is
     * allowed and recorded as a loan in the audit description.
     *
     * @param  array  $data     optional 'assigned_at', 'condition', 'remarks'
     * @param  DeviceRequest|null  $request  request being fulfilled, if any (linked in the log)
     *
     * @throws ValidationException when the device is not available
     */
    public static function assign(Device $device, Employee $employee, array $data = [], ?DeviceRequest $request = null): DeviceAssignment
    {
        if ($device->status !== 'available') {
            throw ValidationException::withMessages([
                'device_id' => "{$device->asset_tag} is not available (current status: {$device->status_label}).",
            ]);
        }

        $assignment = $device->assignments()->create([
            'employee_id' => $employee->id,
            'device_request_id' => $request?->id,
            'assigned_by' => Auth::id(),
            'assigned_at' => $data['assigned_at'] ?? now(),
            'condition_on_assign' => $data['condition'] ?? $device->condition,
            'remarks' => $data['remarks'] ?? null,
        ]);

        $device->update(['status' => 'assigned', 'current_employee_id' => $employee->id]);

        // cross-company hand-over: flag it so the audit trail shows the loan
        $loan = $device->company_id && $employee->company_id && $device->company_id !== $employee->company_id
            ? " (on loan from {$device->company->code} to {$employee->company->code})" : '';

        AuditLog::record('device.assigned',
            "{$device->asset_tag} ({$device->name}) assigned to {$employee->full_name} [{$employee->employee_no}]{$loan}"
                .($request ? " for request {$request->reference_no}" : '')
                .(! empty($data['remarks']) ? ". Remarks: {$data['remarks']}" : ''),
            ['device' => $device, 'employee' => $employee, 'request' => $request]);

        return $assignment;
    }

    /**
     * Take a device back from its holder: closes the open assignment (return
     * date, condition, remarks appended) and gives the device a new status.
     * Status 'lost' is logged as "device.lost" rather than a return.
     *
     * @param  string  $newStatus  available | in_repair | damaged | lost | retired
     * @param  array   $data       optional 'returned_at', 'condition' (also updates the device), 'remarks'
     */
    public static function unassign(Device $device, string $newStatus = 'available', array $data = [], ?DeviceRequest $request = null): void
    {
        $assignment = $device->activeAssignment;
        $employee = $device->currentEmployee;

        if ($assignment) {
            $assignment->update([
                'returned_at' => $data['returned_at'] ?? now(),
                'returned_to' => Auth::id(),
                'condition_on_return' => $data['condition'] ?? null,
                'remarks' => trim(($assignment->remarks ? $assignment->remarks."\n" : '').($data['remarks'] ?? '')) ?: null,
            ]);
        }

        $updates = ['status' => $newStatus, 'current_employee_id' => null];
        if (! empty($data['condition'])) {
            $updates['condition'] = $data['condition'];
        }
        $device->update($updates);

        $holder = $employee ? "{$employee->full_name} [{$employee->employee_no}]" : null;
        $summary = $newStatus === 'lost'
            ? "{$device->asset_tag} ({$device->name}) reported lost".($holder ? " while assigned to {$holder}" : '').'; assignment closed'
            : "{$device->asset_tag} ({$device->name}) returned".($holder ? " by {$holder}" : '')." - now {$device->status_label}";

        AuditLog::record($newStatus === 'lost' ? 'device.lost' : 'device.returned',
            $summary
                .($request ? " (request {$request->reference_no})" : '')
                .(! empty($data['remarks']) ? ". Remarks: {$data['remarks']}" : ''),
            ['device' => $device, 'employee' => $employee, 'request' => $request]);
    }

    /**
     * Change status while keeping the current holder (e.g. assigned -> in_repair
     * when a repair is approved, and back to assigned when it is fixed).
     * The old and new status are stored in the log's "changes".
     */
    public static function setStatus(Device $device, string $status, ?string $remarks = null, ?DeviceRequest $request = null): void
    {
        $old = $device->status_label;
        $device->update(['status' => $status]);

        AuditLog::record('device.status',
            "{$device->asset_tag} status changed from {$old} to {$device->status_label}"
                .($request ? " (request {$request->reference_no})" : '')
                .($remarks ? ". Remarks: {$remarks}" : ''),
            ['device' => $device, 'employee' => $device->currentEmployee, 'request' => $request,
                'changes' => ['status' => [$old, $device->status_label]]]);
    }
}
