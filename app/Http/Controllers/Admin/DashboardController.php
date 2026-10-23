<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Device;
use App\Models\DeviceRequest;
use App\Models\Employee;
use App\Support\CompanyScope;

/**
 * Dashboard (home page after login, both roles). Every figure respects the
 * sidebar company switcher (CompanyScope).
 */
class DashboardController extends Controller
{
    /**
     * Gathers the summary banner, stat tiles, open requests, devices by type,
     * warranties ending in the next 60 days and - when viewing all companies -
     * the per-company breakdown and the count of devices with no company yet.
     */
    public function __invoke()
    {
        // fresh scoped query each time it's called (query builders are mutable)
        $devices = fn () => CompanyScope::apply(Device::query());

        $statusCounts = $devices()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $typeCounts = $devices()->selectRaw('type, COUNT(*) as total')->groupBy('type')->pluck('total', 'type');

        return view('admin.dashboard', [
            'statusCounts' => $statusCounts,
            'typeCounts' => $typeCounts,
            'totalDevices' => $statusCounts->sum(),
            'totalEmployees' => CompanyScope::apply(Employee::where('is_active', true))->count(),
            'pendingRequests' => CompanyScope::applyVia(DeviceRequest::where('status', 'pending'), 'employee')->count(),
            'openRequests' => CompanyScope::applyVia(DeviceRequest::with('employee.company', 'device'), 'employee')
                ->whereIn('status', ['pending', 'approved'])->latest()->take(8)->get(),
            'warrantyExpiring' => $devices()->whereNotIn('status', ['retired', 'lost'])
                ->whereBetween('warranty_until', [now()->startOfDay(), now()->addDays(60)])
                ->orderBy('warranty_until')->get(),
            // per-company breakdown, only when viewing all companies
            'companySummary' => CompanyScope::id() ? collect() : Company::where('is_active', true)->orderBy('name')
                ->withCount(['devices', 'devices as assigned_count' => fn ($q) => $q->where('status', 'assigned'),
                    'devices as available_count' => fn ($q) => $q->where('status', 'available'),
                    'employees' => fn ($q) => $q->where('is_active', true)])
                ->get(),
            'devicesWithoutCompany' => CompanyScope::id() ? 0 : Device::whereNull('company_id')->count(),
        ]);
    }
}
