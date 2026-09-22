<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Automatic issuing
    |--------------------------------------------------------------------------
    |
    | Issue a certificate when statamic-courses fires CourseCompleted. Off, and
    | certificates are only issued through Certificates::issue() or the
    | `certificates:issue` command.
    |
    */

    'issue_on_completion' => true,

    /*
    |--------------------------------------------------------------------------
    | Template
    |--------------------------------------------------------------------------
    |
    | What the PDF and the verification page show besides the learner and the
    | course. Every key here is editable per brand under Settings in the
    | Control Panel; these values are the fallback for a brand that set none.
    |
    | `logo` and `signature` are asset references (`container::path`). They are
    | embedded into the PDF; the renderer never fetches anything remote.
    |
    */

    'template' => [
        'issuer_name' => env('CERTIFICATES_ISSUER_NAME', env('APP_NAME')),
        'logo' => null,
        'signature' => null,
        'signatory_name' => null,
        'signatory_title' => null,
        'accent_color' => '#1f2937',
        'footer' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | Generated PDFs are kept on this disk under `path/{code}.pdf`. A file that
    | is missing (a new server, a cleared disk) is rendered again on the next
    | download; the certificate row is the record, the file is a cache.
    |
    | Use a private disk. The PDF is only served through the download route,
    | which checks that the signed-in user owns it.
    |
    */

    'storage' => [
        'disk' => env('CERTIFICATES_DISK', 'local'),
        'path' => 'certificates',
    ],

    /*
    |--------------------------------------------------------------------------
    | Mail
    |--------------------------------------------------------------------------
    |
    | Send the learner the PDF when a certificate is issued. Off by default:
    | most sites already send their own "course finished" mail and would
    | rather link to the download than attach a second copy.
    |
    */

    'mail' => [
        'enabled' => (bool) env('CERTIFICATES_MAIL_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Front-end routes
    |--------------------------------------------------------------------------
    |
    | GET {prefix}/verify/{code}     public verification page
    | GET {prefix}/{code}/download   the PDF, for the signed-in owner only
    |
    | Read when routes are registered, so a change needs a route cache clear.
    | `throttle` is a Laravel throttle string applied to both routes.
    |
    */

    'routes' => [
        'enabled' => (bool) env('CERTIFICATES_ROUTES_ENABLED', true),
        'prefix' => 'certificates',
        'throttle' => '30,1',
    ],

    /*
    |--------------------------------------------------------------------------
    | Control Panel
    |--------------------------------------------------------------------------
    |
    | The "Certificates" screen: issued certificates, search, revoke.
    |
    */

    'cp' => [
        'enabled' => true,
    ],

];
