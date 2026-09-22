<?php

namespace Goldnead\Certificates;

use DateTimeInterface;
use Goldnead\Certificates\Events\CertificateIssued;
use Goldnead\Certificates\Events\CertificateRevoked;
use Goldnead\Certificates\Exceptions\CourseNotFound;
use Goldnead\Certificates\Models\Certificate;
use Goldnead\Certificates\Support\CertificateCode;
use Goldnead\Certificates\Support\Subject;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Statamic\Facades\Entry;

/**
 * The public API: issue, look up, revoke, and the two URLs a certificate has.
 *
 * Issuing is idempotent. The unique index on (subject, course) decides, not a
 * lock and not a prior read: the read before the insert only saves the work of
 * snapshotting in the common case, the index settles the race.
 */
class Certificates
{
    public function __construct(protected CertificatePdf $pdf) {}

    /**
     * The certificate for this subject and course, issued now if there is none.
     *
     * CertificateIssued fires only when this call created the row.
     *
     * @throws CourseNotFound
     */
    public function issue(mixed $subject, string $courseId, ?DateTimeInterface $issuedAt = null): Certificate
    {
        $subject = Subject::of($subject);

        if ($existing = $this->find($subject, $courseId)) {
            return $existing;
        }

        $course = Entry::find($courseId);

        if (! $course instanceof \Statamic\Entries\Entry) {
            throw new CourseNotFound($courseId);
        }

        try {
            // Inside its own transaction so a failed insert rolls back to a
            // savepoint and leaves an enclosing transaction usable.
            $certificate = DB::transaction(fn () => Certificate::query()->create([
                'code' => CertificateCode::generate(),
                'subject_type' => $subject->type,
                'subject_id' => $subject->id,
                'course_id' => $courseId,
                'learner_name' => $subject->name ?? $subject->email ?? $subject->id,
                'course_title' => (string) ($course->get('title') ?? $course->slug()),
                'issued_at' => $issuedAt ? Carbon::instance($issuedAt) : now(),
            ]));
        } catch (UniqueConstraintViolationException) {
            return $this->find($subject, $courseId)
                ?? throw new \RuntimeException('statamic-certificates: the insert collided, but no certificate is there to read.');
        }

        CertificateIssued::dispatch($certificate);

        return $certificate;
    }

    /**
     * Across brands: the unique index is, too.
     */
    public function find(mixed $subject, string $courseId): ?Certificate
    {
        $subject = $subject instanceof Subject ? $subject : Subject::of($subject);

        return Certificate::query()->acrossBrands()
            ->where('subject_type', $subject->type)
            ->where('subject_id', $subject->id)
            ->where('course_id', $courseId)
            ->first();
    }

    /**
     * The subject's certificates in the current brand, newest first.
     *
     * @return Collection<int, Certificate>
     */
    public function for(mixed $subject): Collection
    {
        $subject = $subject instanceof Subject ? $subject : Subject::of($subject);

        return Certificate::query()
            ->where('subject_type', $subject->type)
            ->where('subject_id', $subject->id)
            ->orderByDesc('issued_at')
            ->get();
    }

    public function findByCode(string $code): ?Certificate
    {
        return Certificate::findByCode($code);
    }

    /**
     * Revoked certificates stay in the table, so the verification page can say
     * "revoked" rather than "unknown". Revoking twice keeps the first date.
     */
    public function revoke(Certificate $certificate, string $reason): Certificate
    {
        if ($certificate->isRevoked()) {
            return $certificate;
        }

        $certificate->forceFill([
            'revoked_at' => now(),
            'revoked_reason' => trim($reason),
        ])->save();

        // The stored PDF of a revoked certificate is not served any more.
        $this->pdf->forget($certificate);

        CertificateRevoked::dispatch($certificate);

        return $certificate;
    }

    /**
     * The PDF bytes, from storage, rendered and stored first if missing.
     */
    public function pdf(Certificate $certificate): string
    {
        return $this->pdf->get($certificate);
    }

    public function verifyUrl(Certificate $certificate): ?string
    {
        return Route::has('certificates.verify')
            ? route('certificates.verify', ['code' => $certificate->code])
            : null;
    }

    public function downloadUrl(Certificate $certificate): ?string
    {
        return Route::has('certificates.download')
            ? route('certificates.download', ['code' => $certificate->code])
            : null;
    }
}
