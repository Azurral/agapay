<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Allows the request when the user holds at least one of the listed permissions: can.any:perm1,perm2 */
class EnsureCanAny
{
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $user = $request->user();

        abort_unless($user && collect($abilities)->contains(fn (string $ability) => $user->can($ability)), 403);

        return $next($request);
    }
}
