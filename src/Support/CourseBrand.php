<?php

namespace Goldnead\Certificates\Support;

use Goldnead\BrandContext\BrandManager;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Entry;

/**
 * The brand a course belongs to, for code that runs without one (console,
 * queue).
 *
 * A course entry lives in a Statamic site, and brand-context maps sites to
 * brands (`brand-context.sites`). That mapping is the only brand a course
 * carries; there is no brand field on entries. Null when the course's site is
 * not mapped, and the caller must then be told the brand (`--brand`) rather
 * than guess: guessing is how a second brand's certificate ends up with the
 * first brand's issuer printed on it.
 */
final class CourseBrand
{
    public static function multiBrand(): bool
    {
        $brands = app()->bound('brand-context') ? app('brand-context') : null;

        return $brands instanceof BrandManager && $brands->multiBrandEnabled();
    }

    public static function of(string $courseId): ?string
    {
        $course = Entry::find($courseId);

        if (! $course instanceof StatamicEntry) {
            return null;
        }

        $handle = ((array) config('brand-context.sites', []))[$course->locale()] ?? null;

        return is_string($handle) && $handle !== '' ? $handle : null;
    }

    /**
     * Run the callback in the given brand when multi-brand is on; as is otherwise.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function run(?string $brand, callable $callback): mixed
    {
        if ($brand === null || ! self::multiBrand()) {
            return $callback();
        }

        return app('brand-context')->runFor($brand, $callback);
    }
}
