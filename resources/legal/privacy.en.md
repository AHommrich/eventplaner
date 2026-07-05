---
updated_at: 2026-07-05T00:00:00Z
---

## Data Controller {#verantwortlich}

The party responsible for processing personal data in this app and on this website is:

André Hommrich
Dernbacher Str. 26
56410 Montabaur
Germany
Email: andre-hommrich@web.de

## What Data We Collect {#datenerhebung}

We process personal data only to provide the app's functionality: to operate a personal host account, to send system emails (verification, password reset, invitations), to manage guest lists and RSVPs, to collect photo uploads for the event, and to display a slideshow during the celebration on a device chosen by the host.

Specifically, we process the following data categories:

- **Host account:** name, email address, hashed password, timestamps of registration and email verification, consent to the privacy policy.
- **Guest data:** first name, last name, food preferences, RSVP status, optional drink logs and photo-game submissions.
- **Photo uploads:** the image file, an optional description, the uploader and the assignment to the event. Metadata contained in the file (in particular GPS coordinates, device model and original timestamps) is stripped automatically on upload; only the plain image is stored.
- **Technically required cookies:** session cookie for authentication and CSRF-protection cookie. These cookies are technically necessary and do not require separate consent.

We do not process any special categories of personal data as defined in Art. 9 GDPR. Communication with the app takes place exclusively over encrypted HTTPS. Passwords are stored hashed (never in plaintext). No personal data is written to application logs.

## Legal Basis {#rechtsgrundlage}

- **Art. 6 (1) (b) GDPR** — performance of a contract with hosts who register an account to organise their event.
- **Art. 6 (1) (a) GDPR** — consent from guests who join an event via QR code and voluntarily contribute content (photos, RSVP, drink logs).
- **Art. 6 (1) (f) GDPR** — legitimate interest in a stable, secure operation of the platform (technical error logging, abuse protection).

## Retention Periods {#speicherdauer}

- Host accounts are stored for as long as the account is active. Deletion is possible at any time via "Delete account".
- Invitation tokens are removed {{retention.invitation_tokens_days}} days after the event date.
- Declined guests without app access are removed {{retention.declined_guests_days}} days after the event date.
- Personal access tokens (e.g. guest app login) expire after their validity window and are deleted daily.
- When an event is deleted, the associated photos are also removed from Object Storage.

## Your Rights {#rechte}

You have the right to:

- **Access** (Art. 15 GDPR) — You can exercise your rights under Art. 15 (access) and Art. 17 (erasure) directly in the app under Settings → "Export my data" or "Delete account".
- **Rectification** (Art. 16 GDPR) — editable directly in Settings.
- **Erasure** (Art. 17 GDPR) — via "Delete account" in Settings.
- **Restriction of processing** (Art. 18 GDPR).
- **Data portability** (Art. 20 GDPR) — covered by the JSON export.
- **Objection** to processing (Art. 21 GDPR).
- **Complaint** to a data-protection authority. The competent authority is the State Commissioner for Data Protection and Freedom of Information of Rhineland-Palatinate (datenschutz.rlp.de) or the data-protection authority of your place of residence.

## Third Parties {#drittanbieter}

We do not use tracking, analytics or third-party cookies. The app communicates exclusively with our own backend at eveplan.de.

To operate the service we use carefully selected data processors, each under a contract for order processing pursuant to Art. 28 GDPR. All processing of your data takes place within the European Union — the sole exception is the optional "Sign in with Google" feature, which you must actively trigger:

- **Hetzner Online GmbH**, Industriestr. 25, 91710 Gunzenhausen, Germany — hosting of the application, the database and object storage for uploaded photos. Location: Nuremberg (Germany); processing exclusively within the EU. Order-processing agreement (Art. 28 GDPR) in place. EXIF, IPTC and XMP metadata are stripped server-side before storage.
- **Plus Five Five, Inc. (Resend)** — sending of system emails (verification, password reset, invitations). Processing in the EU (region Ireland, eu-west-1); data-processing agreement in place.
- **Functional Software, Inc. (Sentry)** — automated backend error monitoring (capturing unhandled exceptions and warning-level events). Processing in the EU (region Frankfurt, `de.sentry.io`). By default **no** IP addresses, session cookies, authenticated-user context, or request headers are transmitted. Data-processing agreement in place upon account activation.
- **Google LLC** (USA) — **only** if you actively sign in with "Sign in with Google". Google then receives your Google ID, email and name (no further scopes). Data transfer to the USA is based on the EU Standard Contractual Clauses (SCCs).

## Contact {#kontakt}

For questions about data protection or to exercise your rights, contact us by email:

andre-hommrich@web.de

This privacy policy may be adapted when the application or legal requirements change. The current version is always available in the app and at eveplan.de/datenschutz. Material changes will be additionally communicated by email or in-app. The authoritative version of this policy is the German original at eveplan.de/datenschutz; this translation is provided for convenience and has not undergone separate legal review.
