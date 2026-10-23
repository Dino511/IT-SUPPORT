<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\CompanyScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Audit Logs page and CSV export (both roles). Read-only: entries are written
 * by AuditLog::record() wherever something changes.
 *
 * Filters (shared by the page and the export): action, user_id, from/to dates
 * (inclusive) and q (description text, asset tag, serial or employee ID).
 */
class AuditLogController extends Controller
{
    /** Paginated log, newest first, 40 per page, grouped by day in the view. */
    public function index(Request $request)
    {
        return view('admin.audit.index', [
            'logs' => $this->query($request)->paginate(40)->withQueryString(),
            'users' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Download the filtered log as CSV. Streamed in chunks of 500 rows so large
     * logs don't have to fit in memory.
     */
    public function export(Request $request)
    {
        $filename = 'audit-log-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($request) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date/Time', 'User', 'Action', 'Company', 'Asset Tag', 'Employee No', 'Employee', 'Request', 'Description', 'IP']);
            $this->query($request)->chunk(500, function ($logs) use ($out) {
                foreach ($logs as $log) {
                    fputcsv($out, [
                        $log->created_at?->format('Y-m-d H:i:s'),
                        $log->user?->name ?? 'System',
                        $log->action_label,
                        $log->device?->company?->name ?? $log->employee?->company?->name,
                        $log->device?->asset_tag,
                        $log->employee?->employee_no,
                        $log->employee?->full_name,
                        $log->request?->reference_no,
                        $log->description,
                        $log->ip_address,
                    ]);
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Narrow logs to the selected company: entries about one of its devices
     * or employees. Master data and account entries show under "All companies".
     */
    public static function scoped(Builder $query): Builder
    {
        return $query->when(CompanyScope::id(), fn ($q, $id) => $q->where(fn ($w) => $w
            ->whereHas('device', fn ($d) => $d->where('company_id', $id))
            ->orWhereHas('employee', fn ($e) => $e->where('company_id', $id))));
    }

    /** Base query with the request's filters applied and relations eager-loaded. */
    private function query(Request $request)
    {
        return static::scoped(AuditLog::with('user', 'device.company', 'employee.company', 'request'))
            ->when($request->action, fn ($q, $a) => $q->where('action', $a))
            ->when($request->user_id, fn ($q, $u) => $q->where('user_id', $u))
            ->when($request->from, fn ($q, $d) => $q->where('created_at', '>=', $d.' 00:00:00'))
            ->when($request->to, fn ($q, $d) => $q->where('created_at', '<=', $d.' 23:59:59'))
            ->when($request->q, function ($query, $q) {
                $query->where(fn ($w) => $w->where('description', 'like', "%{$q}%")
                    ->orWhereHas('device', fn ($d) => $d->where('asset_tag', 'like', "%{$q}%")->orWhere('serial_number', 'like', "%{$q}%"))
                    ->orWhereHas('employee', fn ($e) => $e->where('employee_no', 'like', "%{$q}%")));
            })
            ->orderByDesc('created_at')->orderByDesc('id');
    }
}
