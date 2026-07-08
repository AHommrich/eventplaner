<?php

namespace App\Http\Controllers;

use App\Services\LegalDocumentLoader;
use Inertia\Inertia;
use Inertia\Response;
use League\CommonMark\CommonMarkConverter;

/**
 * Public, unauthenticated legal pages — imprint (§5 DDG) and privacy policy (GDPR Art. 13).
 *
 * The pages read their content from resources/legal/*.de.md via the
 * LegalDocumentLoader so the mobile app (which consumes the same source through
 * /api/legal/*) and the web pages cannot drift apart. Markdown → HTML happens
 * server-side; the Vue templates render each section's HTML with v-html.
 */
class LegalController extends Controller
{
    public function __construct(private readonly LegalDocumentLoader $loader) {}

    public function imprint(): Response
    {
        $document = $this->loader->load('imprint', 'de');

        return Inertia::render('Legal/Imprint', [
            'updated_at' => $document['updated_at'],
            'sections' => $this->toHtmlSections($document['sections']),
        ]);
    }

    public function privacy(): Response
    {
        $document = $this->loader->load('privacy', 'de');

        return Inertia::render('Legal/Privacy', [
            'updated_at' => $document['updated_at'],
            'sections' => $this->toHtmlSections($document['sections']),
        ]);
    }

    /**
     * @param  list<array{id: string, heading: string, body_markdown: string}>  $sections
     * @return list<array{id: string, heading: string, body_html: string}>
     */
    private function toHtmlSections(array $sections): array
    {
        $converter = new CommonMarkConverter;

        return array_map(function (array $section) use ($converter): array {
            return [
                'id' => $section['id'],
                'heading' => $section['heading'],
                'body_html' => (string) $converter->convert($section['body_markdown']),
            ];
        }, $sections);
    }
}
