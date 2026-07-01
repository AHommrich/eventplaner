# Stage 3 — Data-export endpoint (right of access)

**Effort:** ~1.5 h
**Outcome:** A logged-in user can hit `Settings → Privacy → Export my data` and download a single JSON file containing **everything the system holds about them**. The same machinery satisfies GDPR Art. 15 (right of access) and Art. 20 (right to data portability).
**Why this matters:** Today a user can *view* their data in the UI but cannot *take it with them*. When a supervisory authority (or the user) asks for a data export, the only option is a manual DB dump — slow, error-prone, and embarrassing. A self-service endpoint fixes both.

## Scope

A user owns:

- Their own `User` row (name, email, role, timestamps — **no** password hash)
- All `Event` rows they own, including settings (palette, schedule, etc.)
- All `Guest` rows belonging to those events (firstname, lastname, RSVP, food/drink prefs)
- All `Photo` records (filename, description, public URL, upload metadata) — **metadata only**, the binary stays linked via URL
- All `DrinkLog` rows for those events
- All `PhotoGameAssignment` rows
- All `InvitationToken` rows still active
- All `EventTaskOverride`, `EventStylePreset`, etc. — anything keyed by their event

Sub-resources owned by the **guest** (uploaded photos, drink logs) are surfaced under the owning event, not separately — guests authenticate via the QR-token system, not via the user-account flow, so for the v1 endpoint we keep "data subject = the logged-in user (owner)". A guest data-export request can be handled manually until enough guests exist to justify a separate flow.

## Steps

### 1. Service object

`app/Services/UserDataExporter.php` — pure service, no HTTP coupling. Single method `export(User $user): array` that returns a nested associative array. Keep it stable — once an exporter shape is published, you cannot rename keys without notice.

```php
public function export(User $user): array
{
    return [
        'exported_at' => now()->toIso8601String(),
        'export_format_version' => 1,
        'user' => $user->only(['id', 'name', 'email', 'role', 'email_verified_at', 'privacy_accepted_at', 'created_at']),
        'events' => $user->events()->with([
            'guests.foodSpecials',
            'guests.drinks',
            'guests.photos',
            'photos',
            'photoGame.assignments.task',
            'invitationTokens',
        ])->get()->map(fn (Event $event) => $this->serializeEvent($event))->all(),
    ];
}
```

Each `serializeEvent()` returns a stripped, denormalized array — no FK ids that lead nowhere, no internal flags. Photos include `url` so the user can `wget` their album, but the binary itself is not embedded (would balloon the JSON to gigabytes).

### 2. Controller

`app/Http/Controllers/Settings/DataExportController.php` — new:

```php
public function download(Request $request, UserDataExporter $exporter): StreamedResponse
{
    $user = $request->user();
    $payload = $exporter->export($user);

    $filename = sprintf('eveplan-export-%s-%s.json', $user->id, now()->format('Y-m-d'));

    return response()->streamDownload(
        fn () => print(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
        $filename,
        ['Content-Type' => 'application/json'],
    );
}
```

`streamDownload` avoids holding the whole payload in memory if a user has thousands of photos.

### 3. Route

`routes/web.php` — gated by `auth`, lives under the existing settings prefix:

```php
Route::middleware('auth')->group(function () {
    Route::get('/settings/export-data', [DataExportController::class, 'download'])
        ->name('settings.export-data');
});
```

No POST + CSRF needed — it's an idempotent download triggered by a normal anchor tag.

### 4. UI surface

`resources/js/pages/settings/Profile.vue` (or a new `settings/Privacy.vue` if the existing page is crowded) — add a "Privacy" block above the delete-account block:

```html
<section class="space-y-2">
    <h3 class="text-base font-medium">{{ t('settings.privacy.title') }}</h3>
    <p class="text-sm text-muted-foreground">{{ t('settings.privacy.description') }}</p>
    <Button as="a" :href="route('settings.export-data')" variant="outline">
        {{ t('settings.privacy.exportData') }}
    </Button>
</section>
```

Locale strings:

- `de.json` — `settings.privacy.title: "Datenschutz"`, `description: "Lade deine in der App gespeicherten Daten als JSON-Datei herunter."`, `exportData: "Meine Daten exportieren"`
- `en.json` — `title: "Privacy"`, `description: "Download everything we store about you as a JSON file."`, `exportData: "Export my data"`

### 5. Cross-link from the privacy policy

Stage 1 already plans a sentence pointing at this endpoint under "Rechte der Betroffenen" → "Recht auf Auskunft (Art. 15)". After Stage 3 ships, update `Privacy.vue` to make that sentence a working link.

### 6. Rate limiting (light)

The endpoint is expensive in I/O if a user has many events. Throttle to something gentle:

```php
->middleware(['auth', 'throttle:6,1'])
```

6 calls per minute is plenty for legitimate use and bounds abuse.

### 7. Tests

`tests/Feature/Settings/DataExportTest.php` — new:

- A user with one event, three guests, two photos can hit the route and the response JSON contains exactly those records
- A second unrelated user's data is **not** in the response (isolation check — most important test)
- Password hash, remember token, and any internal flags are absent from the payload
- Unauthenticated request → 302 to login
- Throttling kicks in on the 7th request inside one minute

## File list

| File | Action |
|---|---|
| `app/Services/UserDataExporter.php` | new |
| `app/Http/Controllers/Settings/DataExportController.php` | new |
| `routes/web.php` | add route |
| `resources/js/pages/settings/Profile.vue` (or `Privacy.vue`) | UI block |
| `resources/js/locales/de.json` + `en.json` | 3 keys each |
| `tests/Feature/Settings/DataExportTest.php` | new |

## Acceptance criteria

- [ ] Logged-in user hits the link, downloads a JSON file named `eveplan-export-{id}-{date}.json`
- [ ] JSON includes user + owned events + nested guests + photos + drink logs + invitation tokens
- [ ] JSON does **not** include password hash, remember token, sessions, or other users' data
- [ ] JSON is valid (`jq . exported.json` succeeds)
- [ ] Authenticated only — anonymous request redirects to login
- [ ] Throttled — 7th request in a minute returns 429
- [ ] Privacy policy (Stage 1) links to this endpoint under "Recht auf Auskunft"
- [ ] All four feature tests pass

## Commit suggestion

```
feat(settings): JSON data-export endpoint for GDPR right of access
```
