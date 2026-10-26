@extends('layouts.app')
@section('title', 'New Request')

@section('content')
@php($sel = fn ($key) => old($key, $selected[$key] ?? null))
<div class="row g-4 align-items-start">
    <div class="col-xl-8">
        <form method="POST" action="{{ route('admin.requests.store') }}" class="card">
            @csrf
            <div class="card-header"><strong><i class="bi bi-pencil-square me-1" aria-hidden="true"></i>Request details</strong></div>
            <div class="card-body row g-3">
                <div class="col-12">
                    <label class="form-label" for="employee_id">Employee *</label>
                    <select name="employee_id" id="employee_id" data-combo required data-placeholder="Search employee by name or ID...">
                        <option value=""></option>
                        @foreach($employees as $e)
                            <option value="{{ $e->id }}" @selected($sel('employee_id') == $e->id)>{{ $e->employee_no }} - {{ $e->full_name }} ({{ $e->department }}, {{ $e->company?->code }})</option>
                        @endforeach
                    </select>
                </div>

                <fieldset class="col-12">
                    <legend class="form-label fs-6">Request type *</legend>
                    <div class="row g-2">
                        @foreach(['new_device' => ['bi-laptop', 'New device'], 'repair' => ['bi-tools', 'Repair'], 'damaged' => ['bi-exclamation-triangle', 'Damage report'], 'lost' => ['bi-question-octagon', 'Lost report'], 'return' => ['bi-arrow-return-left', 'Return device']] as $k => [$icon, $label])
                            <div class="col-6 col-md">
                                <input type="radio" class="btn-check" name="request_type" id="t_{{ $k }}" value="{{ $k }}" @checked($sel('request_type') === $k)>
                                <label class="btn btn-outline-primary w-100 h-100 py-3" for="t_{{ $k }}"><i class="bi {{ $icon }} d-block fs-4 mb-1" aria-hidden="true"></i>{{ $label }}</label>
                            </div>
                        @endforeach
                    </div>
                </fieldset>

                <div class="col-md-6" data-for="new_device">
                    <label class="form-label" for="device_type">Device type needed *</label>
                    <select name="device_type" id="device_type" data-combo data-placeholder="Select device type...">
                        <option value=""></option>
                        @foreach($types as $t)<option value="{{ $t->code }}" @selected(old('device_type') === $t->code)>{{ $t->name }}</option>@endforeach
                    </select>
                </div>

                {{-- several devices can be picked when the employee holds more than one;
                     each one becomes its own request (see RequestController::store) --}}
                <div class="col-md-6" data-for="repair damaged lost return">
                    <div class="d-flex justify-content-between align-items-baseline">
                        <label class="form-label" for="device_id">Device *</label>
                        <button type="button" class="btn btn-link btn-sm p-0 small" id="all-devices" hidden>Select all</button>
                    </div>
                    <select name="device_ids[]" id="device_id" multiple data-combo data-placeholder="Select the employee's device...">
                    </select>
                    <div class="form-text" id="no-devices" hidden>This employee has no assigned devices.</div>
                    <div class="form-text" id="multi-hint" hidden>You can pick more than one. Each device is filed as its own request.</div>
                </div>

                <div class="col-md-3">
                    <label class="form-label" for="priority">Priority</label>
                    <select name="priority" id="priority" class="form-select">
                        @foreach(\App\Models\DeviceRequest::PRIORITIES as $k => $v)<option value="{{ $k }}" @selected(old('priority', 'normal') === $k)>{{ $v }}</option>@endforeach
                    </select>
                </div>

                <div class="col-md-3" data-for="damaged lost">
                    <label class="form-label" for="incident_date">When did it happen?</label>
                    <input type="datetime-local" name="incident_date" id="incident_date" value="{{ old('incident_date') }}" max="{{ now()->format('Y-m-d\TH:i') }}" class="form-control">
                </div>

                <div class="col-12">
                    <label class="form-label" for="reason">Reason / details *</label>
                    {{-- one-click common reasons for the chosen type (DeviceRequest::REASON_SUGGESTIONS);
                         they only fill in the text box, which can still be typed in freely --}}
                    <div class="reason-chips mb-2" id="reason-chips" role="group" aria-label="Common reasons"></div>
                    <textarea name="reason" id="reason" rows="5" class="form-control" required>{{ old('reason') }}</textarea>
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <button class="btn btn-primary"><i class="bi bi-send me-1" aria-hidden="true"></i>File Request</button>
                <a href="{{ route('admin.requests.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>

    <div class="col-xl-4">
        <div class="card mb-4 snapshot">
            <div class="card-header"><strong><i class="bi bi-person-vcard me-1" aria-hidden="true"></i>Employee snapshot</strong></div>
            <div class="card-body" id="snapshot" aria-live="polite">
                <div class="empty-state py-3"><i class="bi bi-person-bounding-box" aria-hidden="true"></i>Select an employee to see their details and the devices they hold.</div>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><strong><i class="bi bi-signpost-split me-1" aria-hidden="true"></i>What happens next</strong></div>
            <div class="card-body small">
                <ol class="help-list">
                    <li>The request is logged as <b>Pending</b> and appears in the Requests queue.</li>
                    <li>Another admin (or the Super Admin) approves or rejects it. Repair, damage and lost reports update the device status on approval.</li>
                    <li>Completing it applies the outcome: assign a device, return it to stock, retire it or issue a replacement.</li>
                </ol>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const DEVICES = @json($devicesByEmployee);
    const EMPLOYEES = @json($employeeInfo);
    // devices to pre-select: from ?device= on a link, or the previous submit after a validation error
    const SELECTED_DEVICES = @json(collect(old('device_ids', array_filter([$selected['device_id'] ?? null])))->map(fn ($id) => (string) $id)->values());
    const employeeTs = document.getElementById('employee_id').tomselect;
    const deviceTs = document.getElementById('device_id').tomselect;
    const hints = {
        new_device: 'Why is it needed? (e.g. new hire, current unit too slow)',
        repair: 'Describe the problem (e.g. battery drains fast, keyboard keys not working)',
        damaged: 'How was it damaged? Describe the damage.',
        lost: 'Where and how was it lost? Was it reported to security / police?',
        return: 'Why is it being returned? (e.g. resignation, upgrade)',
    };
    const REASONS = @json(\App\Models\DeviceRequest::REASON_SUGGESTIONS);
    const reasonBox = document.getElementById('reason');
    const SEP = ' - ';
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

    function renderSnapshot() {
        const id = employeeTs.getValue(), e = EMPLOYEES[id], box = document.getElementById('snapshot');
        if (!e) {
            box.innerHTML = '<div class="empty-state py-3"><i class="bi bi-person-bounding-box" aria-hidden="true"></i>Select an employee to see their details and the devices they hold.</div>';
            return;
        }
        const devices = DEVICES[id] || [];
        box.innerHTML = `
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="avatar" aria-hidden="true">${esc(e.name.split(' ').map(w => w[0]).slice(0, 2).join(''))}</span>
                <div><div class="fw-bold">${esc(e.name)}</div><div class="small text-muted">${esc(e.position || 'Employee')} · <span class="mono">${esc(e.no)}</span></div></div>
            </div>
            <dl class="row mb-1">
                <dt class="col-5">Department</dt><dd class="col-7">${esc(e.department)}</dd>
                <dt class="col-5">Company</dt><dd class="col-7">${esc(e.company || '-')}</dd>
                <dt class="col-5">Email</dt><dd class="col-7 text-break">${esc(e.email)}</dd>
                <dt class="col-5">Contact</dt><dd class="col-7">${esc(e.contact || '-')}</dd>
            </dl>
            <div class="form-label mb-2">Devices held (${devices.length})</div>
            ${devices.length ? devices.map(d => `<div class="device-item"><span class="small fw-semibold">${esc(d.label)}</span><span class="badge status-pill text-bg-secondary">${esc(d.status)}</span></div>`).join('')
                : '<div class="small text-muted">No devices assigned.</div>'}`;
    }

    // Fills the device box with the employee's devices. With one device it is
    // simply selected; with more, several can be picked (and "Select all" shows).
    function loadDevices(keep) {
        const list = DEVICES[employeeTs.getValue()] || [];
        deviceTs.clear(); deviceTs.clearOptions();
        list.forEach(d => deviceTs.addOption({ value: String(d.id), text: d.label }));
        deviceTs.settings.maxItems = list.length > 1 ? null : 1;
        if (keep && SELECTED_DEVICES.length) deviceTs.setValue(SELECTED_DEVICES, true);
        else if (list.length === 1) deviceTs.setValue(String(list[0].id), true);
        document.getElementById('no-devices').hidden = !employeeTs.getValue() || list.length > 0;
        document.getElementById('multi-hint').hidden = list.length < 2;
        document.getElementById('all-devices').hidden = list.length < 2;
        renderSnapshot();
    }

    document.getElementById('all-devices').addEventListener('click', () => {
        deviceTs.setValue((DEVICES[employeeTs.getValue()] || []).map(d => String(d.id)));
        deviceTs.close();
    });

    /*
     * Common-reason chips. Clicking one puts it at the start of the reason
     * ("Resigned - ") ready for details; clicking another swaps it, clicking
     * the same one again takes it out. Typed text is never removed.
     */
    const currentType = () => document.querySelector('[name=request_type]:checked')?.value;
    // longest first, and across all types, so a chip picked before switching type is swapped too
    const ALL_REASONS = [...new Set(Object.values(REASONS).flat())].sort((a, b) => b.length - a.length);
    function reasonPrefix() {
        const text = reasonBox.value;
        return ALL_REASONS.find(r => text === r || text.startsWith(r + SEP)) || null;
    }
    function markChips() {
        const prefix = reasonPrefix();
        document.querySelectorAll('.reason-chip').forEach(c => {
            c.classList.toggle('active', c.dataset.reason === prefix);
            c.setAttribute('aria-pressed', c.dataset.reason === prefix);
        });
    }
    function renderReasonChips() {
        document.getElementById('reason-chips').innerHTML = (REASONS[currentType()] || [])
            .map(r => `<button type="button" class="reason-chip" data-reason="${esc(r)}" aria-pressed="false">${esc(r)}</button>`).join('');
        markChips();
    }
    document.getElementById('reason-chips').addEventListener('click', e => {
        const chip = e.target.closest('.reason-chip');
        if (!chip) return;
        const pick = chip.dataset.reason, prefix = reasonPrefix();
        const rest = (prefix ? reasonBox.value.slice(prefix.length) : reasonBox.value).replace(/^\s*-\s*/, '').trim();
        reasonBox.value = prefix === pick ? rest : `${pick}${SEP}${rest}`;
        reasonBox.focus();
        reasonBox.setSelectionRange(reasonBox.value.length, reasonBox.value.length);
        markChips();
    });
    reasonBox.addEventListener('input', markChips);
    // a chip with nothing typed after it is saved as just "Resigned", not "Resigned -"
    reasonBox.form.addEventListener('submit', () => { reasonBox.value = reasonBox.value.replace(/\s+-\s*$/, ''); });

    function sync() {
        const type = currentType();
        document.querySelectorAll('[data-for]').forEach(el => {
            const show = el.dataset.for.split(' ').includes(type);
            el.classList.toggle('d-none', !show);
            el.querySelectorAll('input').forEach(i => i.disabled = !show);
            el.querySelectorAll('select').forEach(s => s.tomselect ? (show ? s.tomselect.enable() : s.tomselect.disable()) : s.disabled = !show);
        });
        document.querySelector('[name=reason]').placeholder = hints[type] || '';
        renderReasonChips();
    }

    employeeTs.on('change', () => loadDevices(false));
    document.querySelectorAll('[name=request_type]').forEach(r => r.addEventListener('change', sync));
    loadDevices(true);
    sync();
</script>
@endpush
