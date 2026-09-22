<?php

namespace Goldnead\Certificates\Exceptions;

/**
 * The learner has no name (neither `name` nor first/last name).
 *
 * Refused rather than falling back to the email address: the learner name is
 * printed on the PDF and shown on the public verification page, and an email
 * address has no business on either.
 */
class LearnerNameMissing extends CertificateRefused
{
    public function __construct(public readonly string $subjectType, public readonly string $subjectId)
    {
        parent::__construct("statamic-certificates: the learner [{$subjectType}:{$subjectId}] has no name to print on a certificate.");
    }

    public function reason(): string
    {
        return 'learner_name_missing';
    }
}
