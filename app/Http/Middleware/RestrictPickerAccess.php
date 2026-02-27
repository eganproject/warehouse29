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

        $hasPicker = $user->roles()->where('slug', 'picker')->exists();
        if (!$hasPicker) {
            return $next($request);
        }

        $hasOtherRoles = $user->roles()->where('slug', '!=', 'picker')->exists();
        if ($hasOtherRoles) {
            return $next($request);
        }

        $routeName = $request->route()?->getName() ?? '';
        $path = trim($request->path(), '/');

        $isPickerRoute = str_starts_with($routeName, 'picker.') || str_starts_with($path, 'picker');
        $isOpnameRoute = str_starts_with($routeName, 'opname.') || str_starts_with($path, 'opname');
        $isLogoutRoute = $routeName === 'logout';

        if ($isPickerRoute || $isOpnameRoute || $isLogoutRoute) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Akses dibatasi untuk role picker',
            ], 403);
        }

        return redirect()->route('picker.index');
    }
}
