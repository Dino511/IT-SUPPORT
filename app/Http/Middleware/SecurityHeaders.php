<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Standard browser security headers on every response from the app (registered
 * first in bootstrap/app.php, so error, 429 and maintenance pages get them too):
 *
 *   X-Frame-Options, frame-ancestors  the app can't be shown inside another site's
 *                                     frame (stops "clickjacking" tricks)
 *   X-Content-Type-Options            browsers never guess a different file type
 *   Referrer-Policy                   other sites never see the app's addresses
 *   Permissions-Policy                no camera, microphone or location access
 *   Content-Security-Policy           web pages may only load scripts, styles,
 *                                     fonts, images and data from this server
 *
 * Everything the pages use is on this server (public/vendor), so nothing
 * legitimate is blocked. Inline <script> blocks and onclick="" handlers are
 * allowed ('unsafe-inline'); anything from another website is not. If a page
 * ever needs something external, add its address to CONTENT_SECURITY_POLICY.
 *
 * Files (PO documents, CSV export, JSON) get the policy only when they are web
 * pages, so the browser's PDF viewer keeps working. Static files in public/ are
 * served by the web server directly and don't pass through here.
 */
class SecurityHeaders
{
    private const CONTENT_SECURITY_POLICY = "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; "
        ."img-src 'self' data:; font-src 'self'; connect-src 'self'; form-action 'self'; "
        ."frame-ancestors 'self'; base-uri 'self'; object-src 'none'";

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'same-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // pages from views get their Content-Type only when sent, so "not set yet" means HTML
        $type = (string) $headers->get('Content-Type');
        if ($type === '' || str_starts_with($type, 'text/html')) {
            $headers->set('Content-Security-Policy', self::CONTENT_SECURITY_POLICY);
        }

        return $response;
    }
}
