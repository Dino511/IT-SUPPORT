@extends('layouts.app')
@section('title', 'Audit Logs')
@section('actions')
    <a href="{{ route('admin.audit.export', request()->query()) }}" class="btn btn-outline-secondary"><i class="bi bi-filetype-csv me-1" aria-hidden="true"></i>Export CSV</a>
@endsection

@section('content')
@php($filtered = collect(request()->only(['q', 'action', 'user_id', 'from', 'to']))->filter()->isNotEmpty())
<div class="card mb-4">
    <form class="card-body filter-bar" role="search" aria-label="Filter audit logs">
        <div class="f-search">
            <label class="form-label" for="f-q">Search</label>
            <input id="f-q" name="q" value="{{ request('q') }}" class="form-control" placeholder="Asset tag, serial, employee ID or text">
        </div>
        <div class="f-select">
            <label class="form-label" for="f-action">Action</label>
            <select id="f-action" name="action" class="form-select"><option value="">All actions</option>
                @foreach(\App\Models\AuditLog::ACTIONS as $k => $v)<option value="{{ $k }}" @selected(request('action') === $k)>{{ $v }}</option>@endforeach
            </select>
        </div>
        <div class="f-select">
            <label class="form-label" for="f-user">User</label>
            <select id="f-user" name="user_id" class="form-select"><option value="">All users</option>
                @foreach($users as $u)<option value="{{ $u->id }}" @selected(request('user_id') == $u->id)>{{ $u->name }}</option>@endforeach
            </select>
        </div>
        <div class="f-date">
            <label class="form-label" for="f-from">From</label>
            <input type="date" id="f-from" name="from" value="{{ request('from') }}" class="form-control">
        </div>
        <div class="f-date">
            <label class="form-label" for="f-to">To</label>
            <input type="date" id="f-to" name="to" value="{{ request('to') }}" class="form-control">
        </div>
        <div class="f-actions">
            <button class="btn btn-primary flex-fill"><i class="bi bi-funnel me-1" aria-hidden="true"></i>Filter</button>
            @if($filtered)<a href="{{ route('admin.audit.index') }}" class="btn btn-outline-secondary" title="Clear filters" aria-label="Clear filters"><i class="bi bi-x-lg" aria-hidden="true"></i></a>@endif
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <strong><i class="bi bi-journal-text me-1" aria-hidden="true"></i>Activity</strong>
        <span class="small text-muted">{{ number_format($logs->total()) }} {{ Str::plural('entry', $logs->total()) }}{{ $filtered ? ' match your filters' : '' }}</span>
    </div>
    <div class="table-responsive">
        <table class="table log-table mb-0">
            <thead class="table-light">
                <tr><th style="width:150px">Time</th><th style="width:200px">User</th><th style="width:210px">Action</th><th style="width:250px">Related to</th><th>Description</th></tr>
            </thead>
            <tbody>
            @forelse($logs->getCollection()->groupBy(fn ($l) => $l->created_at->toDateString()) as $day => $entries)
                @php($date = \Illuminate\Support\Carbon::parse($day))
                <tr class="day-row">
                    <td colspan="5"><i class="bi bi-calendar3 me-2" aria-hidden="true"></i>{{ $date->isToday() ? 'Today' : ($date->isYesterday() ? 'Yesterday' : $date->format('l')) }} · {{ $date->format('M d, Y') }}
                        <span class="count ms-2">{{ $entries->count() }} {{ Str::plural('entry', $entries->count()) }}</span></td>
                </tr>
                @foreach($entries as $log)
                    @php([$tone, $icon] = $log->action_style)
                    <tr>
                        <td><span class="log-time" title="{{ $log->created_at->format('M d, Y h:i:s A') }}">{{ $log->created_at->format('h:i:s A') }}<small>{{ $log->created_at->diffForHumans() }}</small></span></td>
                        <td>
                            <span class="who">
                                <span class="avatar-xs {{ $log->user ? '' : 'system' }}" aria-hidden="true">{{ $log->user ? Str::of($log->user->name)->explode(' ')->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') : 'SY' }}</span>
                                {{ $log->user?->name ?? 'System' }}
                            </span>
                        </td>
                        <td><span class="action-pill text-bg-{{ $tone }}"><i class="bi {{ $icon }}" aria-hidden="true"></i>{{ $log->action_label }}</span></td>
                        <td>
                            @if($log->device || $log->employee || $log->request)
                                <div class="rel-list">
                                    @if($log->device)<a href="{{ route('admin.devices.show', $log->device) }}" class="rel-chip mono" title="{{ $log->device->name }}"><i class="bi bi-laptop" aria-hidden="true"></i>{{ $log->device->asset_tag }}</a>@endif
                                    @if($log->employee)<a href="{{ route('admin.employees.show', $log->employee) }}" class="rel-chip" title="{{ $log->employee->employee_no }}"><i class="bi bi-person" aria-hidden="true"></i>{{ $log->employee->full_name }}</a>@endif
                                    @if($log->request)<a href="{{ route('admin.requests.show', $log->request) }}" class="rel-chip mono"><i class="bi bi-inbox" aria-hidden="true"></i>{{ $log->request->reference_no }}</a>@endif
                                </div>
                            @else
                                <span class="text-muted small">-</span>
                            @endif
                        </td>
                        <td class="log-desc">{{ $log->description }}
                            @if($log->changes)
                                <details><summary>View changes ({{ count($log->changes) }})</summary>
                                    <div class="changes">
                                        @foreach($log->changes as $field => [$old, $new])<div><code>{{ $field }}</code>: {{ $old ?? '∅' }} → {{ $new ?? '∅' }}</div>@endforeach
                                    </div>
                                </details>
                            @endif
                        </td>
                    </tr>
                @endforeach
            @empty
                <tr><td colspan="5"><div class="empty-state"><i class="bi bi-journal-x" aria-hidden="true"></i>No log entries match these filters.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($logs->total())
        <div class="card-footer">
            @if($logs->hasPages())
                {{ $logs->onEachSide(1)->links('pagination::bootstrap-5') }}
            @else
                <span class="small text-muted">Showing all {{ number_format($logs->total()) }} {{ Str::plural('entry', $logs->total()) }}</span>
            @endif
        </div>
    @endif
</div>
@endsection
