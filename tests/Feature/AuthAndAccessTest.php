<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\DeviceRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

/** Sign-in, roles, and a smoke test that every page renders for both roles. */
class AuthAndAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/admin')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('Welcome back');
    }

    public function test_login_works_and_is_audited(): void
    {
        $this->post('/login', ['email' => 'admin@company.test', 'password' => 'password'])
            ->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($this->admin());
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login', 'user_id' => $this->admin()->id]);
    }

    public function test_wrong_password_and_disabled_accounts_are_refused(): void
    {
        $this->post('/login', ['email' => 'admin@company.test', 'password' => 'nope'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->admin()->update(['is_active' => false]);
        $this->post('/login', ['email' => 'admin@company.test', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_admins_cannot_open_super_admin_pages(): void
    {
        $this->actingAs($this->admin());

        $this->get(route('admin.master.index'))->assertForbidden();
        $this->get(route('admin.users.index'))->assertForbidden();
        $this->post(route('admin.master.store'), ['category' => 'brand', 'value' => 'Acer'])->assertForbidden();
        $this->post(route('admin.master.companies.store'), ['code' => 'X', 'name' => 'X Co'])->assertForbidden();
    }

    public function test_every_page_renders_for_both_roles(): void
    {
        // a request so the request page exists
        $this->actingAs($this->admin())->post(route('admin.requests.store'), [
            'employee_id' => $this->employee('EMP-0002')->id, 'request_type' => 'repair',
            'device_id' => $this->device('LT-0001')->id, 'priority' => 'normal', 'reason' => 'Smoke test',
        ]);
        $request = DeviceRequest::firstOrFail();

        $pages = [
            route('admin.dashboard'),
            route('admin.devices.index'), route('admin.devices.create'),
            route('admin.devices.show', $this->device('LT-0001')), route('admin.devices.edit', $this->device('LT-0001')),
            route('admin.employees.index'), route('admin.employees.create'),
            route('admin.employees.show', $this->employee('EMP-0002')), route('admin.employees.edit', $this->employee('EMP-0002')),
            route('admin.requests.index'), route('admin.requests.index', ['status' => '']), route('admin.requests.create'),
            route('admin.requests.create', ['employee' => $this->employee('EMP-0002')->id, 'device' => $this->device('LT-0001')->id, 'type' => 'repair']),
            route('admin.requests.show', $request),
            route('admin.audit.index'), route('admin.audit.index', ['action' => 'device.assigned', 'q' => 'LT']),
        ];
        $superOnly = [
            route('admin.master.index'), route('admin.master.index', ['category' => 'device_types']),
            route('admin.master.index', ['category' => 'model']), route('admin.master.index', ['category' => 'department']),
            route('admin.users.index'), route('admin.users.create'), route('admin.users.edit', $this->admin()),
        ];

        foreach ([$this->admin(), $this->superAdmin()] as $user) {
            foreach ($pages as $url) {
                $this->actingAs($user)->get($url)->assertOk();
            }
        }
        foreach ($superOnly as $url) {
            $this->actingAs($this->superAdmin())->get($url)->assertOk();
        }
    }

    public function test_unknown_pages_show_the_themed_404(): void
    {
        $this->actingAs($this->admin())->get('/admin/does-not-exist')->assertNotFound()->assertSee('Not found');
    }

    public function test_with_debug_off_errors_show_the_themed_page_without_details(): void
    {
        config(['app.debug' => false, 'logging.default' => 'null']);
        Route::middleware('web')->get('/_test/boom', fn () => throw new RuntimeException('SQLSTATE secret connection detail'));

        $this->actingAs($this->admin())->get('/_test/boom')->assertStatus(500)
            ->assertSee('Something went wrong')
            ->assertDontSee('SQLSTATE secret connection detail')
            ->assertDontSee('Stack Trace');

        // maintenance mode (php artisan down) has its own page too
        $this->assertStringContainsString('Under maintenance', view('errors.503')->render());
    }

    public function test_logout(): void
    {
        $this->actingAs($this->admin())->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertSame(1, AuditLog::where('action', 'auth.logout')->count());
    }
}
