<?php

namespace Goldnead\Certificates\Listeners;

use Goldnead\Certificates\Certificates;
use Goldnead\Courses\Events\CourseCompleted;
use Throwable;

/**
 * statamic-courses announced a finished course: issue its certificate.
 *
 * Wired by core's listener discovery from the type hint below.
 *
 * The event fires inside the learner's request that completed the last
 * lesson. A certificate that cannot be issued (a deleted user, a course entry
 * gone) must not turn that write into a 500, so the failure is reported to
 * the exception handler, not swallowed and not rethrown. `certificates:issue
 * --backfill` issues whatever was missed.
 */
class IssueCertificateOnCourseCompleted
{
    public function __construct(protected Certificates $certificates) {}

    public function handle(CourseCompleted $event): void
    {
        if (! config('certificates.issue_on_completion', true)) {
            return;
        }

        try {
            $this->certificates->issue($event->userId, $event->courseId);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
