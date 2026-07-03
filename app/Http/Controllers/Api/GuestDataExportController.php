<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GuestDataExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GDPR Art. 15 (right of access) endpoint for the in-app guest.
 *
 * Returns a JSON payload with every piece of personal data the guest can
 * see about themselves — scoped strictly to the authenticated guest.
 */
class GuestDataExportController extends Controller
{
    /**
     * GET /api/guest/export
     */
    public function export(Request $request, GuestDataExporter $exporter): JsonResponse
    {
        /** @var \App\Models\Guest $guest */
        $guest = $request->user();

        return response()->json($exporter->export($guest));
    }
}
