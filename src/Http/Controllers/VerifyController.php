<?php

namespace Goldnead\Certificates\Http\Controllers;

use Goldnead\Certificates\CertificatePdf;
use Goldnead\Certificates\Models\Certificate;
use Goldnead\Certificates\Support\CertificateCode;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * GET /certificates/verify/{code}: valid, revoked or unknown.
 *
 * **No enumeration.** A malformed code and a well-formed code nobody was
 * issued get the same page, the same status code (200) and the same headers.
 * Only the lookup differs, and a malformed code still runs one, so the answer
 * does not arrive measurably faster. The route is throttled on top.
 */
class VerifyController extends Controller
{
    public function __invoke(string $code, CertificatePdf $pdf): Response
    {
        // A route cache built while the switch was on must not keep it open.
        abort_unless(config('certificates.routes.enabled', true), 404);

        // One query either way: a malformed code looks up a value no row can hold.
        $certificate = Certificate::query()->acrossBrands()
            ->where('code', CertificateCode::normalize($code) ?? '-')
            ->first();

        $status = match (true) {
            $certificate === null => 'unknown',
            $certificate->isRevoked() => 'revoked',
            default => 'valid',
        };

        $issuerName = $certificate
            ? $pdf->inBrandOf($certificate, fn () => config('certificates.template.issuer_name'))
            : null;

        return response()
            ->view('certificates::verify', [
                'status' => $status,
                'certificate' => $certificate,
                'code' => $certificate ? CertificateCode::format($certificate->code) : null,
                'issuerName' => is_string($issuerName) && $issuerName !== '' ? $issuerName : null,
            ])
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Cache-Control', 'no-store, private');
    }
}
