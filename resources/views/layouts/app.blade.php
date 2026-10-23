<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ config('app.name') }}</title>
    @include('partials.theme-head')
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    @stack('styles')
</head>
<body>
@php
    $me = auth()->user();
    $scope = \App\Support\CompanyScope::current();
    $pending = \App\Support\CompanyScope::applyVia(\App\Models\DeviceRequest::where('status', 'pending'), 'employee')->count();
    $eyebrow = trim($__env->yieldContent('eyebrow')) ?: match (true) {
        request()->routeIs('admin.dashboard') => 'Command center',
        request()->routeIs('admin.devices.*') => 'Inventory · Devices',
        request()->routeIs('admin.employees.*') => 'People · Employees',
        request()->routeIs('admin.requests.*') => 'Service desk · Requests',
        request()->routeIs('admin.audit.*') => 'Compliance · Audit trail',
        request()->routeIs('admin.master.*') => 'Super Admin · Master data',
        request()->routeIs('admin.users.*') => 'Super Admin · Accounts',
        default => config('app.name'),
    };
    $nav = [
        // route, active pattern, label, icon, accent
        ['admin.dashboard', 'admin.dashboard', 'Dashboard', 'bi-speedometer2', '#818cf8'],
        ['admin.devices.index', 'admin.devices.*', 'Devices', 'bi-laptop', 'var(--sky)'],
        ['admin.employees.index', 'admin.employees.*', 'Employees', 'bi-people', 'var(--emerald)'],
        ['admin.requests.index', ['admin.requests.index', 'admin.requests.show'], 'Requests', 'bi-inbox', 'var(--amber)'],
        ['admin.requests.create', 'admin.requests.create', 'New Request', 'bi-plus-circle', '#a78bfa'],
        ['admin.audit.index', 'admin.audit.*', 'Audit Logs', 'bi-journal-text', 'var(--orange)'],
    ];
    $superNav = [
        ['admin.master.index', 'admin.master.*', 'Master Data', 'bi-database-gear', '#2dd4bf'],
        ['admin.users.index', 'admin.users.*', 'User Accounts', 'bi-person-lock', 'var(--rose)'],
    ];
    $initials = collect(explode(' ', $me->name))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('');
@endphp
<a href="#main" class="skip-link">Skip to content</a>

{{-- mobile top bar --}}
<header class="topbar d-lg-none d-flex align-items-center justify-content-between px-3 py-2">
    <a href="{{ route('home') }}" class="brand"><span class="brand-mark" style="width:34px;height:34px;font-size:.9rem" aria-hidden="true">IT</span><span class="brand-name">{{ config('app.name') }}</span></a>
    <button class="btn btn-sm btn-ghost-light" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar" aria-label="Open menu">
        <i class="bi bi-list fs-5" aria-hidden="true"></i>
    </button>
</header>

<div class="app-shell d-lg-flex">
    <nav class="sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="sidebar" aria-label="Main navigation">
        <div class="offcanvas-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <a href="{{ route('home') }}" class="brand">
                    <span class="brand-mark" aria-hidden="true">IT</span>
                    <span class="brand-name">{{ config('app.name') }}<small>IT asset management</small></span>
                </a>
                <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Close menu"></button>
            </div>

            <form method="POST" action="{{ route('admin.company-scope') }}">@csrf
                <label class="side-label d-block" for="company-scope">Viewing company</label>
                <select name="company_id" id="company-scope" class="form-select form-select-sm scope-select" onchange="this.form.submit()">
                    <option value="">All companies</option>
                    @foreach(\App\Models\Company::active() as $c)
                        <option value="{{ $c->id }}" @selected($scope?->id === $c->id)>{{ $c->code }} - {{ $c->name }}</option>
                    @endforeach
                </select>
            </form>

            <div class="side-scroll">
            <div class="side-label">Inventory</div>
            <ul class="side-nav">
                @foreach($nav as [$route, $pattern, $label, $icon, $color])
                    @php($active = request()->routeIs(...(array) $pattern))
                    <li>
                        <a href="{{ route($route) }}" class="nav-link {{ $active ? 'active' : '' }}" @if($active) aria-current="page" @endif>
                            <span class="nav-ico" style="--c: {{ $color }}" aria-hidden="true"><i class="bi {{ $icon }}"></i></span>
                            {{ $label }}
                            @if($route === 'admin.requests.index' && $pending)
                                <span class="count" aria-label="{{ $pending }} pending">{{ $pending }}</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>

            @if($me->isSuperAdmin())
                <div class="side-label">Super Admin</div>
                <ul class="side-nav">
                    @foreach($superNav as [$route, $pattern, $label, $icon, $color])
                        @php($active = request()->routeIs($pattern))
                        <li>
                            <a href="{{ route($route) }}" class="nav-link {{ $active ? 'active' : '' }}" @if($active) aria-current="page" @endif>
                                <span class="nav-ico" style="--c: {{ $color }}" aria-hidden="true"><i class="bi {{ $icon }}"></i></span>{{ $label }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
            </div>

            <div class="side-user">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="avatar" aria-hidden="true">{{ $initials }}</span>
                    <div class="min-w-0">
                        <div class="fw-bold text-truncate">{{ $me->name }}</div>
                        <span class="role-chip">{{ $me->role_label }}</span>
                    </div>
                </div>
                @if($me->employee)<a href="{{ route('admin.employees.show', $me->employee) }}" class="small d-inline-block mb-2"><i class="bi bi-person-badge me-1" aria-hidden="true"></i>My devices</a>@endif
                <div class="d-flex gap-2">
                    <form method="POST" action="{{ route('logout') }}" class="flex-grow-1">@csrf
                        <button class="btn btn-sm btn-ghost-light w-100"><i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i>Log out</button>
                    </form>
                    <button type="button" class="btn btn-sm btn-ghost-light theme-toggle" aria-label="Switch to dark theme" title="Toggle light/dark theme">
                        <i class="bi bi-moon-stars-fill" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <main id="main" tabindex="-1" class="flex-grow-1">
        <div class="page-wrap">
            <div class="page-head d-flex flex-wrap align-items-end justify-content-between gap-3">
                <div class="min-w-0">
                    <div class="eyebrow"><span class="dot" aria-hidden="true"></span>{{ $eyebrow }}</div>
                    <h1 class="page-title">@yield('title')
                        @if($scope)<span class="chip ms-1" title="Lists are filtered to {{ $scope->name }}"><i class="bi bi-funnel-fill me-1" aria-hidden="true"></i>{{ $scope->code }}</span>@endif
                    </h1>
                </div>
                <div class="d-flex flex-wrap gap-2">@yield('actions')</div>
            </div>

            @foreach (['success' => ['success', 'bi-check-circle-fill', 'status'], 'error' => ['danger', 'bi-exclamation-octagon-fill', 'alert']] as $key => [$class, $icon, $role])
                @if(session($key))
                    <div class="alert alert-{{ $class }} alert-dismissible fade show d-flex align-items-center gap-2" role="{{ $role }}">
                        <i class="bi {{ $icon }} fs-5" aria-hidden="true"></i><span>{{ session($key) }}</span>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Dismiss"></button></div>
                @endif
            @endforeach
            @if($errors->any())
                <div class="alert alert-danger" role="alert">
                    <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-1" aria-hidden="true"></i>Please fix the following:</div>
                    <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif

            @yield('content')
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script>
    /*
     * Searchable dropdowns. <select data-combo> lets the user type to filter the
     * master data options; data-combo="create" additionally lets the Super Admin
     * type a new value, which is saved to the master data with the form.
     */
    const IS_SUPER_ADMIN = @json($me->isSuperAdmin());
    function initCombo(el) {
        if (el.tomselect) return el.tomselect;
        const canCreate = el.dataset.combo === 'create' && IS_SUPER_ADMIN;
        return new TomSelect(el, {
            create: canCreate,
            createOnBlur: canCreate,
            persist: false,
            maxOptions: 1000,
            allowEmptyOption: false,
            plugins: el.required ? [] : ['clear_button'],
            placeholder: el.dataset.placeholder || (canCreate ? 'Search or add...' : 'Search...'),
            render: {
                option_create: (data, escape) => `<div class="create">+ Add <strong>${escape(data.input)}</strong> to master data</div>`,
                no_results: () => `<div class="no-results text-muted small px-2 py-1">${canCreate ? 'No match' : 'No match. Ask the Super Admin to add it.'}</div>`,
            },
        });
    }
    document.querySelectorAll('select[data-combo]').forEach(initCombo);
</script>
@include('partials.theme-toggle')
@stack('scripts')
</body>
</html>
