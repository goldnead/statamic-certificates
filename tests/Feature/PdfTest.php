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

dataset('layouts', [
    'normal' => ['Ana Beispiel', 'Stimmbildung im Chor'],
    'long name and title' => [
        'Bärbel Größ-Öztürk-Weißenfels von Überlingen zu Hohenstein-Ernstthal',
        'Stimme zuerst: Grundlagen der individuellen stimmtechnischen Bildung für Chorsängerinnen und Chorsänger in gemischten Ensembles – Aufbaukurs mit Einstufungstest und Abschlusskolloquium',
    ],
    'maximum lengths' => [str_repeat('Namenteil ', 25), str_repeat('Kurstitelwort ', 18)],
]);

it('fits on exactly one page, code and verification URL included', function (string $name, string $title) {
    config(['certificates.template.footer' => 'Nordlicht Studio · Musterstraße 1 · 20095 Hamburg']);
    $certificate = Certificates::issue($this->makeUser('ada@example.com', $name), $this->makeCourse($title));

    $pages = (new Parser)->parseContent(Certificates::pdf($certificate))->getPages();
    $firstPage = preg_replace('/\s+/', ' ', $pages[0]->getText());

    // Drawn on page 1 is not enough: dompdf happily draws below the media
    // box, where no viewer shows it. Every text baseline must sit inside the
    // frame, which is 10 mm (28.3 pt) in from each edge of the 595 pt page.
    $baselines = array_map(fn (array $item) => (float) $item[0][5], $pages[0]->getDataTm());

    expect(min($baselines))->toBeGreaterThan(30.0)
        ->and(max($baselines))->toBeLessThan(595.0 - 30.0);

    expect($pages)->toHaveCount(1)
        ->and($firstPage)->toContain(implode('-', str_split($certificate->code, 4)))
        ->and($firstPage)->toContain('/certificates/verify/'.$certificate->code)
        ->and($firstPage)->toContain('Musterstraße 1');
})->with('layouts');

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
