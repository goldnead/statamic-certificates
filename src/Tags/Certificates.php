<?php

namespace Goldnead\Certificates\Tags;

use Goldnead\Certificates\Certificates as Manager;
use Goldnead\Certificates\Models\Certificate;
use Goldnead\Certificates\Support\CertificateCode;
use Goldnead\Certificates\Support\Subject;
use Statamic\Tags\Tags;

/**
 * Antlers access to the signed-in user's certificates.
 *
 *   {{ certificates }}…{{ /certificates }}            every certificate, newest first
 *   {{ certificates:for course="{id}" }}…{{ /certificates:for }}   the one for this course
 *
 * Each item: code, code_formatted, course_id, course_title, learner_name,
 * issued_at, is_revoked, download_url, verify_url. A guest gets nothing;
 * `{{ certificates:for }}` renders nothing when there is no certificate.
 * Revoked certificates are listed with is_revoked and no download_url.
 */
class Certificates extends Tags
{
    protected static $handle = 'certificates';

    /**
     * @return list<array<string, mixed>>
     */
    public function index(): array
    {
        $user = Subject::current();

        if (! $user) {
            return [];
        }

        return $this->manager()->for($user)->map(fn (Certificate $c) => $this->toArray($c))->values()->all();
    }

    /**
     * @return array<string, mixed>|string
     */
    public function for(): array|string
    {
        $user = Subject::current();
        $course = $this->params->get('course');

        if (! $user || ! is_string($course) || $course === '') {
            return '';
        }

        $certificate = $this->manager()->find($user, $course);

        return $certificate ? $this->toArray($certificate) : '';
    }

    /**
     * @return array<string, mixed>
     */
    protected function toArray(Certificate $certificate): array
    {
        $manager = $this->manager();

        return [
            'code' => $certificate->code,
            'code_formatted' => CertificateCode::format($certificate->code),
            'course_id' => $certificate->course_id,
            'course_title' => $certificate->course_title,
            'learner_name' => $certificate->learner_name,
            'issued_at' => $certificate->issued_at,
            'is_revoked' => $certificate->isRevoked(),
            'download_url' => $certificate->isRevoked() ? null : $manager->downloadUrl($certificate),
            'verify_url' => $manager->verifyUrl($certificate),
        ];
    }

    protected function manager(): Manager
    {
        return app(Manager::class);
    }
}
