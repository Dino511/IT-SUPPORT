@extends('layouts.app')
@section('title', $user->exists ? "Edit Account: {$user->name}" : 'New Account')

@section('content')
<div class="row g-4 align-items-start">
<div class="col-xl-8">
<form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}" class="card">
    @csrf
    @if($user->exists) @method('PUT') @endif
    <div class="card-body row g-3">
        <div class="col-12">
            <label class="form-label">Linked Employee <span class="text-muted small">(optional; fills name and email)</span></label>
            <select name="employee_id" id="employee_id" data-combo data-placeholder="Search employee...">
                <option value=""></option>
                @foreach($employees as $e)
                    <option value="{{ $e->id }}" @selected(old('employee_id', $user->employee_id) == $e->id)>{{ $e->employee_no }} - {{ $e->full_name }} ({{ $e->department }})</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6"><label class="form-label">Name *</label><input name="name" id="name" value="{{ old('name', $user->name) }}" class="form-control" required></div>
        <div class="col-md-6"><label class="form-label">Email (login) *</label><input type="email" name="email" id="email" value="{{ old('email', $user->email) }}" class="form-control" required></div>
        <div class="col-md-6">
            <label class="form-label">Role *</label>
            <select name="role" class="form-select">
                @foreach(\App\Models\User::ROLES as $k => $v)<option value="{{ $k }}" @selected(old('role', $user->role) === $k)>{{ $v }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-6 d-flex align-items-end">
            <div class="form-check form-switch mb-2">
                <input type="hidden" name="is_active" value="0">
                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', $user->is_active))>
                <label class="form-check-label" for="is_active">Account enabled</label>
            </div>
        </div>
        <div class="col-md-6"><label class="form-label">{{ $user->exists ? 'New password' : 'Password *' }}</label>
            <input type="password" name="password" class="form-control" autocomplete="new-password" @required(! $user->exists) placeholder="{{ $user->exists ? 'Leave blank to keep current' : 'At least 8 characters' }}"></div>
        <div class="col-md-6"><label class="form-label">Confirm password</label>
            <input type="password" name="password_confirmation" class="form-control" autocomplete="new-password"></div>
    </div>
    <div class="card-footer bg-white d-flex gap-2">
        <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Save</button>
        <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
</div>
<div class="col-xl-4">
    <div class="card">
        <div class="card-header"><strong><i class="bi bi-shield-lock me-1" aria-hidden="true"></i>Roles</strong></div>
        <div class="card-body small">
            <p class="mb-2"><span class="badge status-pill text-bg-dark">Super Admin</span></p>
            <ul class="help-list mb-3">
                <li>Everything an Admin can do.</li>
                <li>Maintains master data: companies, device types, dropdown lists.</li>
                <li>Manages user accounts and can process any request.</li>
            </ul>
            <p class="mb-2"><span class="badge status-pill text-bg-primary">Admin</span></p>
            <ul class="help-list">
                <li>Logs devices and employees; assigns and returns devices.</li>
                <li>Files and processes requests, except requests for their own devices.</li>
                <li>Picks dropdown values from the master data only.</li>
            </ul>
        </div>
    </div>
</div>
</div>
@endsection

@push('scripts')
<script>
    const EMPLOYEES = @json($employees->mapWithKeys(fn ($e) => [$e->id => ['name' => $e->full_name, 'email' => $e->email]]));
    document.getElementById('employee_id').tomselect.on('change', id => {
        const emp = EMPLOYEES[id];
        if (!emp) return;
        const name = document.getElementById('name'), email = document.getElementById('email');
        if (!name.value) name.value = emp.name;
        if (!email.value) email.value = emp.email;
    });
</script>
@endpush
