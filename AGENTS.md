# AGENTS.md

This file exists so that AI coding tools which don't read `CLAUDE.md`
(Codex, OpenCode, etc.) still get the load-bearing rules. `CLAUDE.md`
is the source of truth for project conventions, data model, and
feature docs — read it in full before making changes.

The rules below are the ones that must hold regardless of which tool
or model is driving:

## Hard rules

- **Branch flow is one direction only:** `feature-branch → develop →
  staging → production`. Promotions to `staging`/`production` are
  local `--no-ff` merges + push, never a GitHub UI merge (see
  `docs/CONTRIBUTING.md` "Branch model" for why).
- **Never force-push, rewrite history, or delete `develop`/`staging`/
  `production`.** `.githooks/pre-push` blocks this technically once
  enabled (`git config core.hooksPath .githooks` — do this once per
  clone if it isn't already set).
- **Never deploy or redeploy without being explicitly asked.** Coolify
  redeploys automatically on push — a push to `staging`/`production`
  *is* a deploy. Never redeploy `staging` and `production` in the same
  window (parallel Coolify redeploys OOM-kill the VPS).
- **Never commit or push on behalf of the user** unless explicitly
  asked in that turn. Hand back a commit message instead.
- **Log non-trivial decisions in `docs/DECISIONS.md`** before ending a
  session — this is what survives context/session limits; the diff
  alone does not carry the *why*.
- **GDPR/infrastructure changes** follow the checklist in `CLAUDE.md`
  §"Governance-Regel" — touching a sub-processor or storage provider
  without updating the docs listed there is a compliance gap, not just
  a doc gap.

## See also

- `docs/SAFETY_LEVER.md` — full explanation of the hook + the manual
  GitHub branch-protection checklist
- `docs/DECISIONS.md` — decisions log
- `docs/CONTRIBUTING.md` — branch model, commit convention, PR flow
- `docs/GETTING_STARTED.md` — local setup
