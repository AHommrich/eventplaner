# Stage 5 — Retention policy + scheduled cleanup

**Effort:** ~1 h
**Outcome:** Personal data with no remaining business purpose is removed automatically: invitation tokens for events whose date has passed (plus a buffer), declined-and-revoked guest entries kept beyond a reasonable window, expired Sanctum tokens. A weekly scheduled job runs the cleanup, and there's a dry-run flag for verification.
**Why this matters:** GDPR Art. 5 (1) (e) ("storage limitation") requires that personal data is "kept in a form which permits identification … for no longer than is necessary". Letting expired invitation tokens or rejected RSVPs pile up forever is technically a violation. The fix is small and we can keep the policy intentionally lenient — the goal is "no infinite retention", not "aggressive purging".

## Today

Current pile-ups when an event is finished:

- `invitation_tokens` — used to gate QR-login. Once the event has happened, they have no purpose anymore but stay in the DB and remain valid tokens.
- `personal_access_tokens` (Sanctum) — tokens issued to guests via QR-login. Laravel has `sanctum:prune-expired` but it isn't scheduled.
- `guest_drinks` / `guest_food_special` pivots — small, but tied to guest rows that should also age out.
- `guests` themselves where `app_access = false` AND `rsvp_status = 'declined'` AND the event is months in the past — no remaining purpose.

The owning **user account and event metadata stay** as long as the user is active. Deleting an event by hand still works and cascade-cleans everything beneath it (and triggers Stage 2's R2 cleanup).

## Retention windows (defaults)

Pick conservative defaults; document them in the privacy policy.

| Data | Window after event date | Reason for the window |
|---|---|---|
| `invitation_tokens` | 30 days | Lets a host correct a missing guest a few weeks after the wedding |
| Sanctum `personal_access_tokens` | use `expires_at` (already on the column) | Laravel ships pruning, just enable it |
| `guests` with `rsvp_status = 'declined'` AND `app_access = false` | 180 days | Host might still need the list for thank-you cards; 6 months feels right |
| `events` themselves | **kept indefinitely** unless the user deletes their account | An event is the user's own record of their party; not ours to delete unilaterally |

Everything tied to an active or recent event stays put. Numbers go into config so they're easy to tune.

## Steps

### 1. Config

`config/retention.php` — new, all values in days:

```php
return [
    'invitation_tokens_after_event_days' => 30,
    'declined_guests_after_event_days' => 180,
];
```

Both readable via `config('retention.invitation_tokens_after_event_days')` etc. Override per env via plain env vars if needed.

### 2. Command — invitation tokens

`app/Console/Commands/PruneInvitationTokens.php` — new:

```php
public function handle(): int
{
    $days = config('retention.invitation_tokens_after_event_days');
    $cutoff = now()->subDays($days);

    $query = InvitationToken::whereHas('group.event', fn ($q) => $q->where('date', '<', $cutoff))
        ->orWhereHas('guest.event', fn ($q) => $q->where('date', '<', $cutoff));

    $count = $query->count();

    if ($this->option('dry-run')) {
        $this->info("Would delete {$count} invitation tokens older than {$days} days.");
        return self::SUCCESS;
    }

    $query->delete();
    $this->info("Deleted {$count} invitation tokens.");
    return self::SUCCESS;
}
```

`--dry-run` flag mandatory for the first production run.

### 3. Command — declined guests

`app/Console/Commands/PruneDeclinedGuests.php` — new. Same shape, queries `guests` where the linked event's date is older than the configured cutoff, RSVP is "declined", and `app_access` is false. Pivots cascade away with the guest row.

### 4. Sanctum pruning

Already shipped with Laravel — just schedule it:

```php
$schedule->command('sanctum:prune-expired --hours=24')->daily();
```

### 5. Schedule the new commands

`bootstrap/app.php`:

```php
$schedule->command('app:prune-invitation-tokens')->weekly();
$schedule->command('app:prune-declined-guests')->weekly();
$schedule->command('sanctum:prune-expired --hours=24')->daily();
$schedule->command('photos:cleanup-orphans')->weekly();  // from Stage 2
```

Pick a quiet time (`->sundays()->at('03:00')`).

### 6. Privacy-policy footnote

Stage 1's `Privacy.vue` already has a "Speicherdauer" section. After Stage 5 ships, fill in the actual numbers from `config/retention.php` (or just quote the windows from the table above — concrete numbers are better than "as long as legally required" boilerplate).

### 7. Tests

`tests/Feature/Retention/PruneInvitationTokensTest.php` — new:

- Create two events: one dated 60 days ago, one dated yesterday
- Create one invitation token per event
- Run `app:prune-invitation-tokens` (no flag) → assert the old token is gone, the recent one survives
- Run `--dry-run` → assert nothing is deleted but the count is reported

`tests/Feature/Retention/PruneDeclinedGuestsTest.php` — analogous.

`tests/Feature/Retention/SanctumPruneTest.php` — light smoke test that the command exists in the schedule (`$schedule->command('sanctum:prune-expired')`) — schedule list assertion via `Artisan::call('schedule:list')`.

## File list

| File | Action |
|---|---|
| `config/retention.php` | new |
| `app/Console/Commands/PruneInvitationTokens.php` | new |
| `app/Console/Commands/PruneDeclinedGuests.php` | new |
| `bootstrap/app.php` | schedule 4 commands |
| `tests/Feature/Retention/*.php` | 3 new test files |
| `resources/js/pages/Legal/Privacy.vue` | update Speicherdauer section with numbers |

## Acceptance criteria

- [ ] `php artisan schedule:list` shows: prune-invitation-tokens (weekly), prune-declined-guests (weekly), sanctum:prune-expired (daily), photos:cleanup-orphans (weekly)
- [ ] `php artisan app:prune-invitation-tokens --dry-run` reports counts without modifying anything
- [ ] Without the flag, only tokens beyond the configured window vanish
- [ ] Declined-guest pruning respects the 180-day default and skips events from the last six months
- [ ] All retention tests pass
- [ ] Privacy policy "Speicherdauer" section lists the configured windows numerically

## Commit suggestion

```
feat(retention): scheduled cleanup of invitation tokens, declined guests, sanctum tokens
```
