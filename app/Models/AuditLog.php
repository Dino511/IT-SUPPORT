<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * One line of the audit trail: who did what, when, and to which device /
 * employee / request. Rows are only ever inserted (never updated or deleted),
 * so the table is the system's compliance record.
 *
 * Always write entries through AuditLog::record() so the acting user and IP
 * are captured consistently.
 */
class AuditLog extends Model
{
    /** Append-only table: there is no updated_at column. */
    public const UPDATED_AT = null;

    /**
     * Every action code => label used in filters and tables.
     * Add new codes here when introducing a new kind of logged event.
     */
    public const ACTIONS = [
        'device.created' => 'Device Created',
        'device.updated' => 'Device Updated',
        'device.status' => 'Device Status Changed',
        'device.assigned' => 'Device Assigned',
        'device.returned' => 'Device Returned',
        'device.lost' => 'Device Lost',
        'employee.created' => 'Employee Created',
        'employee.updated' => 'Employee Updated',
        'request.created' => 'Request Submitted',
        'request.approved' => 'Request Approved',
        'request.rejected' => 'Request Rejected',
        'request.completed' => 'Request Completed',
        'request.cancelled' => 'Request Cancelled',
        'user.created' => 'User Account Created',
        'user.updated' => 'User Account Updated',
        'lookup.created' => 'Master Data Added',
        'lookup.updated' => 'Master Data Updated',
        'lookup.deleted' => 'Master Data Deleted',
        'device_type.created' => 'Device Type Added',
        'device_type.updated' => 'Device Type Updated',
        'company.created' => 'Company Added',
        'company.updated' => 'Company Updated',
        'device.po_uploaded' => 'PO File Uploaded',
        'device.po_removed' => 'PO File Removed',
        'auth.login' => 'Logged In',
    ];

    protected $fillable = [
        'user_id', 'device_id', 'employee_id', 'device_request_id',
        'action', 'description', 'changes', 'ip_address',
    ];

    protected $casts = ['changes' => 'array', 'created_at' => 'datetime'];

    /**
     * Write one audit entry, attributed to the signed-in user.
     *
     * @param  string  $action  one of the ACTIONS keys, e.g. 'device.assigned'
     * @param  string  $description  full human-readable sentence (shown and exported as is)
     * @param  array  $context  optional: 'device' => Device, 'employee' => Employee,
     *                          'request' => DeviceRequest (linked so the entry shows up
     *                          on their pages), 'changes' => [field => [old, new]]
     */
    public static function record(string $action, string $description, array $context = []): self
    {
        return static::create([
            'user_id' => Auth::id(),
            'device_id' => ($context['device'] ?? null)?->id,
            'employee_id' => ($context['employee'] ?? null)?->id,
            'device_request_id' => ($context['request'] ?? null)?->id,
            'action' => $action,
            'description' => $description,
            'changes' => $context['changes'] ?? null,
            'ip_address' => request()?->ip(),
        ]);
    }

    /** Label of the action code ($log->action_label). */
    public function getActionLabelAttribute(): string
    {
        return self::ACTIONS[$this->action] ?? $this->action;
    }

    /** [pill colour, icon] for showing the action in log tables. */
    public function getActionStyleAttribute(): array
    {
        return match (true) {
            in_array($this->action, ['device.lost', 'request.rejected', 'lookup.deleted', 'device.po_removed'], true) => ['danger', 'bi-exclamation-octagon'],
            in_array($this->action, ['request.completed', 'request.approved'], true) => ['success', 'bi-check2-circle'],
            str_starts_with($this->action, 'device.') => ['info', 'bi-laptop'],
            str_starts_with($this->action, 'request.') => ['warning', 'bi-inbox'],
            str_starts_with($this->action, 'employee.') => ['success', 'bi-person'],
            str_starts_with($this->action, 'auth.') => ['secondary', 'bi-box-arrow-in-right'],
            default => ['primary', 'bi-database-gear'], // master data, companies, accounts
        };
    }

    /** User who performed the action (null = system/seed). */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Device the entry is about, if any. */
    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    /** Employee the entry is about, if any. */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /** Request the entry is about, if any. */
    public function request()
    {
        return $this->belongsTo(DeviceRequest::class, 'device_request_id');
    }
}
