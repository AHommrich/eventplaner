# Stage 6 — Sub-processor documentation (AVV / DPA register)

**Effort:** ~1 h (plus the homework of actually downloading + filing each provider's DPA)
**Outcome:** A versioned `docs/legal/sub-processors.md` register listing every third party that processes personal data on behalf of the app, what data they touch, where they host it, and a link to the signed Data Processing Agreement (Auftragsverarbeitungsvertrag — AVV). This is the source of truth Stage 1's privacy policy quotes.
**Why this matters:** GDPR Art. 28 requires a written contract with every processor, Art. 30 requires you to maintain a record of processing activities. If a supervisory authority asks "with whom do you share data", "I don't remember" is not an acceptable answer. A single Markdown register that you actually update is the cheapest compliant version of that record.

## Sub-processors today

This is the list as of 2026-06-30 — confirm against `config/services.php`, `config/filesystems.php`, `config/mail.php`, and `composer.json`:

| Processor | Purpose | Data | Location | Contract status |
|---|---|---|---|---|
| Hetzner Online GmbH | App + DB hosting | Everything: user accounts, events, guests, sessions, log files | Germany (Falkenstein/Nuremberg) | Hetzner standard AVV — confirm signed |
| Cloudflare R2 | Photo blob storage | Uploaded photos, no metadata beyond the object key | EU region (configure `AWS_DEFAULT_REGION=eu` in env, double-check via R2 dashboard) | Cloudflare DPA — needs download + filing |
| Resend (Resend, Inc.) | Transactional email (verification, password reset, invites) | Recipient email + email body containing names | US-based, GDPR-compliant via SCCs | Resend DPA — review + sign |
| Google LLC | OAuth login (optional, only for users who chose "Sign in with Google") | Google account id, email, display name | US / global | Google's standard processor terms — link only, no separate signed copy needed for a SaaS using OAuth |
| GitHub, Inc. | Source hosting + CI (no production user data, but commit metadata is technically personal data of contributors) | Code, commit author emails | US | GitHub DPA — link only |

This list **must** be re-verified before adding any new package or service. Adding a new sub-processor without listing it here = privacy-policy lying.

## Steps

### 1. Create the register

`docs/legal/sub-processors.md` — new. Format:

```markdown
# Sub-Processors

Last reviewed: 2026-07-XX

This register lists every third party that processes personal data on behalf of eveplan.
Any new entry must be added here **before** the integration ships to production.

## Active processors

### Hetzner Online GmbH
- **Purpose:** Hosting of the application server and managed database
- **Data categories:** All — user accounts, events, guests, sessions, log files
- **Server location:** Germany (Falkenstein / Nuremberg)
- **Contract:** Hetzner standard data processing agreement, signed YYYY-MM-DD, filed at <local path or 1Password vault>
- **Provider info:** <https://www.hetzner.com/legal/privacy-policy>

### Cloudflare R2 (Cloudflare, Inc.)
- **Purpose:** Object storage for uploaded photos
- **Data categories:** Photo blob + object key. No EXIF stripping (TODO: add to roadmap)
- **Server location:** EU region (configured via `AWS_DEFAULT_REGION=eu` env var)
- **Contract:** Cloudflare DPA, signed YYYY-MM-DD, filed at <…>
- **Provider info:** <https://www.cloudflare.com/cloudflare-customer-dpa/>

… (repeat per provider)

## Inactive / removed processors

Keep a small log of services we *stopped* using, with the removal date.
Helpful when answering "did you ever share my data with X" historically.

## How to add a new sub-processor

1. Document the processor here first.
2. Download and sign their DPA, store the signed PDF.
3. Update `resources/js/pages/Legal/Privacy.vue` section 5 ("Empfänger / Sub-Processors") with the new entry.
4. Then merge the integration code.
```

### 2. Collect the contracts

This is the boring but unavoidable part. Action items, not Claude tasks:

- Log into the Hetzner robot panel, confirm the AVV is signed, save a PDF copy locally
- Log into Cloudflare, accept the R2 DPA in the dashboard, download a copy
- Log into Resend, accept their DPA (the link sits under Settings → Legal), download a copy
- Save all PDFs under a non-versioned path (`~/legal/eventplaner/` or password-manager vault — **never commit signed contracts to the repo**)

A reasonable filing convention: `~/legal/eventplaner/dpa-<provider>-<YYYY-MM-DD>.pdf`. Add the filing path (not the PDF itself) to the register entry so future-you can find it.

### 3. Cross-link with Stage 1

Stage 1's `Privacy.vue` section 5 lists processors but says "see sub-processor register for details". Update that section to point at `docs/legal/sub-processors.md` for the developer-facing detail, and inline the user-facing summary right there.

### 4. EXIF stripping reminder

Cloudflare R2 stores photos byte-for-byte. Uploaded photos retain their EXIF data, which may include GPS coordinates, device id, and a UTC timestamp — all considered personal data. Stripping EXIF before upload is **not** in scope for Stage 6 (it's a separate roadmap item), but log it here so it's visible:

> **Known gap:** Uploaded photos retain EXIF metadata (potential GPS coordinates, device info). Stripping is on the roadmap as a separate task. Privacy policy must mention this until fixed.

Stage 1's privacy policy should mention this explicitly.

### 5. Light tests

There's nothing to test in code here — Stage 6 is documentation. But:

- Add a one-line check to a CI step (or just a manual checklist item in `tag-5-ci-coverage.md`-style file) reminding maintainers to re-review `docs/legal/sub-processors.md` quarterly.
- A grep-based test that fails if a new HTTP-client-using service is added without an entry in the register would be neat (e.g. `grep -rE "(Http::|Mail::|->disk\()" app/ | check against allowlist`) — overkill for Stage 6, file it under "follow-up" in the master plan.

## File list

| File | Action |
|---|---|
| `docs/legal/sub-processors.md` | new |
| `resources/js/pages/Legal/Privacy.vue` | section 5 cross-references the new register, mentions EXIF gap |

(Signed PDFs are stored **outside the repo**.)

## Acceptance criteria

- [ ] `docs/legal/sub-processors.md` exists with one entry per active processor (Hetzner, Cloudflare R2, Resend, Google, GitHub at minimum)
- [ ] Each entry names: purpose, data categories, location, contract status (signed/date/local-filing-path), provider info link
- [ ] Signed DPA PDFs are filed locally (you have them) — no PDFs in the repo
- [ ] Privacy policy section 5 cites the register and lists the same processors in user-facing language
- [ ] EXIF metadata caveat is documented (both here and in the privacy policy)
- [ ] Inactive-processors section exists (may be empty today)

## Commit suggestion

```
docs(legal): add sub-processor register + cross-link from privacy policy
```
