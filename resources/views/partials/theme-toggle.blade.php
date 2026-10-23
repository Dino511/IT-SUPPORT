{{-- Light/dark switch for every .theme-toggle button; the choice is remembered per browser. --}}
<script>
    (function () {
        const root = document.documentElement;
        function paint() {
            const dark = root.getAttribute('data-bs-theme') === 'dark';
            document.querySelectorAll('.theme-toggle').forEach(btn => {
                btn.setAttribute('aria-label', dark ? 'Switch to light theme' : 'Switch to dark theme');
                btn.setAttribute('aria-pressed', dark ? 'true' : 'false');
                btn.querySelector('i').className = dark ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
            });
        }
        document.querySelectorAll('.theme-toggle').forEach(btn => btn.addEventListener('click', () => {
            const next = root.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
            root.setAttribute('data-bs-theme', next);
            try { localStorage.setItem('theme', next); } catch (e) {}
            paint();
        }));
        paint();
    })();
</script>
