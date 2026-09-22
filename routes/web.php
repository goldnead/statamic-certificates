<?php

use Goldnead\Certificates\Http\Controllers\DownloadController;
use Goldnead\Certificates\Http\Controllers\VerifyController;
use Illuminate\Support\Facades\Route;

/*
 * Mounted by Statamic inside the `web` group (sessions, auth).
 *
 * `certificates.routes.enabled` switches both off. Checked here, so a disabled
 * route does not exist, and again in the controllers, so a route cache built
 * while it was on cannot keep it open.
 */
if (config('certificates.routes.enabled', true)) {
    $prefix = trim((string) config('certificates.routes.prefix', 'certificates'), '/');
    $throttle = 'throttle:'.config('certificates.routes.throttle', '30,1');

    Route::prefix($prefix)->middleware($throttle)->group(function (): void {
        Route::get('verify/{code}', VerifyController::class)
            ->where('code', '[^/]+')
            ->name('certificates.verify');

        Route::get('{code}/download', DownloadController::class)
            ->where('code', '[^/]+')
            ->name('certificates.download');
    });
}
