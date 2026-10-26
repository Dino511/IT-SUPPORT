<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · {{ config('app.name') }}</title>
    @include('partials.theme-head')
</head>
<body>
<div class="login-page">
    <section class="brand-panel" aria-label="About {{ config('app.name') }}">
        <span class="brand"><span class="brand-mark" aria-hidden="true">IT</span><span class="brand-name">{{ config('app.name') }}<small>IT asset management</small></span></span>

        <div>
            <h1 class="mb-4">Every device, every employee, <span>every company</span> in one place.</h1>
            <ul class="feature-list">
                <li><i class="bi bi-laptop" aria-hidden="true"></i><span><b>Asset register</b>Laptops, phones and gadgets with specs, warranty and PO on file.</span></li>
                <li><i class="bi bi-arrow-left-right" aria-hidden="true"></i><span><b>Assignments &amp; requests</b>Issue, return, repair and lost reports with an approval trail.</span></li>
                <li><i class="bi bi-shield-check" aria-hidden="true"></i><span><b>Audit-ready</b>Every action logged with who and when, exportable to CSV.</span></li>
            </ul>
        </div>

        <div class="small" style="color:#9aa3c7">Multi-company IT support · Authorised staff only</div>
    </section>

    <section class="login-side">
        <div class="login-card">
            <div class="eyebrow"><span class="dot" aria-hidden="true"></span>Staff sign-in</div>
            <h2 class="page-title mb-1">Welcome back</h2>
            <p class="text-muted mb-4">Sign in with your IT Support account.</p>

            <div class="card">
                <div class="card-body p-4">
                    @if($errors->any())
                        <div class="alert alert-danger py-2 small d-flex gap-2" role="alert"><i class="bi bi-exclamation-octagon-fill" aria-hidden="true"></i>{{ $errors->first() }}</div>
                    @endif
                    <form method="POST" action="{{ route('login') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label" for="email">Email</label>
                            <input type="email" name="email" id="email" value="{{ old('email') }}" class="form-control" required autofocus autocomplete="username">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="password">Password</label>
                            <div class="input-group">
                                <input type="password" name="password" id="password" class="form-control" required autocomplete="current-password">
                                <button class="btn btn-outline-secondary" type="button" id="toggle-pw" aria-label="Show password" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i></button>
                            </div>
                        </div>
                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" name="remember" id="remember">
                            <label class="form-check-label" for="remember">Keep me signed in</label>
                        </div>
                        <button class="btn btn-primary w-100 py-2">Sign in</button>
                    </form>
                </div>
            </div>
            <div class="d-flex justify-content-end mt-3">
                <button type="button" class="btn btn-sm btn-outline-secondary theme-toggle" aria-label="Switch to dark theme"><i class="bi bi-moon-stars-fill" aria-hidden="true"></i></button>
            </div>
        </div>
    </section>
</div>
<script>
    document.getElementById('toggle-pw').addEventListener('click', function () {
        const pw = document.getElementById('password'), show = pw.type === 'password';
        pw.type = show ? 'text' : 'password';
        this.setAttribute('aria-pressed', show);
        this.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        this.querySelector('i').className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
    });
</script>
@include('partials.theme-toggle')
@include('partials.connection-status')
</body>
</html>
