<?php

use Goldnead\Certificates\Models\Certificate;
use Goldnead\Courses\Contracts\CourseAccess;
use Goldnead\Courses\CourseProgress;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;

/*
 * The whole way, without a hand-dispatched event: statamic-courses' own API
 * completes the lessons, decides the course is finished, fires CourseCompleted,
 * and this addon's listener issues the certificate.
 */

beforeEach(function () {
    if (! Collection::find('course_lessons')) {
        Collection::make('course_lessons')->title('Lessons')->save();
    }

    // Who may open a course is the site's decision (entitlements in
    // production); here everyone may.
    app()->instance(CourseAccess::class, new class implements CourseAccess
    {
        public function allows(mixed $user, array $course): bool
        {
            return true;
        }
    });
});

function makeLesson(string $courseId, string $slug, int $order): void
{
    Entry::make()->collection('course_lessons')->slug($slug)
        ->data(['title' => ucfirst($slug), 'course' => $courseId, 'sort_order' => $order])
        ->save();
}

it('issues the certificate when the last lesson is completed through statamic-courses', function () {
    $ada = $this->makeUser('ada@example.com', 'Ada Lovelace');
    $course = $this->makeCourse('Stimmbildung im Chor');
    makeLesson($course, 'atmung', 1);
    makeLesson($course, 'resonanz', 2);

    $progress = app(CourseProgress::class);

    expect($progress->setLessonCompletion($ada, 'stimmbildung-im-chor', 'atmung', true))->not->toBeNull()
        ->and(Certificate::query()->count())->toBe(0);

    $progress->setLessonCompletion($ada, 'stimmbildung-im-chor', 'resonanz', true);

    $certificate = Certificate::query()->sole();

    expect($certificate->subject_id)->toBe((string) $ada->id())
        ->and($certificate->course_id)->toBe($course)
        ->and($certificate->learner_name)->toBe('Ada Lovelace')
        ->and($certificate->course_title)->toBe('Stimmbildung im Chor');

    // Reopening and completing again does not complete the course twice.
    $progress->setLessonCompletion($ada, 'stimmbildung-im-chor', 'resonanz', false);
    $progress->setLessonCompletion($ada, 'stimmbildung-im-chor', 'resonanz', true);

    expect(Certificate::query()->count())->toBe(1);
});
