<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, $roles, true)) {
            if ($request->expectsJson()) {
                abort(403, 'غير مصرح لك بهذا الإجراء.');
            }

            abort(403, 'هذه الصفحة متاحة لمدير النظام فقط.');
        }

        return $next($request);
    }
}
