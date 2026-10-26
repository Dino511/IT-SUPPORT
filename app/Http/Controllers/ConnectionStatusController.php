<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Tiny status check that every page polls (partials/connection-status) so users
 * can see when they lose the connection to the server, when the database stops
 * answering, or when the office has no internet ("offline mode": the app keeps
 * working on the local network).
 *
 * Route: GET /connection-status. It is registered outside the "web" middleware
 * group (bootstrap/app.php), so polling never starts a session, sets a cookie
 * or keeps an idle session alive.
 */
class ConnectionStatusController extends Controller
{
    /** Public addresses tried for "is there internet?": a TCP connection only, no data is sent. */
    private const INTERNET_PROBES = [['1.1.1.1', 443], ['8.8.8.8', 443]];

    /** How long the internet result is reused, so all open pages together cause one probe a minute. */
    private const INTERNET_CACHE_SECONDS = 60;

    public function __invoke(): JsonResponse
    {
        return response()->json([
            'server' => true,
            'database' => $this->databaseUp(),
            // file cache, not the default (database) store, so this still answers when SQL Server is down
            'internet' => Cache::store('file')->remember('connection-status.internet', self::INTERNET_CACHE_SECONDS, fn () => $this->internetUp()),
        ])->header('Cache-Control', 'no-store');
    }

    private function databaseUp(): bool
    {
        try {
            DB::select('SELECT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function internetUp(): bool
    {
        foreach (self::INTERNET_PROBES as [$host, $port]) {
            $socket = @fsockopen($host, $port, $errorCode, $errorMessage, 1.5);
            if ($socket) {
                fclose($socket);

                return true;
            }
        }

        return false;
    }
}
