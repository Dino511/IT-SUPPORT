<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware `role:<role>[,<role>...]` - lets the request through only
 * when the signed-in user has one of the listed roles, otherwise 403.
 * Registered as the "role" alias in bootstrap/app.php.
 *
 * Example: Route::middleware('role:super_admin')->group(...)
 */
class EnsureRole
{
    /**
     * @param  string  ...$roles  allowed User::ROLE_* values
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_unless($user && in_array($user->role, $roles, true), 403, 'You are not allowed to access this page.');

        return $next($request);
    }
}
