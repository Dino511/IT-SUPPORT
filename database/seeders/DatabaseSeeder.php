<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\Employee;
use App\Models\Lookup;
use App\Models\User;
use App\Support\Inventory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Demo data for development and training: `php artisan migrate:fresh --seed`.
 * Do NOT run against production data (migrate:fresh drops every table).
 *
 * Creates: 8 device types, the master data lists, 3 companies (PH/SG/VN),
 * 6 employees, 2 logins, 8 devices with auto asset tags, 5 assignments.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Demo data. Logins (password: "password"):
     *   superadmin@company.test - Super Admin
     *   admin@company.test      - Admin
     */
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
            DeviceType::create(['code' => $code, 'name' => $name, 'prefix' => $prefix, 'has_computer_specs' => $computer, 'has_imei' => $imei]);
        }

        // dropdown lists (master data)
        $this->seedLookups([
            'processor' => ['Intel Core i5-1335U', 'Intel Core i7-1355U', 'AMD Ryzen 5 PRO 7540U', 'AMD Ryzen 7 PRO 7840U', 'Apple M3', 'Apple A16 Bionic', 'Apple A14 Bionic', 'Exynos 1480', 'Snapdragon 8 Gen 2'],
            'memory' => ['4GB', '6GB', '8GB', '16GB', '32GB', '64GB'],
            'storage' => ['64GB', '128GB', '256GB', '512GB SSD', '1TB SSD', '2TB SSD'],
            'operating_system' => ['Windows 11 Pro', 'Windows 10 Pro', 'macOS Sonoma', 'Android 14', 'iOS 17', 'iPadOS 17', 'Ubuntu 22.04 LTS'],
            'color' => ['Black', 'Silver', 'Space Gray', 'White', 'Blue'],
            'supplier' => ['TechSource Inc.', 'PC Express', 'Octagon Computer Superstore'],
            'spec_name' => ['Screen', 'GPU', 'Battery', 'Ports', 'SIM', 'Connection', 'Warranty Type'],
            'department' => ['IT', 'Finance', 'Human Resources', 'Sales', 'Marketing', 'Operations', 'Admin'],
            'position' => ['IT Manager', 'IT Support Specialist', 'Accountant', 'HR Officer', 'Sales Executive', 'Marketing Lead', 'Operations Supervisor'],
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
            $parent = Lookup::create(['category' => 'brand', 'value' => $brand]);
            foreach ($models as $model) {
                Lookup::create(['category' => 'model', 'value' => $model, 'parent_id' => $parent->id]);
            }
        }

        // companies of the group, keyed by code
        $companies = collect([
            ['AHI', 'Acme Holdings Inc.', 'Philippines', 'Ayala Ave., Makati City'],
            ['ASG', 'Acme Singapore Pte. Ltd.', 'Singapore', 'Raffles Place, Singapore'],
            ['AVN', 'Acme Vietnam Co., Ltd.', 'Vietnam', 'District 1, Ho Chi Minh City'],
        ])->mapWithKeys(fn ($c) => [$c[0] => Company::create(['code' => $c[0], 'name' => $c[1], 'country' => $c[2], 'address' => $c[3]])]);

        // [employee no, first, last, position, department, company code, contact, email?]
        $employee = fn (array $e) => Employee::create([
            'employee_no' => $e[0], 'first_name' => $e[1], 'last_name' => $e[2], 'position' => $e[3],
            'department' => $e[4], 'company_id' => $companies[$e[5]]->id, 'contact_no' => $e[6],
            'email' => $e[7] ?? strtolower(str_replace(' ', '', "{$e[1]}.{$e[2]}")).'@company.test',
        ]);

        // IT staff, each with a login linked to their employee record
        $itHead = $employee(['EMP-0001', 'Carlo', 'Mendoza', 'IT Manager', 'IT', 'AHI', '09170000001', 'superadmin@company.test']);
        $itStaff = $employee(['EMP-0006', 'Liza', 'Ramos', 'IT Support Specialist', 'IT', 'AHI', '09170000002', 'admin@company.test']);
        $superAdmin = User::create(['name' => $itHead->full_name, 'email' => $itHead->email, 'password' => 'password',
            'role' => User::ROLE_SUPER_ADMIN, 'employee_id' => $itHead->id]);
        User::create(['name' => $itStaff->full_name, 'email' => $itStaff->email, 'password' => 'password',
            'role' => User::ROLE_ADMIN, 'employee_id' => $itStaff->id]);

        Auth::login($superAdmin); // so seeded audit logs are attributed to the super admin

        $employees = collect([
            ['EMP-0002', 'Juan', 'Dela Cruz', 'Accountant', 'Finance', 'AHI', '09171234567'],
            ['EMP-0003', 'Maria', 'Santos', 'HR Officer', 'Human Resources', 'AHI', '09181234567'],
            ['EMP-0004', 'Jose', 'Reyes', 'Sales Executive', 'Sales', 'ASG', '+6591234567'],
            ['EMP-0005', 'Ana', 'Garcia', 'Marketing Lead', 'Marketing', 'AVN', '+84901234567'],
        ])->map($employee);

        // [company code, type, brand, model, serial, processor, memory, storage, OS, extra specs]
        // asset tags come from the type prefix, exactly as in the app
        $devices = collect([
            ['AHI', 'laptop', 'Dell', 'Latitude 5440', 'DL5440-8H2K1', 'Intel Core i7-1355U', '16GB', '512GB SSD', 'Windows 11 Pro', ['Screen' => '14" FHD', 'GPU' => 'Intel Iris Xe']],
            ['AHI', 'laptop', 'Lenovo', 'ThinkPad T14 Gen 4', 'LNT14-PF4A9Z', 'AMD Ryzen 7 PRO 7840U', '32GB', '1TB SSD', 'Windows 11 Pro', ['Screen' => '14" WUXGA']],
            ['AHI', 'laptop', 'Apple', 'MacBook Air M3', 'C02XM3A1B2C3', 'Apple M3', '16GB', '512GB SSD', 'macOS Sonoma', ['Screen' => '13.6" Liquid Retina']],
            ['AHI', 'mobile', 'Samsung', 'Galaxy A55', 'R58W12ABCDE', 'Exynos 1480', '8GB', '256GB', 'Android 14', ['SIM' => 'Globe Postpaid']],
            ['ASG', 'mobile', 'Apple', 'iPhone 15', 'F2LXK9ZP1Q', 'Apple A16 Bionic', '6GB', '128GB', 'iOS 17', []],
            ['ASG', 'tablet', 'Apple', 'iPad 10th Gen', 'DMPX12345Q', 'Apple A14 Bionic', '4GB', '64GB', 'iPadOS 17', []],
            ['AVN', 'monitor', 'LG', '27UL500-W 27" 4K', '305NTAB12345', null, null, null, null, ['Ports' => 'HDMI x2, DP']],
            ['AVN', 'gadget', 'Logitech', 'MX Master 3S Mouse', '2219LZ0ABC', null, null, null, null, ['Connection' => 'Bluetooth / Bolt']],
        ])->map(fn ($row, $i) => DB::transaction(function () use ($row, $i, $companies) {
            $companyId = $companies[array_shift($row)]->id;
            $d = $row;
            $device = Device::create([
                'company_id' => $companyId,
                'asset_tag' => DeviceType::generateAssetTag($d[0]),
                'type' => $d[0], 'brand' => $d[1], 'model' => $d[2], 'serial_number' => $d[3],
                'processor' => $d[4], 'memory' => $d[5], 'storage' => $d[6], 'operating_system' => $d[7], 'specs' => $d[8] ?: null,
                'imei' => $d[0] === 'mobile' ? '35'.str_pad((string) (1234567890123 + $i), 13, '0', STR_PAD_LEFT) : null,
                'color' => ['Black', 'Black', 'Space Gray', 'Blue', 'Black', 'Silver', 'White', 'Black'][$i],
                'purchase_date' => now()->subMonths(10 - $i)->toDateString(),
                'warranty_until' => now()->addDays(30 + $i * 60)->toDateString(),
                'purchase_cost' => [65000, 82000, 74990, 23990, 52990, 25990, 15500, 6500][$i],
                'supplier' => 'TechSource Inc.', 'condition' => 'new', 'status' => 'available', 'created_by' => Auth::id(),
            ]);
            AuditLog::record('device.created', "Logged new {$device->type_label}: {$device->asset_tag} ({$device->name})", ['device' => $device]);

            return $device;
        }));

        // hand some devices out so the dashboard and histories have data
        Inventory::assign($devices[0], $employees[0], ['remarks' => 'With charger and laptop bag']);
        Inventory::assign($devices[3], $employees[0]);
        Inventory::assign($devices[1], $employees[1]);
        Inventory::assign($devices[4], $employees[2]);
        Inventory::assign($devices[2], $itStaff);

        Auth::logout();
    }

    /** Create flat (parent-less) dropdown options: [category => [values...]]. */
    private function seedLookups(array $lists): void
    {
        foreach ($lists as $category => $values) {
            foreach ($values as $value) {
                Lookup::create(['category' => $category, 'value' => $value]);
            }
        }
    }
}
