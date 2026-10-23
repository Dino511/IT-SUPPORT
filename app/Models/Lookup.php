<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * A dropdown option in the master data, maintained by the super admin
 * (Master Data page, or by typing a new value in a form dropdown).
 *
 * Records store the chosen text, not a foreign key (e.g. devices.brand = 'Dell'),
 * so reports stay readable. Because of that, renaming an option also updates
 * every record that uses it - CATEGORIES says which table/column to update.
 *
 * Models are children of a brand (parent_id), so the model dropdown can be
 * filtered by the chosen brand. Options in use are deactivated, never deleted.
 */
class Lookup extends Model
{
    /**
     * Every list the system knows about.
     *   label  - name shown on the Master Data page
     *   group  - heading it is listed under
     *   table/column - where records store the value (null: not stored in a column,
     *                  e.g. spec names live inside devices.specs JSON)
     *   parent - category of the parent option (models belong to a brand)
     */
    public const CATEGORIES = [
        'brand' => ['label' => 'Brands', 'group' => 'Devices', 'table' => 'devices', 'column' => 'brand'],
        'model' => ['label' => 'Models', 'group' => 'Devices', 'table' => 'devices', 'column' => 'model', 'parent' => 'brand'],
        'processor' => ['label' => 'Processors', 'group' => 'Devices', 'table' => 'devices', 'column' => 'processor'],
        'memory' => ['label' => 'Memory (RAM)', 'group' => 'Devices', 'table' => 'devices', 'column' => 'memory'],
        'storage' => ['label' => 'Storage', 'group' => 'Devices', 'table' => 'devices', 'column' => 'storage'],
        'operating_system' => ['label' => 'Operating Systems', 'group' => 'Devices', 'table' => 'devices', 'column' => 'operating_system'],
        'color' => ['label' => 'Colors', 'group' => 'Devices', 'table' => 'devices', 'column' => 'color'],
        'supplier' => ['label' => 'Suppliers', 'group' => 'Devices', 'table' => 'devices', 'column' => 'supplier'],
        'spec_name' => ['label' => 'Other Spec Names', 'group' => 'Devices', 'table' => null, 'column' => null],
        'department' => ['label' => 'Departments', 'group' => 'Employees', 'table' => 'employees', 'column' => 'department'],
        'position' => ['label' => 'Positions', 'group' => 'Employees', 'table' => 'employees', 'column' => 'position'],
    ];

    protected $fillable = ['category', 'value', 'parent_id', 'is_active'];

    protected $casts = ['is_active' => 'boolean', 'parent_id' => 'integer'];

    /** Active option values for a category, sorted (for form dropdowns). */
    public static function values(string $category): array
    {
        return static::where('category', $category)->where('is_active', true)
            ->orderBy('value')->pluck('value')->all();
    }

    /** Active models grouped by brand: ['Dell' => ['Latitude 5440', ...]]. */
    public static function modelsByBrand(): array
    {
        return static::with('parent')->where('category', 'model')->where('is_active', true)
            ->orderBy('value')->get()
            ->groupBy(fn ($m) => $m->parent?->value)
            ->map(fn ($group) => $group->pluck('value')->all())
            ->all();
    }

    /** Parent option (the brand of a model). */
    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** Child options (the models of a brand). */
    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Number of records currently using this option. An option in use can only
     * be deactivated, not deleted. Model usage is counted within its brand.
     */
    public function usageCount(): int
    {
        $meta = self::CATEGORIES[$this->category] ?? null;
        if (! $meta || ! $meta['table']) {
            return 0;
        }

        $query = DB::table($meta['table'])->where($meta['column'], $this->value);
        if ($this->category === 'model' && $this->parent) {
            $query->where('brand', $this->parent->value);
        }

        return $query->count();
    }
}
