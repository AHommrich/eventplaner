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

- **2026-07-13** — Split the monolithic `Event/Settings.vue` into three
  pages: slim **Event settings** (`/event/settings`: core data + feature
  toggles + projector token), **App → Design** (`/app/design`: cover,
  colors, font, style presets, phone preview), and **App → Schedule**
  (`/schedule`: venue editor + stations + per-group visibility). New
  sidebar "App" group. Venue editing (`event.venue_*`) moved to the
  schedule page via `PATCH /schedule/venue` — the main venue is
  conceptually the first station and stays the guest-app fallback.
  **Why:** the old page mixed core event data with everything shaping the
  guest app; the split gives the App section a clear home and keeps venue
  data with the schedule it belongs to. Feature toggles stayed on Event
  settings (not a third App page) to avoid IA sprawl. The legacy freetext
  `events.schedule` field is now unused by the UI (superseded by stations)
  and was dropped from the settings validation/payload.
