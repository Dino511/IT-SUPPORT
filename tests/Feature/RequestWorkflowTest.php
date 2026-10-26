<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Device;
use App\Models\DeviceRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The request workflow: filing, approve / reject / cancel, and every completion outcome. */
class RequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    /** File a request as the admin (Liza) and return it. */
    private function file(string $type, string $employeeNo, ?string $assetTag = null, array $extra = []): DeviceRequest
    {
        $this->actingAs($this->admin())->post(route('admin.requests.store'), array_merge([
            'employee_id' => $this->employee($employeeNo)->id,
            'request_type' => $type,
            'device_id' => $assetTag ? $this->device($assetTag)->id : null,
            'priority' => 'normal',
            'reason' => "Test {$type}",
        ], $extra))->assertSessionHasNoErrors();

        return DeviceRequest::latest('id')->firstOrFail();
    }

    private function approve(DeviceRequest $r, $as = null)
    {
        return $this->actingAs($as ?? $this->admin())->post(route('admin.requests.approve', $r), ['admin_remarks' => 'ok']);
    }

    private function complete(DeviceRequest $r, array $data, $as = null)
    {
        return $this->actingAs($as ?? $this->admin())->post(route('admin.requests.complete', $r), $data);
    }

    public function test_repair_goes_in_repair_then_back_to_the_employee(): void
    {
        $r = $this->file('repair', 'EMP-0002', 'LT-0001');
        $this->assertSame('pending', $r->status);
        $this->assertMatchesRegularExpression('/^REQ-\d{8}-[A-Z0-9]{5}$/', $r->reference_no);

        $this->approve($r)->assertSessionHasNoErrors();
        $this->assertSame('in_repair', $this->device('LT-0001')->status);
        $this->assertSame('approved', $r->fresh()->status);

        $this->complete($r, ['device_status' => 'assigned'])->assertSessionHasNoErrors();
        $device = $this->device('LT-0001');
        $this->assertSame('assigned', $device->status);
        $this->assertSame($this->employee('EMP-0002')->id, $device->current_employee_id);
        $this->assertSame('completed', $r->fresh()->status);
        $this->assertSame($this->admin()->id, $r->fresh()->handled_by);
    }

    public function test_new_device_request_assigns_the_chosen_device(): void
    {
        $r = $this->file('new_device', 'EMP-0005', null, ['device_type' => 'tablet']);
        $this->approve($r)->assertSessionHasNoErrors();

        // a new-device request needs a device on completion
        $this->complete($r, [])->assertSessionHasErrors('device_id');

        $tablet = $this->device('TB-0001');
        $this->complete($r, ['device_id' => $tablet->id])->assertSessionHasNoErrors();
        $this->assertSame($this->employee('EMP-0005')->id, $tablet->fresh()->current_employee_id);
        $this->assertSame($tablet->id, $r->fresh()->device_id);
        $this->assertSame($r->id, $tablet->fresh()->activeAssignment->device_request_id);
    }

    public function test_only_devices_of_the_requested_type_are_offered_and_accepted(): void
    {
        // in stock: GD-0001 (gadget), MN-0001 (monitor), TB-0001 (tablet)
        $r = $this->file('new_device', 'EMP-0005', null, ['device_type' => 'tablet']);
        $this->approve($r);

        $this->get(route('admin.requests.show', $r))->assertOk()
            ->assertSee('Showing available Tablet units only')
            ->assertSee('TB-0001')
            ->assertDontSee('GD-0001')
            ->assertDontSee('MN-0001');

        // a posted device of another type is refused, and stays in stock
        $this->complete($r, ['device_id' => $this->device('GD-0001')->id])
            ->assertSessionHasErrors(['device_id' => 'Choose a device of the requested type (Tablet).']);
        $this->assertSame('available', $this->device('GD-0001')->status);
        $this->assertSame('approved', $r->fresh()->status);

        // no laptop in stock: the list is empty and links to logging one
        $laptop = $this->file('new_device', 'EMP-0005', null, ['device_type' => 'laptop']);
        $this->approve($laptop);
        $this->get(route('admin.requests.show', $laptop))->assertOk()
            ->assertSee('No available Laptop in stock.')
            ->assertSee(route('admin.devices.create', ['type' => 'laptop']), false)
            ->assertDontSee('TB-0001');
    }

    public function test_lost_report_closes_the_assignment(): void
    {
        $r = $this->file('lost', 'EMP-0004', 'MB-0002', ['incident_date' => now()->subDay()->format('Y-m-d H:i')]);
        $this->approve($r)->assertSessionHasNoErrors();

        $phone = $this->device('MB-0002');
        $this->assertSame('lost', $phone->status);
        $this->assertNull($phone->current_employee_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'device.lost', 'device_id' => $phone->id]);

        $this->complete($r, ['device_status' => 'lost'])->assertSessionHasNoErrors();
        $this->assertSame('completed', $r->fresh()->status);
    }

    public function test_damage_report_with_a_replacement_unit(): void
    {
        $r = $this->file('damaged', 'EMP-0002', 'MB-0001');
        $this->approve($r);
        $this->assertSame('damaged', $this->device('MB-0001')->status);

        // the replacement must be the same type as the damaged phone
        $this->complete($r, ['device_status' => 'damaged', 'device_id' => $this->device('GD-0001')->id])->assertSessionHasErrors('device_id');
        $this->assertSame('approved', $r->fresh()->status);

        $replacement = Device::create(['asset_tag' => 'MB-0099', 'type' => 'mobile', 'brand' => 'Samsung', 'model' => 'Galaxy A35',
            'company_id' => $this->device('MB-0001')->company_id, 'status' => 'available', 'condition' => 'new']);
        $this->complete($r, ['device_status' => 'damaged', 'condition' => 'poor', 'device_id' => $replacement->id])->assertSessionHasNoErrors();

        $broken = $this->device('MB-0001');
        $this->assertSame('damaged', $broken->status);
        $this->assertNull($broken->current_employee_id);
        $this->assertSame('poor', $broken->condition);
        $this->assertSame($this->employee('EMP-0002')->id, $replacement->fresh()->current_employee_id);
    }

    public function test_return_request_puts_the_device_back_in_stock(): void
    {
        $r = $this->file('return', 'EMP-0003', 'LT-0002');
        $this->approve($r);
        $this->assertSame('assigned', $this->device('LT-0002')->status); // nothing changes until completion

        $this->complete($r, ['condition' => 'fair'])->assertSessionHasNoErrors();
        $device = $this->device('LT-0002');
        $this->assertSame('available', $device->status);
        $this->assertSame('fair', $device->condition);
        $this->assertNull($device->current_employee_id);
    }

    public function test_only_one_open_request_per_device(): void
    {
        $this->file('repair', 'EMP-0002', 'LT-0001');

        $this->actingAs($this->admin())->post(route('admin.requests.store'), [
            'employee_id' => $this->employee('EMP-0002')->id, 'request_type' => 'damaged',
            'device_id' => $this->device('LT-0001')->id, 'priority' => 'high', 'reason' => 'Again',
        ])->assertSessionHasErrors(['device_ids' => 'There is already an open request for LT-0001.']);
    }

    public function test_the_device_must_belong_to_the_employee(): void
    {
        $this->actingAs($this->admin())->post(route('admin.requests.store'), [
            'employee_id' => $this->employee('EMP-0002')->id, 'request_type' => 'repair',
            'device_id' => $this->device('LT-0002')->id, // Maria's laptop
            'priority' => 'normal', 'reason' => 'Wrong device',
        ])->assertSessionHasErrors('device_ids.0');
    }

    public function test_several_devices_are_filed_as_one_request_each(): void
    {
        // Juan (EMP-0002) holds LT-0001 and MB-0001: the form lets you pick both
        $this->actingAs($this->admin())->get(route('admin.requests.create', ['employee' => $this->employee('EMP-0002')->id]))
            ->assertOk()->assertSee('name="device_ids[]" id="device_id" multiple', false);

        $devices = [$this->device('LT-0001'), $this->device('MB-0001')];
        $response = $this->post(route('admin.requests.store'), [
            'employee_id' => $this->employee('EMP-0002')->id, 'request_type' => 'return',
            'device_ids' => [$devices[0]->id, $devices[1]->id], 'priority' => 'normal', 'reason' => 'Resigned',
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.requests.index'));

        $filed = DeviceRequest::where('reason', 'Resigned')->orderBy('id')->get();
        $this->assertSame([$devices[0]->id, $devices[1]->id], $filed->pluck('device_id')->all());
        $this->assertSame(['pending', 'pending'], $filed->pluck('status')->all());
        $this->assertNotSame($filed[0]->reference_no, $filed[1]->reference_no);
        $response->assertSessionHas('success', '2 requests filed, one per device: '.$filed->pluck('reference_no')->implode(', ').'.');
        $this->assertSame(2, AuditLog::where('action', 'request.created')->count());

        // each one is then handled on its own
        $this->approve($filed[0]);
        $this->assertSame(['approved', 'pending'], $filed->map->fresh()->pluck('status')->all());
    }

    public function test_the_form_offers_common_reasons_for_every_type(): void
    {
        $this->assertSame(array_keys(DeviceRequest::TYPES), array_keys(DeviceRequest::REASON_SUGGESTIONS));

        $this->actingAs($this->admin())->get(route('admin.requests.create'))->assertOk()
            ->assertSee('id="reason-chips"', false)
            ->assertSee(json_encode(DeviceRequest::REASON_SUGGESTIONS), false);

        // a reason is still free text: anything typed is saved as is
        $r = $this->file('return', 'EMP-0003', 'LT-0002', ['reason' => 'Resigned - last day 30 Oct, laptop bag included']);
        $this->assertSame('Resigned - last day 30 Oct, laptop bag included', $r->reason);
    }

    public function test_a_multi_device_request_is_all_or_nothing(): void
    {
        $this->file('repair', 'EMP-0002', 'LT-0001');
        $post = fn (array $ids) => $this->actingAs($this->admin())->post(route('admin.requests.store'), [
            'employee_id' => $this->employee('EMP-0002')->id, 'request_type' => 'damaged',
            'device_ids' => $ids, 'priority' => 'normal', 'reason' => 'Dropped the bag',
        ]);

        // LT-0001 already has an open request, so MB-0001 is not filed either
        $post([$this->device('MB-0001')->id, $this->device('LT-0001')->id])
            ->assertSessionHasErrors(['device_ids' => 'There is already an open request for LT-0001.']);
        // LT-0002 is Maria's laptop, not Juan's
        $post([$this->device('MB-0001')->id, $this->device('LT-0002')->id])->assertSessionHasErrors('device_ids.1');
        $post([])->assertSessionHasErrors('device_ids');

        $this->assertSame(0, DeviceRequest::where('reason', 'Dropped the bag')->count());
    }

    public function test_reject_needs_a_reason(): void
    {
        $r = $this->file('repair', 'EMP-0002', 'LT-0001');

        $this->actingAs($this->admin())->post(route('admin.requests.reject', $r), [])->assertSessionHasErrors('admin_remarks');
        $this->actingAs($this->admin())->post(route('admin.requests.reject', $r), ['admin_remarks' => 'Not covered'])->assertSessionHasNoErrors();

        $this->assertSame('rejected', $r->fresh()->status);
        $this->assertSame('assigned', $this->device('LT-0001')->status); // untouched
    }

    public function test_only_pending_requests_can_be_cancelled_or_approved(): void
    {
        $r = $this->file('repair', 'EMP-0002', 'LT-0001');
        $this->approve($r);

        $this->actingAs($this->admin())->post(route('admin.requests.cancel', $r))->assertSessionHasErrors('status');
        $this->approve($r)->assertSessionHasErrors('status');

        $pending = $this->file('return', 'EMP-0003', 'LT-0002');
        $this->actingAs($this->admin())->post(route('admin.requests.cancel', $pending))->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $pending->fresh()->status);
    }

    public function test_admins_cannot_process_their_own_requests_but_the_super_admin_can(): void
    {
        $own = $this->file('repair', 'EMP-0006', 'LT-0003'); // Liza's own laptop, filed by Liza

        $this->approve($own)->assertSessionHasErrors('status');
        $this->actingAs($this->admin())->post(route('admin.requests.reject', $own), ['admin_remarks' => 'x'])->assertSessionHasErrors('status');
        $this->assertSame('pending', $own->fresh()->status);
        $this->actingAs($this->admin())->get(route('admin.requests.show', $own))->assertSee('This request is for your own devices');

        $this->approve($own, $this->superAdmin())->assertSessionHasNoErrors();
        $this->assertSame('approved', $own->fresh()->status);
    }
}
