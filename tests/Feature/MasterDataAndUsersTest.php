<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceType;
use App\Models\Lookup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Super admin tools: dropdown lists, companies, device types, user accounts; and employee records. */
class MasterDataAndUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_renaming_an_option_renames_it_on_every_record(): void
    {
        $sixteen = Lookup::where(['category' => 'memory', 'value' => '16GB'])->firstOrFail();
        $using = Device::where('memory', '16GB')->count();
        $this->assertGreaterThan(0, $using);

        $this->actingAs($this->superAdmin())->put(route('admin.master.update', $sixteen), ['value' => '16 GB DDR5', 'is_active' => '1'])
            ->assertSessionHasNoErrors();

        $this->assertSame($using, Device::where('memory', '16 GB DDR5')->count());
        $this->assertSame(0, Device::where('memory', '16GB')->count());
    }

    public function test_options_in_use_cannot_be_deleted_but_unused_ones_can(): void
    {
        $this->actingAs($this->superAdmin());
        $dell = Lookup::where(['category' => 'brand', 'value' => 'Dell'])->firstOrFail();

        $this->delete(route('admin.master.destroy', $dell))->assertSessionHas('error');
        $this->assertModelExists($dell);

        $this->post(route('admin.master.store'), ['category' => 'color', 'value' => 'Teal'])->assertSessionHasNoErrors();
        $teal = Lookup::where(['category' => 'color', 'value' => 'Teal'])->firstOrFail();
        $this->delete(route('admin.master.destroy', $teal))->assertSessionHas('success');
        $this->assertModelMissing($teal);
    }

    public function test_duplicate_options_and_models_without_a_brand_are_refused(): void
    {
        $this->actingAs($this->superAdmin());

        $this->post(route('admin.master.store'), ['category' => 'brand', 'value' => 'Dell'])->assertSessionHasErrors('value');
        $this->post(route('admin.master.store'), ['category' => 'model', 'value' => 'Orphan 1'])->assertSessionHasErrors('parent_id');
    }

    public function test_deactivated_options_disappear_from_admin_choices(): void
    {
        $hp = Lookup::where(['category' => 'brand', 'value' => 'HP'])->firstOrFail();
        $this->actingAs($this->superAdmin())->put(route('admin.master.update', $hp), ['value' => 'HP']); // is_active omitted = off

        $this->assertFalse($hp->fresh()->is_active);
        $this->actingAs($this->admin())->post(route('admin.devices.store'), [
            'company_id' => $this->company('AHI')->id, 'type' => 'laptop', 'brand' => 'HP', 'model' => 'EliteBook 840 G10', 'condition' => 'new',
        ])->assertSessionHasErrors('brand');
    }

    public function test_companies_and_device_types(): void
    {
        $this->actingAs($this->superAdmin());

        $this->post(route('admin.master.companies.store'), ['code' => 'ajp', 'name' => 'Acme Japan K.K.', 'country' => 'Japan'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('companies', ['code' => 'AJP', 'name' => 'Acme Japan K.K.', 'is_active' => true]);
        $this->post(route('admin.master.companies.store'), ['code' => 'AHI', 'name' => 'Duplicate'])->assertSessionHasErrors('code');

        $this->post(route('admin.master.types.store'), ['name' => 'Printer', 'prefix' => 'prt'])->assertSessionHasNoErrors();
        $type = DeviceType::where('name', 'Printer')->firstOrFail();
        $this->assertSame(['printer', 'PRT'], [$type->code, $type->prefix]);
        $this->assertSame('PRT-0001', DeviceType::generateAssetTag('printer'));
        $this->post(route('admin.master.types.store'), ['name' => 'Plotter', 'prefix' => 'LT'])->assertSessionHasErrors('prefix');
    }

    public function test_user_accounts(): void
    {
        $this->actingAs($this->superAdmin());
        $maria = $this->employee('EMP-0003');

        $this->post(route('admin.users.store'), [
            'name' => 'Maria Santos', 'email' => 'maria.it@company.test', 'role' => 'admin', 'employee_id' => $maria->id,
            'password' => 'secret123', 'password_confirmation' => 'secret123', 'is_active' => '1',
        ])->assertSessionHasNoErrors();
        $user = User::where('email', 'maria.it@company.test')->firstOrFail();

        // an employee can only have one account
        $this->post(route('admin.users.store'), [
            'name' => 'Dup', 'email' => 'dup@company.test', 'role' => 'admin', 'employee_id' => $maria->id,
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertSessionHasErrors('employee_id');

        // blank password keeps the current one
        $this->put(route('admin.users.update', $user), ['name' => 'Maria S.', 'email' => $user->email, 'role' => 'admin', 'employee_id' => $maria->id, 'is_active' => '1'])
            ->assertSessionHasNoErrors();
        auth()->logout();
        $this->post('/login', ['email' => 'maria.it@company.test', 'password' => 'secret123']);
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_super_admin_cannot_demote_or_disable_themselves(): void
    {
        $me = $this->superAdmin();
        $this->actingAs($me)->put(route('admin.users.update', $me), [
            'name' => $me->name, 'email' => $me->email, 'role' => 'admin', 'employee_id' => $me->employee_id, 'is_active' => '1',
        ])->assertSessionHas('error');

        $this->assertSame('super_admin', $me->fresh()->role);
    }

    public function test_employee_records_use_the_master_data(): void
    {
        $this->actingAs($this->admin());
        $form = [
            'employee_no' => 'EMP-0100', 'first_name' => 'Nina', 'last_name' => 'Cruz', 'department' => 'Finance',
            'position' => 'Accountant', 'company_id' => $this->company('ASG')->id, 'email' => 'nina@company.test', 'is_active' => '1',
        ];

        $this->post(route('admin.employees.store'), array_merge($form, ['department' => 'Space Program']))->assertSessionHasErrors('department');
        $this->post(route('admin.employees.store'), $form)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('employees', ['employee_no' => 'EMP-0100', 'company_id' => $this->company('ASG')->id]);
        $this->post(route('admin.employees.store'), array_merge($form, ['employee_no' => 'EMP-0101']))->assertSessionHasErrors('email');
    }
}
