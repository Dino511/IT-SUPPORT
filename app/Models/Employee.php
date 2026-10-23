<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A person in one of the group's companies who can be issued devices.
 *
 * Employees do not log in. IT staff may also have a login (User.employee_id),
 * which lets the system recognise "your own" requests (an Admin cannot approve
 * requests for their own devices).
 *
 * Employees are never deleted: set is_active = false so history stays intact.
 * `department` and `position` hold text from the master data lists (Lookup).
 */
class Employee extends Model
{
    protected $fillable = [
        'employee_no', 'first_name', 'last_name', 'position', 'department',
        'company_id', 'contact_no', 'email', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean', 'company_id' => 'integer'];

    /** Company the employee works for. */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /** "First Last" ($employee->full_name). */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /** Login account, if this employee is IT staff with access to the system. */
    public function user()
    {
        return $this->hasOne(User::class);
    }

    /** Devices the employee currently holds. */
    public function devices()
    {
        return $this->hasMany(Device::class, 'current_employee_id');
    }

    /** Every device the employee has ever been given (current and returned). */
    public function assignments()
    {
        return $this->hasMany(DeviceAssignment::class);
    }

    /** Requests filed for this employee. */
    public function requests()
    {
        return $this->hasMany(DeviceRequest::class);
    }

    /** Audit trail entries that mention this employee. */
    public function logs()
    {
        return $this->hasMany(AuditLog::class);
    }
}
