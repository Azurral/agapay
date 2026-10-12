<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Until a person replaces the password the administrator gave them, every page leads to Change Password. */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->must_change_password && ! $request->routeIs('password.edit', 'password.update')) {
            return redirect()->route('password.edit');
        }

        return $next($request);
    }
}
