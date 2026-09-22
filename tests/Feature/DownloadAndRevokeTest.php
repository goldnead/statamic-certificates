<?php

use Goldnead\Certificates\CertificatePdf;
use Goldnead\Certificates\Events\CertificateRevoked;
use Goldnead\Certificates\Exceptions\CertificateIsRevoked;
use Goldnead\Certificates\Facades\Certificates;
use Goldnead\Certificates\Mail\CertificateMail;
use Goldnead\Certificates\Models\Certificate;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

it('gives the owner the PDF', function () {
    $ada = $this->makeUser('ada@example.com', 'Ada');
    $certificate = Certificates::issue($ada, $this->makeCourse('Stimmbildung im Chor'));

    $response = $this->actingAs($ada)->get('/certificates/'.$certificate->code.'/download');

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect(substr($response->getContent(), 0, 5))->toBe('%PDF-')
        ->and($response->headers->get('Content-Disposition'))->toContain('stimmbildung-im-chor-'.$certificate->code.'.pdf');
});

it('refuses a guest', function () {
    $certificate = Certificates::issue($this->makeUser('ada@example.com', 'Ada'), $this->makeCourse('Kurs'));

    $this->get('/certificates/'.$certificate->code.'/download')->assertForbidden();
});

it('answers another user as if the code did not exist', function () {
    $certificate = Certificates::issue($this->makeUser('ada@example.com', 'Ada'), $this->makeCourse('Kurs'));
    $grace = $this->makeUser('grace@example.com', 'Grace');

    $this->actingAs($grace)->get('/certificates/'.$certificate->code.'/download')->assertNotFound();
    $this->actingAs($grace)->get('/certificates/ZZZZZZZZZZZZZZZZZZZZ/download')->assertNotFound();
});

it('revokes with a reason, keeps the first revocation, and fires an event', function () {
    Event::fake([CertificateRevoked::class]);
    $certificate = Certificates::issue($this->makeUser('ada@example.com', 'Ada'), $this->makeCourse('Kurs'));

    Certificates::revoke($certificate, '  Doppelt ausgestellt ');
    $firstRevokedAt = $certificate->fresh()->revoked_at;

    $this->travel(1)->days();
    Certificates::revoke($certificate->fresh(), 'Anderer Grund');

    $fresh = $certificate->fresh();

    expect($fresh->isRevoked())->toBeTrue()
        ->and($fresh->revoked_reason)->toBe('Doppelt ausgestellt')
        ->and($fresh->revoked_at->equalTo($firstRevokedAt))->toBeTrue();

    Event::assertDispatchedTimes(CertificateRevoked::class, 1);
});

it('stops serving and deletes the stored PDF of a revoked certificate', function () {
    $ada = $this->makeUser('ada@example.com', 'Ada');
    $certificate = Certificates::issue($ada, $this->makeCourse('Kurs'));
    Certificates::pdf($certificate);
    $path = app(CertificatePdf::class)->path($certificate);

    Certificates::revoke($certificate, 'Aberkannt');

    expect(Storage::disk('certificates-test')->exists($path))->toBeFalse();
    $this->actingAs($ada)->get('/certificates/'.$certificate->code.'/download')->assertStatus(410);
});

it('refuses to render or serve the PDF of a revoked certificate', function () {
    $certificate = Certificates::issue($this->makeUser('ada@example.com', 'Ada'), $this->makeCourse('Kurs'));
    Certificates::revoke($certificate, 'Aberkannt');

    expect(fn () => Certificates::pdf($certificate))->toThrow(CertificateIsRevoked::class)
        ->and(fn () => app(CertificatePdf::class)->render($certificate))->toThrow(CertificateIsRevoked::class)
        ->and(Storage::disk('certificates-test')->exists(app(CertificatePdf::class)->path($certificate)))->toBeFalse();
});

it('does not send a mail that was queued before the certificate was revoked', function () {
    config(['mail.default' => 'array']);
    $certificate = Certificates::issue($this->makeUser('ada@example.com', 'Ada'), $this->makeCourse('Kurs'));
    $mail = new CertificateMail($certificate);

    // Revoked between queueing and sending: the queued copy holds the old row.
    Certificate::query()->whereKey($certificate->id)->update(['revoked_at' => now(), 'revoked_reason' => 'x']);

    Mail::to('ada@example.com')->send($mail);

    expect(app('mailer')->getSymfonyTransport()->messages())->toHaveCount(0);
});

it('does not reissue a revoked certificate', function () {
    $ada = $this->makeUser('ada@example.com', 'Ada');
    $course = $this->makeCourse('Kurs');
    $certificate = Certificates::issue($ada, $course);
    Certificates::revoke($certificate, 'Aberkannt');

    expect(Certificates::issue($ada, $course)->isRevoked())->toBeTrue();
});
