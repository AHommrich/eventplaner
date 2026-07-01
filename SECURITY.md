# Security Policy

## Reporting a vulnerability

If you find a security issue in this project, please **do not open a public GitHub issue**. Send the details privately to:

**andre-hommrich@web.de**

Include, if possible:

- A short description of the issue and its impact.
- Steps to reproduce (a curl command, a request/response pair, or a small script).
- The affected endpoint or file path.
- Whether you have already tried the issue against `https://beta.hommrich.app` (staging) or `https://eveplan.de` (production).

## What to expect

- **Acknowledgement:** within 3 working days.
- **First triage response** (accepted / needs-info / not applicable): within 7 working days.
- **Fix or mitigation** for accepted reports: as soon as reasonably possible; usually within 30 days for exploitable issues. You will get progress updates until it lands.
- **Public disclosure:** we prefer coordinated disclosure. Once a fix has shipped to production, we can credit you in the release notes if you wish.

## Scope

- **In scope:** anything in this repository, the production site `eveplan.de`, and the staging site `beta.hommrich.app`.
- **Out of scope:**
  - Denial-of-service tests against production.
  - Automated vulnerability-scanner reports without a reproduction path.
  - Social-engineering or physical attacks.
  - Third-party services (Hetzner, Resend, Google OAuth) — please report those directly to the vendor.

## Safe harbour

Good-faith security research that stays within scope, does not degrade service, and does not access other users' data will not be pursued legally. If you are unsure whether a specific test is acceptable, ask first via the email above.

## Preferred communication

Plain email is fine — no PGP required. If you already have a PGP key you use for advisories, feel free to attach the encrypted version to the same email.
