<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A company in the group (the system supports multinational groups with
 * several companies). Employees and devices each belong to one company.
 *
 * Maintained by the super admin on Master Data > Companies. `code` is the
 * short label shown on badges (AHI, ASG...). Deactivated companies keep their
 * records but can't be chosen for new ones.
 *
 * The sidebar "Viewing company" switcher narrows lists to one company - see
 * App\Support\CompanyScope.
 */
class Company extends Model
{
    protected $fillable = ['code', 'name', 'country', 'address', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    /** Companies that can be picked in forms and the switcher, by name. */
    public static function active()
    {
        return static::where('is_active', true)->orderBy('name')->get();
    }

    /** People working for the company. */
    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    /** Devices the company owns (some may be on loan to other companies). */
    public function devices()
    {
        return $this->hasMany(Device::class);
    }
}
