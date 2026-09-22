<?php

use Goldnead\BrandContext\Models\Brand;
use Goldnead\BrandContext\Settings\SettingsManager;
use Goldnead\BrandContext\Settings\SettingsRegistry;
use Goldnead\Certificates\CertificatePdf;
use Goldnead\Certificates\Facades\Certificates;
use Goldnead\Certificates\Models\Certificate;
use Smalot\PdfParser\Parser;

beforeEach(function () {
    config(['brand-context.multi_brand' => true]);

    $this->nord = Brand::create(['handle' => 'nord', 'name' => 'Nord'])->id;
    $this->sued = Brand::create(['handle' => 'sued', 'name' => 'Süd'])->id;

    $manager = app(SettingsManager::class);
    $brands = app('brand-context');

    $brands->runFor($this->nord, fn () => $manager->for('certificates')->save([
        'template.issuer_name' => 'Chorakademie Nord',
        'template.accent_color' => '#cca560',
    ]));
    $brands->runFor($this->sued, fn () => $manager->for('certificates')->save([
        'template.issuer_name' => 'Singschule Süd',
    ]));

    $manager->apply(force: true);
});

afterEach(function () {
    config(['brand-context.multi_brand' => false]);
});

it('registers its fields with the shared settings layer', function () {
    expect(app(SettingsRegistry::class)->has('certificates'))->toBeTrue();
});

it('stamps the brand that was current when the certificate was issued', function () {
    $course = $this->makeCourse('Kurs');
    $ada = $this->makeUser('ada@example.com', 'Ada');

    $certificate = app('brand-context')->runFor($this->sued, fn () => Certificates::issue($ada, $course));

    expect($certificate->brand_id)->toBe($this->sued);
});

it('renders each certificate with its own brand\'s template, whatever brand is current', function () {
    $brands = app('brand-context');
    $ada = $this->makeUser('ada@example.com', 'Ada');
    $grace = $this->makeUser('grace@example.com', 'Grace');

    $nord = $brands->runFor($this->nord, fn () => Certificates::issue($ada, $this->makeCourse('Eins')));
    $sued = $brands->runFor($this->sued, fn () => Certificates::issue($grace, $this->makeCourse('Zwei')));

    // Rendered while the other brand is current: the row's brand wins.
    $nordData = $brands->runFor($this->sued, fn () => app(CertificatePdf::class)->viewData($nord));
    expect($nordData['issuerName'])->toBe('Singschule Süd'); // viewData alone reads the current config …

    $nordText = $brands->runFor($this->sued, fn () => (new Parser)->parseContent(Certificates::pdf($nord))->getText());
    $suedText = $brands->runFor($this->nord, fn () => (new Parser)->parseContent(Certificates::pdf($sued))->getText());

    // … render() switches to the certificate's brand first.
    expect($nordText)->toContain('CHORAKADEMIE NORD')->not->toContain('SINGSCHULE')
        ->and($suedText)->toContain('SINGSCHULE SÜD')->not->toContain('CHORAKADEMIE');
});

it('shows the issuing brand on the verification page, with no brand in the session', function () {
    $certificate = app('brand-context')->runFor($this->sued, fn () => Certificates::issue($this->makeUser('ada@example.com', 'Ada'), $this->makeCourse('Kurs')));

    $this->get('/certificates/verify/'.$certificate->code)
        ->assertOk()
        ->assertSee('Singschule Süd')
        ->assertDontSee('Chorakademie Nord');
});

it('keeps one certificate per learner and course across brands', function () {
    $brands = app('brand-context');
    $ada = $this->makeUser('ada@example.com', 'Ada');
    $course = $this->makeCourse('Kurs');

    $first = $brands->runFor($this->nord, fn () => Certificates::issue($ada, $course));
    $second = $brands->runFor($this->sued, fn () => Certificates::issue($ada, $course));

    expect($second->id)->toBe($first->id)
        ->and(Certificate::query()->acrossBrands()->count())->toBe(1);
});
