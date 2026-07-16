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

- **2026-07-16** — Keep the three App Designer colour worlds code-backed and
  global, separate from event-owned saved style presets.
  **Why:** every event should have the same reliable starting points, while
  saved styles remain private, editable snapshots of one event's full design.

- **2026-07-16** — Contrast remediation in the App Designer must remain an
  explicit, reversible user action; it must never mutate a palette colour on
  detection alone.
  **Why:** one of three global palette colours can feed several roles, so an
  automatic change can silently alter intentional parts of an event design.

- **2026-07-16** — Backfill a legacy saved style's missing navbar role from
  its card-background role, while retaining every explicitly saved role.
  **Why:** old presets predate `role_nav_bg`; deriving only that absent value
  restores the intended surface hierarchy without destroying deliberate
  inversions such as light text on a dark card.

- **2026-07-16** — Contrast remediation offers an explicit brightness-only
  candidate for a user-selected palette slot, rather than changing roles or
  colours at detection time.
  **Why:** palette colours are shared by several surfaces; preserving hue and
  saturation keeps the event's intended colour character and makes every
  affected role visible before the user confirms the local preview change.

- **2026-07-16** — Keep the ten colour roles as the existing data contract,
  but present them in five semantic editor groups with per-group reset.
  **Why:** grouping makes the intent legible without reducing flexibility;
  reset remains an explicit action so intentional inversions are never lost.

- **2026-07-16** — Provide one local undo slot for confirmed multi-field
  Designer actions, while preserving the existing save/discard boundary.
  **Why:** colour worlds, preset loads, contrast corrections and group resets
  otherwise change multiple preview fields at once; one-step undo is clear and
  reversible without persisting or complicating the form's dirty state.

- **2026-07-16** — Derive role-group recommendations from palette luminance
  and contrast rather than from fixed palette slot names.
  **Why:** a custom secondary colour may be dark or saturated; assigning it to
  the app background solely by slot produces unusable, visually heavy designs.
