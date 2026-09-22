<?php

namespace Goldnead\Certificates\Mail;

use Goldnead\Certificates\Certificates;
use Goldnead\Certificates\Models\Certificate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Mail\Factory;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CertificateMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Certificate $certificate) {}

    /**
     * Queued at issue time, sent later: a certificate revoked in between is
     * not sent. Read again from the table, because the queued copy may be
     * older than the revocation.
     *
     * @param  Factory|Mailer  $mailer
     */
    public function send($mailer)
    {
        $current = Certificate::query()->acrossBrands()->whereKey($this->certificate->getKey())->first();

        if ($current === null || $current->isRevoked()) {
            Log::info('statamic-certificates: mail not sent, the certificate was revoked or deleted after it was queued.', [
                'certificate' => $this->certificate->code,
            ]);

            return null;
        }

        $this->certificate = $current;

        return parent::send($mailer);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('certificates::messages.mail.subject', ['course' => $this->certificate->course_title]),
        );
    }

    public function content(): Content
    {
        /** @var view-string $view */
        $view = 'certificates::mail';

        return new Content(
            text: $view,
            with: [
                'certificate' => $this->certificate,
                'verifyUrl' => app(Certificates::class)->verifyUrl($this->certificate),
            ],
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        $certificate = $this->certificate;

        return [
            Attachment::fromData(
                fn () => app(Certificates::class)->pdf($certificate),
                Str::slug($certificate->course_title ?: 'certificate').'.pdf',
            )->withMime('application/pdf'),
        ];
    }
}
