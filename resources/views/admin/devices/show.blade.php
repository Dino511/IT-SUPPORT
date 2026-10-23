@extends('layouts.app')
@section('title', "{$device->asset_tag} · {$device->name}")
@section('actions')
    @if($device->status === 'available')
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#assignModal"><i class="bi bi-person-plus me-1"></i>Assign</button>
    @endif
    @if($device->current_employee_id)
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#returnModal"><i class="bi bi-arrow-return-left me-1"></i>Return</button>
        <a href="{{ route('admin.requests.create', ['employee' => $device->current_employee_id, 'device' => $device->id, 'type' => 'repair']) }}" class="btn btn-outline-warning"><i class="bi bi-tools me-1"></i>File Request</a>
    @endif
    <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#statusModal"><i class="bi bi-arrow-repeat me-1"></i>Change Status</button>
    <a href="{{ route('admin.devices.edit', $device) }}" class="btn btn-outline-primary"><i class="bi bi-pencil me-1"></i>Edit</a>
@endsection

@section('content')
<div class="row g-3">
    <div class="col-xl-4">
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="text-muted small">{{ $device->type_label }}</div>
                        <div class="h5 mb-0">{{ $device->name }}</div>
                    </div>
                    <x-status :value="$device->status" class="fs-6" />
                </div>
                <dl class="row specs small mb-0">
                    <dt class="col-5">Company</dt><dd class="col-7">{!! $device->company ? e($device->company->name).' <span class="badge text-bg-light border">'.e($device->company->code).'</span>' : '<span class="text-danger">Not set - edit to choose</span>' !!}</dd>
                    <dt class="col-5">Asset Tag</dt><dd class="col-7">{{ $device->asset_tag }}</dd>
                    <dt class="col-5">Serial No.</dt><dd class="col-7">{{ $device->serial_number ?? '-' }}</dd>
                    @if($device->imei)<dt class="col-5">IMEI</dt><dd class="col-7">{{ $device->imei }}</dd>@endif
                    <dt class="col-5">Processor</dt><dd class="col-7">{{ $device->processor ?? '-' }}</dd>
                    <dt class="col-5">Memory</dt><dd class="col-7">{{ $device->memory ?? '-' }}</dd>
                    <dt class="col-5">Storage</dt><dd class="col-7">{{ $device->storage ?? '-' }}</dd>
                    <dt class="col-5">OS</dt><dd class="col-7">{{ $device->operating_system ?? '-' }}</dd>
                    @if($device->color)<dt class="col-5">Color</dt><dd class="col-7">{{ $device->color }}</dd>@endif
                    @foreach($device->specs ?? [] as $k => $v)
                        <dt class="col-5">{{ $k }}</dt><dd class="col-7">{{ $v }}</dd>
                    @endforeach
                    <dt class="col-5">Condition</dt><dd class="col-7">{{ \App\Models\Device::CONDITIONS[$device->condition] ?? $device->condition }}</dd>
                </dl>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header bg-white"><strong>Current Holder</strong></div>
            <div class="card-body">
                @if($device->currentEmployee)
                    <a href="{{ route('admin.employees.show', $device->currentEmployee) }}" class="fw-semibold">{{ $device->currentEmployee->full_name }}</a>
                    <div class="small text-muted">{{ $device->currentEmployee->employee_no }} · {{ $device->currentEmployee->department }} · {{ $device->currentEmployee->company?->name }}</div>
                    @if($device->company_id && $device->currentEmployee->company_id !== $device->company_id)
                        <div class="small text-warning-emphasis mt-1"><i class="bi bi-arrow-left-right me-1"></i>On loan from {{ $device->company?->code }} to {{ $device->currentEmployee->company?->code }}</div>
                    @endif
                    @if($device->assignments->firstWhere('returned_at', null))
                        <div class="small mt-1">Since {{ $device->assignments->firstWhere('returned_at', null)->assigned_at->format('M d, Y') }}</div>
                    @endif
                @else
                    <span class="text-muted">Not assigned</span>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white"><strong>Purchase</strong></div>
            <div class="card-body">
                <dl class="row specs small mb-0">
                    <dt class="col-5">Purchased</dt><dd class="col-7">{{ $device->purchase_date?->format('M d, Y') ?? '-' }}</dd>
                    <dt class="col-5">Cost</dt><dd class="col-7">{{ $device->purchase_cost ? number_format($device->purchase_cost, 2) : '-' }}</dd>
                    <dt class="col-5">Warranty</dt><dd class="col-7 {{ $device->warranty_until?->isPast() ? 'text-danger' : '' }}">{{ $device->warranty_until?->format('M d, Y') ?? '-' }}</dd>
                    <dt class="col-5">Supplier</dt><dd class="col-7">{{ $device->supplier ?? '-' }}</dd>
                    <dt class="col-5">PO File</dt>
                    <dd class="col-7">@if($device->po_file_path)<a href="{{ route('admin.devices.po-file', $device) }}" target="_blank"><i class="bi bi-file-earmark-text me-1"></i>{{ $device->po_file_name }}</a>@else - @endif</dd>
                    <dt class="col-5">Logged by</dt><dd class="col-7">{{ $device->creator?->name ?? '-' }} on {{ $device->created_at->format('M d, Y') }}</dd>
                </dl>
                @if($device->notes)<hr><div class="small">{!! nl2br(e($device->notes)) !!}</div>@endif
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="card mb-3">
            <div class="card-header bg-white"><strong>Assignment History</strong> <span class="text-muted small">(who had it and when)</span></div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Employee</th><th>Assigned</th><th>By</th><th>Returned</th><th>Condition (out → in)</th><th>Remarks</th></tr></thead>
                    <tbody>
                    @forelse($device->assignments as $a)
                        <tr>
                            <td><a href="{{ route('admin.employees.show', $a->employee) }}">{{ $a->employee->full_name }}</a><div class="small text-muted">{{ $a->employee->department }}</div></td>
                            <td class="small">{{ $a->assigned_at->format('M d, Y h:i A') }}</td>
                            <td class="small">{{ $a->assignedBy?->name }}</td>
                            <td class="small">{!! $a->returned_at ? $a->returned_at->format('M d, Y h:i A').'<div class="text-muted">to '.e($a->returnedTo?->name).'</div>' : '<span class="badge text-bg-primary">Current</span>' !!}</td>
                            <td class="small">{{ $a->condition_on_assign ?? '-' }} → {{ $a->condition_on_return ?? '-' }}</td>
                            <td class="small">{!! nl2br(e($a->remarks)) !!}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-3">Never assigned.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header bg-white"><strong>Requests (repair / lost / damage / return)</strong></div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light"><tr><th>Ref #</th><th>Type</th><th>Employee</th><th>Reason</th><th>Status</th><th>Filed</th></tr></thead>
                    <tbody>
                    @forelse($device->requests as $r)
                        <tr onclick="location='{{ route('admin.requests.show', $r) }}'" style="cursor:pointer">
                            <td>{{ $r->reference_no }}</td><td>{{ $r->type_label }}</td><td>{{ $r->employee->full_name }}</td>
                            <td class="small">{{ \Illuminate\Support\Str::limit($r->reason, 60) }}</td>
                            <td><x-status :value="$r->status" /></td><td class="small">{{ $r->created_at->format('M d, Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-3">No requests for this device.</td></tr>
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

{{-- Assign --}}
<div class="modal fade" id="assignModal" tabindex="-1"><div class="modal-dialog"><form method="POST" action="{{ route('admin.devices.assign', $device) }}" class="modal-content">@csrf
    <div class="modal-header"><h5 class="modal-title">Assign {{ $device->asset_tag }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3"><label class="form-label">Employee *</label>
            <select name="employee_id" class="form-select" required><option value="">Select employee...</option>
                @foreach($employeesByCompany as $companyName => $group)
                    <optgroup label="{{ $companyName }}">
                        @foreach($group as $e)<option value="{{ $e->id }}">{{ $e->last_name }}, {{ $e->first_name }} ({{ $e->employee_no }}) - {{ $e->department }}</option>@endforeach
                    </optgroup>
                @endforeach
            </select>
            @if($device->company)<div class="form-text">Employees of {{ $device->company->name }} are listed first. Assigning to another company is recorded as a loan.</div>@endif
        </div>
        <div class="row g-2 mb-3">
            <div class="col"><label class="form-label">Date</label><input type="datetime-local" name="assigned_at" value="{{ now()->format('Y-m-d\TH:i') }}" class="form-control"></div>
            <div class="col"><label class="form-label">Condition</label>
                <select name="condition" class="form-select">@foreach(\App\Models\Device::CONDITIONS as $k => $v)<option value="{{ $k }}" @selected($device->condition === $k)>{{ $v }}</option>@endforeach</select></div>
        </div>
        <label class="form-label">Remarks</label><textarea name="remarks" class="form-control" rows="2" placeholder="Accessories included, etc."></textarea>
    </div>
    <div class="modal-footer"><button class="btn btn-primary">Assign Device</button></div>
</form></div></div>

{{-- Return --}}
<div class="modal fade" id="returnModal" tabindex="-1"><div class="modal-dialog"><form method="POST" action="{{ route('admin.devices.return', $device) }}" class="modal-content">@csrf
    <div class="modal-header"><h5 class="modal-title">Return {{ $device->asset_tag }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <p class="small text-muted">Returning from {{ $device->currentEmployee?->full_name }}.</p>
        <div class="row g-2 mb-3">
            <div class="col"><label class="form-label">Device status after return</label>
                <select name="status" class="form-select">
                    @foreach(['available', 'in_repair', 'damaged', 'lost', 'retired'] as $s)<option value="{{ $s }}">{{ \App\Models\Device::STATUSES[$s] }}</option>@endforeach
                </select></div>
            <div class="col"><label class="form-label">Condition</label>
                <select name="condition" class="form-select">@foreach(\App\Models\Device::CONDITIONS as $k => $v)<option value="{{ $k }}" @selected($device->condition === $k)>{{ $v }}</option>@endforeach</select></div>
        </div>
        <label class="form-label">Remarks</label><textarea name="remarks" class="form-control" rows="2"></textarea>
    </div>
    <div class="modal-footer"><button class="btn btn-success">Confirm Return</button></div>
</form></div></div>

{{-- Status --}}
<div class="modal fade" id="statusModal" tabindex="-1"><div class="modal-dialog"><form method="POST" action="{{ route('admin.devices.status', $device) }}" class="modal-content">@csrf
    <div class="modal-header"><h5 class="modal-title">Change Status</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <label class="form-label">New status</label>
        <select name="status" class="form-select mb-3">
            @foreach(\App\Models\Device::STATUSES as $k => $v)<option value="{{ $k }}" @selected($device->status === $k)>{{ $v }}</option>@endforeach
        </select>
        <label class="form-label">Remarks</label><textarea name="remarks" class="form-control" rows="2"></textarea>
        <p class="small text-muted mt-2 mb-0">To give the device to someone use <b>Assign</b>; to take it back use <b>Return</b>.</p>
    </div>
    <div class="modal-footer"><button class="btn btn-primary">Update Status</button></div>
</form></div></div>
@endsection
