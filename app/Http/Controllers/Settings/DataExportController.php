<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\UserDataExporter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * GDPR Art. 15 (right of access) + Art. 20 (data portability) endpoint.
 *
 * Streams the authenticated user's entire dataset as a single JSON file so it
 * can be downloaded with one click from the settings page. Throttled to bound
 * abuse — a legitimate user will never need more than a handful of exports.
 */
class DataExportController extends Controller
{
    public function download(Request $request, UserDataExporter $exporter): StreamedResponse
    {
        $user = $request->user();
        $payload = $exporter->export($user);

        $filename = sprintf('eveplan-export-%d-%s.json', $user->id, now()->format('Y-m-d'));

        return response()->streamDownload(
            function () use ($payload) {
                echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            },
            $filename,
            ['Content-Type' => 'application/json'],
        );
    }
}
