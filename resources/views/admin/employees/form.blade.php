@extends('layouts.app')
@section('title', $employee->exists ? "Edit {$employee->full_name}" : 'Add Employee')

@section('content')
<form method="POST" action="{{ $employee->exists ? route('admin.employees.update', $employee) : route('admin.employees.store') }}" class="card">
    @csrf
    @if($employee->exists) @method('PUT') @endif
    <div class="card-body row g-3">
        @unless(auth()->user()->isSuperAdmin())
            <div class="col-12"><div class="alert alert-info py-2 small mb-0"><i class="bi bi-info-circle me-1"></i>Department, company and position come from the master data. If one is missing, ask the Super Admin to add it.</div></div>
        @endunless
        <div class="col-md-4">
            <label class="form-label">Employee ID *</label>
            <input name="employee_no" value="{{ old('employee_no', $employee->employee_no) }}" class="form-control" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">First Name *</label>
            <input name="first_name" value="{{ old('first_name', $employee->first_name) }}" class="form-control" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">Last Name *</label>
            <input name="last_name" value="{{ old('last_name', $employee->last_name) }}" class="form-control" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">Position</label>
            <x-combo name="position" :options="$options['position']" :value="$employee->position" placeholder="Search position..." />
        </div>
        <div class="col-md-4">
            <label class="form-label">Department *</label>
            <x-combo name="department" :options="$options['department']" :value="$employee->department" required placeholder="Search department..." />
        </div>
        <div class="col-md-4">
            <label class="form-label">Company *</label>
            <select name="company_id" data-combo required data-placeholder="Search company...">
                <option value=""></option>
                @foreach($companies as $c)<option value="{{ $c->id }}" @selected(old('company_id', $employee->company_id) == $c->id)>{{ $c->code }} - {{ $c->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Email *</label>
            <input type="email" name="email" value="{{ old('email', $employee->email) }}" class="form-control" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Contact No.</label>
            <input name="contact_no" value="{{ old('contact_no', $employee->contact_no) }}" class="form-control">
        </div>
        <div class="col-12">
            <div class="form-check form-switch">
                <input type="hidden" name="is_active" value="0">
                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', $employee->is_active))>
                <label class="form-check-label" for="is_active">Active employee</label>
            </div>
        </div>
    </div>
    <div class="card-footer bg-white d-flex gap-2">
        <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Save</button>
        <a href="{{ $employee->exists ? route('admin.employees.show', $employee) : route('admin.employees.index') }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
@endsection
