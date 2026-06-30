<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * Public, unauthenticated legal pages — imprint (§5 DDG) and privacy policy (GDPR Art. 13).
 *
 * Both pages are render-only: no DB access, no auth middleware. The actual content lives
 * in the Vue components so it can be reviewed and updated without a deploy if needed
 * — the controller is intentionally a no-op pass-through.
 */
class LegalController extends Controller
{
    public function imprint(): Response
    {
        return Inertia::render('Legal/Imprint');
    }

    public function privacy(): Response
    {
        return Inertia::render('Legal/Privacy');
    }
}
