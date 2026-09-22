<?php

namespace Goldnead\Certificates\Events;

use Goldnead\Certificates\Models\Certificate;
use Illuminate\Foundation\Events\Dispatchable;

class CertificateRevoked
{
    use Dispatchable;

    public function __construct(public readonly Certificate $certificate) {}
}
