<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Uso: ->middleware('role:admin,seller'). El contador queda en solo lectura. */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        abort_unless($request->user() && in_array($request->user()->role, $roles, true), 403,
            'Su usuario no tiene permiso para esta parte del sistema.');

        return $next($request);
    }
}
