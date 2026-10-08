<?php

namespace App\Http\Middleware;

use App\Support\Settings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Per-request account guard: disabled accounts, agent IP allow-list, agent idle timeout,
 * forced password change.
 */
class AppGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        if (! $user->is_active || $user->trashed()) {
            return $this->kick($request, 'تم إيقاف هذا الحساب. تواصل مع المدير.');
        }

        if ($user->isAgent()) {
            $allow = array_filter(array_map('trim', explode(',', (string) Settings::get('agent_ip_allowlist', ''))));
            if ($allow && ! in_array($request->ip(), $allow, true)) {
                return $this->kick($request, 'الدخول مسموح فقط من شبكة الشركة.');
            }

            if ($request->hasSession()) {
                $idle = (int) Settings::get('agent_idle_minutes', 45) * 60;
                $last = $request->session()->get('last_seen_at');
                if ($last && $idle > 0 && (time() - $last) > $idle) {
                    return $this->kick($request, 'انتهت الجلسة لعدم النشاط. سجّل الدخول مرة أخرى.');
                }
                $request->session()->put('last_seen_at', time());
            }
        }

        if ($user->must_change_password
            && ! $request->routeIs('password.*', 'logout')
            && ! $request->is('api/*')) {
            return redirect()->route('password.edit')->with('warning', 'يجب تغيير كلمة المرور قبل المتابعة.');
        }

        if ($user->isAdmin() && ! app()->environment('testing') && ! $request->is('api/*') && $request->isMethod('GET') && \App\Services\BackupService::due()
            && \Illuminate\Support\Facades\Cache::add('backup-lock', 1, 600)) {
            app()->terminating(fn () => \App\Services\BackupService::run('auto'));
        }

        return $next($request);
    }

    private function kick(Request $request, string $message): Response
    {
        $isAdminArea = $request->user()?->isAdmin();
        Auth::guard('web')->logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        if ($request->expectsJson()) {
            abort(401, $message);
        }

        return redirect()->route($isAdminArea ? 'admin.login' : 'staff.login')->withErrors(['username' => $message]);
    }
}
