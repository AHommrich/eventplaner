<?php

/*
 * Retention windows in days.
 *
 * Personal data must not be kept "longer than necessary" (GDPR Art. 5 (1) (e)).
 * We pick conservative defaults — tweakable per env via plain env vars — and
 * the privacy policy quotes these numbers directly.
 */

return [
    /*
     * Invitation tokens (group + solo) belonging to events whose date is more
     * than this many days in the past will be purged by the scheduled
     * `app:prune-invitation-tokens` command.
     */
    'invitation_tokens_after_event_days' => (int) env('RETENTION_INVITATION_TOKENS_DAYS', 30),

    /*
     * Declined guests without app/drinks access are removed this many days
     * after their event date by `app:prune-declined-guests`.
     */
    'declined_guests_after_event_days' => (int) env('RETENTION_DECLINED_GUESTS_DAYS', 180),
];
