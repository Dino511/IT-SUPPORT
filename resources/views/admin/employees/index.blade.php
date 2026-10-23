@extends('layouts.app')
@section('title', 'Employees')
@section('actions')
    <a href="{{ route('admin.employees.create') }}" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i>Add Employee</a>
@endsection

@section('content')
<div class="card">
    <div class="card-body border-bottom">
        <form class="row g-2">
            <div class="col-md-7"><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Search employee ID, name, email..."></div>
            <div class="col-md-3">
                <select name="department" class="form-select"><option value="">All departments</option>
                    @foreach($departments as $d)<option @selected(request('department') === $d)>{{ $d }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-secondary flex-fill">Filter</button>
                <a href="{{ route('admin.employees.index') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>Employee ID</th><th>Name</th><th>Department</th><th>Company</th><th>Contact</th><th>Email</th><th class="text-center">Devices</th><th>Status</th></tr></thead>
            <tbody>
            @forelse($employees as $e)
                <tr onclick="location='{{ route('admin.employees.show', $e) }}'" style="cursor:pointer">
                    <td class="fw-semibold">{{ $e->employee_no }}</td>
                    <td>{{ $e->full_name }}<div class="small text-muted">{{ $e->position }}</div></td>
                    <td>{{ $e->department }}</td>
                    <td>{{ $e->company?->name }} <span class="badge text-bg-light border">{{ $e->company?->code }}</span></td>
                    <td class="small">{{ $e->contact_no ?? '-' }}</td>
                    <td class="small">{{ $e->email }}</td>
                    <td class="text-center"><span class="badge text-bg-primary rounded-pill">{{ $e->devices_count }}</span></td>
                    <td>{!! $e->is_active ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-secondary">Inactive</span>' !!}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No employees found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($employees->hasPages())<div class="card-footer bg-white">{{ $employees->links('pagination::bootstrap-5') }}</div>@endif
</div>
@endsection
