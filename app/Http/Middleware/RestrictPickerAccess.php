<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictPickerAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            return $next($request);
        }

        $roles = $user->roles()->pluck('slug');
        $hasPicker = $roles->contains('picker');
        $hasPacker = $roles->contains('packer');
        if (!$hasPicker && !$hasPacker) {
            return $next($request);
        }

        $hasOtherRoles = $roles->diff(['picker', 'packer'])->isNotEmpty();
        if ($hasOtherRoles) {
            return $next($request);
        }

        $routeName = $request->route()?->getName() ?? '';
        $path = trim($request->path(), '/');

        $isDashboardRoute = $routeName === 'picker.dashboard' || $path === 'picker/dashboard';
        $isPackerRoute = str_starts_with($routeName, 'picker.packer') || str_starts_with($path, 'picker/packer');
        $isPickerRoute = (str_starts_with($routeName, 'picker.') || str_starts_with($path, 'picker'))
            && !$isPackerRoute
            && !$isDashboardRoute;
        $isOpnameRoute = str_starts_with($routeName, 'opname.') || str_starts_with($path, 'opname');
        $isLogoutRoute = $routeName === 'logout';

        if ($hasPicker) {
            if ($isPickerRoute || $isOpnameRoute || $isLogoutRoute || $isDashboardRoute) {
                return $next($request);
            }
        }

        if ($hasPacker) {
            if ($isPackerRoute || $isOpnameRoute || $isLogoutRoute || $isDashboardRoute) {
                return $next($request);
            }
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Akses dibatasi untuk role picker',
            ], 403);
        }

        return redirect()->route('picker.dashboard');
    }
}
