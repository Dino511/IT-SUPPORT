@extends('layouts.app')
@section('title', 'Devices')
@section('actions')
    <a href="{{ route('admin.devices.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Log Device</a>
@endsection

@section('content')
<div class="card">
    <div class="card-body border-bottom">
        <form class="row g-2">
            <div class="col-md-5"><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Search asset tag, brand, model, serial, IMEI..."></div>
            <div class="col-md-3">
                <select name="type" class="form-select"><option value="">All types</option>
                    @foreach($types as $k => $v)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $v }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select"><option value="">All statuses</option>
                    @foreach(\App\Models\Device::STATUSES as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-secondary flex-fill">Filter</button>
                <a href="{{ route('admin.devices.index') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr><th>Asset Tag</th><th>Company</th><th>Type</th><th>Brand / Model</th><th>Specs</th><th>Serial No.</th><th>Status</th><th>Assigned To</th></tr>
            </thead>
            <tbody>
            @forelse($devices as $d)
                <tr onclick="location='{{ route('admin.devices.show', $d) }}'" style="cursor:pointer">
                    <td class="fw-semibold">{{ $d->asset_tag }}</td>
                    <td>@if($d->company)<span class="badge text-bg-light border" title="{{ $d->company->name }}">{{ $d->company->code }}</span>@else<span class="text-danger small">not set</span>@endif</td>
                    <td>{{ $d->type_label }}</td>
                    <td>{{ $d->name }}</td>
                    <td class="small text-muted">{{ collect([$d->processor, $d->memory, $d->storage])->filter()->implode(' · ') ?: '-' }}</td>
                    <td class="small">{{ $d->serial_number ?? '-' }}</td>
                    <td><x-status :value="$d->status" /></td>
                    <td>{{ $d->currentEmployee?->full_name ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No devices found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($devices->hasPages())<div class="card-footer bg-white">{{ $devices->links('pagination::bootstrap-5') }}</div>@endif
</div>
@endsection
