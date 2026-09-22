<?php

namespace Goldnead\Certificates\Events;

use Goldnead\Certificates\Models\Certificate;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A certificate was created. Fired once per certificate: an issue() call that
 * finds an existing one does not fire it again.
 */
class CertificateIssued
{
    use Dispatchable;

    public function __construct(public readonly Certificate $certificate) {}
}
