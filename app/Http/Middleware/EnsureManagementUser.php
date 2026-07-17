<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;

/** Base guard for User-authenticated management API requests. */
class EnsureManagementUser
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->tokenCan('management:*')) {
            return response()->json(['message' => 'This token is not authorized for management access.'], 403);
        }

        if (! $user->hasVerifiedEmail() || ! $user->isApproved()) {
            return response()->json(['message' => 'Account is not authorized for management access.'], 403);
        }

        return $next($request);
    }
}
