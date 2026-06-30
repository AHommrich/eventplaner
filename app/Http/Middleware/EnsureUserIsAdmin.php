<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protects admin routes (e.g. `/admin/users`) — only users with `role = 'admin'`.
 *
 * On refusal the request is **not** rejected with 403 but redirected to the onboarding
 * page (end users should not see that an admin section exists).
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isAdmin()) {
            return redirect()->route('onboarding');
        }

        return $next($request);
    }
}
