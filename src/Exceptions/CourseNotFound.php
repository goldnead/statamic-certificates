<?php

namespace Goldnead\Certificates\Exceptions;

class CourseNotFound extends CertificateRefused
{
    public function __construct(public readonly string $courseId)
    {
        parent::__construct("statamic-certificates: no entry with the id [{$courseId}], so there is no course title to certify.");
    }

    public function reason(): string
    {
        return 'course_not_found';
    }
}
