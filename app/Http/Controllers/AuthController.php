<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
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
     * the credentials. Successful sign-ins are written to the audit log.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials + ['is_active' => true], $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Invalid credentials or inactive account.'])->onlyInput('email');
        }

        $request->session()->regenerate(); // prevent session fixation
        AuditLog::record('auth.login', Auth::user()->name.' logged in', ['employee' => Auth::user()->employee]);

        return redirect()->intended(route('home'));
    }

    /** Sign out and invalidate the session. */
    public function logout(Request $request)
    {
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
