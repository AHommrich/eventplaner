<?php

namespace App\Services;

use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

/**
 * Loads legal documents (privacy policy, later: imprint) from Markdown files
 * living in resources/legal/{slug}.{locale}.md.
 *
 * Each file starts with a YAML front-matter block (updated_at) and a body of
 * H2 sections. Sections are split at every "## " line; the heading may carry
 * an explicit anchor via the {#slug} suffix, otherwise the section id is
 * derived from a slugified heading.
 *
 * Retention numbers from config/retention.php are interpolated into the body
 * via {{retention.invitation_tokens_days}} / {{retention.declined_guests_days}}
 * / {{retention.guest_erasure_grace_days}} / {{retention.photo_reports_days}}
 * placeholders so the user-facing text cannot drift from the actual scheduled
 * command windows.
 *
 * Locale handling: unknown locales fall back to "de" (German is the
 * authoritative version because the primary audience and the legal
 * jurisdiction are German).
 */
class LegalDocumentLoader
{
    public const SUPPORTED_LOCALES = ['de', 'en'];

    public const DEFAULT_LOCALE = 'de';

    /**
     * @return array{locale: string, updated_at: string, sections: list<array{id: string, heading: string, body_markdown: string}>}
     */
    public function load(string $slug, ?string $locale): array
    {
        $resolvedLocale = in_array($locale, self::SUPPORTED_LOCALES, true)
            ? $locale
            : self::DEFAULT_LOCALE;

        $path = resource_path("legal/{$slug}.{$resolvedLocale}.md");

        if (! is_file($path)) {
            throw new RuntimeException("Legal document not found: {$slug} ({$resolvedLocale}).");
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException("Failed to read legal document: {$path}.");
        }

        [$frontMatter, $body] = $this->splitFrontMatter($raw);

        $updatedAt = $this->normalizeUpdatedAt($frontMatter['updated_at'] ?? null);
        $body = $this->interpolatePlaceholders($body);
        $sections = $this->splitSections($body);

        return [
            'locale' => $resolvedLocale,
            'updated_at' => $updatedAt,
            'sections' => $sections,
        ];
    }

    /**
     * @return array{0: array<string, mixed>, 1: string}
     */
    private function splitFrontMatter(string $raw): array
    {
        $raw = ltrim($raw, "\xEF\xBB\xBF");

        if (! str_starts_with($raw, "---\n") && ! str_starts_with($raw, "---\r\n")) {
            return [[], $raw];
        }

        $withoutOpen = preg_replace('/^---\r?\n/', '', $raw, 1);
        $parts = preg_split('/^---\r?\n/m', $withoutOpen, 2);

        if ($parts === false || count($parts) !== 2) {
            return [[], $raw];
        }

        $frontMatter = Yaml::parse($parts[0]) ?: [];
        if (! is_array($frontMatter)) {
            $frontMatter = [];
        }

        return [$frontMatter, ltrim($parts[1], "\r\n")];
    }

    private function normalizeUpdatedAt(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }

        if (is_string($value) && $value !== '') {
            $ts = strtotime($value);
            if ($ts !== false) {
                return gmdate('Y-m-d\TH:i:s\Z', $ts);
            }
        }

        return gmdate('Y-m-d\TH:i:s\Z');
    }

    private function interpolatePlaceholders(string $body): string
    {
        $replacements = [
            '{{retention.invitation_tokens_days}}' => (string) config('retention.invitation_tokens_after_event_days'),
            '{{retention.declined_guests_days}}' => (string) config('retention.declined_guests_after_event_days'),
            '{{retention.guest_erasure_grace_days}}' => (string) config('retention.guest_erasure_grace_days'),
        ];

        return strtr($body, $replacements);
    }

    /**
     * @return list<array{id: string, heading: string, body_markdown: string}>
     */
    private function splitSections(string $body): array
    {
        $lines = preg_split('/\r?\n/', $body);
        if ($lines === false) {
            return [];
        }

        $sections = [];
        $current = null;

        foreach ($lines as $line) {
            if (preg_match('/^##\s+(.+?)(?:\s+\{#([a-z0-9-]+)\})?\s*$/', $line, $m)) {
                if ($current !== null) {
                    $sections[] = $this->finalizeSection($current);
                }

                $heading = trim($m[1]);
                $id = $m[2] ?? Str::slug($heading);

                $current = [
                    'id' => $id,
                    'heading' => $heading,
                    'lines' => [],
                ];

                continue;
            }

            if ($current !== null) {
                $current['lines'][] = $line;
            }
        }

        if ($current !== null) {
            $sections[] = $this->finalizeSection($current);
        }

        return $sections;
    }

    /**
     * @param  array{id: string, heading: string, lines: list<string>}  $section
     * @return array{id: string, heading: string, body_markdown: string}
     */
    private function finalizeSection(array $section): array
    {
        return [
            'id' => $section['id'],
            'heading' => $section['heading'],
            'body_markdown' => trim(implode("\n", $section['lines'])),
        ];
    }
}
