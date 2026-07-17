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

    /*
     * Grace window in days between a guest requesting erasure (Art. 17) and
     * the hard delete. The guest can revoke during this window via a one-time
     * recovery token; after that `app:purge-expired-erasures` removes the row.
     */
    'guest_erasure_grace_days' => (int) env('RETENTION_GUEST_ERASURE_DAYS', 30),

    /*
     * Photo reports (App Store Guideline 1.2 moderation trail) are kept this
     * many days past the associated event date. `app:prune-photo-reports`
     * removes older rows; photo_hides and guest_content_hides cascade via
     * their guest/photo FKs and do not need a dedicated pruner.
     */
    'photo_reports_after_event_days' => (int) env('RETENTION_PHOTO_REPORTS_DAYS', 180),

    /*
     * Notes & todos (organizer task list). `app:prune-notes` hard-purges rows
     * this many days after they were soft-deleted, and purges an event's whole
     * note list this many days after the event date.
     */
    'notes_after_delete_days' => (int) env('RETENTION_NOTES_DAYS', 30),

    /* Resolved Expo ticket/receipt diagnostics contain no notification body. */
    'push_tickets_days' => (int) env('RETENTION_PUSH_TICKETS_DAYS', 7),

    /* Expired, unredeemed pairing challenges retain only a short audit grace. */
    'expired_device_pairings_hours' => (int) env('RETENTION_DEVICE_PAIRINGS_HOURS', 24),

    /* Failed async jobs can contain organizer user/note IDs in their payload. */
    'failed_jobs_days' => (int) env('RETENTION_FAILED_JOBS_DAYS', 7),
];
