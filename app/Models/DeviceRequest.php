<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A request filed by an admin on behalf of an employee: a new device, or a
 * repair / damage / lost / return report about a device the employee holds.
 *
 * Workflow (handled in Admin\RequestController):
 *   pending --approve--> approved --complete--> completed
 *   pending --reject---> rejected
 *   pending --cancel---> cancelled
 *
 * `device_type` is used only for new_device requests (a DeviceType code);
 * `device_id` points at the employee's device for the other types, and is
 * filled in with the issued device when a new_device request is completed.
 *
 * @property string $reference_no e.g. REQ-20261023-4FD42
 * @property string $request_type one of TYPES
 * @property string $status one of STATUSES
 */
class DeviceRequest extends Model
{
    /** Allowed values of `request_type` => label. */
    public const TYPES = [
        'new_device' => 'New Device',
        'repair' => 'Repair',
        'damaged' => 'Damage Report',
        'lost' => 'Lost Report',
        'return' => 'Return Device',
    ];

    /** Allowed values of `status` => label. */
    public const STATUSES = [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    /** Allowed values of `priority` => label (also used to sort the queue). */
    public const PRIORITIES = ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High'];

    /**
     * Common reasons per request type, shown as one-click chips above the
     * "Reason / details" box on the New Request form. Clicking one only fills
     * in text: the reason stays free text, so add or edit entries here freely.
     */
    public const REASON_SUGGESTIONS = [
        'new_device' => ['New Employee', 'Intern / OJT', 'Reassignment', 'Replacement - current unit too old or slow',
            'Upgrade for role', 'Additional device', 'Temporary / project use', 'Transfer from another company'],
        'repair' => ['Will not power on', 'Battery or charging issue', 'Screen or display problem', 'Keyboard / trackpad not working',
            'Slow performance', 'Software / OS issue', 'Overheating', 'Network / Wi-Fi issue'],
        'damaged' => ['Dropped', 'Liquid spill', 'Cracked screen', 'Damaged in transit', 'Power surge', 'Wear and tear'],
        'lost' => ['Stolen', 'Misplaced in the office', 'Lost while travelling', 'Lost in transit / courier'],
        'return' => ['Resigned', 'End of contract', 'End of internship', 'Reassignment', 'Upgrade / replaced',
            'No longer needed', 'Extended leave', 'Transfer to another company'],
    ];

    protected $fillable = [
        'reference_no', 'employee_id', 'requested_by', 'request_type', 'device_type',
        'device_id', 'priority', 'reason', 'incident_date', 'status', 'handled_by',
        'handled_at', 'admin_remarks',
    ];

    protected $casts = [
        'incident_date' => 'datetime', 'handled_at' => 'datetime',
        'employee_id' => 'integer', 'device_id' => 'integer', 'requested_by' => 'integer', 'handled_by' => 'integer',
    ];

    /** Give every new request a human-friendly reference number. */
    protected static function booted(): void
    {
        static::creating(function (self $request) {
            $request->reference_no ??= 'REQ-'.now()->format('Ymd').'-'.strtoupper(substr(uniqid(), -5));
        });
    }

    /** Label of the request type ($request->type_label). */
    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->request_type] ?? $this->request_type;
    }

    /** Name of the device type asked for in a new_device request, else null. */
    public function getDeviceTypeLabelAttribute(): ?string
    {
        return $this->device_type ? (DeviceType::names()[$this->device_type] ?? $this->device_type) : null;
    }

    /** Label of the status ($request->status_label). */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** True while the request still needs action (pending or approved). */
    public function isOpen(): bool
    {
        return in_array($this->status, ['pending', 'approved'], true);
    }

    /** Employee the request is for. */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /** Admin who filed the request. */
    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** Device concerned (or the device issued, for a completed new_device request). */
    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    /** Admin who last approved / rejected / completed it. */
    public function handler()
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /** Audit trail entries for this request. */
    public function logs()
    {
        return $this->hasMany(AuditLog::class);
    }
}
