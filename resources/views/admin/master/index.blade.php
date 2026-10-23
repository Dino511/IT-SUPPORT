@extends('layouts.app')
@section('title', 'Master Data')

@section('content')
<div class="row g-3">
    <div class="col-lg-3">
        <div class="card">
            <div class="list-group list-group-flush">
                <a href="{{ route('admin.master.index') }}" class="list-group-item list-group-item-action {{ $category === 'companies' ? 'active' : '' }}">
                    Companies</a>
                <a href="{{ route('admin.master.index', ['category' => 'device_types']) }}" class="list-group-item list-group-item-action {{ $category === 'device_types' ? 'active' : '' }}">
                    Device Types &amp; Asset Tags</a>
                @foreach(collect(\App\Models\Lookup::CATEGORIES)->groupBy('group', true) as $group => $cats)
                    <div class="list-group-item small text-uppercase text-muted bg-light fw-semibold">{{ $group }}</div>
                    @foreach($cats as $key => $meta)
                        <a href="{{ route('admin.master.index', ['category' => $key]) }}" class="list-group-item list-group-item-action d-flex justify-content-between {{ $category === $key ? 'active' : '' }}">
                            {{ $meta['label'] }} <span class="badge rounded-pill {{ $category === $key ? 'text-bg-light' : 'text-bg-secondary' }}">{{ $counts[$key] ?? 0 }}</span>
                        </a>
                    @endforeach
                @endforeach
            </div>
        </div>
        <p class="small text-muted mt-3">These lists feed the dropdowns on the device and employee forms. Admins can only pick from them. You can also add a new option by typing it in any form dropdown.</p>
    </div>

    <div class="col-lg-9">
    @if($category === 'companies')
        <div class="card mb-3">
            <div class="card-header bg-white"><strong>Add Company</strong></div>
            <form method="POST" action="{{ route('admin.master.companies.store') }}" class="card-body row g-2 align-items-end">@csrf
                <div class="col-md-2"><label class="form-label small">Code</label><input name="code" value="{{ old('code') }}" class="form-control text-uppercase" required maxlength="20" placeholder="e.g. ASG"></div>
                <div class="col-md-4"><label class="form-label small">Company name</label><input name="name" value="{{ old('name') }}" class="form-control" required maxlength="150" placeholder="e.g. Acme Singapore Pte. Ltd."></div>
                <div class="col-md-2"><label class="form-label small">Country</label><input name="country" value="{{ old('country') }}" class="form-control" maxlength="100"></div>
                <div class="col-md-3"><label class="form-label small">Address</label><input name="address" value="{{ old('address') }}" class="form-control" maxlength="255"></div>
                <div class="col-md-1"><button class="btn btn-primary w-100" title="Add"><i class="bi bi-plus-lg"></i></button></div>
            </form>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead class="table-light"><tr><th style="width:110px">Code</th><th>Name</th><th style="width:140px">Country</th><th>Address</th><th class="text-center">Employees</th><th class="text-center">Devices</th><th>Active</th><th></th></tr></thead>
                    <tbody>
                    @forelse($companies as $c)
                        <tr class="{{ $c->is_active ? '' : 'table-secondary' }}">
                            <td><input name="code" value="{{ $c->code }}" class="form-control form-control-sm text-uppercase" form="co-{{ $c->id }}" required maxlength="20"></td>
                            <td><input name="name" value="{{ $c->name }}" class="form-control form-control-sm" form="co-{{ $c->id }}" required maxlength="150"></td>
                            <td><input name="country" value="{{ $c->country }}" class="form-control form-control-sm" form="co-{{ $c->id }}" maxlength="100"></td>
                            <td><input name="address" value="{{ $c->address }}" class="form-control form-control-sm" form="co-{{ $c->id }}" maxlength="255"></td>
                            <td class="text-center">{{ $c->employees_count }}</td>
                            <td class="text-center">{{ $c->devices_count }}</td>
                            <td><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" value="1" form="co-{{ $c->id }}" @checked($c->is_active)></div></td>
                            <td><button class="btn btn-sm btn-outline-primary" form="co-{{ $c->id }}">Save</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">No companies yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white small text-muted">Every employee and device belongs to one company. Inactive companies keep their records but can't be picked for new ones.</div>
        </div>
        @foreach($companies as $c)
            <form method="POST" action="{{ route('admin.master.companies.update', $c) }}" id="co-{{ $c->id }}">@csrf @method('PUT')</form>
        @endforeach
    @elseif($category === 'device_types')
        <div class="card mb-3">
            <div class="card-header bg-white"><strong>Add Device Type</strong></div>
            <form method="POST" action="{{ route('admin.master.types.store') }}" class="card-body row g-2 align-items-end">@csrf
                <div class="col-md-4"><label class="form-label small">Name</label><input name="name" class="form-control" required placeholder="e.g. Printer"></div>
                <div class="col-md-2"><label class="form-label small">Tag prefix</label><input name="prefix" class="form-control text-uppercase" required maxlength="10" placeholder="e.g. PRT"></div>
                <div class="col-md-4">
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="has_computer_specs" value="1" id="n_cs"><label class="form-check-label small" for="n_cs">Has processor / memory / storage / OS</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="has_imei" value="1" id="n_imei"><label class="form-check-label small" for="n_imei">Has IMEI</label></div>
                </div>
                <div class="col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i> Add</button></div>
            </form>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead class="table-light"><tr><th>Name</th><th style="width:110px">Prefix</th><th>Spec fields</th><th class="text-center">Devices</th><th>Next tag</th><th>Active</th><th></th></tr></thead>
                    <tbody>
                    @foreach($deviceTypes as $t)
                        <tr>
                            <td><input name="name" value="{{ $t->name }}" class="form-control form-control-sm" form="type-{{ $t->id }}" required></td>
                            <td><input name="prefix" value="{{ $t->prefix }}" class="form-control form-control-sm text-uppercase" form="type-{{ $t->id }}" required maxlength="10"></td>
                            <td class="small">
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="has_computer_specs" value="1" form="type-{{ $t->id }}" id="cs{{ $t->id }}" @checked($t->has_computer_specs)><label class="form-check-label" for="cs{{ $t->id }}">Computer specs</label></div>
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="has_imei" value="1" form="type-{{ $t->id }}" id="im{{ $t->id }}" @checked($t->has_imei)><label class="form-check-label" for="im{{ $t->id }}">IMEI</label></div>
                            </td>
                            <td class="text-center">{{ $t->devices_count }}</td>
                            <td class="small text-muted text-nowrap">{{ sprintf('%s-%04d', $t->prefix, $t->last_number + 1) }}</td>
                            <td><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" value="1" form="type-{{ $t->id }}" @checked($t->is_active)></div></td>
                            <td><button class="btn btn-sm btn-outline-primary" form="type-{{ $t->id }}">Save</button></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white small text-muted">Asset tags are generated as PREFIX-0001, PREFIX-0002... Changing a prefix only affects new devices.</div>
        </div>
        @foreach($deviceTypes as $t)
            <form method="POST" action="{{ route('admin.master.types.update', $t) }}" id="type-{{ $t->id }}">@csrf @method('PUT')</form>
        @endforeach
    @else
        @php($meta = \App\Models\Lookup::CATEGORIES[$category])
        @php($hasParent = isset($meta['parent']))
        <div class="card mb-3">
            <div class="card-header bg-white"><strong>Add to {{ $meta['label'] }}</strong></div>
            <form method="POST" action="{{ route('admin.master.store') }}" class="card-body row g-2 align-items-end">@csrf
                <input type="hidden" name="category" value="{{ $category }}">
                @if($hasParent)
                    <div class="col-md-4"><label class="form-label small">Brand</label>
                        <select name="parent_id" class="form-select" data-combo required><option value="">Select brand...</option>
                            @foreach($brands as $b)<option value="{{ $b->id }}" @selected(old('parent_id') == $b->id)>{{ $b->value }}</option>@endforeach
                        </select></div>
                @endif
                <div class="col"><label class="form-label small">Value</label><input name="value" class="form-control" required maxlength="150"></div>
                <div class="col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i> Add</button></div>
            </form>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr>@if($hasParent)<th style="width:28%">Brand</th>@endif<th>Value</th><th class="text-center">In use</th><th>Active</th><th style="width:130px"></th></tr></thead>
                    <tbody>
                    @forelse($items as $item)
                        <tr class="{{ $item->is_active ? '' : 'table-secondary' }}">
                            @if($hasParent)
                                <td><select name="parent_id" class="form-select form-select-sm" form="lk-{{ $item->id }}">
                                    @foreach($brands as $b)<option value="{{ $b->id }}" @selected($item->parent_id === $b->id)>{{ $b->value }}</option>@endforeach
                                </select></td>
                            @endif
                            <td><input name="value" value="{{ $item->value }}" class="form-control form-control-sm" form="lk-{{ $item->id }}" required maxlength="150"></td>
                            <td class="text-center small">{{ $meta['table'] ? $item->usage : '-' }}</td>
                            <td><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" value="1" form="lk-{{ $item->id }}" @checked($item->is_active)></div></td>
                            <td class="text-nowrap">
                                <button class="btn btn-sm btn-outline-primary" form="lk-{{ $item->id }}">Save</button>
                                <button class="btn btn-sm btn-outline-danger" form="del-{{ $item->id }}" @disabled($item->usage) title="{{ $item->usage ? 'In use: deactivate instead' : 'Delete' }}"><i class="bi bi-trash"></i></button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">No options yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white small text-muted">Renaming an option also renames it on every {{ $meta['group'] === 'Devices' ? 'device' : 'employee' }} that uses it. Inactive options stay on existing records but disappear from dropdowns.</div>
        </div>
        @foreach($items as $item)
            <form method="POST" action="{{ route('admin.master.update', $item) }}" id="lk-{{ $item->id }}">@csrf @method('PUT')</form>
            <form method="POST" action="{{ route('admin.master.destroy', $item) }}" id="del-{{ $item->id }}" onsubmit="return confirm('Delete {{ addslashes($item->value) }}?')">@csrf @method('DELETE')</form>
        @endforeach
    @endif
    </div>
</div>
@endsection
