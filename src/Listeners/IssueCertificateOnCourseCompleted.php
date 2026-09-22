<?php

namespace Goldnead\Certificates\Listeners;

use Goldnead\Certificates\Certificates;
use Goldnead\Certificates\Events\CertificateNotIssued;
use Goldnead\Certificates\Exceptions\CertificateRefused;
use Goldnead\Courses\Events\CourseCompleted;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * statamic-courses announced a finished course: issue its certificate.
 *
 * Wired by core's listener discovery from the type hint below.
 *
 * The event fires inside the learner's request that completed the last
 * lesson. A certificate that cannot be issued must not turn that write into a
 * 500. A refusal with a known reason (no learner name, course entry gone) is
 * logged and announced as CertificateNotIssued; anything else is reported to
 * the exception handler. Neither is swallowed, neither is rethrown. `certificates:issue
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
        } catch (CertificateRefused $e) {
            Log::warning($e->getMessage(), ['reason' => $e->reason(), 'user' => $event->userId, 'course' => $event->courseId]);
            CertificateNotIssued::dispatch($event->userId, $event->courseId, $e->reason(), $e->getMessage());
        } catch (Throwable $e) {
            report($e);
        }
    }
}
