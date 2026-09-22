<?php

namespace Goldnead\Certificates\Listeners;

use Goldnead\Certificates\CertificatePdf;
use Goldnead\Certificates\Certificates;
use Goldnead\Certificates\Events\CertificateIssued;
use Goldnead\Certificates\Mail\CertificateMail;
use Goldnead\Certificates\Support\Subject;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends the learner the PDF, when `certificates.mail.enabled` is on.
 *
 * Queued through the mailable, so the PDF render happens on the queue and not
 * in the request that completed the course.
 */
class MailCertificate
{
    public function __construct(protected Certificates $certificates, protected CertificatePdf $pdf) {}

    public function handle(CertificateIssued $event): void
    {
        $certificate = $event->certificate;

        // The switch is a per-brand setting: read it in the certificate's
        // brand, not in whatever brand the announcing process runs under.
        $enabled = $this->pdf->inBrandOf($certificate, fn () => (bool) config('certificates.mail.enabled', false));

        if (! $enabled || $this->certificates->mailSuppressed()) {
            return;
        }

        $subject = Subject::find($certificate->subject_type, $certificate->subject_id);

        if ($subject?->email === null) {
            Log::warning('statamic-certificates: certificate issued, but its owner has no email address to send it to.', [
                'certificate' => $certificate->code,
            ]);

            return;
        }

        Mail::to($subject->email)->queue(new CertificateMail($certificate));
    }
}
