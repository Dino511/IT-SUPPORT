<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One hand-over of a device to an employee: when it went out, who issued it,
 * its condition, and (once closed) when it came back and in what condition.
 *
 * An assignment with returned_at = null is the device's current one. Rows are
 * created and closed only by App\Support\Inventory (assign / unassign).
 */
class DeviceAssignment extends Model
{
    protected $fillable = [
        'device_id', 'employee_id', 'device_request_id', 'assigned_by', 'assigned_at',
        'condition_on_assign', 'returned_at', 'returned_to', 'condition_on_return', 'remarks',
    ];

    protected $casts = [
        'assigned_at' => 'datetime', 'returned_at' => 'datetime',
        'device_id' => 'integer', 'employee_id' => 'integer', 'device_request_id' => 'integer',
    ];

    /** Device handed over. */
    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    /** Employee who received it. */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /** Request that led to this assignment (new device / replacement), if any. */
    public function request()
    {
        return $this->belongsTo(DeviceRequest::class, 'device_request_id');
    }

    /** Admin who issued the device. */
    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /** Admin who took it back. */
    public function returnedTo()
    {
        return $this->belongsTo(User::class, 'returned_to');
    }
}
