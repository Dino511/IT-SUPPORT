<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Login accounts for IT staff (User Accounts page). Super admin only.
 *
 * An account may be linked to an employee record; that link is what lets the
 * system stop an Admin from approving requests for their own devices.
 * Accounts are disabled, never deleted. Passwords are never logged.
 */
class UserController extends Controller
{
    /** All accounts, super admins first. */
    public function index()
    {
        return view('admin.users.index', [
            'users' => User::with('employee')->orderBy('role')->orderBy('name')->get(),
        ]);
    }

    /** "New Account" form (defaults to an active Admin). */
    public function create()
    {
        return $this->form(new User(['role' => User::ROLE_ADMIN, 'is_active' => true]));
    }

    /** Create the account (the password is hashed by the User model cast). */
    public function store(Request $request)
    {
        $user = User::create($this->validated($request));
        AuditLog::record('user.created', "Created {$user->role_label} account for {$user->name} ({$user->email})", ['employee' => $user->employee]);

        return redirect()->route('admin.users.index')->with('success', "Account for {$user->name} created.");
    }

    public function edit(User $user)
    {
        return $this->form($user);
    }

    /**
     * Update role, details, enabled flag or password (blank = keep). The signed-in
     * super admin cannot demote or disable themselves, so the system always keeps
     * someone able to manage it.
     */
    public function update(Request $request, User $user)
    {
        $data = $this->validated($request, $user);

        // keep at least this super admin able to manage the system
        if ($user->is(auth()->user()) && ($data['role'] !== User::ROLE_SUPER_ADMIN || ! $data['is_active'])) {
            return back()->withInput()->with('error', 'You cannot remove your own Super Admin role or disable your own account.');
        }

        // log which fields changed, but never the password value itself
        $user->fill($data);
        $changed = array_keys(collect($user->getDirty())->except(['password', 'remember_token'])->all());
        if ($user->isDirty('password')) {
            $changed[] = 'password';
        }
        $user->save();

        if ($changed) {
            AuditLog::record('user.updated', "Updated account {$user->name}: ".implode(', ', $changed)." (role: {$user->role_label}, ".($user->is_active ? 'active' : 'disabled').')',
                ['employee' => $user->employee]);
        }

        return redirect()->route('admin.users.index')->with('success', 'Account updated.');
    }

    /** Shared create/edit form; offers active employees to link the account to. */
    private function form(User $user)
    {
        return view('admin.users.form', [
            'user' => $user,
            'employees' => Employee::where('is_active', true)->orderBy('last_name')->get(),
        ]);
    }

    /**
     * Validate the account form. Email is unique; an employee can be linked to
     * one account only; the password (min 8, confirmed) is required on create.
     */
    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users')->ignore($user)],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'employee_id' => ['nullable', Rule::exists('employees', 'id'), Rule::unique('users')->ignore($user)],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)],
        ], ['employee_id.unique' => 'That employee already has an account.']);

        $data['is_active'] = $request->boolean('is_active');
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        return $data;
    }
}
