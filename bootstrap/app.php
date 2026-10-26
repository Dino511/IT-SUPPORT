<?php

use App\Http\Controllers\ConnectionStatusController;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // polled by every page; outside the "web" group so it never touches the session
        then: fn () => Route::get('/connection-status', ConnectionStatusController::class)->name('connection-status'),
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // browser security headers on every response; first, so error and maintenance pages get them too
        $middleware->prepend(SecurityHeaders::class);
        // `role:super_admin,admin` route middleware (see app/Http/Middleware/EnsureRole.php)
        $middleware->alias(['role' => EnsureRole::class]);
        // unauthenticated visitors go to the sign-in page
        $middleware->redirectGuestsTo('/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
