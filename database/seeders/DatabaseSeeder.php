<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Development / demo database: `php artisan migrate:fresh --seed` (also used by
 * the test suite). Do NOT run against production data: migrate:fresh drops
 * every table, and the demo logins use the password "password".
 *
 * A real installation runs only the master data:
 *   php artisan db:seed --class=MasterDataSeeder --force
 * then creates its first login with  php artisan app:create-super-admin
 * (see docs/DEPLOYMENT.md).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([MasterDataSeeder::class, DemoDataSeeder::class]);
    }
}
