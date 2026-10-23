@extends('layouts.app')
@section('title', 'User Accounts')
@section('actions')
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i>New Account</a>
@endsection

@section('content')
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>Name</th><th>Email</th><th>Role</th><th>Employee Record</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @foreach($users as $u)
                <tr>
                    <td class="fw-semibold">{{ $u->name }} @if($u->is(auth()->user()))<span class="badge text-bg-light border">You</span>@endif</td>
                    <td>{{ $u->email }}</td>
                    <td><span class="badge {{ $u->isSuperAdmin() ? 'text-bg-dark' : 'text-bg-primary' }}">{{ $u->role_label }}</span></td>
                    <td>@if($u->employee)<a href="{{ route('admin.employees.show', $u->employee) }}">{{ $u->employee->employee_no }}</a> · {{ $u->employee->department }}@else<span class="text-muted">-</span>@endif</td>
                    <td>{!! $u->is_active ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-secondary">Disabled</span>' !!}</td>
                    <td class="text-end"><a href="{{ route('admin.users.edit', $u) }}" class="btn btn-sm btn-outline-primary">Edit</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
<div class="card mt-3">
    <div class="card-body small text-muted">
        <b>Super Admin</b>: everything, plus Master Data (dropdown lists, device types) and user accounts. Can add new dropdown options while filling in forms, and can process any request.<br>
        <b>Admin</b>: logs devices and employees, assigns/returns devices, files and processes requests, views audit logs. Picks dropdown values from the master data only, and cannot process requests for their own devices.
    </div>
</div>
@endsection
