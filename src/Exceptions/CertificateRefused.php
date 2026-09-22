<?php

namespace Goldnead\Certificates\Exceptions;

use RuntimeException;

/**
 * Issuing was refused for a reason the caller can act on. The event path
 * turns this into a CertificateNotIssued event and a log line instead of an
 * error report; the manual path (API, command) sees the exception.
 */
abstract class CertificateRefused extends RuntimeException
{
    /** A stable machine-readable reason, e.g. `learner_name_missing`. */
    abstract public function reason(): string;
}
