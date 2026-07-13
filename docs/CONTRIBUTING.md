# Contributing

Ground rules for changes to this repository. Keep the diff surface
small, keep CI green, and keep the docs in sync with the code — that's
the whole workflow.

For local setup see `docs/GETTING_STARTED.md`.
For deploy mechanics see `README.md` "Deploy workflow".

---

## Branch model

Three long-lived branches, promoted in one direction only:

```
feature-branch → develop → staging → production
```

- Feature work happens on short-lived branches off `develop`, opened as
  a PR against `develop`.
- Promotions to `staging` and `production` are local `--no-ff` merges
  followed by a push. Snippets are in `README.md` "Deploy workflow".
- **Never merge via the GitHub UI** on `staging` or `production` — the
  UI has no direction guard, and PR #4 (2026-07-07) already once
  overwrote `develop`'s dev compose with the prod version that way.
- **Never redeploy staging and production in parallel.** Parallel Coolify redeploys exhaust the VPS RAM and have caused outages.
- **Force-push and branch deletion on `develop`/`staging`/`production` are
  blocked by `.githooks/pre-push`** (enable once via `git config
  core.hooksPath .githooks`, see `docs/SAFETY_LEVER.md`). It doesn't
  interfere with the promotion merges above — only with history rewrites.

## Commit convention

- **Language: English, imperative mood.** "Add restore drill", not
  "Added" or "Adds". Conversation with the maintainer may be German,
  but commit messages, code, docs, and PR descriptions are English.
- **Scope prefix optional but common.** Examples from the log:
  `docs(runbook)`, `refactor(settings)`, `mail(queue)`, `ops(deploy)`,
  `security(auth)`.
- **Body allowed.** Explain the *why*, not the *what* the diff already
  shows. Reference the file/section that motivates the change if it
  helps a future reader (audit item number, follow-up step number).
- **One logical change per commit.** Refactors that mix a rename with a
  behaviour change hide bugs.

## Tests

Every PR keeps these green:

| Command                        | Runs                             |
| ------------------------------ | -------------------------------- |
| `docker exec laravel-app composer test` | Backend (Pest)          |
| `npm test`                     | Frontend (Vitest)                |
| `npm run typecheck`            | `vue-tsc --noEmit`               |
| `npx playwright test`          | E2E (Playwright)                 |

Tests must run against the `laravel_test` database only. `tests/TestCase.php`
enforces this with a runtime guard — do not disable it.

CI additionally runs `composer audit` and `npm audit` (currently
non-blocking) and, on PRs, `actions/dependency-review-action` (fails on
new high-severity advisories). See `.github/workflows/`.

## Pull requests

- Use the template in `.github/PULL_REQUEST_TEMPLATE.md`. The
  "GDPR / privacy touched?" section is mandatory when your change
  touches sub-processors, data collection, or retention.
- Small PRs merge faster. If a change grew past ~400 lines, consider
  splitting it into two.
- Link the audit item, follow-up step, or memory entry that motivated
  the change, if applicable.

## Documentation sync rule

This one is easy to miss and it matters. From `CLAUDE.md` §"Governance-Regel":

> Any sub-processor or infrastructure change updates
> `docs/legal/sub-processors.md`, `resources/js/pages/Legal/Privacy.vue`,
> the README files, and `CLAUDE.md` in the same PR.

The full checklist lives in `CLAUDE.md`. Before opening the PR:

```bash
grep -rln "Cloudflare\|R2\|hommrich.app" \
  --exclude-dir=node_modules --exclude-dir=vendor --exclude-dir=.git .
```

Anything that surfaces outside historical migration docs
(`docs/gdpr/*`, `docs/showcase/*`, `NEXT_SESSION.md`) needs to be
updated in the same PR.

## Security

- Never commit `.env`, DPA PDFs, or exported customer data. `.dockerignore`
  and `.gitignore` cover the standard traps but final responsibility is
  on the author.
- Vulnerabilities: private disclosure per `SECURITY.md`, not a public
  issue.
- Secret rotations: document every rotation in the maintainer's private operations log.

## Maintainer contact

For anything that does not fit an issue or a PR: see the email in
`SECURITY.md`.
