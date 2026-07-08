<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LegalDocumentLoader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public, unauthenticated legal endpoints for the mobile client.
 *
 * The app renders legal documents natively so users can read them before they
 * consent — that means no auth, no session. `throttle:30,1` at the route level
 * keeps abuse in check without penalising legitimate readers who might refresh
 * a few times.
 */
class LegalController extends Controller
{
    public function __construct(private readonly LegalDocumentLoader $loader) {}

    public function privacy(Request $request): JsonResponse
    {
        $document = $this->loader->load('privacy', $request->query('locale'));

        return response()->json($document);
    }

    public function imprint(Request $request): JsonResponse
    {
        $document = $this->loader->load('imprint', $request->query('locale'));

        return response()->json($document);
    }
}
