@extends('layouts.app')
@section('title', $device->exists ? "Edit {$device->asset_tag}" : 'Log New Device')

@section('content')
@php
    $specs = old('spec_keys')
        ? collect(old('spec_keys'))->combine(old('spec_values', []))->all()
        : ($device->specs ?? []);
    $typeMeta = $types->mapWithKeys(fn ($t) => [$t->code => [
        'prefix' => $t->prefix,
        'next' => sprintf('%s-%04d', $t->prefix, $t->last_number + 1),
        'computer' => $t->has_computer_specs,
        'imei' => $t->has_imei,
    ]]);
@endphp
<form method="POST" action="{{ $device->exists ? route('admin.devices.update', $device) : route('admin.devices.store') }}" enctype="multipart/form-data">
    @csrf
    @if($device->exists) @method('PUT') @endif

    @unless(auth()->user()->isSuperAdmin())
        <div class="alert alert-info py-2 small"><i class="bi bi-info-circle me-1"></i>Pick values from the dropdowns. If an option is missing, ask the Super Admin to add it to the master data.</div>
    @endunless

    <div class="card mb-3">
        <div class="card-header bg-white"><strong>Identification</strong></div>
        <div class="card-body row g-3">
            <div class="col-md-6">
                <label class="form-label">Company (owner) *</label>
                <select name="company_id" data-combo required data-placeholder="Which company owns this device?">
                    <option value=""></option>
                    @foreach($companies as $c)<option value="{{ $c->id }}" @selected(old('company_id', $device->company_id) == $c->id)>{{ $c->code }} - {{ $c->name }}{{ $c->country ? " ({$c->country})" : '' }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-6"></div>
            <div class="col-md-3">
                <label class="form-label">Asset Tag</label>
                <input id="asset_tag" class="form-control bg-light fw-semibold" readonly value="{{ $device->asset_tag }}">
                <div class="form-text" id="asset_tag_help">{{ $device->exists ? 'Re-generated if the type changes.' : 'Generated automatically on save.' }}</div>
            </div>
            <div class="col-md-3">
                <label class="form-label">Device Type *</label>
                <select name="type" id="type" data-combo required data-placeholder="Select type...">
                    <option value=""></option>
                    @foreach($types as $t)<option value="{{ $t->code }}" @selected(old('type', $device->type) === $t->code)>{{ $t->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Brand *</label>
                <x-combo name="brand" :options="$options['brand']" :value="$device->brand" required placeholder="Search brand..." />
            </div>
            <div class="col-md-3">
                <label class="form-label">Model *</label>
                <x-combo name="model" :options="[]" :value="$device->model" required placeholder="Select a brand first" />
            </div>
            <div class="col-md-4">
                <label class="form-label">Serial Number</label>
                <input name="serial_number" value="{{ old('serial_number', $device->serial_number) }}" class="form-control">
            </div>
            <div class="col-md-4" data-show="imei">
                <label class="form-label">IMEI</label>
                <input name="imei" value="{{ old('imei', $device->imei) }}" class="form-control">
            </div>
            <div class="col-md-4">
                <label class="form-label">Color</label>
                <x-combo name="color" :options="$options['color']" :value="$device->color" />
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header bg-white"><strong>Specifications</strong></div>
        <div class="card-body row g-3">
            <div class="col-md-3" data-show="computer">
                <label class="form-label">Processor</label>
                <x-combo name="processor" :options="$options['processor']" :value="$device->processor" />
            </div>
            <div class="col-md-3" data-show="computer">
                <label class="form-label">Memory (RAM)</label>
                <x-combo name="memory" :options="$options['memory']" :value="$device->memory" />
            </div>
            <div class="col-md-3" data-show="computer">
                <label class="form-label">Storage</label>
                <x-combo name="storage" :options="$options['storage']" :value="$device->storage" />
            </div>
            <div class="col-md-3" data-show="computer">
                <label class="form-label">Operating System</label>
                <x-combo name="operating_system" :options="$options['operating_system']" :value="$device->operating_system" />
            </div>

            <div class="col-12">
                <label class="form-label">Other Specs <span class="text-muted small">(screen size, GPU, battery, ports, etc.)</span></label>
                <div id="spec-rows">
                    @foreach($specs as $key => $value)
                        @include('admin.devices.spec-row', ['key' => $key, 'value' => $value])
                    @endforeach
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="add-spec"><i class="bi bi-plus"></i> Add spec</button>
                <template id="spec-template">@include('admin.devices.spec-row', ['key' => null, 'value' => null])</template>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header bg-white"><strong>Purchase &amp; Condition</strong></div>
        <div class="card-body row g-3">
            <div class="col-md-3">
                <label class="form-label">Purchase Date</label>
                <input type="date" name="purchase_date" value="{{ old('purchase_date', $device->purchase_date?->format('Y-m-d')) }}" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">Purchase Cost</label>
                <input type="number" step="0.01" min="0" name="purchase_cost" value="{{ old('purchase_cost', $device->purchase_cost) }}" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">Warranty Until</label>
                <input type="date" name="warranty_until" value="{{ old('warranty_until', $device->warranty_until?->format('Y-m-d')) }}" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">Supplier</label>
                <x-combo name="supplier" :options="$options['supplier']" :value="$device->supplier" />
            </div>
            <div class="col-md-6">
                <label class="form-label">Purchase Order (PO) file</label>
                <input type="file" name="po_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                <div class="form-text">PDF, image, Word or Excel, up to 10 MB.{{ $device->po_file_path ? ' Uploading a new file replaces the current one.' : '' }}</div>
                @if($device->po_file_path)
                    <div class="d-flex align-items-center gap-3 mt-1 small">
                        <a href="{{ route('admin.devices.po-file', $device) }}" target="_blank"><i class="bi bi-file-earmark-text me-1"></i>{{ $device->po_file_name }}</a>
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" name="remove_po_file" value="1" id="remove_po_file">
                            <label class="form-check-label text-danger" for="remove_po_file">Remove</label>
                        </div>
                    </div>
                @endif
            </div>
            <div class="col-md-3">
                <label class="form-label">Condition *</label>
                <select name="condition" class="form-select">
                    @foreach(\App\Models\Device::CONDITIONS as $k => $v)<option value="{{ $k }}" @selected(old('condition', $device->condition) === $k)>{{ $v }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-3"></div>
            <div class="col-12">
                <label class="form-label">Notes</label>
                <textarea name="notes" rows="2" class="form-control">{{ old('notes', $device->notes) }}</textarea>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Device</button>
        <a href="{{ $device->exists ? route('admin.devices.show', $device) : route('admin.devices.index') }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
@endsection

@push('scripts')
<script>
    const TYPES = @json($typeMeta);
    const MODELS = @json($modelsByBrand);
    const ORIGINAL = { type: @json($device->type), tag: @json($device->asset_tag) };
    const typeTs = document.getElementById('type').tomselect;
    const brandTs = document.querySelector('[name=brand]').tomselect;
    const modelTs = document.querySelector('[name=model]').tomselect;

    // spec fields and asset tag preview follow the device type
    function onTypeChange() {
        const meta = TYPES[typeTs.getValue()];
        document.querySelectorAll('[data-show]').forEach(el => {
            el.classList.toggle('d-none', !meta || !meta[el.dataset.show]);
        });
        const tag = document.getElementById('asset_tag');
        if (ORIGINAL.tag && typeTs.getValue() === ORIGINAL.type) tag.value = ORIGINAL.tag;
        else tag.value = meta ? meta.next : '';
    }

    // the model list follows the brand
    function loadModels(keepValue) {
        if (!keepValue) modelTs.clear();
        modelTs.clearOptions(); // keeps the selected model
        (MODELS[brandTs.getValue()] || []).forEach(m => modelTs.addOption({ value: m, text: m }));
        modelTs.control_input.placeholder = brandTs.getValue() ? 'Search model...' : 'Select a brand first';
    }

    typeTs.on('change', onTypeChange);
    brandTs.on('change', () => loadModels(false));
    onTypeChange();
    loadModels(true);

    document.getElementById('add-spec').addEventListener('click', () => {
        const row = document.getElementById('spec-template').content.firstElementChild.cloneNode(true);
        document.getElementById('spec-rows').appendChild(row);
        initCombo(row.querySelector('select'));
    });
    document.getElementById('spec-rows').addEventListener('click', e => {
        if (e.target.closest('.remove-spec')) e.target.closest('.spec-row').remove();
    });
</script>
@endpush
