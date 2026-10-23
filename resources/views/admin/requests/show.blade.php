@extends('layouts.app')
@section('title', "Request {$req->reference_no}")
@section('actions')
    @if($req->status === 'pending')
        <form method="POST" action="{{ route('admin.requests.cancel', $req) }}" onsubmit="return confirm('Cancel this request?')">@csrf
            <button class="btn btn-outline-danger"><i class="bi bi-x-circle me-1"></i>Cancel Request</button></form>
    @endif
@endsection

@section('content')
@php($type = $req->request_type)
<div class="row g-3">
    <div class="col-xl-7">
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between mb-3">
                    <div><div class="text-muted small">{{ $req->type_label }}</div><div class="h5 mb-0">{{ $req->reference_no }}</div></div>
                    <div><x-status :value="$req->priority" :label="ucfirst($req->priority).' priority'" /> <x-status :value="$req->status" class="fs-6" /></div>
                </div>
                <dl class="row specs mb-0">
                    <dt class="col-sm-4">Employee</dt>
                    <dd class="col-sm-8"><a href="{{ route('admin.employees.show', $req->employee) }}">{{ $req->employee->full_name }}</a>
                        <div class="small text-muted">{{ $req->employee->employee_no }} · {{ $req->employee->department }} · {{ $req->employee->company?->name }} · {{ $req->employee->contact_no }}</div></dd>
                    @if($type === 'new_device')
                        <dt class="col-sm-4">Requested Type</dt><dd class="col-sm-8">{{ $req->device_type_label ?? '-' }}</dd>
                    @endif
                    @if($req->device)
                        <dt class="col-sm-4">Device</dt>
                        <dd class="col-sm-8"><a href="{{ route('admin.devices.show', $req->device) }}">{{ $req->device->asset_tag }}</a> · {{ $req->device->name }}
                            <x-status :value="$req->device->status" class="ms-1" /></dd>
                    @endif
                    @if($req->incident_date)
                        <dt class="col-sm-4">Incident Date</dt><dd class="col-sm-8">{{ $req->incident_date->format('M d, Y h:i A') }}</dd>
                    @endif
                    <dt class="col-sm-4">Reason / Details</dt><dd class="col-sm-8">{!! nl2br(e($req->reason)) !!}</dd>
                    <dt class="col-sm-4">Filed</dt><dd class="col-sm-8">{{ $req->created_at->format('M d, Y h:i A') }} by {{ $req->requester?->name }}</dd>
                    @if($req->handler)
                        <dt class="col-sm-4">Handled</dt><dd class="col-sm-8">{{ $req->handled_at?->format('M d, Y h:i A') }} by {{ $req->handler->name }}</dd>
                    @endif
                    @if($req->admin_remarks)
                        <dt class="col-sm-4">IT Remarks</dt><dd class="col-sm-8">{!! nl2br(e($req->admin_remarks)) !!}</dd>
                    @endif
                </dl>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white"><strong>Request Log</strong></div>
            @include('partials.logs', ['logs' => $req->logs->sortByDesc('created_at')])
        </div>
    </div>

    <div class="col-xl-5">
        @if($isOwnRequest && $req->isOpen())
            <div class="alert alert-warning"><i class="bi bi-shield-lock me-1"></i>This request is for your own devices. Another admin or the Super Admin must approve and complete it.</div>
        @elseif($req->status === 'pending')
            <div class="card mb-3">
                <div class="card-header bg-white"><strong>Review</strong></div>
                <div class="card-body">
                    <p class="small text-muted">
                        @switch($type)
                            @case('repair') Approving marks the device <b>In Repair</b>. @break
                            @case('damaged') Approving marks the device <b>Damaged</b>. @break
                            @case('lost') Approving marks the device <b>Lost</b> and ends the employee's assignment. @break
                            @case('return') After approving, complete the request once the device is physically received. @break
                            @default After approving, complete the request by picking a device to assign.
                        @endswitch
                    </p>
                    <form method="POST" action="{{ route('admin.requests.approve', $req) }}" class="mb-3">@csrf
                        <textarea name="admin_remarks" class="form-control mb-2" rows="2" placeholder="Remarks (optional)"></textarea>
                        <button class="btn btn-success w-100"><i class="bi bi-check-lg me-1"></i>Approve</button>
                    </form>
                    <form method="POST" action="{{ route('admin.requests.reject', $req) }}">@csrf
                        <textarea name="admin_remarks" class="form-control mb-2" rows="2" placeholder="Reason for rejection (required)" required></textarea>
                        <button class="btn btn-outline-danger w-100"><i class="bi bi-x-lg me-1"></i>Reject</button>
                    </form>
                </div>
            </div>
        @endif

        @if($req->status === 'approved' && ! $isOwnRequest)
            <form method="POST" action="{{ route('admin.requests.complete', $req) }}" class="card mb-3">@csrf
                <div class="card-header bg-white"><strong>Complete Request</strong></div>
                <div class="card-body">
                    @if(in_array($type, ['repair', 'damaged', 'lost']))
                        <label class="form-label">Outcome for {{ $req->device->asset_tag }} *</label>
                        <select name="device_status" class="form-select mb-3" required>
                            @if($type !== 'lost' && $req->device->current_employee_id === $req->employee_id)
                                <option value="assigned">Fixed - returned to {{ $req->employee->first_name }}</option>
                            @endif
                            @if($type === 'lost')<option value="lost">Remains lost</option>@endif
                            <option value="available">{{ $type === 'lost' ? 'Found - back in stock' : 'Fixed - back to stock (available)' }}</option>
                            @if($type !== 'lost')<option value="damaged">Beyond repair - keep as damaged</option>@endif
                            <option value="retired">Retire / dispose</option>
                        </select>
                    @endif

                    @if($type === 'return' || in_array($type, ['repair', 'damaged']))
                        <label class="form-label">Condition {{ $type === 'return' ? 'on return' : 'after' }}</label>
                        <select name="condition" class="form-select mb-3">
                            <option value="">- unchanged -</option>
                            @foreach(\App\Models\Device::CONDITIONS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                        </select>
                    @endif

                    @if($type !== 'return')
                        <label class="form-label">{{ $type === 'new_device' ? 'Device to assign *' : 'Replacement device (optional)' }}</label>
                        <select name="device_id" class="form-select mb-3" @required($type === 'new_device')>
                            <option value="">{{ $type === 'new_device' ? 'Select an available device...' : 'No replacement' }}</option>
                            @foreach($availableDevices->groupBy('type') as $t => $group)
                                <optgroup label="{{ \App\Models\DeviceType::names()[$t] ?? $t }}">
                                    @foreach($group as $d)
                                        <option value="{{ $d->id }}">[{{ $d->company?->code ?? '?' }}] {{ $d->asset_tag }} - {{ $d->name }} {{ collect([$d->processor, $d->memory, $d->storage])->filter()->implode(', ') ? '('.collect([$d->processor, $d->memory, $d->storage])->filter()->implode(', ').')' : '' }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        @if($availableDevices->isEmpty())<div class="alert alert-warning small py-2">No available devices in stock.</div>@endif
                    @endif

                    <textarea name="admin_remarks" class="form-control mb-2" rows="2" placeholder="Remarks (optional)"></textarea>
                    <button class="btn btn-primary w-100"><i class="bi bi-check2-all me-1"></i>Mark as Completed</button>
                </div>
            </form>
        @endif

        @if(! $req->isOpen())
            <div class="alert alert-secondary">This request is <b>{{ strtolower($req->status_label) }}</b>. No further action needed.</div>
        @endif
    </div>
</div>
@endsection
