# Changelog

## Unreleased

First version.

### Added
- Certificates issued on statamic-courses' `CourseCompleted`, through `Certificates::issue()` and
  with `php artisan certificates:issue` (single, or `--backfill` from completed enrollments).
- Table `certificates_issued`: one certificate per learner and course via a unique index; learner
  name and course title snapshotted at issue time; revocation with date and reason.
- Non-guessable 20-character Crockford base32 code.
- PDF via dompdf, A4 landscape, per-brand template (issuer, logo, signature, signatory, accent
  colour, footer) through statamic-brand-context's settings layer; stored on a configurable disk,
  re-rendered when missing. Publishable Blade views.
- Public verification page `/certificates/verify/{code}` (valid, revoked, unknown; no difference
  between unknown and malformed codes; throttled) and an owner-only download route, both behind
  `CERTIFICATES_ROUTES_ENABLED`.
- Events `CertificateIssued` and `CertificateRevoked`; optional mail with the PDF attached.
- Antlers tags `{{ certificates }}` and `{{ certificates:for course="…" }}`.
- Control Panel screen "Certificates" with search and revoke, permission `manage certificates`.
- German and English translations.
