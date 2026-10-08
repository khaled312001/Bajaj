<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    private const MAX_ATTEMPTS = 5;
    private const LOCK_MINUTES = 15;

    public function gateway()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.gateway');
    }

    public function showAdmin()
    {
        return view('auth.login', ['role' => User::ROLE_ADMIN]);
    }

    public function showStaff()
    {
        return view('auth.login', ['role' => User::ROLE_AGENT]);
    }

    public function loginAdmin(Request $request)
    {
        return $this->attempt($request, User::ROLE_ADMIN);
    }

    public function loginStaff(Request $request)
    {
        return $this->attempt($request, User::ROLE_AGENT);
    }

    private function attempt(Request $request, string $role)
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'max:200'],
        ]);

        $user = User::whereRaw('LOWER(username) = ?', [mb_strtolower(trim($data['username']))])->first();
        $generic = 'بيانات الدخول غير صحيحة.';

        if (! $user || $user->role !== $role) {
            // keep timing similar whether or not the account exists
            Hash::make($data['password']);
            Activity::log('login_failed', null, 'محاولة دخول فاشلة: ' . $data['username'] . ' (' . $role . ')');
            throw ValidationException::withMessages(['username' => $generic]);
        }

        if ($user->isLocked()) {
            $mins = max(1, (int) ceil(now()->diffInSeconds($user->locked_until, false) / 60));
            throw ValidationException::withMessages(['username' => "الحساب مقفل مؤقتاً بسبب محاولات خاطئة. حاول بعد {$mins} دقيقة."]);
        }

        if (! Hash::check($data['password'], $user->password)) {
            $user->failed_attempts++;
            if ($user->failed_attempts >= self::MAX_ATTEMPTS) {
                $user->locked_until = now()->addMinutes(self::LOCK_MINUTES);
                $user->failed_attempts = 0;
                Activity::log('security.alert', $user, 'تم قفل حساب ' . $user->username . ' بعد محاولات دخول فاشلة متكررة', userId: $user->id);
            }
            $user->saveQuietly();
            Activity::log('login_failed', $user, 'كلمة مرور خاطئة', userId: $user->id);
            throw ValidationException::withMessages(['username' => $generic]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages(['username' => 'هذا الحساب موقوف. تواصل مع المدير.']);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $request->session()->put('last_seen_at', time());

        $user->forceFill([
            'failed_attempts' => 0, 'locked_until' => null,
            'last_login_at' => now(), 'last_login_ip' => $request->ip(),
        ])->saveQuietly();

        Activity::log('login', $user, 'تسجيل دخول ' . $user->role_label);

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        $wasAdmin = Auth::user()?->isAdmin();
        if (Auth::check()) {
            Activity::log('logout', Auth::user(), 'تسجيل خروج');
        }
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route($wasAdmin ? 'admin.login' : 'staff.login');
    }
}
