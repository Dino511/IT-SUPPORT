<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * A login account for IT Support staff. There are only two roles:
 *
 * - super_admin: everything, plus master data, companies, device types and
 *   user accounts; may add new dropdown options while filling in forms and
 *   may process any request.
 * - admin: day-to-day inventory work; picks dropdown values from the master
 *   data only and cannot process requests for their own devices.
 *
 * Routes are guarded by the `role:` middleware (App\Http\Middleware\EnsureRole).
 * Disabled accounts (is_active = false) cannot sign in (see AuthController).
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_ADMIN = 'admin';

    /** Role code => label shown in the UI. */
    public const ROLES = [
        self::ROLE_SUPER_ADMIN => 'Super Admin',
        self::ROLE_ADMIN => 'Admin',
    ];

    protected $fillable = ['name', 'email', 'password', 'role', 'employee_id', 'is_active'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed', // assigning a plain password stores its hash
            'is_active' => 'boolean',
            'employee_id' => 'integer',
        ];
    }

    /** Employee record of this staff member (optional). */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    /** "Super Admin" / "Admin" ($user->role_label). */
    public function getRoleLabelAttribute(): string
    {
        return self::ROLES[$this->role] ?? $this->role;
    }
}
