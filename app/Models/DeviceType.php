<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A kind of device (Laptop, Mobile Phone, Printer...), maintained by the
 * super admin on the Master Data page.
 *
 * - `code` is the permanent key stored in devices.type and device_requests.device_type
 *   (generated from the name when the type is created; never changes).
 * - `prefix` + `last_number` produce the automatic asset tags (LT-0001, LT-0002...).
 * - `has_computer_specs` / `has_imei` decide which spec fields the device form shows.
 */
class DeviceType extends Model
{
    protected $fillable = ['code', 'name', 'prefix', 'has_computer_specs', 'has_imei', 'is_active'];

    protected $casts = [
        'last_number' => 'integer',
        'has_computer_specs' => 'boolean',
        'has_imei' => 'boolean',
        'is_active' => 'boolean',
    ];

    /** code => name for every type, active or not (for labels). Cached per request. */
    public static function names(): array
    {
        return once(fn () => static::orderBy('name')->pluck('name', 'code')->all());
    }

    /** Types that can be chosen for new devices / requests, by name. */
    public static function active()
    {
        return static::where('is_active', true)->orderBy('name')->get();
    }

    /**
     * Next asset tag for a type, e.g. LT-0007. Must run inside a DB
     * transaction: the type row is locked so concurrent saves get distinct
     * numbers. Skips numbers already taken (e.g. after a prefix change).
     */
    public static function generateAssetTag(string $code): string
    {
        $type = static::where('code', $code)->lockForUpdate()->firstOrFail();

        do {
            $type->last_number++;
            $tag = sprintf('%s-%04d', $type->prefix, $type->last_number);
        } while (Device::where('asset_tag', $tag)->exists());

        $type->save();

        return $tag;
    }

    /** Devices of this type. */
    public function devices()
    {
        return $this->hasMany(Device::class, 'type', 'code');
    }
}
