import css from '@eslint/css';
import js from '@eslint/js';
import markdown from '@eslint/markdown';
import pluginVue from 'eslint-plugin-vue';
import { defineConfig } from 'eslint/config';
import globals from 'globals';
import tseslint from 'typescript-eslint';

export default defineConfig([
    {
        ignores: ['vendor/**', 'node_modules/**', 'public/**', 'coverage/**', 'bootstrap/ssr/**'],
    },
    { files: ['**/*.{js,mjs,cjs,ts,mts,cts,vue}'], plugins: { js }, extends: ['js/recommended'] },
    { files: ['**/*.{js,mjs,cjs,ts,mts,cts,vue}'], languageOptions: { globals: globals.browser } },
    tseslint.configs.recommended,
    // eslint-plugin-vue v9 ships rule-only configs without a `files` filter,
    // which makes vue/* rules try to parse non-Vue files (e.g. CLAUDE.md) and crash.
    // Scope every entry that carries rules to *.vue.
    ...pluginVue.configs['flat/essential'].map((c) =>
        c.rules && !c.files ? { ...c, files: ['**/*.vue'] } : c,
    ),
    { files: ['**/*.vue'], languageOptions: { parserOptions: { parser: tseslint.parser } } },
    {
        files: ['**/*.md'],
        plugins: { markdown },
        language: 'markdown/commonmark',
        extends: ['markdown/recommended'],
        rules: {
            // Doc files contain code blocks copied from GitHub/Slack notification emails
            // and inline command-line snippets that would all need language tags otherwise.
            'markdown/fenced-code-language': 'off',
            // False positives on tables that use `[x]`-style markers.
            'markdown/no-missing-label-refs': 'off',
        },
    },
    {
        // Tailwind 4 / `tw-animate-css` introduce at-rules (`@custom-variant`, `@source`,
        // `@theme`, `@apply`) that the @eslint/css parser does not understand, so we keep
        // CSS lint off the Tailwind entry file.
        files: ['**/*.css'],
        ignores: ['resources/css/**'],
        plugins: { css },
        language: 'css/css',
        extends: ['css/recommended'],
    },
    {
        languageOptions: {
            globals: {
                route: 'readonly',
            },
        },
        rules: {
            'vue/multi-word-component-names': 'off',
            '@typescript-eslint/no-explicit-any': 'off',
            '@typescript-eslint/no-unused-vars': [
                'error',
                {
                    argsIgnorePattern: '^_',
                    varsIgnorePattern: '^_',
                    destructuredArrayIgnorePattern: '^_',
                },
            ],
        },
    },
]);
