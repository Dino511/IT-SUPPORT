{{-- Fonts, Bootstrap and the maximalist theme. The inline script applies the
     saved (or system) light/dark theme before first paint to avoid a flash.
     Everything is served from public/vendor (no CDN), so pages work without
     internet: Bootstrap 5.3.3, Bootstrap Icons 1.11.3 and the two web fonts. --}}
<script>
    (function () {
        var theme;
        try { theme = localStorage.getItem('theme'); } catch (e) {}
        if (theme !== 'light' && theme !== 'dark') {
            theme = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        document.documentElement.setAttribute('data-bs-theme', theme);
    })();
</script>
<link href="{{ asset('vendor/fonts/fonts.css') }}" rel="stylesheet">
<link href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
<link href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}" rel="stylesheet">
<link href="{{ asset('css/theme.css') }}?v={{ filemtime(public_path('css/theme.css')) }}" rel="stylesheet">
