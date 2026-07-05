import { describe, expect, it } from 'vitest';
import { originalLabel, taskLabel, type TaskInPool } from '../photoGameLabels';

function makeTask(overrides: Partial<TaskInPool> = {}): TaskInPool {
    return {
        id: 1,
        override_id: null,
        description: 'Take a photo of the wedding cake',
        translation_key: null,
        state: 'normal',
        original_text: null,
        ...overrides,
    };
}

// i18n fakes — tiny doubles that mimic vue-i18n's `t` / `te`.
const t = (key: string) => `T[${key}]`;
const teAlways = () => true;
const teNever = () => false;
const teOnly = (allowed: string) => (key: string) => key === allowed;

describe('taskLabel', () => {
    it('returns the translated string when the translation key exists in the current locale', () => {
        const task = makeTask({ translation_key: 'photoGame.tasks.cake', description: 'ignored' });
        expect(taskLabel(task, t, teAlways)).toBe('T[photoGame.tasks.cake]');
    });

    it('falls back to the raw description when the translation key is unknown to the locale', () => {
        const task = makeTask({ translation_key: 'photoGame.tasks.cake', description: 'Take a photo of the cake' });
        expect(taskLabel(task, t, teNever)).toBe('Take a photo of the cake');
    });

    it('falls back to the raw description when there is no translation key at all', () => {
        const task = makeTask({ translation_key: null, description: 'Custom task added by admin' });
        expect(taskLabel(task, t, teAlways)).toBe('Custom task added by admin');
    });

    it('renders the override description for a modified base task (description carries the delta)', () => {
        const task = makeTask({ state: 'modified', translation_key: null, description: 'Modified text', original_text: 'Original text' });
        expect(taskLabel(task, t, teAlways)).toBe('Modified text');
    });
});

describe('originalLabel', () => {
    it('returns the translation of the base task when it exists (that IS the original)', () => {
        const task = makeTask({ state: 'modified', translation_key: 'photoGame.tasks.dance', original_text: 'Old text' });
        expect(originalLabel(task, t, teOnly('photoGame.tasks.dance'))).toBe('T[photoGame.tasks.dance]');
    });

    it('returns the stored original_text when the base task has no translation key', () => {
        const task = makeTask({ state: 'modified', translation_key: null, original_text: 'Old text' });
        expect(originalLabel(task, t, teAlways)).toBe('Old text');
    });

    it('returns empty string when neither translation nor original is available', () => {
        const task = makeTask({ state: 'added', translation_key: null, original_text: null });
        expect(originalLabel(task, t, teAlways)).toBe('');
    });
});
