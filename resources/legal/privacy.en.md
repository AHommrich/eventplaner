---
updated_at: 2026-07-17T12:00:00Z
---

## Data Controller {#verantwortlich}

The party responsible for processing personal data in this app and on this website is:

André Hommrich
Dernbacher Str. 26
56410 Montabaur
Germany
Email: andre-hommrich@web.de

## What Data We Collect {#datenerhebung}

We process personal data only to provide the app's functionality: to operate a personal host account, to send system emails (verification, password reset, invitations), to manage guest lists and RSVPs, to collect photo uploads for the event, to provide the photo game and optional drink logs, and to display a slideshow during the celebration on a device chosen by the host.

Specifically, we process the following data categories:

- **Host account:** name, email address, hashed password, timestamps of registration and email verification, consent to the privacy policy.
- **Host device and session data:** user-selected device label, hashed one-time pairing code, expiry, redemption and last-use timestamps, and personal access tokens.
- **RSVP and guest data:** first name, last name, food preferences, RSVP status, group assignment and invitation status.
- **Photo gallery:** the image file, an optional description, the uploader and the assignment to the event. Metadata contained in the file (in particular GPS coordinates, device model and original timestamps) is stripped automatically on upload; only the plain image is stored.
- **Photo game:** task status, assigned task, submitted image file, optional description and uploader.
- **Drink log:** selected drink, quantity, timestamp and associated guest, if the host enables this feature and the guest uses it.
- **Reports and hides:** if you report a photo or hide another guest's content, we store the photo or guest ID, the timestamp, the reason and your optional message. The reported guest is never told who flagged them.
- **Camera and photo library:** the app accesses the camera or photo library only if you allow this on your device and actively want to take or select a photo. Without your device consent, no access takes place.
- **Optional push notifications for hosts:** Expo push token, device platform, last registration timestamp, and technical delivery tickets and receipts. Push messages contain only a generic notice and technical event/note IDs; note titles or contents, guest data, event name and sender name are never transmitted for display on the lock screen. Push can be disabled in the app at any time.
- **Technically required cookies:** session cookie for authentication and CSRF-protection cookie. These cookies are technically necessary and do not require separate consent.

We do not process any special categories of personal data as defined in Art. 9 GDPR. Communication with the app takes place exclusively over encrypted HTTPS. Passwords are stored hashed (never in plaintext). No personal data is written to application logs. We do not show advertising and do not use tracking, third-party analytics or marketing profiles. Fonts are bundled locally with the application; no fonts are loaded from a CDN or external font provider.

## Legal Basis {#rechtsgrundlage}

- **Art. 6 (1) (b) GDPR** — performance of a contract with hosts who register an account to organise their event.
- **Art. 6 (1) (a) GDPR** — consent from guests who join an event via QR code, allow camera or photo-library access and voluntarily contribute content (photos, RSVP, drink logs, photo-game submissions).
- **Art. 6 (1) (f) GDPR** — legitimate interest in a stable, secure operation of the platform (technical error logging, abuse protection).

## Retention Periods {#speicherdauer}

- Host accounts are stored for as long as the account is active. Deletion is possible at any time via "Delete account".
- Invitation tokens are removed {{retention.invitation_tokens_days}} days after the event date.
- Declined guests without app access are removed {{retention.declined_guests_days}} days after the event date.
- Personal access tokens for the host app expire after {{auth.management_token_ttl_days}} days; guest tokens expire after their separately defined validity window. Expired tokens are deleted daily. Unredeemed pairing records are removed no later than {{retention.device_pairings_hours}} hours after expiry.
- Expo push tokens are deleted when push is disabled in the app, the associated device session is revoked, the account is deleted, or Expo reports `DeviceNotRegistered`. Technical delivery tickets and receipts are retained only briefly for delivery verification and then removed automatically. Failed queue jobs containing technical user/note IDs are deleted after {{retention.failed_jobs_days}} days.
- Guests can submit an erasure request in the app. The record is marked for possible revocation for {{retention.guest_erasure_grace_days}} days and is then automatically deleted.
- Photo reports are removed {{retention.photo_reports_days}} days after the event date. Per-guest hides are cascaded automatically when the affected guest or photo is deleted.
- When an event is deleted, the associated photos are also removed from Object Storage.

## Your Rights {#rechte}

You have the right to:

- **Access** (Art. 15 GDPR) — You can exercise your rights under Art. 15 (access) and Art. 17 (erasure) directly in the app under Settings → "Export my data" or "Delete account".
- **Rectification** (Art. 16 GDPR) — editable directly in Settings.
- **Erasure** (Art. 17 GDPR) — via "Delete account" in Settings.
- **Restriction of processing** (Art. 18 GDPR).
- **Data portability** (Art. 20 GDPR) — covered by the JSON data export in the app.
- **Objection** to processing (Art. 21 GDPR).
- **Complaint** to a data-protection authority. The competent authority is the State Commissioner for Data Protection and Freedom of Information of Rhineland-Palatinate (datenschutz.rlp.de) or the data-protection authority of your place of residence.

## Third Parties {#drittanbieter}

We do not use advertising, tracking, third-party analytics or third-party cookies. For its core functionality, the app communicates exclusively with our own backend at eveplan.de. Fonts are bundled locally; no font request is made to Google Fonts, Adobe Fonts or a CDN.

To operate the service we use carefully selected data processors, each under an Art. 28 GDPR data-processing agreement or equivalent addendum. Processing takes place primarily within the European Union. The optional Google sign-in and optional push notifications for hosts may involve transfers to the United States:

- **Hetzner Online GmbH**, Industriestr. 25, 91710 Gunzenhausen, Germany — hosting of the application, the database and object storage for uploaded photos. Location: Nuremberg (Germany); processing exclusively within the EU. Order-processing agreement (Art. 28 GDPR) in place. EXIF, IPTC and XMP metadata are stripped server-side before storage.
- **Plus Five Five, Inc. (Resend)** — sending of system emails (verification, password reset, invitations). Processing in the EU (region Ireland, eu-west-1); data-processing agreement in place.
- **Functional Software, Inc. (Sentry)** — automated error monitoring for both the backend (unhandled exceptions and warning-level events) and the web client in the browser (JavaScript exceptions). Processing in the EU (region Frankfurt, `de.sentry.io`). By default **no** IP addresses, session cookies, authenticated-user context, or request headers are transmitted; no browser session replay is captured. Data-processing agreement in place upon account activation.
- **650 Industries, Inc. (Expo)** (USA) — **only for optional push notifications to host devices**. Expo receives the push token, device platform, generic notification text and technical event/note IDs, then relays the message to Apple (iOS) or Google (Android). No note text, guest data, event name or sender name is transmitted. The processor terms and EU Standard Contractual Clauses (module two) are incorporated into §3.2 of Expo's Terms of Service. Push can be disabled in the app at any time.
- **Google LLC** (USA) — **only** if you actively sign in with "Sign in with Google". Google then receives your Google ID, email and name (no further scopes). Data transfer to the USA is based on the EU Standard Contractual Clauses (SCCs).

## Contact {#kontakt}

For questions about data protection or to exercise your rights, contact us by email:

andre-hommrich@web.de

This privacy policy may be adapted when the application or legal requirements change. The current version is always available in the app and at eveplan.de/datenschutz. Material changes will be additionally communicated by email or in-app. The authoritative version of this policy is the German original at eveplan.de/datenschutz; this translation is provided for convenience and has not undergone separate legal review.
