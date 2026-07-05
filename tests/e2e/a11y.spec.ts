import { AxeBuilder } from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

/**
 * Accessibility smoke — public routes must not produce WCAG 2.1 A/AA
 * violations. Kept scoped to unauthenticated routes so we don't have to
 * seed a demo user just for a11y coverage.
 *
 * Failures print the axe violation list; fix them or annotate the specific
 * rule id with a documented exception. Never blanket-`test.skip` an a11y
 * finding — that defeats the point of running the scan.
 */

const PUBLIC_ROUTES = ['/', '/login', '/register', '/impressum', '/datenschutz'];

for (const route of PUBLIC_ROUTES) {
    test(`a11y: ${route} passes WCAG 2 A/AA`, async ({ page }) => {
        await page.goto(route);
        await page.waitForLoadState('networkidle');

        const results = await new AxeBuilder({ page })
            .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
            .analyze();

        // Print the offending rules on failure — Playwright's default diff
        // for {violations: []} is unreadable.
        if (results.violations.length) {
            const summary = results.violations
                .map((v) => `${v.id} (${v.impact ?? 'unknown'}): ${v.help}`)
                .join('\n');
            console.log('a11y violations:\n' + summary);
        }

        expect(results.violations).toEqual([]);
    });
}
