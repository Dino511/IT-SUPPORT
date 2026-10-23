@extends('layouts.app')
@section('title', 'Dashboard')
@section('actions')
    <a href="{{ route('admin.requests.create') }}" class="btn btn-outline-primary"><i class="bi bi-plus-circle me-1" aria-hidden="true"></i>New Request</a>
    <a href="{{ route('admin.devices.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Log Device</a>
@endsection

@section('content')
@php
    $scope = \App\Support\CompanyScope::current();
    $count = fn (string ...$statuses) => collect($statuses)->sum(fn ($s) => $statusCounts[$s] ?? 0);
    $hour = now()->hour;
    $greeting = $hour < 12 ? 'Good morning,' : ($hour < 18 ? 'Good afternoon,' : 'Good evening,');
    $firstName = explode(' ', auth()->user()->name)[0];

    // fleet mix: status => [label, colour]
    $mix = [
        'assigned' => ['Assigned', 'var(--sky)'],
        'available' => ['Available', 'var(--emerald)'],
        'in_repair' => ['In repair', 'var(--amber)'],
        'damaged' => ['Damaged', 'var(--orange)'],
        'lost' => ['Lost', 'var(--rose)'],
        'retired' => ['Retired', 'var(--slate)'],
    ];
    $tiles = [
        ['Total devices', $totalDevices, 'bi-hdd-stack', 'var(--primary)', route('admin.devices.index'), true],
        ['Available', $count('available'), 'bi-check2-circle', 'var(--emerald)', route('admin.devices.index', ['status' => 'available']), false],
        ['Assigned', $count('assigned'), 'bi-person-check', 'var(--sky)', route('admin.devices.index', ['status' => 'assigned']), false],
        ['In repair / damaged', $count('in_repair', 'damaged'), 'bi-tools', 'var(--amber)', route('admin.devices.index', ['status' => 'in_repair']), false],
        ['Lost', $count('lost'), 'bi-question-octagon', 'var(--rose)', route('admin.devices.index', ['status' => 'lost']), false],
        ['Pending requests', $pendingRequests, 'bi-inbox', '#a78bfa', route('admin.requests.index', ['status' => 'pending']), false],
    ];
    $barColors = ['var(--primary)', 'var(--sky)', 'var(--emerald)', 'var(--amber)', '#a78bfa', 'var(--orange)'];
    $maxType = max(1, $typeCounts->max() ?? 1);
@endphp

{{-- summary banner --}}
<section class="hero mb-4" aria-labelledby="hero-title">
    <div class="row g-4 align-items-center">
        <div class="col-lg-7">
            <div class="hero-kicker">{{ $greeting }}</div>
            <h2 class="hero-title" id="hero-title">{{ $firstName }}</h2>
            <p class="mb-3">
                {{ $totalDevices }} {{ Str::plural('device', $totalDevices) }} {{ $scope ? 'at '.$scope->name : 'across '.($companySummary->count() ?: 'all').' companies' }}:
                {{ $count('available') }} ready to issue, {{ $pendingRequests }} {{ Str::plural('request', $pendingRequests) }} awaiting review.
            </p>
            <div class="d-flex flex-wrap gap-2">
                <span class="hero-meta"><i class="bi bi-calendar-event" aria-hidden="true"></i>{{ now()->format('l, M d, Y') }}</span>
                <span class="hero-meta"><i class="bi bi-buildings" aria-hidden="true"></i>{{ $scope ? $scope->name.($scope->country ? ' · '.$scope->country : '') : 'All companies' }}</span>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card hero-panel">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-baseline mb-2">
                        <strong class="font-display">Fleet mix</strong>
                        <span class="mono small text-muted">{{ $totalDevices }} total</span>
                    </div>
                    @if($totalDevices)
                        <div class="mix-bar" role="img" aria-label="Device status mix: {{ collect($mix)->map(fn ($m, $s) => ($statusCounts[$s] ?? 0).' '.strtolower($m[0]))->filter(fn ($t) => ! str_starts_with($t, '0 '))->implode(', ') }}">
                            @foreach($mix as $status => [$label, $color])
                                @if($statusCounts[$status] ?? 0)<span style="width: {{ round(100 * $statusCounts[$status] / $totalDevices, 2) }}%; background: {{ $color }}"></span>@endif
                            @endforeach
                        </div>
                        <div class="mix-legend">
                            @foreach($mix as $status => [$label, $color])
                                @if($statusCounts[$status] ?? 0)<span><i style="background: {{ $color }}"></i>{{ $label }} <b class="mono">{{ $statusCounts[$status] }}</b></span>@endif
                            @endforeach
                        </div>
                    @else
                        <div class="empty-state py-3"><i class="bi bi-box-seam" aria-hidden="true"></i>No devices logged yet.</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>

{{-- stat tiles --}}
<div class="row g-3 mb-4 stat-tiles">
    @foreach($tiles as [$label, $value, $icon, $color, $link, $lightText])
        <div class="col-6 col-md-4 col-xl-2">
            <a href="{{ $link }}" class="stat-tile {{ $lightText ? 'light-text' : '' }}" style="--c: {{ $color }}">
                <span class="stat-ico" aria-hidden="true"><i class="bi {{ $icon }}"></i></span>
                <div class="stat-num">{{ $value }}</div>
                <div class="stat-label">{{ $label }}</div>
            </a>
        </div>
    @endforeach
</div>

@if($devicesWithoutCompany)
    <div class="alert alert-warning d-flex align-items-center gap-2" role="status"><i class="bi bi-exclamation-triangle-fill fs-5" aria-hidden="true"></i>
        <span>{{ $devicesWithoutCompany }} {{ Str::plural('device', $devicesWithoutCompany) }} {{ $devicesWithoutCompany === 1 ? 'has' : 'have' }} no owning company yet. Open each one and choose its company.</span></div>
@endif

<div class="row g-4 mb-4">
    <div class="col-xl-8">
        <div class="card h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong><i class="bi bi-inbox me-1" aria-hidden="true"></i>Open requests</strong>
                <a href="{{ route('admin.requests.index') }}" class="small fw-semibold">View all <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light"><tr><th>Ref #</th><th>Employee</th><th>Type</th><th>Device</th><th>Priority</th><th>Status</th><th>Filed</th></tr></thead>
                    <tbody>
                    @forelse($openRequests as $r)
                        <tr onclick="location='{{ route('admin.requests.show', $r) }}'" style="cursor:pointer">
                            <td class="fw-semibold"><a href="{{ route('admin.requests.show', $r) }}" class="text-reset text-decoration-none">{{ $r->reference_no }}</a></td>
                            <td>{{ $r->employee->full_name }}<div class="small text-muted">{{ $r->employee->department }} · {{ $r->employee->company?->code }}</div></td>
                            <td>{{ $r->type_label }}</td>
                            <td class="mono small">{{ $r->device?->asset_tag ?? $r->device_type_label ?? '-' }}</td>
                            <td><x-status :value="$r->priority" /></td>
                            <td><x-status :value="$r->status" /></td>
                            <td class="small text-nowrap">{{ $r->created_at->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="empty-state"><i class="bi bi-cup-hot" aria-hidden="true"></i>All clear. No open requests right now.</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header bg-white"><strong><i class="bi bi-bar-chart-line me-1" aria-hidden="true"></i>Devices by type</strong></div>
            <div class="py-2">
                @forelse($typeCounts->sortDesc() as $type => $n)
                    <a href="{{ route('admin.devices.index', ['type' => $type]) }}" class="hbar-row">
                        <span class="fw-semibold text-truncate">{{ \App\Models\DeviceType::names()[$type] ?? $type }}</span>
                        <span class="hbar-track" aria-hidden="true"><span class="hbar-fill" style="width: {{ round(100 * $n / $maxType) }}%; --c: {{ $barColors[$loop->index % count($barColors)] }}"></span></span>
                        <span class="hbar-count">{{ $n }}</span>
                    </a>
                @empty
                    <div class="empty-state"><i class="bi bi-box-seam" aria-hidden="true"></i>No devices logged yet.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    @if($companySummary->isNotEmpty())
        <div class="col-xl-8">
            <div class="card h-100">
                <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <strong><i class="bi bi-buildings me-1" aria-hidden="true"></i>By company</strong>
                    <span class="small text-muted">Pick a company in the sidebar to focus on it</span>
                </div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead class="table-light"><tr><th>Company</th><th>Country</th><th class="text-center">People</th><th class="text-center">Devices</th><th style="min-width:180px">Assigned / available</th></tr></thead>
                        <tbody>
                        @foreach($companySummary as $c)
                            @php($other = max(0, $c->devices_count - $c->assigned_count - $c->available_count))
                            <tr>
                                <td><span class="badge text-bg-light border me-2">{{ $c->code }}</span><span class="fw-semibold">{{ $c->name }}</span></td>
                                <td class="small">{{ $c->country ?? '-' }}</td>
                                <td class="text-center mono">{{ $c->employees_count }}</td>
                                <td class="text-center mono fw-bold">{{ $c->devices_count }}</td>
                                <td>
                                    @if($c->devices_count)
                                        <div class="stack-bar" aria-hidden="true">
                                            <span style="width: {{ 100 * $c->assigned_count / $c->devices_count }}%; background: var(--sky)"></span>
                                            <span style="width: {{ 100 * $c->available_count / $c->devices_count }}%; background: var(--emerald)"></span>
                                            <span style="width: {{ 100 * $other / $c->devices_count }}%; background: var(--amber)"></span>
                                        </div>
                                    @endif
                                    <div class="small text-muted mt-1 mono">{{ $c->assigned_count }} assigned · {{ $c->available_count }} available{{ $other ? " · {$other} other" : '' }}</div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <div class="{{ $companySummary->isNotEmpty() ? 'col-xl-4' : 'col-xl-6' }}">
        <div class="card h-100">
            <div class="card-header bg-white"><strong><i class="bi bi-shield-exclamation me-1" aria-hidden="true"></i>Warranty ending soon</strong></div>
            <ul class="list-group list-group-flush">
                @forelse($warrantyExpiring as $d)
                    @php($days = (int) ceil(now()->startOfDay()->diffInDays($d->warranty_until, false)))
                    <a href="{{ route('admin.devices.show', $d) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center gap-2">
                        <span class="min-w-0"><span class="asset-tag">{{ $d->asset_tag }}</span><span class="d-block small text-muted text-truncate">{{ $d->name }} · {{ $d->warranty_until->format('M d, Y') }}</span></span>
                        <span class="due-chip {{ $days <= 14 ? 'urgent' : '' }}">{{ $days <= 0 ? 'today' : "in {$days}d" }}</span>
                    </a>
                @empty
                    <li class="list-group-item"><div class="empty-state py-3"><i class="bi bi-shield-check" aria-hidden="true"></i>Nothing expiring in the next 60 days.</div></li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection
