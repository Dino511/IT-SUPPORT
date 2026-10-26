<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Device;
use App\Models\Lookup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Logging devices, asset tags, master-data dropdowns, hand-overs and PO files. */
class DeviceTest extends TestCase
{
    use RefreshDatabase;

    private function newLaptop(array $overrides = []): array
    {
        return array_merge([
            'company_id' => $this->company('AHI')->id, 'type' => 'laptop', 'brand' => 'Dell',
            'model' => 'Latitude 7440', 'memory' => '16GB', 'condition' => 'new',
        ], $overrides);
    }

    public function test_asset_tags_are_generated_per_type_in_sequence(): void
    {
        $this->actingAs($this->admin());

        // the seed already has LT-0001..LT-0003
        $this->post(route('admin.devices.store'), $this->newLaptop(['serial_number' => 'A1']))->assertSessionHasNoErrors();
        $this->post(route('admin.devices.store'), $this->newLaptop(['serial_number' => 'A2']))->assertSessionHasNoErrors();

        $this->assertSame('LT-0004', Device::where('serial_number', 'A1')->value('asset_tag'));
        $this->assertSame('LT-0005', Device::where('serial_number', 'A2')->value('asset_tag'));
        $this->assertSame('available', Device::where('serial_number', 'A1')->value('status'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'device.created']);
    }

    public function test_serial_numbers_are_optional_but_unique(): void
    {
        $this->actingAs($this->admin());

        // several devices can be logged without a serial number (SQL Server
        // used to treat the second NULL as a duplicate)
        $this->post(route('admin.devices.store'), $this->newLaptop())->assertSessionHasNoErrors();
        $this->post(route('admin.devices.store'), $this->newLaptop(['serial_number' => '']))->assertSessionHasNoErrors();
        $this->assertSame(['LT-0004', 'LT-0005'], Device::whereNull('serial_number')->orderBy('asset_tag')->pluck('asset_tag')->all());

        // a serial number already in use is still refused
        $this->post(route('admin.devices.store'), $this->newLaptop(['serial_number' => 'DL5440-8H2K1']))->assertSessionHasErrors('serial_number');
        $this->assertSame(1, Device::where('serial_number', 'DL5440-8H2K1')->count());
    }

    public function test_changing_the_type_re_tags_the_device(): void
    {
        $this->actingAs($this->superAdmin());
        $device = $this->device('LT-0002');

        $this->put(route('admin.devices.update', $device), $this->deviceForm($device, ['type' => 'desktop']))->assertSessionHasNoErrors();

        $this->assertSame('DT-0001', $device->fresh()->asset_tag);
    }

    public function test_admins_must_pick_dropdown_values_from_the_master_data(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.devices.store'), $this->newLaptop(['brand' => 'Asus', 'model' => 'ExpertBook B9', 'memory' => '128GB']))
            ->assertSessionHasErrors(['brand', 'model', 'memory']);

        $this->assertDatabaseMissing('lookups', ['category' => 'brand', 'value' => 'Asus']);
    }

    public function test_a_model_must_belong_to_the_chosen_brand(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.devices.store'), $this->newLaptop(['brand' => 'Dell', 'model' => 'iPhone 15']))
            ->assertSessionHasErrors('model');
    }

    public function test_super_admin_new_dropdown_values_join_the_master_data(): void
    {
        $this->actingAs($this->superAdmin())
            ->post(route('admin.devices.store'), $this->newLaptop(['brand' => 'Asus', 'model' => 'ExpertBook B9', 'serial_number' => 'S1']))
            ->assertSessionHasNoErrors();

        $brand = Lookup::where(['category' => 'brand', 'value' => 'Asus'])->firstOrFail();
        $this->assertDatabaseHas('lookups', ['category' => 'model', 'value' => 'ExpertBook B9', 'parent_id' => $brand->id]);
        $this->assertSame('Asus', Device::where('serial_number', 'S1')->value('brand'));
    }

    public function test_assign_and_return(): void
    {
        $this->actingAs($this->admin());
        $tablet = $this->device('TB-0001');
        $juan = $this->employee('EMP-0002');

        $this->post(route('admin.devices.assign', $tablet), ['employee_id' => $juan->id, 'condition' => 'good'])->assertSessionHasNoErrors();
        $tablet->refresh();
        $this->assertSame('assigned', $tablet->status);
        $this->assertSame($juan->id, $tablet->current_employee_id);
        $this->assertNull($tablet->activeAssignment->returned_at);

        $this->post(route('admin.devices.return', $tablet), ['status' => 'available', 'condition' => 'fair', 'remarks' => 'Back'])->assertSessionHasNoErrors();
        $tablet->refresh();
        $this->assertSame('available', $tablet->status);
        $this->assertNull($tablet->current_employee_id);
        $this->assertSame('fair', $tablet->condition);
        $this->assertNotNull($tablet->assignments()->first()->returned_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'device.returned', 'device_id' => $tablet->id]);
    }

    public function test_assigning_across_companies_is_logged_as_a_loan(): void
    {
        $monitor = $this->device('MN-0001'); // owned by AVN
        $this->actingAs($this->admin())->post(route('admin.devices.assign', $monitor), ['employee_id' => $this->employee('EMP-0002')->id]); // Juan, AHI

        $this->assertStringContainsString('on loan from AVN to AHI',
            AuditLog::where('action', 'device.assigned')->where('device_id', $monitor->id)->value('description'));
    }

    public function test_assigned_devices_cannot_be_assigned_again(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.devices.assign', $this->device('LT-0001')), ['employee_id' => $this->employee('EMP-0003')->id])
            ->assertSessionHasErrors('device_id');
    }

    public function test_status_change_rules(): void
    {
        $this->actingAs($this->admin());

        // "assigned" only through Assign; "available" only after Return
        $this->post(route('admin.devices.status', $this->device('TB-0001')), ['status' => 'assigned'])->assertSessionHas('error');
        $this->post(route('admin.devices.status', $this->device('LT-0001')), ['status' => 'available'])->assertSessionHas('error');

        $this->post(route('admin.devices.status', $this->device('TB-0001')), ['status' => 'retired', 'remarks' => 'Cracked'])->assertSessionHas('success');
        $this->assertSame('retired', $this->device('TB-0001')->status);
    }

    public function test_purchase_order_upload_view_replace_and_remove(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin());
        $device = $this->device('LT-0001');

        $this->put(route('admin.devices.update', $device), $this->deviceForm($device, [
            'po_file' => UploadedFile::fake()->create('PO-1.pdf', 120, 'application/pdf'),
        ]))->assertSessionHasNoErrors();
        $first = $device->fresh()->po_file_path;
        Storage::disk('local')->assertExists($first);
        $this->assertSame('PO-1.pdf', $device->fresh()->po_file_name);
        $this->get(route('admin.devices.po-file', $device))->assertOk();

        // a new upload replaces (and deletes) the old file
        $this->put(route('admin.devices.update', $device), $this->deviceForm($device, [
            'po_file' => UploadedFile::fake()->create('PO-2.pdf', 120, 'application/pdf'),
        ]));
        Storage::disk('local')->assertMissing($first);
        $this->assertSame('PO-2.pdf', $device->fresh()->po_file_name);

        $this->put(route('admin.devices.update', $device), $this->deviceForm($device, ['remove_po_file' => '1']));
        $this->assertNull($device->fresh()->po_file_path);
        $this->get(route('admin.devices.po-file', $device))->assertNotFound();
        $this->assertDatabaseHas('audit_logs', ['action' => 'device.po_removed', 'device_id' => $device->id]);
    }

    public function test_po_files_must_be_documents(): void
    {
        Storage::fake('local');
        $device = $this->device('LT-0001');

        $this->actingAs($this->admin())->put(route('admin.devices.update', $device), $this->deviceForm($device, [
            'po_file' => UploadedFile::fake()->create('invoice.exe', 10, 'application/x-msdownload'),
        ]))->assertSessionHasErrors('po_file');
    }

    public function test_po_files_are_not_public(): void
    {
        auth()->logout();
        $this->get(route('admin.devices.po-file', $this->device('LT-0001')))->assertRedirect('/login');
    }
}
