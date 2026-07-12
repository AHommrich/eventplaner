# Safety Lever

Why this exists: multiple AI coding tools (Claude Code, Codex,
OpenCode, ...) work on this repo. A rule written only in `CLAUDE.md` is
advisory — it's read by Claude Code, ignored by everything else, and
even Claude Code can misjudge a situation. This doc collects the parts
of the safety net that are actually *enforced* (by Git or by GitHub),
not just documented, plus the parts that still need a human to click a
button once.

## 1. Git hook — enforced today, works for every tool

`.githooks/pre-push` blocks, regardless of which tool or human runs
`git push`:

- force-push / history rewrite on `develop`, `staging`, `production`
- deleting any of those three branches via push
- a silent push straight to `production` — it requires typing
  `PRODUCTION` at an interactive prompt, which only works if a human
  is at the terminal

It does **not** interfere with the documented promotion workflow
(`git checkout staging && git merge develop && git push origin
staging`), because that's a fast-forward-able merge, not a rewrite.

**One-time setup per clone:**

```bash
git config core.hooksPath .githooks
```

This is a local git config, not something Git syncs automatically —
every clone (and every sandbox/VM/container an AI tool spins up) needs
to run it once. Consider it part of "is this checkout ready to push
from" alongside `cp .env.example .env`.

**Deliberate bypass** (for the rare legitimate case, e.g. an approved
recovery from a `backup-*` branch):

```bash
git -c core.hooksPath=/dev/null push ...
```

This requires actively typing the bypass — it cannot happen by
accident.

## 2. Decisions log — enforced by convention, survives session limits

`docs/DECISIONS.md`. Plain, git-tracked markdown. Any tool or human
appends a dated entry before ending a session that produced a
non-obvious decision. Unlike a chat context window, this survives
compaction, session limits, and tool switches — it's just a file in
the repo.

## 3. GitHub branch protection — stronger, but manual (not done yet)

The git hook only protects pushes that originate from a machine that
has it enabled. The strongest version of this lever is server-side, on
GitHub, and applies no matter what tool or machine is pushing. Not set
up yet — needs either the `gh` CLI (not installed on this machine) or
five minutes in the GitHub UI. Settings to apply per protected branch
(`staging`, `production`):

1. GitHub → repo → **Settings → Branches → Add branch ruleset** (or
   classic "Add rule" under Branch protection rules)
2. Branch name pattern: `staging` (repeat for `production`)
3. Enable:
   - **Require a pull request before merging** — with the caveat from
     `docs/CONTRIBUTING.md`: the documented promotion is a local merge
     + push, not a PR. If this is turned on, the promotion workflow
     needs to change to "open a PR from `develop` into `staging`, merge
     via UI" — decide this deliberately, don't turn it on silently.
   - **Do not allow force pushes**
   - **Do not allow deletions**
4. Do **not** require status checks to pass before the *hook* exists to
   guarantee the promotion path still works — check this after a real
   promotion once it's configured, on `staging` first, before doing the
   same to `production`.

This is intentionally left as a manual step: it changes a shared,
GitHub-visible setting and (per point 3) may require changing the
documented workflow, so it deserves a deliberate decision rather than
being flipped on as a side effect of an unrelated task.

## 4. Rollout to `ahommrichnuxt` / `eventplaner-app`

Same three building blocks, not yet applied there:

- `.githooks/pre-push` — same script, swap the protected-branch list
  for that repo's actual branches (both of those repos deploy off a
  single `main`, not a three-stage flow, so the branch list and the
  "confirm before push" branch need to be adjusted, not copy-pasted
  verbatim).
- `docs/DECISIONS.md` — identical pattern.
- `AGENTS.md` — `eventplaner-app` already has one (currently a
  portfolio-cleanup backlog, unrelated content) — add a new section
  there rather than replacing it. `ahommrichnuxt` has none yet.

Do this as a separate, explicit pass per repo — each has its own stack
and its own `CLAUDE.md` that should be read first, not assumed to
match this one.

## What this does not cover

- **VPS/DB backups** — tracked separately, not part of this lever (see
  the maintainer's project notes for status).
- **Secrets rotation, CI failures, dependency vulnerabilities** — CI
  already runs lint/test/typecheck/audit on every push; this doc is
  specifically about destructive git operations and silent deploys.
