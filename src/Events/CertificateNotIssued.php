<?php

namespace Goldnead\Certificates\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A completed course did not get its certificate, and why.
 *
 * Fired from the CourseCompleted listener only; the manual path throws the
 * CertificateRefused exception instead. `reason` is the exception's reason()
 * code, e.g. `learner_name_missing` or `course_not_found`.
 */
class CertificateNotIssued
{
    use Dispatchable;

    public function __construct(
        public readonly string $subjectId,
        public readonly string $courseId,
        public readonly string $reason,
        public readonly string $message,
    ) {}
}
