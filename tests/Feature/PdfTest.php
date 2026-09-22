<?php

use Goldnead\Certificates\CertificatePdf;
use Goldnead\Certificates\Facades\Certificates;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;

function pdfText(string $pdf): string
{
    return (new Parser)->parseContent($pdf)->getText();
}

it('renders a real PDF that names the learner and the course', function () {
    config(['certificates.template.issuer_name' => 'Chorakademie Nord']);
    $certificate = Certificates::issue($this->makeUser('ada@example.com', 'Ada Lovelace'), $this->makeCourse('Stimmbildung im Chor'));

    $pdf = Certificates::pdf($certificate);
    $text = pdfText($pdf);

    expect(substr($pdf, 0, 5))->toBe('%PDF-')
        ->and($text)->toContain('Ada Lovelace')
        ->and($text)->toContain('Stimmbildung im Chor')
        // Set in small caps by the stylesheet.
        ->and($text)->toContain('CHORAKADEMIE NORD')
        ->and($text)->toContain(implode('-', str_split($certificate->code, 4)));
});

it('prints the verification URL on the certificate', function () {
    $certificate = Certificates::issue($this->makeUser('ada@example.com', 'Ada'), $this->makeCourse('Kurs'));

    expect(pdfText(Certificates::pdf($certificate)))->toContain('/certificates/verify/'.$certificate->code);
});

it('stores the PDF on the configured disk and renders it again when the file is gone', function () {
    $certificate = Certificates::issue($this->makeUser('ada@example.com', 'Ada'), $this->makeCourse('Kurs'));
    $path = app(CertificatePdf::class)->path($certificate);
    $disk = Storage::disk('certificates-test');

    expect($disk->exists($path))->toBeFalse();

    $first = Certificates::pdf($certificate);
    expect($disk->exists($path))->toBeTrue();

    $disk->delete($path);
    $second = Certificates::pdf($certificate);

    expect($disk->exists($path))->toBeTrue()
        ->and($second)->toBe($first);
});

it('serves the stored file rather than rendering again', function () {
    $certificate = Certificates::issue($this->makeUser('ada@example.com', 'Ada'), $this->makeCourse('Kurs'));
    Storage::disk('certificates-test')->put(app(CertificatePdf::class)->path($certificate), '%PDF-stored');

    expect(Certificates::pdf($certificate))->toBe('%PDF-stored');
});

it('lets only a hex colour into the stylesheet', function () {
    config(['certificates.template.accent_color' => 'red;}body{display:none']);
    $certificate = Certificates::issue($this->makeUser('ada@example.com', 'Ada'), $this->makeCourse('Kurs'));

    expect(app(CertificatePdf::class)->viewData($certificate)['accentColor'])->toBe('#1f2937');

    config(['certificates.template.accent_color' => '#cca560']);
    expect(app(CertificatePdf::class)->viewData($certificate)['accentColor'])->toBe('#cca560');
});

it('renders without the logo when the asset reference does not resolve', function () {
    config(['certificates.template.logo' => 'assets::missing.png']);
    $certificate = Certificates::issue($this->makeUser('ada@example.com', 'Ada'), $this->makeCourse('Kurs'));

    expect(app(CertificatePdf::class)->viewData($certificate)['logo'])->toBeNull()
        ->and(substr(Certificates::pdf($certificate), 0, 5))->toBe('%PDF-');
});
