# Decisions Log

Append-only. One dated entry per non-trivial decision or hard-won
insight — the kind of thing that would otherwise only live in a chat
context window and get lost when a session ends or compacts.

Format: date, one-line decision, one-line why. Keep it short — this is
a log, not a design doc. Link to `docs/ARCHITECTURE.md` or a commit if
more detail is needed.

Any agent (Claude Code, Codex, OpenCode, human) should add an entry
here before ending a session that produced a non-obvious architectural
choice, a rejected alternative worth remembering, or a constraint that
took real effort to discover.

---

- **2026-07-12** — Added `.githooks/pre-push` + this log instead of
  relying on `CLAUDE.md` alone as the safety mechanism.
  **Why:** `CLAUDE.md` is only read by Claude Code; other tools (Codex,
  OpenCode) ignore it, and even for Claude Code a doc file is advisory,
  not enforced. A git hook is enforced by Git itself regardless of
  which tool or human is driving. See `docs/SAFETY_LEVER.md`.
