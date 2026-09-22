<?php

namespace Goldnead\Certificates\Console\Commands;

use Goldnead\Certificates\Certificates;
use Goldnead\Courses\Models\Enrollment;
use Illuminate\Console\Command;
use Statamic\Facades\User;
use Throwable;

/**
 * Issue one certificate by hand, or every missing one from statamic-courses'
 * completed enrollments.
 *
 *   php artisan certificates:issue {user} {course}
 *   php artisan certificates:issue --backfill [--dry-run]
 *
 * Idempotent either way: an existing certificate is reported, not duplicated.
 * The backfill dates each certificate at the enrollment's completion, not at
 * the time the command ran.
 */
class Issue extends Command
{
    protected $signature = 'certificates:issue
        {user? : The user id (or email)}
        {course? : The course entry id}
        {--backfill : Issue for every completed course enrollment that has no certificate}
        {--dry-run : With --backfill, list what would be issued}';

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

        try {
            $existing = $certificates->find($subject, $course);
            $certificate = $certificates->issue($subject, $course);
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

        Enrollment::query()->whereNotNull('completed_at')->orderBy('id')->each(function (Enrollment $enrollment) use ($certificates, $dryRun, &$issued, &$skipped, &$failed) {
            $label = "user {$enrollment->user_id}, course {$enrollment->course_entry_id}";

            try {
                if ($certificates->find((string) $enrollment->user_id, (string) $enrollment->course_entry_id)) {
                    $skipped++;

                    return;
                }

                if ($dryRun) {
                    $this->line("Would issue: {$label}");
                    $issued++;

                    return;
                }

                $certificates->issue((string) $enrollment->user_id, (string) $enrollment->course_entry_id, $enrollment->completed_at);
                $this->line("Issued: {$label}");
                $issued++;
            } catch (Throwable $e) {
                $this->warn("Failed: {$label}: {$e->getMessage()}");
                $failed++;
            }
        });

        $this->info(($dryRun ? 'Would issue' : 'Issued')." {$issued}, already had one {$skipped}, failed {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
