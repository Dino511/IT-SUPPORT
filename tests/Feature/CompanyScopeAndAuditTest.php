<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The "Viewing company" switcher and the audit log page / CSV export. */
class CompanyScopeAndAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_choosing_a_company_filters_the_lists(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('admin.company-scope'), ['company_id' => $this->company('ASG')->id]);
        $this->get(route('admin.devices.index'))->assertOk()
            ->assertSee('MB-0002')->assertSee('TB-0001')->assertDontSee('LT-0001');
        $this->get(route('admin.employees.index'))->assertSee('Jose')->assertDontSee('Juan');

        $this->post(route('admin.company-scope'), ['company_id' => '']);
        $this->get(route('admin.devices.index'))->assertSee('LT-0001')->assertSee('MB-0002');
    }

    public function test_unknown_companies_are_refused(): void
    {
        $this->actingAs($this->admin())->post(route('admin.company-scope'), ['company_id' => 99999])->assertSessionHasErrors('company_id');
    }

    public function test_audit_log_filters_and_export(): void
    {
        $this->actingAs($this->admin());

        // ("Device Created" is always on the page as a filter option, so check entry text)
        $this->get(route('admin.audit.index', ['action' => 'device.assigned']))->assertOk()
            ->assertSee('assigned to Juan Dela Cruz')->assertDontSee('Logged new Laptop');

        $csv = $this->get(route('admin.audit.export', ['action' => 'device.assigned']))->assertOk()->streamedContent();
        $lines = array_values(array_filter(explode("\n", trim($csv))));

        $this->assertStringStartsWith('Date/Time,User,Action,Company', $lines[0]);
        $this->assertCount(6, $lines); // header + the 5 seeded hand-overs
        foreach (array_slice($lines, 1) as $line) {
            $this->assertStringContainsString('Device Assigned', $line);
        }
    }
}
