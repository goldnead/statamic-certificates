<?php

use Goldnead\Certificates\Facades\Certificates;
use Goldnead\Certificates\Mail\CertificateMail;
use Goldnead\Certificates\Models\Certificate;
use Goldnead\Courses\Models\Enrollment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Statamic\Facades\Antlers;

function renderAntlers(string $template): string
{
    return (string) Antlers::parse($template, [], true);
}

it('lists the signed-in user\'s certificates with download and verify URLs', function () {
    $ada = $this->makeUser('ada@example.com', 'Ada');
    $certificate = Certificates::issue($ada, $this->makeCourse('Stimmbildung im Chor'));
    Certificates::issue($this->makeUser('grace@example.com', 'Grace'), $this->makeCourse('Anderer Kurs'));

    $this->actingAs($ada);
    $out = renderAntlers('{{ certificates }}[{{ course_title }}|{{ download_url }}|{{ verify_url }}]{{ /certificates }}');

    expect($out)->toContain('Stimmbildung im Chor')
        ->and($out)->not->toContain('Anderer Kurs')
        ->and($out)->toContain('/certificates/'.$certificate->code.'/download')
        ->and($out)->toContain('/certificates/verify/'.$certificate->code);
});

it('renders nothing but no_results when there are no certificates', function () {
    $this->actingAs($this->makeUser('ada@example.com', 'Ada'));

    expect(renderAntlers('{{ certificates }}[{{ course_title }}]{{ /certificates }}'))->toBe('')
        ->and(renderAntlers('{{ certificates }}{{ if no_results }}Keine{{ else }}[{{ course_title }}]{{ /if }}{{ /certificates }}'))->toBe('Keine');

    auth()->logout();

    expect(renderAntlers('{{ certificates }}[{{ course_title }}]{{ /certificates }}'))->toBe('');
});

it('finds the certificate for one course, and nothing for a guest or another course', function () {
    $ada = $this->makeUser('ada@example.com', 'Ada');
    $course = $this->makeCourse('Kurs');
    $certificate = Certificates::issue($ada, $course);
    $other = $this->makeCourse('Anderer');

    expect(renderAntlers('{{ certificates:for course="'.$course.'" }}{{ code }}{{ /certificates:for }}'))->toBe('');

    $this->actingAs($ada);

    expect(renderAntlers('{{ certificates:for course="'.$course.'" }}{{ code }}{{ /certificates:for }}'))->toBe($certificate->code)
        ->and(renderAntlers('{{ certificates:for course="'.$other.'" }}{{ code }}{{ /certificates:for }}'))->toBe('');
});

it('leaves the download URL off a revoked certificate', function () {
    $ada = $this->makeUser('ada@example.com', 'Ada');
    $certificate = Certificates::issue($ada, $this->makeCourse('Kurs'));
    Certificates::revoke($certificate, 'Aberkannt');

    $this->actingAs($ada);

    expect(renderAntlers('{{ certificates }}{{ if is_revoked }}revoked{{ /if }}|{{ download_url }}{{ /certificates }}'))->toBe('revoked|');
});

it('mails the PDF when mail is on, and not when it is off', function () {
    Mail::fake();
    $ada = $this->makeUser('ada@example.com', 'Ada');

    Certificates::issue($ada, $this->makeCourse('Eins'));
    Mail::assertNothingQueued();

    config(['certificates.mail.enabled' => true]);
    $certificate = Certificates::issue($ada, $this->makeCourse('Zwei'));

    Mail::assertQueued(CertificateMail::class, function (CertificateMail $mail) use ($certificate) {
        return $mail->hasTo('ada@example.com') && $mail->certificate->is($certificate);
    });
});

it('attaches a real PDF to the mail', function () {
    $certificate = Certificates::issue($this->makeUser('ada@example.com', 'Ada'), $this->makeCourse('Kurs'));

    $mail = new CertificateMail($certificate);
    $attachment = $mail->attachments()[0];
    $data = null;
    $attachment->attachWith(fn () => null, function ($resolver) use (&$data) {
        $data = $resolver();
    });

    expect(substr((string) $data, 0, 5))->toBe('%PDF-');
    $mail->assertSeeInText('Kurs');
});

it('refuses to issue by hand without a completed enrollment, unless forced', function () {
    $ada = $this->makeUser('ada@example.com', 'Ada');
    $course = $this->makeCourse('Kurs');
    Enrollment::query()->create(['user_id' => (string) $ada->id(), 'course_entry_id' => $course, 'current_week' => 1]);

    $this->artisan('certificates:issue', ['user' => 'ada@example.com', 'course' => $course])
        ->expectsOutputToContain('--force')
        ->assertFailed();
    expect(Certificate::query()->count())->toBe(0);

    $this->artisan('certificates:issue', ['user' => 'ada@example.com', 'course' => $course, '--force' => true])->assertSuccessful();
    expect(Certificate::query()->count())->toBe(1);
});

it('issues by hand from the command, once, dated at the completion', function () {
    $ada = $this->makeUser('ada@example.com', 'Ada');
    $course = $this->makeCourse('Kurs');
    $completedAt = Carbon::parse('2026-04-02 09:30:00');
    Enrollment::query()->create(['user_id' => (string) $ada->id(), 'course_entry_id' => $course, 'current_week' => 1, 'completed_at' => $completedAt]);

    $this->artisan('certificates:issue', ['user' => 'ada@example.com', 'course' => $course])->assertSuccessful();
    $this->artisan('certificates:issue', ['user' => (string) $ada->id(), 'course' => $course])
        ->expectsOutputToContain('Already issued')
        ->assertSuccessful();

    expect(Certificate::query()->count())->toBe(1)
        ->and(Certificate::query()->sole()->issued_at->equalTo($completedAt))->toBeTrue();
});

it('does not mail from the backfill unless asked to', function () {
    Mail::fake();
    config(['certificates.mail.enabled' => true]);
    $ada = $this->makeUser('ada@example.com', 'Ada');
    $grace = $this->makeUser('grace@example.com', 'Grace');
    $course = $this->makeCourse('Kurs');
    Enrollment::query()->create(['user_id' => (string) $ada->id(), 'course_entry_id' => $course, 'current_week' => 1, 'completed_at' => now()]);

    $this->artisan('certificates:issue', ['--backfill' => true])->assertSuccessful();
    Mail::assertNothingQueued();

    Enrollment::query()->create(['user_id' => (string) $grace->id(), 'course_entry_id' => $course, 'current_week' => 1, 'completed_at' => now()]);
    $this->artisan('certificates:issue', ['--backfill' => true, '--mail' => true])->assertSuccessful();
    Mail::assertQueued(CertificateMail::class, 1);
});

it('takes the course by slug as well as by id', function () {
    $ada = $this->makeUser('ada@example.com', 'Ada');
    $course = $this->makeCourse('Stimmbildung im Chor');
    Enrollment::query()->create(['user_id' => (string) $ada->id(), 'course_entry_id' => $course, 'current_week' => 1, 'completed_at' => now()]);

    $this->artisan('certificates:issue', ['user' => 'ada@example.com', 'course' => 'stimmbildung-im-chor'])->assertSuccessful();
    $this->artisan('certificates:issue', ['user' => 'ada@example.com', 'course' => $course])
        ->expectsOutputToContain('Already issued')
        ->assertSuccessful();

    expect(Certificate::query()->sole()->course_id)->toBe($course);

    $this->artisan('certificates:issue', ['user' => 'ada@example.com', 'course' => 'gibt-es-nicht'])
        ->expectsOutputToContain('No course')
        ->assertFailed();
});

it('does not mail a manual issue with --no-mail', function () {
    Mail::fake();
    config(['certificates.mail.enabled' => true]);
    $ada = $this->makeUser('ada@example.com', 'Ada');

    $this->artisan('certificates:issue', ['user' => 'ada@example.com', 'course' => $this->makeCourse('Kurs'), '--force' => true, '--no-mail' => true])
        ->assertSuccessful();

    Mail::assertNothingQueued();
    expect(Certificate::query()->count())->toBe(1);
});

it('backfills from completed enrollments, dated at completion', function () {
    $ada = $this->makeUser('ada@example.com', 'Ada');
    $grace = $this->makeUser('grace@example.com', 'Grace');
    $course = $this->makeCourse('Kurs');
    $completedAt = Carbon::parse('2026-03-01 10:00:00');

    Enrollment::query()->create(['user_id' => (string) $ada->id(), 'course_entry_id' => $course, 'current_week' => 1, 'completed_at' => $completedAt]);
    Enrollment::query()->create(['user_id' => (string) $grace->id(), 'course_entry_id' => $course, 'current_week' => 1]);

    $this->artisan('certificates:issue', ['--backfill' => true, '--dry-run' => true])->assertSuccessful();
    expect(Certificate::query()->count())->toBe(0);

    $this->artisan('certificates:issue', ['--backfill' => true])->assertSuccessful();
    $this->artisan('certificates:issue', ['--backfill' => true])->expectsOutputToContain('already had one 1')->assertSuccessful();

    $certificate = Certificate::query()->sole();
    expect($certificate->subject_id)->toBe((string) $ada->id())
        ->and($certificate->issued_at->equalTo($completedAt))->toBeTrue();
});
