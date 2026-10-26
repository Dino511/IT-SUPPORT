<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * GET /connection-status, which every page polls for the connection card
 * (partials/connection-status). Needs no tables, so no RefreshDatabase.
 */
class ConnectionStatusTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // a separate file cache, so the live app's cached internet result is never touched
        config(['cache.stores.file.path' => storage_path('framework/testing/connection-status-cache')]);
    }

    public function test_it_reports_server_database_and_internet_without_a_session(): void
    {
        Cache::store('file')->put('connection-status.internet', false, 60); // no real internet probe in tests

        $this->getJson('/connection-status')
            ->assertOk()
            ->assertExactJson(['server' => true, 'database' => true, 'internet' => false])
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeaderMissing('Set-Cookie'); // polling must never start or extend a session
    }

    public function test_it_reports_a_database_that_does_not_answer(): void
    {
        Cache::store('file')->put('connection-status.internet', true, 60);
        DB::shouldReceive('select')->once()->andThrow(new RuntimeException('SQL Server is not running'));

        $this->getJson('/connection-status')
            ->assertOk()
            ->assertExactJson(['server' => true, 'database' => false, 'internet' => true]);
    }

    public function test_the_card_is_on_the_sign_in_page_and_in_the_app_layout(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('id="conn-status"', false)
            ->assertSee(json_encode(route('connection-status')), false);

        $this->assertStringContainsString("@include('partials.connection-status')", file_get_contents(resource_path('views/layouts/app.blade.php')));
    }
}
