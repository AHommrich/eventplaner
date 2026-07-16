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

- **2026-07-16** — (P0.5) Change the `events.user_id` FK from `ON DELETE
  CASCADE` to `RESTRICT`, and split user-deletion behaviour by actor: an admin
  deleting *another* user is refused while that user owns an event
  (`Admin/UserController::destroy`); a user deleting *their own* account has
  their owned events removed explicitly first
  (`Settings/ProfileController::destroy`).
  **Why:** the cascade let an admin (or, once co-ownership lands, any deletion)
  silently wipe a whole event — guests, photos, drink logs — and would strip
  equal co-owners of it. RESTRICT makes accidental destruction impossible.
  Self-service deletion is the deliberate opposite (GDPR Art. 17 erasure of
  one's own data), so it must still succeed and therefore removes owned events
  on purpose. Also guards against deleting the last superadmin. First slice of
  the P0 multi-tenancy hardening (see `docs/EVENT_MANAGER_ROLE_PLAN.md`).

- **2026-07-17** — (P0.1) Make `food_specials` a per-event delta catalog
  (nullable `event_id`: null = global read-only seeded template auto-visible to
  every event, non-null = event-local custom entry; reads = templates ∪
  event-local; writes always `event_id = activeEvent()`; FK
  `cascadeOnDelete`). **Why:** the catalog was global, so one event's writes /
  reads leaked across every event. The delta model (mirrors
  `PhotoGameTaskCatalog`) isolates custom entries while keeping the seeded i18n
  templates shared, and solves "new events have no defaults" for free. Cascade
  (not nullOnDelete) so deleting an event drops its private rows instead of
  nulling them into global templates — that would leak private free-text. See
  `docs/EVENT_MANAGER_ROLE_PLAN.md` §11 P0.1 / §13.5 #1.

- **2026-07-17** — (P0.1) Drop the `categories` table and its
  model/controller/route/prop entirely instead of scoping it per event.
  **Why:** the plan assumed `guests.category_id` still existed, but that column
  was dropped in an earlier refactor (`4ff6b72`). What remained was dead code —
  no read path, an unread Inertia prop, and an unscoped `categories.store`
  route reachable by any member. Removing it eliminates the cross-event write
  hole outright rather than scoping a feature nobody uses (André, 2026-07-17).

- **2026-07-17** — (P0.3) Scope `GuestController` store/update reference
  validation to the active event: `group_id` strict own-event `exists`;
  `food_specials.*` accepts own-event rows OR global templates
  (`event_id IS NULL`), rejecting other events' private rows.
  **Why:** `exists:groups,id` / `exists:food_specials,id` accepted a foreign
  event's row — a cross-event write hole route-gating does not catch. Depends
  on the P0.1 `event_id` column. See `docs/EVENT_MANAGER_ROLE_PLAN.md` §11 P0.3.
