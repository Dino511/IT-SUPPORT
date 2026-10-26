<?php

namespace Database\Seeders;

use App\Models\DeviceType;
use App\Models\Lookup;
use Illuminate\Database\Seeder;

/**
 * Starter master data for a real installation: the 8 device types (with their
 * asset tag prefixes) and generic dropdown lists (processors, memory, storage,
 * operating systems, colours, spec names, brands and their models).
 *
 *   php artisan db:seed --class=MasterDataSeeder --force
 *
 * No companies, people, devices or logins. Safe to run again: existing types
 * and options are left as they are, only missing ones are added. Company
 * specific lists (departments, positions, suppliers) are left to the Super
 * Admin, who can add them on Master Data or by typing in any form dropdown.
 */
class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        // device types: code, name, asset tag prefix, has computer specs, has IMEI
        foreach ([
            ['laptop', 'Laptop', 'LT', true, false],
            ['desktop', 'Desktop', 'DT', true, false],
            ['mobile', 'Mobile Phone', 'MB', true, true],
            ['tablet', 'Tablet', 'TB', true, true],
            ['monitor', 'Monitor', 'MN', false, false],
            ['peripheral', 'Peripheral', 'PR', false, false],
            ['gadget', 'Gadget', 'GD', false, false],
            ['other', 'Other', 'OT', false, false],
        ] as [$code, $name, $prefix, $computer, $imei]) {
            DeviceType::firstOrCreate(['code' => $code], ['name' => $name, 'prefix' => $prefix, 'has_computer_specs' => $computer, 'has_imei' => $imei]);
        }

        self::seedLookups([
            'processor' => ['Intel Core i5-1335U', 'Intel Core i7-1355U', 'AMD Ryzen 5 PRO 7540U', 'AMD Ryzen 7 PRO 7840U', 'Apple M3', 'Apple A16 Bionic', 'Apple A14 Bionic', 'Exynos 1480', 'Snapdragon 8 Gen 2'],
            'memory' => ['4GB', '6GB', '8GB', '16GB', '32GB', '64GB'],
            'storage' => ['64GB', '128GB', '256GB', '512GB SSD', '1TB SSD', '2TB SSD'],
            'operating_system' => ['Windows 11 Pro', 'Windows 10 Pro', 'macOS Sonoma', 'Android 14', 'iOS 17', 'iPadOS 17', 'Ubuntu 22.04 LTS'],
            'color' => ['Black', 'Silver', 'Space Gray', 'White', 'Blue'],
            'spec_name' => ['Screen', 'GPU', 'Battery', 'Ports', 'SIM', 'Connection', 'Warranty Type'],
        ]);

        // brands and their models (models are child options of a brand)
        foreach ([
            'Dell' => ['Latitude 5440', 'Latitude 7440', 'OptiPlex 7010'],
            'Lenovo' => ['ThinkPad T14 Gen 4', 'ThinkPad E14 Gen 5'],
            'Apple' => ['MacBook Air M3', 'iPhone 15', 'iPad 10th Gen'],
            'Samsung' => ['Galaxy A55', 'Galaxy S24'],
            'HP' => ['EliteBook 840 G10', 'ProBook 450 G10'],
            'LG' => ['27UL500-W 27" 4K', '24MP400 24" FHD'],
            'Logitech' => ['MX Master 3S Mouse', 'MX Keys Keyboard'],
        ] as $brand => $models) {
            $parent = Lookup::firstOrCreate(['category' => 'brand', 'value' => $brand, 'parent_id' => null]);
            foreach ($models as $model) {
                Lookup::firstOrCreate(['category' => 'model', 'value' => $model, 'parent_id' => $parent->id]);
            }
        }
    }

    /** Add flat (parent-less) dropdown options that don't exist yet: [category => [values...]]. */
    public static function seedLookups(array $lists): void
    {
        foreach ($lists as $category => $values) {
            foreach ($values as $value) {
                Lookup::firstOrCreate(['category' => $category, 'value' => $value, 'parent_id' => null]);
            }
        }
    }
}
