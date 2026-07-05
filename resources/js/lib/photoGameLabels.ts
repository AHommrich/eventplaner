/**
 * Photo-game task label resolution (delta-catalog model).
 *
 * Each task in the pool carries a `description` (already resolved for the
 * current event — modified overrides land here as the new text), an
 * `original_text` (only meaningful for `modified` overrides — the base task's
 * original description), and an optional `translation_key` that references
 * the i18n catalog.
 *
 * Precedence:
 *   1. Translation key wins when it exists in the current locale.
 *   2. Otherwise fall back to the description/original.
 *
 * The i18n bindings are passed in so the pure logic can be unit-tested
 * without mounting the whole PhotoGame page.
 *
 * Extracted from resources/js/pages/PhotoGame/Index.vue.
 */

export interface TaskInPool {
    id: number | null;
    override_id: number | null;
    description: string;
    translation_key: string | null;
    state: 'normal' | 'hidden' | 'modified' | 'added';
    original_text: string | null;
}

export type TranslateFn = (key: string) => string;
export type TranslationExistsFn = (key: string) => boolean;

export function taskLabel(task: TaskInPool, t: TranslateFn, te: TranslationExistsFn): string {
    if (task.translation_key && te(task.translation_key)) {
        return t(task.translation_key);
    }
    return task.description;
}

export function originalLabel(task: TaskInPool, t: TranslateFn, te: TranslationExistsFn): string {
    if (task.translation_key && te(task.translation_key)) {
        return t(task.translation_key);
    }
    return task.original_text ?? '';
}
