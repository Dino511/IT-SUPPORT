<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A physical asset tracked by IT Support (laptop, phone, monitor, gadget...).
 *
 * Lifecycle (see App\Support\Inventory for the operations that move it):
 *   available -> assigned -> (in_repair | damaged | lost) -> available / retired
 *
 * - `asset_tag` is generated from the device type's prefix (DeviceType::generateAssetTag).
 * - `type` stores DeviceType::code (string FK), not an id.
 * - Brand/model/processor/... hold the text chosen from the master data lists (Lookup).
 * - `company_id` is the owning company; `current_employee_id` is who holds it now
 *   (may belong to another company = a "loan").
 * - `po_file_path` points to the purchase order on the private "local" disk.
 *
 * @property int $id
 * @property string $asset_tag
 * @property string $type
 * @property string $status
 * @property array|null $specs extra key => value specifications
 */
class Device extends Model
{
    /** Allowed values of `status` => label shown in the UI. */
    public const STATUSES = [
        'available' => 'Available',
        'assigned' => 'Assigned',
        'in_repair' => 'In Repair',
        'damaged' => 'Damaged',
        'lost' => 'Lost',
        'retired' => 'Retired',
    ];

    /** Allowed values of `condition` (physical state) => label. */
    public const CONDITIONS = ['new' => 'New', 'good' => 'Good', 'fair' => 'Fair', 'poor' => 'Poor'];

    protected $fillable = [
        'asset_tag', 'type', 'brand', 'model', 'serial_number', 'imei', 'processor',
        'memory', 'storage', 'operating_system', 'color', 'specs', 'purchase_date',
        'purchase_cost', 'warranty_until', 'supplier', 'status', 'condition', 'notes',
        'current_employee_id', 'created_by', 'company_id', 'po_file_path', 'po_file_name',
    ];

    // Integer casts matter on SQL Server: pdo_sqlsrv returns integer columns as
    // strings, which breaks strict (===) id comparisons in the controllers.
    protected $casts = [
        'specs' => 'array',
        'purchase_date' => 'date',
        'warranty_until' => 'date',
        'purchase_cost' => 'decimal:2',
        'current_employee_id' => 'integer',
        'created_by' => 'integer',
        'company_id' => 'integer',
    ];

    /** Company that owns the device. */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /** "Brand Model", e.g. "Dell Latitude 5440" ($device->name). */
    public function getNameAttribute(): string
    {
        return "{$this->brand} {$this->model}";
    }

    /** Human name of the device type ($device->type_label). */
    public function getTypeLabelAttribute(): string
    {
        return DeviceType::names()[$this->type] ?? ucfirst($this->type);
    }

    /** Device type record (joined on device_types.code). */
    public function deviceType()
    {
        return $this->belongsTo(DeviceType::class, 'type', 'code');
    }

    /** Human label of the status ($device->status_label). */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    /** Employee currently holding the device (null when in stock). */
    public function currentEmployee()
    {
        return $this->belongsTo(Employee::class, 'current_employee_id');
    }

    /** User who logged the device. */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Full hand-over history (who had it, from when to when). */
    public function assignments()
    {
        return $this->hasMany(DeviceAssignment::class);
    }

    /** The open assignment (not yet returned), if any. */
    public function activeAssignment()
    {
        return $this->hasOne(DeviceAssignment::class)->whereNull('returned_at')->latestOfMany('assigned_at');
    }

    /** Repair / damage / lost / return requests filed about this device. */
    public function requests()
    {
        return $this->hasMany(DeviceRequest::class);
    }

    /** Audit trail entries that mention this device. */
    public function logs()
    {
        return $this->hasMany(AuditLog::class);
    }
}
