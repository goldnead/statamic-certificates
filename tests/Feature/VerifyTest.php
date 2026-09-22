<?php

use Goldnead\Certificates\Facades\Certificates;

it('shows a valid certificate with learner, course, date and issuer', function () {
    config(['certificates.template.issuer_name' => 'Chorakademie Nord']);
    $certificate = Certificates::issue($this->makeUser('ada@example.com', 'Ada Lovelace'), $this->makeCourse('Stimmbildung im Chor'));

    $this->get('/certificates/verify/'.$certificate->code)
        ->assertOk()
        ->assertSee('data-status="valid"', false)
        ->assertSee('Ada Lovelace')
        ->assertSee('Stimmbildung im Chor')
        ->assertSee('Chorakademie Nord')
        ->assertSee($certificate->issued_at->locale('en')->isoFormat('LL'))
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

it('keeps a side gutter on narrow screens', function () {
    $this->get('/certificates/verify/ZZZZZZZZZZZZZZZZZZZZ')
        ->assertSee('name="viewport"', false)
        ->assertSee('body { margin: 0; padding: 0 16px;', false)
        ->assertSee('box-sizing: border-box', false);
});

it('accepts the code as a person types it', function () {
    $certificate = Certificates::issue($this->makeUser('ada@example.com', 'Ada'), $this->makeCourse('Kurs'));
    $typed = strtolower(implode('-', str_split($certificate->code, 4)));

    $this->get('/certificates/verify/'.$typed)->assertOk()->assertSee('data-status="valid"', false);
});

it('shows a revoked certificate as revoked, without the reason', function () {
    $certificate = Certificates::issue($this->makeUser('ada@example.com', 'Ada'), $this->makeCourse('Kurs'));
    Certificates::revoke($certificate, 'Prüfung nachträglich aberkannt');

    $this->get('/certificates/verify/'.$certificate->code)
        ->assertOk()
        ->assertSee('data-status="revoked"', false)
        ->assertDontSee('aberkannt');
});

it('answers an unknown and a malformed code identically', function () {
    Certificates::issue($this->makeUser('ada@example.com', 'Ada'), $this->makeCourse('Kurs'));

    $unknown = $this->get('/certificates/verify/ZZZZZZZZZZZZZZZZZZZZ');
    $malformed = $this->get('/certificates/verify/'.str_repeat('x', 300));
    $short = $this->get('/certificates/verify/abc');

    foreach ([$unknown, $malformed, $short] as $response) {
        $response->assertOk()->assertSee('data-status="unknown"', false);
    }

    // Byte for byte: nothing about the page tells the two cases apart.
    expect($malformed->getContent())->toBe($unknown->getContent())
        ->and($short->getContent())->toBe($unknown->getContent())
        ->and(array_keys($malformed->headers->all()))->toEqualCanonicalizing(array_keys($unknown->headers->all()));
});

it('throttles the verification route', function () {
    config(['certificates.routes.throttle' => '30,1']);

    for ($i = 0; $i < 30; $i++) {
        $this->get('/certificates/verify/ZZZZZZZZZZZZZZZZZZZZ')->assertOk();
    }

    $this->get('/certificates/verify/ZZZZZZZZZZZZZZZZZZZZ')->assertStatus(429);
});
