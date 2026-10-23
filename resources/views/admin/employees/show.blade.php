@extends('layouts.app')
@section('title', $employee->full_name)
@section('actions')
    <a href="{{ route('admin.requests.create', ['employee' => $employee->id]) }}" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>New Request</a>
    <a href="{{ route('admin.employees.edit', $employee) }}" class="btn btn-outline-primary"><i class="bi bi-pencil me-1"></i>Edit</a>
@endsection

@section('content')
<div class="row g-3">
    <div class="col-xl-4">
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between mb-3">
                    <div><div class="h5 mb-0">{{ $employee->full_name }}</div><div class="text-muted small">{{ $employee->position }}</div></div>
                    {!! $employee->is_active ? '<span class="badge text-bg-success align-self-start">Active</span>' : '<span class="badge text-bg-secondary align-self-start">Inactive</span>' !!}
                </div>
                <dl class="row specs small mb-0">
                    <dt class="col-5">Employee ID</dt><dd class="col-7">{{ $employee->employee_no }}</dd>
                    <dt class="col-5">Department</dt><dd class="col-7">{{ $employee->department }}</dd>
                    <dt class="col-5">Company</dt><dd class="col-7">{{ $employee->company?->name }} <span class="badge text-bg-light border">{{ $employee->company?->code }}</span>@if($employee->company?->country)<div class="text-muted">{{ $employee->company->country }}</div>@endif</dd>
                    <dt class="col-5">Contact</dt><dd class="col-7">{{ $employee->contact_no ?? '-' }}</dd>
                    <dt class="col-5">Email</dt><dd class="col-7">{{ $employee->email }}</dd>
                </dl>
            </div>
        </div>

        @if($employee->user)
            <div class="card mb-3">
                <div class="card-body small d-flex justify-content-between align-items-center">
                    <div><i class="bi bi-person-lock me-1"></i>System user: <b>{{ $employee->user->role_label }}</b>
                        {!! $employee->user->is_active ? '' : '<span class="badge text-bg-secondary ms-1">Disabled</span>' !!}</div>
                    @if(auth()->user()->isSuperAdmin())<a href="{{ route('admin.users.edit', $employee->user) }}">Manage</a>@endif
                </div>
            </div>
        @endif

        <div class="card">
            <div class="card-header bg-white"><strong>Devices Currently Held ({{ $employee->devices->count() }})</strong></div>
            <ul class="list-group list-group-flush">
                @forelse($employee->devices as $d)
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between"><a href="{{ route('admin.devices.show', $d) }}" class="fw-semibold">{{ $d->asset_tag }}</a><x-status :value="$d->status" /></div>
                        <div class="small text-muted mb-1">{{ $d->type_label }} · {{ $d->name }}
                            @if($d->company_id && $d->company_id !== $employee->company_id)<span class="badge text-bg-warning ms-1" title="Owned by {{ $d->company?->name }}">on loan from {{ $d->company?->code }}</span>@endif</div>
                        <div class="d-flex flex-wrap gap-1">
                            @foreach(['repair' => 'Repair', 'damaged' => 'Damaged', 'lost' => 'Lost', 'return' => 'Return'] as $type => $label)
                                <a href="{{ route('admin.requests.create', ['employee' => $employee->id, 'device' => $d->id, 'type' => $type]) }}" class="btn btn-sm btn-outline-secondary py-0">{{ $label }}</a>
                            @endforeach
                        </div>
                    </li>
                @empty
                    <li class="list-group-item text-muted small">No devices assigned.</li>
                @endforelse
            </ul>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="card mb-3">
            <div class="card-header bg-white"><strong>Device History</strong></div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Device</th><th>Assigned</th><th>By</th><th>Returned</th><th>Condition</th></tr></thead>
                    <tbody>
                    @forelse($employee->assignments as $a)
                        <tr>
                            <td><a href="{{ route('admin.devices.show', $a->device) }}">{{ $a->device->asset_tag }}</a><div class="small text-muted">{{ $a->device->name }}</div></td>
                            <td class="small">{{ $a->assigned_at->format('M d, Y') }}</td>
                            <td class="small">{{ $a->assignedBy?->name }}</td>
                            <td class="small">{!! $a->returned_at?->format('M d, Y') ?? '<span class="badge text-bg-primary">Current</span>' !!}</td>
                            <td class="small">{{ $a->condition_on_assign ?? '-' }} → {{ $a->condition_on_return ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">No device history.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header bg-white"><strong>Requests / Repair / Lost / Damage Reports</strong></div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light"><tr><th>Ref #</th><th>Type</th><th>Device</th><th>Reason</th><th>Status</th><th>Filed / Handled By</th><th>Filed</th></tr></thead>
                    <tbody>
                    @forelse($employee->requests as $r)
                        <tr onclick="location='{{ route('admin.requests.show', $r) }}'" style="cursor:pointer">
                            <td>{{ $r->reference_no }}</td><td>{{ $r->type_label }}</td>
                            <td>{{ $r->device?->asset_tag ?? $r->device_type_label ?? '-' }}</td>
                            <td class="small">{{ \Illuminate\Support\Str::limit($r->reason, 50) }}</td>
                            <td><x-status :value="$r->status" /></td>
                            <td class="small">{{ $r->requester?->name ?? '-' }} / {{ $r->handler?->name ?? '-' }}</td>
                            <td class="small">{{ $r->created_at->format('M d, Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-3">No requests.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white"><strong>Audit Trail</strong></div>
            @include('partials.logs', ['logs' => $logs])
        </div>
    </div>
</div>
@endsection
