<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHasEventAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ($user->isAdmin() || $user->accessibleEvents()->exists())) {
            return $next($request);
        }

        return redirect()->route('onboarding');
    }
}
