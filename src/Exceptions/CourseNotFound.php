<?php

namespace Goldnead\Certificates\Exceptions;

use RuntimeException;

class CourseNotFound extends RuntimeException
{
    public function __construct(public readonly string $courseId)
    {
        parent::__construct("statamic-certificates: no entry with the id [{$courseId}], so there is no course title to certify.");
    }
}
