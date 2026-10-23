@extends('layouts.app')
@section('title', 'Employee Requests')
@section('actions')
    <a href="{{ route('admin.requests.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>New Request</a>
@endsection

@section('content')
<ul class="nav nav-tabs mb-0">
    @foreach(['' => 'Open', 'pending' => 'Pending', 'approved' => 'Approved', 'completed' => 'Completed', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled', 'all' => 'All'] as $k => $v)
        @php($active = $k === 'all' ? request()->has('status') && ! request('status') : (request('status', '') === $k && ($k !== '' || ! request()->has('status'))))
        <li class="nav-item"><a class="nav-link {{ $active ? 'active' : '' }}" href="{{ $k === '' ? route('admin.requests.index') : route('admin.requests.index', ['status' => $k === 'all' ? '' : $k]) }}">{{ $v }}</a></li>
    @endforeach
</ul>
<div class="card rounded-top-0">
    <div class="card-body border-bottom">
        <form class="row g-2">
            @if(request()->has('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
            <div class="col-md-6"><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Search reference, employee name or ID..."></div>
            <div class="col-md-4">
                <select name="type" class="form-select"><option value="">All request types</option>
                    @foreach(\App\Models\DeviceRequest::TYPES as $k => $v)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $v }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-secondary w-100">Filter</button></div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>Ref #</th><th>Employee</th><th>Type</th><th>Device</th><th>Priority</th><th>Status</th><th>Filed</th><th>Filed By</th><th>Handled By</th></tr></thead>
            <tbody>
            @forelse($requests as $r)
                <tr onclick="location='{{ route('admin.requests.show', $r) }}'" style="cursor:pointer">
                    <td class="fw-semibold">{{ $r->reference_no }}</td>
                    <td>{{ $r->employee->full_name }}<div class="small text-muted">{{ $r->employee->employee_no }} · {{ $r->employee->department }} · {{ $r->employee->company?->code }}</div></td>
                    <td>{{ $r->type_label }}</td>
                    <td>{{ $r->device?->asset_tag ?? $r->device_type_label ?? '-' }}</td>
                    <td><x-status :value="$r->priority" /></td>
                    <td><x-status :value="$r->status" /></td>
                    <td class="small">{{ $r->created_at->format('M d, Y h:i A') }}</td>
                    <td class="small">{{ $r->requester?->name ?? '-' }}</td>
                    <td class="small">{{ $r->handler?->name ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-muted py-4">No requests.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($requests->hasPages())<div class="card-footer bg-white">{{ $requests->links('pagination::bootstrap-5') }}</div>@endif
</div>
@endsection
