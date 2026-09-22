<?php

use Goldnead\Certificates\CertificatePdf;
use Goldnead\Certificates\Events\CertificateIssued;
use Goldnead\Certificates\Exceptions\CourseNotFound;
use Goldnead\Certificates\Facades\Certificates;
use Goldnead\Certificates\Models\Certificate;
use Goldnead\Courses\Events\CourseCompleted;
use Illuminate\Support\Facades\Event;
use Statamic\Facades\Entry;

it('issues a certificate when statamic-courses fires CourseCompleted', function () {
    $user = $this->makeUser('ada@example.com', 'Ada Lovelace');
    $course = $this->makeCourse('Stimmbildung im Chor');

    CourseCompleted::dispatch((string) $user->id(), $course, 'stimmbildung-im-chor');

    $certificate = Certificate::query()->sole();

    expect($certificate->subject_type)->toBe('user')
        ->and($certificate->subject_id)->toBe((string) $user->id())
        ->and($certificate->course_id)->toBe($course)
        ->and($certificate->learner_name)->toBe('Ada Lovelace')
        ->and($certificate->course_title)->toBe('Stimmbildung im Chor')
        ->and($certificate->code)->toMatch('/^[0-9A-HJKMNP-TV-Z]{20}$/')
        ->and($certificate->revoked_at)->toBeNull();
});

it('does not issue from the event when issue_on_completion is off', function () {
    config(['certificates.issue_on_completion' => false]);
    $user = $this->makeUser('ada@example.com', 'Ada');

    CourseCompleted::dispatch((string) $user->id(), $this->makeCourse('Kurs'), 'kurs');

    expect(Certificate::query()->count())->toBe(0);
});

it('issues one certificate per learner and course, however often it is asked', function () {
    Event::fake([CertificateIssued::class]);
    $user = $this->makeUser('ada@example.com', 'Ada');
    $course = $this->makeCourse('Kurs');

    $first = Certificates::issue($user, $course);
    $second = Certificates::issue((string) $user->id(), $course);
    CourseCompleted::dispatch((string) $user->id(), $course, 'kurs');

    expect($second->id)->toBe($first->id)
        ->and(Certificate::query()->count())->toBe(1);

    Event::assertDispatchedTimes(CertificateIssued::class, 1);
});

it('lets the unique index, not a prior read, settle a race', function () {
    $user = $this->makeUser('ada@example.com', 'Ada');
    $course = $this->makeCourse('Kurs');

    // The row a concurrent request wrote between our read and our insert.
    $winner = Certificate::query()->create([
        'code' => 'ABCDEFGHJKMNPQRSTVWX',
        'subject_type' => 'user',
        'subject_id' => (string) $user->id(),
        'course_id' => $course,
        'learner_name' => 'Ada',
        'course_title' => 'Kurs',
        'issued_at' => now(),
    ]);

    // Simulate the stale read: the pre-check sees nothing on its first call.
    $manager = new class(app(CertificatePdf::class)) extends Goldnead\Certificates\Certificates
    {
        public int $calls = 0;

        public function find(mixed $subject, string $courseId): ?Certificate
        {
            return $this->calls++ === 0 ? null : parent::find($subject, $courseId);
        }
    };

    $result = $manager->issue($user, $course);

    expect($result->id)->toBe($winner->id)
        ->and(Certificate::query()->count())->toBe(1);
});

it('also issues for different courses and different learners', function () {
    $ada = $this->makeUser('ada@example.com', 'Ada');
    $grace = $this->makeUser('grace@example.com', 'Grace');
    $one = $this->makeCourse('Eins');
    $two = $this->makeCourse('Zwei');

    Certificates::issue($ada, $one);
    Certificates::issue($ada, $two);
    Certificates::issue($grace, $one);

    expect(Certificate::query()->count())->toBe(3)
        ->and(Certificate::query()->pluck('code')->unique())->toHaveCount(3);
});

it('keeps the snapshot when the course and the learner are renamed', function () {
    $user = $this->makeUser('ada@example.com', 'Ada Lovelace');
    $course = $this->makeCourse('Stimmbildung im Chor');

    $certificate = Certificates::issue($user, $course);

    Entry::find($course)->set('title', 'Neuer Titel')->save();
    $user->set('name', 'Ada King')->save();

    $fresh = $certificate->fresh();

    expect($fresh->course_title)->toBe('Stimmbildung im Chor')
        ->and($fresh->learner_name)->toBe('Ada Lovelace')
        ->and(Certificates::issue($user, $course)->course_title)->toBe('Stimmbildung im Chor');
});

it('refuses a course id that is no entry', function () {
    $user = $this->makeUser('ada@example.com', 'Ada');

    Certificates::issue($user, 'does-not-exist');
})->throws(CourseNotFound::class);

it('does not break the learner request when the event cannot be certified', function () {
    CourseCompleted::dispatch('no-such-user', $this->makeCourse('Kurs'), 'kurs');

    expect(Certificate::query()->count())->toBe(0);
});

it('falls back to the email when the user has no name', function () {
    $user = $this->makeUser('ada@example.com');

    expect(Certificates::issue($user, $this->makeCourse('Kurs'))->learner_name)->toBe('ada@example.com');
});
