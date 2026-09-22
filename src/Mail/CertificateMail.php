<?php

namespace Goldnead\Certificates\Mail;

use Goldnead\Certificates\Certificates;
use Goldnead\Certificates\Models\Certificate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class CertificateMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Certificate $certificate) {}

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
