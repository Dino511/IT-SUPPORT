<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\DeviceType;
use App\Models\Lookup;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** What a real installation runs: the master data seeder and the first Super Admin command. */
class InstallationTest extends TestCase
{
    use RefreshDatabase;

    private const ASK_NAME = 'Full name';

    private const ASK_EMAIL = 'Sign-in email';

    private const ASK_PASSWORD = 'Password (at least 8 characters, hidden while typing)';

    private const ASK_AGAIN = 'Type the password again';

    public function test_master_data_seeder_only_adds_what_is_missing(): void
    {
        $before = [DeviceType::count(), Lookup::count()];
        $this->seed(MasterDataSeeder::class); // already seeded once: nothing new, no duplicate errors
        $this->assertSame($before, [DeviceType::count(), Lookup::count()]);

        // it brings back a removed option, but leaves Super Admin edits alone
        Lookup::where('category', 'memory')->where('value', '64GB')->delete();
        DeviceType::where('code', 'laptop')->update(['prefix' => 'NB']);
        $this->seed(MasterDataSeeder::class);
        $this->assertTrue(Lookup::where('category', 'memory')->where('value', '64GB')->exists());
        $this->assertSame('NB', DeviceType::where('code', 'laptop')->value('prefix'));
        $this->assertSame(8, DeviceType::count());
    }

    public function test_create_super_admin_command(): void
    {
        $this->artisan('app:create-super-admin')
            ->expectsQuestion(self::ASK_NAME, 'Rosa Villanueva')
            ->expectsQuestion(self::ASK_EMAIL, 'rosa.villanueva@company.test')
            ->expectsQuestion(self::ASK_PASSWORD, 'Str0ng-pass')
            ->expectsQuestion(self::ASK_AGAIN, 'Str0ng-pass')
            ->expectsOutputToContain('Super Admin rosa.villanueva@company.test created')
            ->assertSuccessful();

        $user = User::where('email', 'rosa.villanueva@company.test')->firstOrFail();
        $this->assertTrue($user->isSuperAdmin());
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('Str0ng-pass', $user->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.created', 'user_id' => null]);
        $this->assertStringContainsString('from the command line', AuditLog::latest('id')->value('description'));

        // and it can sign in and reach Super Admin pages
        $this->post(route('login'), ['email' => 'rosa.villanueva@company.test', 'password' => 'Str0ng-pass'])->assertRedirect();
        $this->get(route('admin.users.index'))->assertOk();
    }

    public function test_create_super_admin_refuses_bad_input(): void
    {
        $run = fn (string $email, string $password, string $again) => $this->artisan('app:create-super-admin', ['--name' => 'Test Person', '--email' => $email])
            ->expectsQuestion(self::ASK_PASSWORD, $password)
            ->expectsQuestion(self::ASK_AGAIN, $again);

        $run('superadmin@company.test', 'Str0ng-pass', 'Str0ng-pass')->expectsOutputToContain('already been taken')->assertFailed();
        $run('new.person@company.test', 'short', 'short')->expectsOutputToContain('at least 8 characters')->assertFailed();
        $run('new.person@company.test', 'Str0ng-pass', 'Different-1')->expectsOutputToContain('confirmation does not match')->assertFailed();
        $run('not-an-email', 'Str0ng-pass', 'Str0ng-pass')->expectsOutputToContain('valid email')->assertFailed();

        $this->assertFalse(User::where('email', 'new.person@company.test')->exists());
    }
}
