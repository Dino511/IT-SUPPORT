<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('code') · {{ config('app.name') }}</title>
    @include('partials.theme-head')
</head>
<body class="d-grid" style="min-height:100dvh;place-items:center;padding:1.25rem">
<main class="card" style="max-width:480px;width:100%">
    <div class="card-body p-4 p-md-5 text-center">
        <div class="eyebrow justify-content-center"><span class="dot" aria-hidden="true"></span>Error @yield('code')</div>
        <h1 class="page-title mb-2">@yield('heading')</h1>
        <p class="text-muted mb-4">@yield('message')</p>
        <div class="d-flex flex-wrap gap-2 justify-content-center">
            <a href="{{ url()->previous() !== url()->current() ? url()->previous() : url('/') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Go back</a>
            <a href="{{ url('/') }}" class="btn btn-primary"><i class="bi bi-speedometer2 me-1" aria-hidden="true"></i>Dashboard</a>
        </div>
    </div>
</main>
</body>
</html>
