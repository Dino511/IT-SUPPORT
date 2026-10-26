<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Every kind of action ends up on the Audit Logs page, whatever company is selected. */
class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private function latest(string $action): AuditLog
    {
        return AuditLog::where('action', $action)->latest('id')->firstOrFail();
    }

    public function test_sign_ins_failed_sign_ins_and_sign_outs_are_logged(): void
    {
        $this->post('/login', ['email' => 'admin@company.test', 'password' => 'wrong-one']);
        $this->assertSame('Failed sign-in as admin@company.test: wrong password', $this->latest('auth.failed')->description);
        $this->assertSame($this->employee('EMP-0006')->id, $this->latest('auth.failed')->employee_id); // whose account it was
        $this->assertSame('Not signed in', $this->latest('auth.failed')->actor_name);

        $this->post('/login', ['email' => 'nobody@company.test', 'password' => 'x']);
        $this->assertStringEndsWith('no account with this email', $this->latest('auth.failed')->description);

        $this->admin()->update(['is_active' => false]);
        $this->post('/login', ['email' => 'admin@company.test', 'password' => 'password']);
        $this->assertStringEndsWith('account is disabled', $this->latest('auth.failed')->description);
        $this->admin()->update(['is_active' => true]);

        $this->post('/login', ['email' => 'admin@company.test', 'password' => 'password']);
        $this->assertSame('Liza Ramos logged in', $this->latest('auth.login')->description);
        $this->post(route('logout'));
        $this->assertSame('Liza Ramos logged out', $this->latest('auth.logout')->description);
        $this->assertSame($this->admin()->id, $this->latest('auth.logout')->user_id);
    }

    public function test_email_password_and_status_changes_of_an_account_are_logged(): void
    {
        $liza = $this->admin();
        $this->actingAs($this->superAdmin())->put(route('admin.users.update', $liza), [
            'name' => $liza->name, 'email' => 'liza.ramos@company.test', 'role' => 'admin', 'employee_id' => $liza->employee_id,
            'password' => 'N3w-password', 'password_confirmation' => 'N3w-password', // is_active left out = disable
        ])->assertSessionHasNoErrors();

        $updated = $this->latest('user.updated');
        $this->assertSame('Updated account Liza Ramos: Email', $updated->description);
        $this->assertSame(['email' => ['admin@company.test', 'liza.ramos@company.test']], $updated->changes);
        $this->assertSame('Changed the password of Liza Ramos (liza.ramos@company.test)', $this->latest('user.password_changed')->description);
        $this->assertSame('Disabled the account of Liza Ramos (liza.ramos@company.test)', $this->latest('user.status')->description);
        $this->assertStringNotContainsString('N3w-password', AuditLog::pluck('description')->implode(' ').json_encode(AuditLog::pluck('changes')));

        // a role change shows the role names
        $this->put(route('admin.users.update', $liza), [
            'name' => $liza->name, 'email' => 'liza.ramos@company.test', 'role' => 'super_admin', 'employee_id' => $liza->employee_id, 'is_active' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertSame(['role' => ['Admin', 'Super Admin']], $this->latest('user.updated')->changes);
        $this->assertSame('Enabled the account of Liza Ramos (liza.ramos@company.test)', $this->latest('user.status')->description);
    }

    public function test_employee_edits_and_deactivation_are_logged(): void
    {
        $ana = $this->employee('EMP-0005');
        $this->actingAs($this->admin())->put(route('admin.employees.update', $ana), [
            'employee_no' => $ana->employee_no, 'first_name' => 'Ana', 'last_name' => 'Garcia', 'position' => $ana->position,
            'department' => $ana->department, 'company_id' => $ana->company_id, 'contact_no' => '+84900000000', 'email' => $ana->email,
            // is_active left out = deactivate
        ])->assertSessionHasNoErrors();

        $edit = $this->latest('employee.updated');
        $this->assertSame('Updated employee Ana Garcia: Contact no.', $edit->description);
        $this->assertSame(['contact_no' => ['+84901234567', '+84900000000']], $edit->changes);
        $this->assertSame('Deactivated employee Ana Garcia [EMP-0005]', $this->latest('employee.status')->description);
        $this->assertFalse(Employee::find($ana->id)->is_active);

        // the page shows readable names and values
        $this->get(route('admin.audit.index', ['action' => 'employee.status']))->assertOk()
            ->assertSee('Employee Activated / Deactivated')->assertSee('Active</b>: Yes → No', false);
    }

    public function test_a_selected_company_still_shows_system_wide_entries(): void
    {
        $this->actingAs($this->superAdmin());
        $this->post(route('logout'));
        $this->actingAs($this->superAdmin())->post(route('admin.master.store'), ['category' => 'memory', 'value' => '12GB'])->assertSessionHasNoErrors();
        $this->post(route('admin.company-scope'), ['company_id' => $this->company('AVN')->id]);

        // AHI staff signing out, and a master data change, show under AVN
        $this->get(route('admin.audit.index', ['action' => 'auth.logout']))->assertSee('Carlo Mendoza logged out');
        $this->get(route('admin.audit.index', ['action' => 'lookup.created']))->assertSee('12GB');
        $this->get(route('admin.audit.index'))->assertSee('plus system-wide entries');

        // company data is still filtered: AVN's monitor yes, AHI's laptop no
        $this->get(route('admin.audit.index', ['action' => 'device.created']))->assertSee('MN-0001')->assertDontSee('LT-0001');
    }

    public function test_exporting_the_log_is_logged(): void
    {
        $this->actingAs($this->admin())->get(route('admin.audit.export', ['action' => 'device.assigned']))->assertOk()->streamedContent();

        $entry = $this->latest('audit.exported');
        $this->assertSame('Exported 5 audit log entries to CSV (action=device.assigned)', $entry->description);
        $this->assertSame($this->admin()->id, $entry->user_id);
    }

    public function test_every_action_code_has_a_label(): void
    {
        $used = collect(glob(app_path('{Http/Controllers,Http/Controllers/Admin,Support,Console/Commands}/*.php'), GLOB_BRACE))
            ->flatMap(fn ($f) => preg_match_all("/AuditLog::record\\('([a-z_.]+)'/", file_get_contents($f), $m) ? $m[1] : []);
        $this->assertNotEmpty($used);
        $this->assertSame([], $used->unique()->diff(array_keys(AuditLog::ACTIONS))->values()->all(), 'unlabelled audit actions');
        $this->assertInstanceOf(User::class, $this->admin());
    }
}
