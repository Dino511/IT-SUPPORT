{{-- Connection status card (bottom-right) so users know when something is off:
       device    this PC has no network            (red)
       server    the IT Inventory server doesn't answer (red)
       database  the server answers but SQL Server doesn't (red)
       updating  maintenance mode, php artisan down  (amber)
       offline   no internet, the app still works on the office network (amber, can be hidden)
     then "Connection restored" (green) for a few seconds. Nothing shows while all is well.
     Polls route('connection-status') every 20 s (every 5 s while there is a problem, and
     right away when the PC goes on/offline or the tab comes back). While there is a
     problem, submitting a form re-checks first and, if still down, keeps the page (and
     whatever was typed) instead of sending it into a browser error page.
     Plain JavaScript: also used on the sign-in page, which has no Bootstrap JS. --}}
<div id="conn-status" class="conn-status" hidden>
    <span class="conn-ico" aria-hidden="true"><i class="bi"></i></span>
    <div class="conn-body">
        <strong class="conn-title"></strong>
        <span class="conn-msg"></span>
        <span class="conn-retry" hidden><span class="conn-spinner" aria-hidden="true"></span>Checking again automatically</span>
    </div>
    <button type="button" class="conn-btn" data-conn="retry" hidden>Try now</button>
    <button type="button" class="conn-x" data-conn="close" aria-label="Hide this message" hidden><i class="bi bi-x-lg" aria-hidden="true"></i></button>
</div>
<script>
    (function () {
        const STATUS_URL = @json(route('connection-status'));
        const box = document.getElementById('conn-status');
        const STATES = {
            device: { tone: 'danger', icon: 'bi-wifi-off', title: 'This computer is offline', msg: 'Check the network cable or Wi-Fi. Saving won\'t work until the connection is back; what you typed stays on the page.' },
            server: { tone: 'danger', icon: 'bi-hdd-network', title: 'Can\'t reach IT Inventory', msg: 'The server isn\'t answering. Check your network, or ask IT whether the server PC is on. Saving won\'t work until it\'s back.' },
            database: { tone: 'danger', icon: 'bi-database-exclamation', title: 'Database not responding', msg: 'The server is up but its database isn\'t answering, so saving won\'t work. Please tell IT.' },
            updating: { tone: 'info', icon: 'bi-tools', title: 'IT Inventory is being updated', msg: 'It will be back in a few minutes. Please wait before saving anything.' },
            offline: { tone: 'info', icon: 'bi-cloud-slash', title: 'Offline mode', msg: 'No internet connection. IT Inventory keeps working normally on the office network.', closable: true },
            restored: { tone: 'success', icon: 'bi-wifi', title: 'Connection restored', msg: 'Everything is working again.' },
        };
        const PROBLEMS = ['device', 'server', 'database', 'updating'];
        let state = 'ok', timer = null, hideTimer = null, offlineHidden = false;
        try { offlineHidden = sessionStorage.getItem('conn-offline-hidden') === '1'; } catch (e) {}

        function show(name) {
            const s = STATES[name], problem = PROBLEMS.includes(name);
            box.className = 'conn-status tone-' + s.tone;
            box.setAttribute('role', s.tone === 'danger' ? 'alert' : 'status');
            box.querySelector('.conn-ico i').className = 'bi ' + s.icon;
            box.querySelector('.conn-title').textContent = s.title;
            box.querySelector('.conn-msg').textContent = s.msg;
            box.querySelector('.conn-retry').hidden = !problem;
            box.querySelector('[data-conn=retry]').hidden = !problem;
            box.querySelector('[data-conn=close]').hidden = !s.closable;
            box.hidden = false;
        }

        // Only a change of state touches the card, so repeated checks don't cancel
        // the "restored" message's timer or re-show a hidden offline-mode card.
        function setState(next) {
            if (next === state) return;
            const wasProblem = PROBLEMS.includes(state);
            state = next;
            clearTimeout(hideTimer);
            if (next === 'ok') {
                if (wasProblem) { show('restored'); hideTimer = setTimeout(() => { box.hidden = true; }, 3500); }
                else box.hidden = true;
            } else if (next === 'offline' && offlineHidden) {
                box.hidden = true;
            } else {
                show(next);
            }
        }

        async function check() {
            clearTimeout(timer);
            if (!navigator.onLine) { setState('device'); return schedule(); }
            const abort = new AbortController(), giveUp = setTimeout(() => abort.abort(), 6000);
            try {
                const res = await fetch(STATUS_URL, { cache: 'no-store', signal: abort.signal, headers: { Accept: 'application/json' } });
                if (res.status === 503) setState('updating');
                else if (!res.ok) throw new Error('HTTP ' + res.status);
                else { const s = await res.json(); setState(!s.database ? 'database' : (s.internet ? 'ok' : 'offline')); }
            } catch (e) {
                setState(navigator.onLine ? 'server' : 'device');
            } finally {
                clearTimeout(giveUp);
            }
            schedule();
        }

        function schedule() {
            clearTimeout(timer);
            if (document.visibilityState === 'visible') timer = setTimeout(check, PROBLEMS.includes(state) ? 5000 : 20000);
        }

        box.addEventListener('click', e => {
            const action = e.target.closest('[data-conn]')?.dataset.conn;
            if (action === 'retry') check();
            if (action === 'close') {
                offlineHidden = true; box.hidden = true;
                try { sessionStorage.setItem('conn-offline-hidden', '1'); } catch (err) {}
            }
        });
        window.addEventListener('online', check);
        window.addEventListener('offline', () => setState('device'));
        document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') check(); else clearTimeout(timer); });

        // While there is a problem, check again before a form is sent; if still down,
        // keep the page (and the typed entries) and nudge the message instead.
        document.addEventListener('submit', async e => {
            const form = e.target;
            if (!PROBLEMS.includes(state) || form.dataset.connChecked) return;
            e.preventDefault();
            e.stopPropagation();
            const submitter = e.submitter;
            await check();
            if (PROBLEMS.includes(state)) {
                box.classList.remove('conn-nudge'); void box.offsetWidth; box.classList.add('conn-nudge');
                return;
            }
            form.dataset.connChecked = '1';
            form.requestSubmit(submitter && submitter.form === form ? submitter : undefined);
            delete form.dataset.connChecked;
        }, true);

        setTimeout(check, 1000);
    })();
</script>
