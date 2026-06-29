# Tag 4 — Frontend nur kritisch (Vitest)

**Aufwand**: ~3h
**Outcome**: Vitest läuft, 4 Component-Specs decken die wirklich kritischen Frontend-Stellen ab (ConfirmDialog, CreatableCombobox, InfoTooltip, i18n-Plugin).

## Warum nur 4 Specs

Bewusste Beschränkung: 162 Vue-Komponenten test-mäßig hochzufahren wäre 1-2 Wochen Arbeit ohne klaren Showcase-Mehrwert. Stattdessen die **vier Komponenten/Plugins die überall im Code wiederverwendet werden** und/oder **Bug-anfällige Logik** enthalten:

- **ConfirmDialog** — wird bei JEDER Lösch-Aktion verwendet, defensiver Code muss sitzen
- **CreatableCombobox** — komplexe Eigenheiten (ESC-Propagation-Stop, Duplicate-Detection)
- **InfoTooltip** — Mobile-Click-Toggle-Verhalten ist Custom (vgl. Commit d784cbd)
- **i18n-Plugin** — localStorage-Persistierung + reaktive Updates

Alles andere ist UI-Polish und wird visuell durch den User getestet.

## Schritte

### 1. DevDeps installieren

```bash
docker exec eventplaner-vite-1 npm install -D vitest @vue/test-utils jsdom @vitest/ui @vitest/coverage-v8
```

### 2. `vitest.config.ts` neu

```ts
import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'
import { fileURLToPath } from 'node:url'

export default defineConfig({
    plugins: [vue()],
    test: {
        environment: 'jsdom',
        globals: true,
        coverage: {
            provider: 'v8',
            reporter: ['text', 'html'],
            include: ['resources/js/**/*.{ts,vue}'],
            exclude: ['resources/js/**/*.d.ts', 'resources/js/types/**'],
        },
    },
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
})
```

### 3. `package.json` Scripts ergänzen

```json
"scripts": {
    "test": "vitest --run",
    "test:watch": "vitest",
    "test:coverage": "vitest --run --coverage"
}
```

### 4. `resources/js/components/__tests__/ConfirmDialog.spec.ts` (4 Cases)

```ts
import { mount } from '@vue/test-utils'
import { describe, it, expect } from 'vitest'
import ConfirmDialog from '../ConfirmDialog.vue'

describe('ConfirmDialog', () => {
    it('renders nothing when open=false')
    it('renders dialog content when open=true')
    it('emits confirm when confirm button is clicked')
    it('renders destructive variant when destructive=true')
})
```

### 5. `resources/js/components/__tests__/CreatableCombobox.spec.ts` (5 Cases)

```ts
describe('CreatableCombobox', () => {
    it('shows existing options matching the query')
    it('shows a create-new option when query has no exact match')
    it('emits update:modelValue with the new value on create')
    it('stops ESC propagation when dropdown is open (prevents parent modal close)')
    it('shows "create again?" hint when query matches an already-created option')
})
```

ESC-Propagation: via `wrapper.trigger('keydown', { key: 'Escape' })` und Event-Spy auf Parent.

### 6. `resources/js/components/__tests__/InfoTooltip.spec.ts` (3 Cases)

```ts
describe('InfoTooltip', () => {
    it('opens tooltip on hover')
    it('toggles tooltip on click (mobile behavior)')
    it('closes tooltip when clicking outside')
})
```

### 7. `resources/js/plugins/__tests__/i18n.spec.ts` (3 Cases)

```ts
import { createI18n } from '../i18n'  // oder wie auch immer der Export heißt

describe('i18n', () => {
    it('defaults to german (de) when no localStorage entry exists')
    it('persists language change to localStorage')
    it('reactively updates components on language change')
})
```

Für den reaktiven Test: minimale Wrapper-Komponente mounten, `$t()` aufrufen, `i18n.global.locale.value = 'en'`, dann mit `nextTick()` neu rendern und prüfen.

### 8. Lokal verifizieren

```bash
docker exec eventplaner-vite-1 npm test
```

→ Alle 15 Cases grün.

```bash
docker exec eventplaner-vite-1 npm run test:coverage
```

→ Coverage-Report wird generiert (HTML in `coverage/`, Text in CLI).

## Datei-Liste

| Datei | Aktion |
|---|---|
| `package.json` | DevDeps + Scripts |
| `package-lock.json` | regen |
| `vitest.config.ts` | neu |
| `resources/js/components/__tests__/ConfirmDialog.spec.ts` | neu (4) |
| `resources/js/components/__tests__/CreatableCombobox.spec.ts` | neu (5) |
| `resources/js/components/__tests__/InfoTooltip.spec.ts` | neu (3) |
| `resources/js/plugins/__tests__/i18n.spec.ts` | neu (3) |

→ **4 Spec-Files, 15 Cases**

## Akzeptanzkriterien

- [ ] `npm test` läuft alle 15 Cases grün
- [ ] `npm run test:coverage` generiert HTML-Coverage-Report
- [ ] ESC-Propagation-Test in CreatableCombobox feuert nachweislich (bei `stopPropagation` missing → Test failt)
- [ ] i18n-Test prüft sowohl Default als auch Persistierung in localStorage
- [ ] Kein einziger `it.skip` / `test.skip` in den 4 Spec-Files
- [ ] Vitest läuft im Docker-Vite-Container, nicht lokal (lokales `npm run build` ist bekanntermaßen kaputt — siehe CLAUDE.md)

## Commit-Vorschlag

```
test: Vitest-Setup + Specs für kritische Komponenten (ConfirmDialog, CreatableCombobox, InfoTooltip, i18n)
```
