<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Browser security headers (App\Http\Middleware\SecurityHeaders) and the sign-in rate limit page. */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    private function assertSecurityHeaders(TestResponse $response): TestResponse
    {
        return $response->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'same-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }

    public function test_pages_send_the_security_headers_and_content_policy(): void
    {
        foreach ([$this->get('/login'), $this->actingAs($this->admin())->get(route('admin.dashboard')), $this->get('/admin/no-such-page')] as $response) {
            $policy = $this->assertSecurityHeaders($response)->headers->get('Content-Security-Policy');
            $this->assertStringContainsString("default-src 'self'", $policy);
            $this->assertStringContainsString("frame-ancestors 'self'", $policy);
            $this->assertStringContainsString("object-src 'none'", $policy);
        }
    }

    public function test_files_and_data_get_the_headers_but_no_page_policy(): void
    {
        // JSON (the connection check)
        $this->assertSecurityHeaders($this->getJson('/connection-status'))->assertHeaderMissing('Content-Security-Policy');

        // a PO file opens in the browser's own viewer, which a page policy could block
        Storage::fake('local');
        $device = $this->device('LT-0001');
        Storage::disk('local')->put("po/{$device->id}/PO-123.pdf", '%PDF-1.4 test');
        $device->update(['po_file_path' => "po/{$device->id}/PO-123.pdf", 'po_file_name' => 'PO-123.pdf']);

        $this->assertSecurityHeaders($this->actingAs($this->admin())->get(route('admin.devices.po-file', $device))->assertOk())
            ->assertHeaderMissing('Content-Security-Policy');
    }

    public function test_too_many_sign_in_attempts_show_the_themed_page(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->post('/login', ['email' => 'admin@company.test', 'password' => "guess-{$i}"])->assertRedirect();
        }

        $response = $this->post('/login', ['email' => 'admin@company.test', 'password' => 'guess-11'])
            ->assertStatus(429)
            ->assertSee('Too many attempts')
            ->assertSee('seconds</b>, then try again', false);
        $this->assertSecurityHeaders($response);
        $this->assertNotNull($response->headers->get('Retry-After'));
    }
}
