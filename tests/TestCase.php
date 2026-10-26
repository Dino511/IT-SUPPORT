<?php

namespace Tests;

use App\Models\Company;
use App\Models\Device;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

/**
 * Base for feature tests. Tests use RefreshDatabase with the demo seeder
 * (DatabaseSeeder), so every test starts from the same known data:
 * companies AHI/ASG/AVN, devices LT-0001..GD-0001, Carlo (super admin),
 * Liza (admin), Juan, Maria, Jose, Ana.
 */
abstract class TestCase extends BaseTestCase
{
    /** Run DatabaseSeeder after migrating (once per test run). */
    protected $seed = true;

    /**
     * RefreshDatabase drops every table, so refuse to run against anything
     * that is not a dedicated test database (see phpunit.xml). Checked here,
     * before the traits run, because the RefreshDatabase trait's own hooks
     * would override any hook method defined in this class.
     */
    protected function setUpTraits()
    {
        $db = (string) config('database.connections.'.config('database.default').'.database');
        if (! str_ends_with($db, '_test') && $db !== ':memory:') {
            throw new RuntimeException("Refusing to run tests against database \"{$db}\": use a *_test database (phpunit.xml).");
        }

        return parent::setUpTraits();
    }

    protected function superAdmin(): User
    {
        return User::where('email', 'superadmin@company.test')->firstOrFail();
    }

    protected function admin(): User
    {
        return User::where('email', 'admin@company.test')->firstOrFail();
    }

    protected function device(string $assetTag): Device
    {
        return Device::where('asset_tag', $assetTag)->firstOrFail();
    }

    protected function employee(string $employeeNo): Employee
    {
        return Employee::where('employee_no', $employeeNo)->firstOrFail();
    }

    protected function company(string $code): Company
    {
        return Company::where('code', $code)->firstOrFail();
    }

    /** Minimal valid device form for $device (used by update tests). */
    protected function deviceForm(Device $device, array $overrides = []): array
    {
        return array_merge([
            'company_id' => $device->company_id,
            'type' => $device->type,
            'brand' => $device->brand,
            'model' => $device->model,
            'condition' => $device->condition,
            'serial_number' => $device->serial_number,
        ], $overrides);
    }
}
