<?php

use Goldnead\Certificates\CertificatePdf;
use Goldnead\Certificates\Events\CertificateRevoked;
use Goldnead\Certificates\Facades\Certificates;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Swaps the certificates disk for a proxy of the real one whose given methods
 * return the given values; everything else is forwarded.
 *
 * @param  array<string, mixed>  $overrides
 */
function brokenDisk(array $overrides): void
{
    $spy = Mockery::mock(Storage::disk('certificates-test'))->makePartial();

    foreach ($overrides as $method => $return) {
        $spy->shouldReceive($method)->andReturn($return);
    }

    Storage::set('certificates-test', $spy);
}

it('does not report a revoke as clean when the stored PDF cannot be deleted', function () {
    $certificate = Certificates::issue($this->makeUser('ada@example.com', 'Ada'), $this->makeCourse('Kurs'));
    Certificates::pdf($certificate);
    Event::fake([CertificateRevoked::class]);
    brokenDisk(['delete' => false]);

    expect(fn () => Certificates::revoke($certificate, 'Aberkannt'))
        ->toThrow(RuntimeException::class, 'could not delete');

    // The revocation itself stands and is announced; only the cleanup failed.
    expect($certificate->fresh()->isRevoked())->toBeTrue();
    Event::assertDispatchedTimes(CertificateRevoked::class, 1);
});

it('retries the PDF cleanup when an already revoked certificate is revoked again', function () {
    $certificate = Certificates::issue($this->makeUser('ada@example.com', 'Ada'), $this->makeCourse('Kurs'));
    Certificates::pdf($certificate);
    $path = app(CertificatePdf::class)->path($certificate);
    $real = Storage::disk('certificates-test');
    brokenDisk(['delete' => false]);

    try {
        Certificates::revoke($certificate, 'Aberkannt');
    } catch (RuntimeException) {
    }

    Storage::set('certificates-test', $real);
    Certificates::revoke($certificate->fresh(), 'Nochmal');

    expect($real->exists($path))->toBeFalse();
});

it('renders again instead of serving an empty PDF when the stored file cannot be read', function () {
    $certificate = Certificates::issue($this->makeUser('ada@example.com', 'Ada'), $this->makeCourse('Kurs'));
    Certificates::pdf($certificate);
    brokenDisk(['get' => null]);

    expect(substr(Certificates::pdf($certificate), 0, 5))->toBe('%PDF-');
});

it('keeps the cleanup error when a revoke listener throws as well', function () {
    $certificate = Certificates::issue($this->makeUser('ada@example.com', 'Ada'), $this->makeCourse('Kurs'));
    Certificates::pdf($certificate);
    brokenDisk(['delete' => false]);
    Event::listen(CertificateRevoked::class, fn () => throw new LogicException('listener'));

    expect(fn () => Certificates::revoke($certificate, 'Aberkannt'))
        ->toThrow(RuntimeException::class, 'could not delete');
});

it('logs when a rendered PDF could not be stored', function () {
    $certificate = Certificates::issue($this->makeUser('ada@example.com', 'Ada'), $this->makeCourse('Kurs'));
    brokenDisk(['put' => false]);
    Log::spy();

    expect(substr(Certificates::pdf($certificate), 0, 5))->toBe('%PDF-');

    Log::shouldHaveReceived('error')->once();
});
