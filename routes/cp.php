<?php

use Goldnead\Certificates\Http\Controllers\Cp\CertificatesController;
use Illuminate\Support\Facades\Route;

// The switch bites here as well as on the nav item: a hidden entry with a
// reachable URL is not a disabled screen.
if (! config('certificates.cp.enabled', true)) {
    return;
}

Route::middleware('can:manage certificates')->group(function (): void {
    Route::get('certificates', [CertificatesController::class, 'index'])->name('certificates.index');
    Route::post('certificates/{certificate}/revoke', [CertificatesController::class, 'revoke'])
        ->whereNumber('certificate')
        ->name('certificates.revoke');
});
