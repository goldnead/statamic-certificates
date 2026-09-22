<?php

namespace Goldnead\Certificates\Console\Commands;

use Goldnead\Certificates\Certificates;
use Goldnead\Certificates\Support\CourseBrand;
use Goldnead\Certificates\Support\Subject;
use Goldnead\Courses\Models\Enrollment;
use Illuminate\Console\Command;
use RuntimeException;
use Statamic\Facades\User;
use Throwable;

/**
 * Issue one certificate by hand, or every missing one from statamic-courses'
 * completed enrollments.
 *
 *   php artisan certificates:issue {user} {course} [--force] [--no-mail] [--brand=]
 *   php artisan certificates:issue --backfill [--dry-run] [--mail] [--brand=]
 *
 * **Only for completed courses.** A manual issue needs a completed enrollment
 * in statamic-courses; `--force` issues without one (a course finished
 * outside the site). Each certificate is dated at the enrollment's completion.
 *
 * **In the course's brand.** The console has no current brand. Each issue runs
 * in the brand the course's site maps to (`brand-context.sites`), or the one
 * `--brand` names. With multi-brand on and neither, it refuses instead of
 * stamping the default brand's issuer on another brand's certificate.
 *
 * **Mail.** A manual issue mails as configured, `--no-mail` suppresses it.
 * The backfill does not mail unless `--mail` is passed.
 *
 * Idempotent either way: an existing certificate is reported, not duplicated.
 */
class Issue extends Command
{
    protected $signature = 'certificates:issue
        {user? : The user id (or email)}
        {course? : The course entry id}
        {--backfill : Issue for every completed course enrollment that has no certificate}
        {--dry-run : With --backfill, list what would be issued}
        {--force : Issue by hand even without a completed enrollment}
        {--brand= : Brand handle, when the course\'s site maps to none}
        {--mail : With --backfill, mail the certificates as configured}
        {--no-mail : Do not mail the certificate}';

    protected $description = 'Issue a completion certificate, or backfill all missing ones';

    public function handle(Certificates $certificates): int
    {
        if ($this->option('backfill')) {
            return $this->backfill($certificates);
        }

        $user = $this->argument('user');
        $course = $this->argument('course');

        if (! is_string($user) || ! is_string($course)) {
            $this->error('Pass a user and a course, or --backfill.');

            return self::FAILURE;
        }

        $subject = User::find($user) ?? User::findByEmail($user);

        if (! $subject) {
            $this->error("No user [{$user}].");

            return self::FAILURE;
        }

        $enrollment = Enrollment::query()
            ->where('user_id', Subject::of($subject)->id)
            ->where('course_entry_id', $course)
            ->whereNotNull('completed_at')
            ->first();

        try {
            $existing = $certificates->find($subject, $course);

            if (! $existing && ! $enrollment && ! $this->option('force')) {
                $this->error("[{$user}] has not completed the course [{$course}] in statamic-courses. Pass --force to issue anyway.");

                return self::FAILURE;
            }

            $issue = fn () => $certificates->issue($subject, $course, $enrollment?->completed_at);

            $certificate = CourseBrand::run($this->brandFor($course), $this->option('no-mail')
                ? fn () => $certificates->withoutMail($issue)
                : $issue);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line(($existing ? 'Already issued: ' : 'Issued: ').$certificate->code);

        return self::SUCCESS;
    }

    protected function backfill(Certificates $certificates): int
    {
        $issued = $skipped = $failed = 0;
        $dryRun = (bool) $this->option('dry-run');
        $mail = (bool) $this->option('mail') && ! $this->option('no-mail');

        Enrollment::query()->whereNotNull('completed_at')->orderBy('id')->each(function (Enrollment $enrollment) use ($certificates, $dryRun, $mail, &$issued, &$skipped, &$failed) {
            $userId = (string) $enrollment->user_id;
            $courseId = (string) $enrollment->course_entry_id;
            $label = "user {$userId}, course {$courseId}";

            try {
                if ($certificates->find($userId, $courseId)) {
                    $skipped++;

                    return;
                }

                $brand = $this->brandFor($courseId);

                if ($dryRun) {
                    $this->line("Would issue: {$label}".($brand ? " (brand {$brand})" : ''));
                    $issued++;

                    return;
                }

                $issue = fn () => $certificates->issue($userId, $courseId, $enrollment->completed_at);

                CourseBrand::run($brand, $mail ? $issue : fn () => $certificates->withoutMail($issue));

                $this->line("Issued: {$label}");
                $issued++;
            } catch (Throwable $e) {
                $this->warn("Failed: {$label}: {$e->getMessage()}");
                $failed++;
            }
        });

        $this->info(($dryRun ? 'Would issue' : 'Issued')." {$issued}, already had one {$skipped}, failed {$failed}.".($mail || $dryRun ? '' : ' No mail sent (pass --mail to send).'));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * `--brand` if given, else the brand of the course's site; null in
     * single-brand mode. Throws when multi-brand is on and neither says.
     */
    protected function brandFor(string $courseId): ?string
    {
        if (! CourseBrand::multiBrand()) {
            return null;
        }

        $brand = $this->option('brand');
        $brand = is_string($brand) && $brand !== '' ? $brand : (CourseBrand::of($courseId) ?? CourseBrand::onlyOne());

        if ($brand === null) {
            throw new RuntimeException("The course [{$courseId}] is in a site no brand is mapped to (brand-context.sites). Pass --brand=<handle>.");
        }

        return $brand;
    }
}
