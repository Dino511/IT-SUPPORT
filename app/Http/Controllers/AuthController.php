<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Sign in / sign out for IT staff. Uses Laravel's session guard with the
 * users table; there is no self-registration (accounts are created by the
 * super admin). Login attempts are throttled in routes/web.php.
 */
class AuthController extends Controller
{
    /** Sign-in page. */
    public function showLogin()
    {
        return view('auth.login');
    }

    /**
     * Authenticate. Disabled accounts are refused by adding is_active = true to
     * the credentials. Every attempt is written to the audit log (failed ones
     * with the reason, which the person signing in is not told).
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials + ['is_active' => true], $request->boolean('remember'))) {
            // record why, for the audit trail only; the person signing in just sees one generic message
            $account = User::where('email', $credentials['email'])->first();
            $reason = match (true) {
                ! $account => 'no account with this email',
                ! $account->is_active => 'account is disabled',
                default => 'wrong password',
            };
            AuditLog::record('auth.failed', "Failed sign-in as {$credentials['email']}: {$reason}", ['employee' => $account?->employee]);

            return back()->withErrors(['email' => 'Invalid credentials or inactive account.'])->onlyInput('email');
        }

        $request->session()->regenerate(); // prevent session fixation
        AuditLog::record('auth.login', Auth::user()->name.' logged in', ['employee' => Auth::user()->employee]);

        return redirect()->intended(route('home'));
    }

    /** Sign out (logged first, while we still know who it is) and invalidate the session. */
    public function logout(Request $request)
    {
        if ($user = Auth::user()) {
            AuditLog::record('auth.logout', "{$user->name} logged out", ['employee' => $user->employee]);
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /** "/" sends signed-in users to the dashboard. */
    public function home()
    {
        return redirect()->route('admin.dashboard');
    }
}
