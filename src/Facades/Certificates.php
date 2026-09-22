<?php

namespace Goldnead\Certificates\Facades;

use Goldnead\Certificates\Certificates as Manager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Goldnead\Certificates\Models\Certificate issue(mixed $subject, string $courseId, ?\DateTimeInterface $issuedAt = null)
 * @method static \Goldnead\Certificates\Models\Certificate|null find(mixed $subject, string $courseId)
 * @method static \Illuminate\Support\Collection<int, \Goldnead\Certificates\Models\Certificate> for(mixed $subject)
 * @method static \Goldnead\Certificates\Models\Certificate|null findByCode(string $code)
 * @method static \Goldnead\Certificates\Models\Certificate revoke(\Goldnead\Certificates\Models\Certificate $certificate, string $reason)
 * @method static string pdf(\Goldnead\Certificates\Models\Certificate $certificate)
 * @method static string|null verifyUrl(\Goldnead\Certificates\Models\Certificate $certificate)
 * @method static mixed withoutMail(callable $callback)
 * @method static bool mailSuppressed()
 * @method static string|null downloadUrl(\Goldnead\Certificates\Models\Certificate $certificate)
 *
 * @see Manager
 */
class Certificates extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Manager::class;
    }
}
