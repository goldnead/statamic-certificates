# Statamic Certificates

Completion certificates for Statamic 6. When a learner finishes a course in
[statamic-courses](https://github.com/goldnead/statamic-courses), a certificate is issued: a PDF with
the learner's name, the course, the date and your brand, and a public verification page where
anyone holding the certificate's code can check that it is genuine and not revoked.

Commercial.

## Requirements

- PHP 8.2+, Laravel 12.40+ or 13, Statamic 6
- goldnead/statamic-courses 0.1+ (the `CourseCompleted` event)
- goldnead/statamic-brand-context 1.13+ (per-brand template settings)
- dompdf, installed with the package. Pure PHP, no Chrome, no binary.

## Install

```bash
composer require goldnead/statamic-certificates
php artisan migrate
```

That is all. The next `CourseCompleted` issues a certificate. For courses finished before the
addon was installed:

```bash
php artisan certificates:issue --backfill --dry-run   # what would be issued
php artisan certificates:issue --backfill             # dated at each enrollment's completion, no mail
php artisan certificates:issue --backfill --mail      # the same, mailing as configured
```

One by hand, for a learner who completed the course (a completed enrollment in
statamic-courses; `--force` issues without one, `--no-mail` suppresses the mail):

```bash
php artisan certificates:issue ada@example.com {course-entry-id-or-slug}
```

**Brand on the console.** The console has no current brand, so each certificate is issued in the
brand the course's Statamic site maps to (`brand-context.sites`), or the one `--brand=<handle>`
names. With multi-brand off, the default brand is used. With multi-brand on, one brand only,
that one. With multi-brand on, several brands and neither, the command refuses rather than stamp
the default brand's issuer on another brand's certificate.

## When is a course "completed"

Whenever statamic-courses fires `CourseCompleted`: every lesson completed, milestones included.
That is statamic-courses' definition, not this addon's, and it includes lessons **skipped by a
test-out**: a learner who tested out of a phase gets the certificate without having opened those
lessons. If a certificate should mean "did every lesson", that is a decision for the course
design (no test-out lessons), not a setting here.

## How issuing works

- **One certificate per learner and course.** A unique index on (subject, course) decides, not a
  lock, so two completion events racing each other still end in one row. Asking again returns
  the existing certificate; a revoked one stays revoked.
- **Snapshots.** The learner's name, the course title, the issuer, the signatory's name and
  title, and the brand handle are copied when the certificate is issued. Renaming the course,
  the user or the brand's settings afterwards does not change what the PDF and the verification
  page say. Logo, signature image, accent colour and footer stay live: they are looks, not
  statements, and a re-rendered PDF picks up the current ones.
- **No name, no certificate.** The name is printed on a public page, so a user without one
  (neither `name` nor `first_name`/`last_name`) is refused rather than certified under their
  email address. The manual path throws `LearnerNameMissing`; the event path logs a warning and
  fires `CertificateNotIssued` with the reason `learner_name_missing`. Add the name and run the
  backfill.
- **The code.** 20 characters of Crockford base32 from `random_bytes()` (100 bits), printed as
  `ABCD-EFGH-JKMN-PQRS-TVWX`. Typing it in lower case, with or without dashes, works.
- **A failure does not break the learner's request.** The listener runs inside the request that
  completed the last lesson; a refusal (see above, or a deleted course entry) fires
  `CertificateNotIssued`, any other error is reported to the exception handler, and the backfill
  picks it up later.
- **Revoked means gone.** A revoked certificate is not rendered, not downloadable and not mailed:
  a mail queued before the revocation is dropped when it comes to be sent.

## PHP API

```php
use Goldnead\Certificates\Facades\Certificates;

$certificate = Certificates::issue($user, $courseEntryId);   // idempotent, fires CertificateIssued once
Certificates::find($user, $courseEntryId);                   // or null
Certificates::for($user);                                    // the user's certificates, newest first
Certificates::findByCode('abcd-efgh-…');
Certificates::revoke($certificate, 'Reason');                // fires CertificateRevoked
Certificates::pdf($certificate);                             // the PDF bytes; throws CertificateIsRevoked
Certificates::verifyUrl($certificate);
Certificates::downloadUrl($certificate);
Certificates::withoutMail(fn () => Certificates::issue($user, $id));   // issue without mailing
```

`issue()` throws a `CertificateRefused` (`CourseNotFound`, `LearnerNameMissing`) when it cannot
issue, and runs in the current brand: call it inside `BrandContext::runFor()` from a job or
command.

`$user` is a Statamic user, the auth guard's user model, or a user id. It is stored as
`user` plus the auth identifier, the same id statamic-courses keys progress on. Any other
Eloquent model is stored under its morph class.

Events: `Goldnead\Certificates\Events\CertificateIssued` and `CertificateRevoked`, both carrying
the `Certificate` model, and `CertificateNotIssued` (subject id, course id, reason, message).

## Front end

Two routes, inside the `web` group, both throttled (`certificates.routes.throttle`, default
`30,1`):

| Route | Name | What |
|---|---|---|
| `GET /certificates/verify/{code}` | `certificates.verify` | Public. Valid, revoked or unknown, with learner, course, date and issuer. |
| `GET /certificates/{code}/download` | `certificates.download` | The PDF, for the signed-in owner only. |

The verification page answers an unknown code and a malformed one with the same page, status and
headers, so it cannot be used to probe which codes exist. The revocation reason is not shown
publicly. A guest asking for a download gets 403, another user 404 (as if the code did not exist),
the owner of a revoked certificate 410.

`CERTIFICATES_ROUTES_ENABLED=false` removes both routes; the tags then return no URLs.

### Antlers

```antlers
{{ certificates }}
    <a href="{{ download_url }}">{{ course_title }}</a> ({{ issued_at format="d.m.Y" }})
    <a href="{{ verify_url }}">{{ code_formatted }}</a>
{{ /certificates }}

{{ certificates:for course="{id}" }}
    <a href="{{ download_url }}">Download your certificate</a>
{{ /certificates:for }}
```

Fields: `code`, `code_formatted`, `course_id`, `course_title`, `learner_name`, `issued_at`,
`is_revoked`, `download_url` (null when revoked), `verify_url`. Always for the signed-in user; a
guest gets nothing. With no certificates the pair renders nothing, or its
`{{ if no_results }}…{{ /if }}` branch when the template has one.

## Template and brand

Under **Settings** in the Control Panel (the shared settings screen of statamic-brand-context),
per brand: issuer name, logo, signature image, signatory name and title, accent colour, footer
text, and whether to mail the PDF. Logo and signature are asset references
(`assets::logos/logo.png`); they are embedded into the PDF, nothing is fetched over the network.
Unset values fall back to `config/certificates.php`.

A certificate belongs to the brand that was current when it was issued. Issuer and signatory
are frozen on the certificate then; logo, signature image, accent colour and footer are read
from that brand's current settings whenever the PDF is rendered, whichever brand serves the
download.

To change the layout, publish the views and edit them:

```bash
php artisan vendor:publish --tag=certificates-views
```

`pdf.blade.php` (A4 landscape, dompdf reads CSS 2.1: tables, not flexbox), `verify.blade.php`
(the public page, plain HTML so it works without a theme) and `mail.blade.php`. The variables
the PDF view receives are listed in `CertificatePdf::viewData()`.

## Storage

Generated PDFs live on `certificates.storage.disk` (default `local`) under
`certificates/{code}.pdf`. The row is the record, the file is a cache: a missing file is rendered
again on the next download, and revoking deletes it. Use a private disk; files are only served
through the download route. A changed logo, colour or footer applies to PDFs rendered
afterwards; delete the folder to re-render existing ones (issuer and signatory stay as issued).

## Mail

`CERTIFICATES_MAIL_ENABLED=true` (or the setting per brand) queues a mail with the PDF attached
when a certificate is issued. Off by default. The switch is read in the certificate's brand. The
backfill never mails unless `--mail` is passed; `certificates:issue --no-mail` and
`Certificates::withoutMail()` suppress it for one run. The mail goes out through the default
mailer and sender.

## Control Panel

**Certificates** lists the current brand's certificates with search and sort, a link to each
verification page, and **Revoke** with a required reason (shown only in the CP). Permission:
`manage certificates`. The template settings need `manage certificates settings`.
`certificates.cp.enabled` hides the screen.

## Configuration

`config/certificates.php`: `issue_on_completion`, `template.*`, `storage.*`, `mail.enabled`,
`routes.enabled|prefix|throttle`, `cp.enabled`.

## Testing

```bash
composer test                                 # SQLite
DB_DRIVER=mysql DB_PORT=3306 composer test:mysql
composer analyse && composer lint
```
