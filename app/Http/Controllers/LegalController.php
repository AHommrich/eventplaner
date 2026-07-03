<?php

namespace App\Http\Controllers;

use App\Services\LegalDocumentLoader;
use Inertia\Inertia;
use Inertia\Response;
use League\CommonMark\CommonMarkConverter;

/**
 * Public, unauthenticated legal pages — imprint (§5 DDG) and privacy policy (GDPR Art. 13).
 *
 * The privacy page reads its content from resources/legal/privacy.de.md via the
 * LegalDocumentLoader so the mobile app (which consumes the same source through
 * /api/legal/privacy) and the web page cannot drift apart. Markdown → HTML happens
 * server-side; the Vue template renders each section's HTML with v-html.
 */
class LegalController extends Controller
{
    public function __construct(private readonly LegalDocumentLoader $loader)
    {
    }

    public function imprint(): Response
    {
        return Inertia::render('Legal/Imprint');
    }

    public function privacy(): Response
    {
        $document = $this->loader->load('privacy', 'de');
        $converter = new CommonMarkConverter();

        $sections = array_map(function (array $section) use ($converter): array {
            return [
                'id' => $section['id'],
                'heading' => $section['heading'],
                'body_html' => (string) $converter->convert($section['body_markdown']),
            ];
        }, $document['sections']);

        return Inertia::render('Legal/Privacy', [
            'updated_at' => $document['updated_at'],
            'sections' => $sections,
        ]);
    }
}
