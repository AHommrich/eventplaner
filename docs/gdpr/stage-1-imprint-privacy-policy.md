# Stage 1 — Imprint + Privacy Policy + footer links

**Effort:** ~2 h (plus a legal-review pass on the German copy)
**Outcome:** Public `/impressum` and `/datenschutz` pages, both linked from the welcome page footer and from inside the authenticated app. Signup requires an explicit "I have read the privacy policy" checkbox so consent is recorded per user.
**Why this matters:** Without these two pages the app is **legally non-shippable in Germany** (§5 DDG for the imprint, GDPR Art. 13 for the privacy policy). Both must be reachable in two clicks from anywhere in the app — that's why we also add the footer links here, not in a separate UI stage.

## End-user content stays German

The two pages address German-speaking end users (wedding hosts and their guests) and must — per GDPR transparency — be written in the data subject's own language. So the Vue component text is **German**, even though the surrounding code, route names that face developers, and this plan are English.

## Steps

### 1. Routes

`routes/web.php` — add two public routes, no middleware:

```php
Route::get('/impressum', [LegalController::class, 'imprint'])->name('legal.imprint');
Route::get('/datenschutz', [LegalController::class, 'privacy'])->name('legal.privacy');
```

### 2. Controller

`app/Http/Controllers/LegalController.php` — thin Inertia render, no DB work, no auth:

```php
public function imprint(): Response
{
    return Inertia::render('Legal/Imprint');
}

public function privacy(): Response
{
    return Inertia::render('Legal/Privacy');
}
```

### 3. Vue pages (German content)

`resources/js/pages/Legal/Imprint.vue` — single-column long-form text. Required fields per §5 DDG:

- Name + address of the responsible person (André Hommrich, postal address)
- Contact email + phone (or a reachable contact form)
- Optional: VAT ID if you have one, professional regulatory body (not applicable here)
- Optional: editorial responsibility per §18 MStV (only if you publish content)

`resources/js/pages/Legal/Privacy.vue` — sections in this order (GDPR Art. 13 checklist):

1. **Verantwortliche Stelle** (responsible entity) — same as imprint
2. **Zwecke der Datenverarbeitung** — running the event-planning app, sending mails, hosting uploaded photos
3. **Welche Daten werden verarbeitet** — list per data category:
   - User account: name, email, password hash, role
   - Guest entries: firstname, lastname, food preferences, RSVP, drink logs
   - Uploaded photos: image file + optional description + the uploading guest's id
   - Session cookies (essential only)
4. **Rechtsgrundlage** — mostly Art. 6 (1) (b) (contract performance) for the event-owner relationship and Art. 6 (1) (a) (consent) for invited guests when they upload content
5. **Empfänger / Sub-Processors** — list pulled from [Stage 6](stage-6-subprocessor-documentation.md): Hetzner (hosting), Cloudflare R2 (photo storage), Resend (email), Google (only for users who chose "Sign in with Google")
6. **Speicherdauer** — accounts kept while the user is active, expired invitation tokens cleaned up automatically (see [Stage 5](stage-5-retention-policy.md)), photos deleted with the event
7. **Rechte der Betroffenen** — list of GDPR rights: access (Art. 15, see [Stage 3](stage-3-data-export.md)), rectification (Art. 16), erasure (Art. 17 — already implemented via account-delete + [Stage 2](stage-2-data-deletion-r2-cleanup.md)), restriction, objection, data portability (covered by Stage 3), complaint to a supervisory authority
8. **Sicherheit** — HTTPS forced, hashed passwords, no PII logging, security headers (see [Stage 4](stage-4-security-headers.md))
9. **Änderungen dieser Datenschutzerklärung** — boilerplate paragraph saying it may be updated

### 4. Footer links

Two places need footer links:

- `resources/js/pages/Welcome.vue` — add a small footer block above or below the existing landing content with: `Impressum` / `Datenschutz` / `MIT-Lizenz`. Same text in German for both legal links (audience), the license link can stay as it is.
- `resources/js/layouts/AppSidebarLayout.vue` (or wherever the global app chrome lives) — same two links, probably at the very bottom of the sidebar, small text-xs muted-foreground. The user is allowed to navigate to legal pages even mid-session.

### 5. Signup consent

`resources/js/pages/auth/Register.vue` — add a required checkbox below the existing fields:

```html
<label class="flex items-start gap-2 text-sm">
    <input type="checkbox" v-model="form.privacy_accepted" required />
    <span>
        Ich habe die <a :href="route('legal.privacy')" target="_blank" class="underline">Datenschutzerklärung</a>
        gelesen und stimme der Verarbeitung meiner Daten zur Nutzung der App zu.
    </span>
</label>
```

The backend (`RegisteredUserController` or wherever the signup happens) validates `'privacy_accepted' => 'accepted'`. If you want a paper trail, add a `privacy_accepted_at` timestamp column to `users` and set it on registration — that's the cheapest way to prove consent later.

### 6. Locale handling

i18n strings on the legal pages are not needed — the pages are German-only by design. But the link labels in the footer (`Impressum`, `Datenschutz`) should be German on the German UI and stay German on the English UI too (legal labels do not translate), so put them as **hardcoded German** in the template, not in the `de.json` / `en.json` files.

## File list

| File | Action |
|---|---|
| `routes/web.php` | add 2 routes |
| `app/Http/Controllers/LegalController.php` | new |
| `resources/js/pages/Legal/Imprint.vue` | new (German content) |
| `resources/js/pages/Legal/Privacy.vue` | new (German content) |
| `resources/js/pages/Welcome.vue` | footer links |
| `resources/js/layouts/AppSidebarLayout.vue` | sidebar bottom links |
| `resources/js/pages/auth/Register.vue` | privacy-accepted checkbox |
| `app/Http/Controllers/Auth/RegisteredUserController.php` | validation + timestamp |
| `database/migrations/2026_07_xx_add_privacy_accepted_at_to_users.php` | new (optional but recommended) |

## Acceptance criteria

- [ ] `GET /impressum` returns 200 without auth, shows all §5-DDG fields filled in
- [ ] `GET /datenschutz` returns 200 without auth, covers all nine GDPR-Art.-13 sections above
- [ ] Both pages reachable from the welcome page footer **and** from the authenticated app chrome
- [ ] Signup form has a required privacy-consent checkbox, server-side validated
- [ ] (Optional) `users.privacy_accepted_at` is set on first registration
- [ ] No third-party widget loaded (no iubenda, no cookiebot) — pure self-hosted markdown/Vue
- [ ] Hetzner IP, exact home address, mail-config-secrets etc. are *not* leaked into version-controlled content; everything goes through Vue components with editable copy

## Commit suggestion

```
feat(legal): imprint + privacy policy pages with signup consent
```
