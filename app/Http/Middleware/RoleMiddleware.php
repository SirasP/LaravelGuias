<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        if (! in_array($user->role, $roles, true)) {
            abort(403, 'No tienes permiso para acceder.');
        }

        return $next($request);
    }
}
