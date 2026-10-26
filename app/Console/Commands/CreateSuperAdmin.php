<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Creates a Super Admin login from the command line: the first account on a
 * fresh installation (which has no users), or a way back in if every Super
 * Admin is locked out.
 *
 *   php artisan app:create-super-admin
 *   php artisan app:create-super-admin --name="Juan Dela Cruz" --email=juan@company.com
 *
 * The password is always asked for (typing is hidden), never taken as an
 * option, so it doesn't end up in the shell history. Same rules as the User
 * Accounts page: unique email, password of at least 8 characters.
 */
class CreateSuperAdmin extends Command
{
    protected $signature = 'app:create-super-admin
                            {--name= : Full name}
                            {--email= : Sign-in email}';

    protected $description = 'Create a Super Admin login (e.g. the first account on a new installation)';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Full name');
        $email = $this->option('email') ?: $this->ask('Sign-in email');
        $password = $this->secret('Password (at least 8 characters, hidden while typing)');
        $confirmation = $this->secret('Type the password again');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password, 'password_confirmation' => $confirmation],
            [
                'name' => ['required', 'string', 'max:150'],
                'email' => ['required', 'email', 'max:150', 'unique:users,email'],
                'password' => ['required', 'confirmed', Password::min(8)],
            ],
        );
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create(['name' => $name, 'email' => $email, 'password' => $password, 'role' => User::ROLE_SUPER_ADMIN, 'is_active' => true]);
        AuditLog::record('user.created', "Created account {$user->name} ({$user->email}) as Super Admin from the command line");

        $this->info("Super Admin {$user->email} created. Sign in, then link it to your employee record under User Accounts if you like.");

        return self::SUCCESS;
    }
}
