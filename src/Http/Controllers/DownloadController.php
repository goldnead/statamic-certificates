<?php

namespace Goldnead\Certificates\Http\Controllers;

use Goldnead\Certificates\Certificates;
use Goldnead\Certificates\Models\Certificate;
use Goldnead\Certificates\Support\Subject;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /certificates/{code}/download: the PDF, for its owner, in the session.
 *
 * A guest gets 403. A signed-in user who does not own the code gets 404, the
 * same as for a code that does not exist, so the route does not confirm codes
 * to anyone but their owner. A revoked certificate is not downloadable (410);
 * its verification page still says what happened to it.
 */
class DownloadController extends Controller
{
    public function __invoke(string $code, Certificates $certificates): Response
    {
        abort_unless(config('certificates.routes.enabled', true), 404);

        $user = Subject::current();

        abort_if($user === null, 403);

        $certificate = Certificate::findByCode($code);

        abort_if($certificate === null || ! $user->is($certificate->subject_type, $certificate->subject_id), 404);
        abort_if($certificate->isRevoked(), 410);

        $filename = Str::slug($certificate->course_title ?: 'certificate').'-'.$certificate->code.'.pdf';

        return response($certificates->pdf($certificate), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
