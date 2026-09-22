<?php

namespace Goldnead\Certificates\Exceptions;

use RuntimeException;

/**
 * A revoked certificate is not rendered, stored or sent. Its verification page
 * still says what happened to it.
 */
class CertificateIsRevoked extends RuntimeException
{
    public function __construct(public readonly string $certificateCode)
    {
        parent::__construct("statamic-certificates: the certificate [{$certificateCode}] is revoked and is not rendered.");
    }
}
