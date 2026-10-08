<?php

namespace App\Http\Middleware;

use App\Support\Permissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $module, string $action = 'view'): Response
    {
        $user = $request->user();
        if (! $user || ! Permissions::allows($user, $module, $action)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'غير مصرح لك بهذا الإجراء.'], 403);
            }
            abort(403, 'ليست لديك صلاحية لهذا الإجراء. تواصل مع المدير.');
        }

        return $next($request);
    }
}
