<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * Public, unauthenticated legal pages — imprint (§5 DDG) and privacy policy (GDPR Art. 13).
 *
 * Both pages are render-only: no DB access, no auth middleware. The privacy page
 * receives the retention windows from `config/retention.php` so the user-facing text
 * cannot silently drift from the actual scheduled-command behaviour.
 */
class LegalController extends Controller
{
    public function imprint(): Response
    {
        return Inertia::render('Legal/Imprint');
    }

    public function privacy(): Response
    {
        return Inertia::render('Legal/Privacy', [
            'retention' => [
                'invitation_tokens_days' => (int) config('retention.invitation_tokens_after_event_days'),
                'declined_guests_days' => (int) config('retention.declined_guests_after_event_days'),
            ],
        ]);
    }
}
