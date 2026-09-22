<?php

use Goldnead\Certificates\Facades\Certificates;
use Smalot\PdfParser\Parser;

/*
 * Learner names and course titles are user-controlled and end up in a PDF
 * rendered by dompdf and on a public HTML page. Both must print them as text.
 */

const HOSTILE_NAME = '<img src=file:///etc/passwd>';
const HOSTILE_TITLE = '<script>alert(1)</script><img src="/etc/hostname">';

it('prints markup in name and title as text in the PDF, and reads no file', function () {
    $certificate = Certificates::issue($this->makeUser('ada@example.com', HOSTILE_NAME), $this->makeCourse(HOSTILE_TITLE));

    $pdf = Certificates::pdf($certificate);
    $text = (new Parser)->parseContent($pdf)->getText();

    expect($text)->toContain(HOSTILE_NAME)
        ->and($text)->toContain('<script>alert(1)</script>')
        // No image was embedded: neither passwd nor hostname was read as one.
        ->and($pdf)->not->toContain('/Subtype /Image')
        ->and($text)->not->toContain('root:');
});

it('escapes markup in name and title on the verification page', function () {
    $certificate = Certificates::issue($this->makeUser('ada@example.com', HOSTILE_NAME), $this->makeCourse(HOSTILE_TITLE));

    $this->get('/certificates/verify/'.$certificate->code)
        ->assertOk()
        ->assertDontSee('<script>', false)
        ->assertDontSee('<img src=file', false)
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
        ->assertSee('&lt;img src=file:///etc/passwd&gt;', false);
});
