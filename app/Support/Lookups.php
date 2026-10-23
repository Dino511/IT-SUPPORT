<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Lookup;
use Closure;

/**
 * Validation and saving of dropdown values (master data, see App\Models\Lookup).
 *
 * Admins must pick an existing option. The super admin may type a new value
 * in the same dropdown; it is added to the master data when the form saves.
 *
 * Typical controller use:
 *   validate:  'brand' => ['required', Lookups::rule('brand', $device?->brand)]
 *   save:      $data['brand'] = Lookups::remember('brand', $data['brand']);
 */
class Lookups
{
    /**
     * Validation rule: value must be an active option (under $parentValue for
     * models), unless it is one of $current (an unchanged value on edit, which
     * may have been deactivated since) or the user is the super admin.
     *
     * @param  string             $category     a Lookup::CATEGORIES key
     * @param  string|array|null  $current      value(s) the record already has
     * @param  string|null        $parentValue  brand name, when validating a model
     */
    public static function rule(string $category, string|array|null $current = null, ?string $parentValue = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($category, $current, $parentValue) {
            if (blank($value) || in_array($value, (array) $current, true) || auth()->user()?->isSuperAdmin()) {
                return;
            }
            if (! static::find($category, $value, $parentValue)?->is_active) {
                $label = strtolower(Lookup::CATEGORIES[$category]['label']);
                $fail("\"{$value}\" is not in the {$label} list. Pick a suggested option or ask the Super Admin to add it.");
            }
        };
    }

    /**
     * Find an option (active or not) by its text. For child categories (models)
     * the parent must match too. SQL Server compares case-insensitively.
     */
    public static function find(string $category, ?string $value, ?string $parentValue = null): ?Lookup
    {
        if (blank($value)) {
            return null;
        }

        return Lookup::where('category', $category)->where('value', trim($value))
            ->when(isset(Lookup::CATEGORIES[$category]['parent']), function ($q) use ($category, $parentValue) {
                $parent = static::find(Lookup::CATEGORIES[$category]['parent'], $parentValue);
                $parent ? $q->where('parent_id', $parent->id) : $q->whereNull('parent_id');
            })
            ->first();
    }

    /**
     * Return the stored spelling of a value, creating the option first when
     * the super admin typed a new one. Call after validation, inside the save
     * transaction. Save the parent (brand) before the child (model).
     */
    public static function remember(string $category, ?string $value, ?string $parentValue = null): ?string
    {
        if (blank($value)) {
            return null;
        }
        $value = trim($value);

        if ($existing = static::find($category, $value, $parentValue)) {
            return $existing->value;
        }
        if (! auth()->user()?->isSuperAdmin()) {
            return $value; // unchanged legacy value kept on edit
        }

        $parent = isset(Lookup::CATEGORIES[$category]['parent'])
            ? static::find(Lookup::CATEGORIES[$category]['parent'], $parentValue)
            : null;
        $lookup = Lookup::create(['category' => $category, 'value' => $value, 'parent_id' => $parent?->id]);

        AuditLog::record('lookup.created', 'Added '.Lookup::CATEGORIES[$category]['label']." option \"{$value}\""
            .($parent ? " under {$parent->value}" : ''));

        return $lookup->value;
    }
}
