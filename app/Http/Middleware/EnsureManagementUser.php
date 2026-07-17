<?php

namespace App\Http\Middleware;

use App\Services\ManagementSessionResolver;
use Closure;
use Illuminate\Http\Request;

/** Base guard for User-authenticated management API requests. */
class EnsureManagementUser
{
    public function __construct(private readonly ManagementSessionResolver $sessions) {}

    public function handle(Request $request, Closure $next)
    {
        $pairing = $this->sessions->resolve($request);
        if (! $pairing) {
            return response()->json(['message' => 'Account is not authorized for management access.'], 403);
        }

        $request->attributes->set('management_pairing', $pairing);
        $request->attributes->set('management_event', $pairing->event);

        return $next($request);
    }
}
