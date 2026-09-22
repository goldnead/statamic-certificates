{{--
    The public verification page. Deliberately plain: it has to work on any
    site without a theme, and a site that wants its layout publishes and
    overrides this view (php artisan vendor:publish --tag=certificates-views).

    $status is 'valid', 'revoked' or 'unknown'. For 'unknown' $certificate is
    null, and the page must look the same whether the code was malformed or
    simply not issued.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('certificates::messages.verify.title') }}</title>
    <style>
        body { margin: 0; font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; color: #1f2937; background: #f9fafb; }
        main { max-width: 36rem; margin: 4rem auto; padding: 2rem; background: #fff; border: 1px solid #e5e7eb; border-radius: 6px; }
        h1 { font-size: 1.25rem; margin: 0 0 1rem; }
        .status { font-weight: 600; margin: 0 0 1.5rem; }
        .valid { color: #047857; }
        .revoked, .unknown { color: #b91c1c; }
        dl { display: grid; grid-template-columns: max-content 1fr; gap: .5rem 1rem; margin: 0; }
        dt { color: #6b7280; }
        dd { margin: 0; }
    </style>
</head>
<body>
<main>
    <h1>{{ __('certificates::messages.verify.title') }}</h1>

    <p class="status {{ $status }}" data-status="{{ $status }}">{{ __('certificates::messages.verify.status_'.$status) }}</p>

    @if ($certificate)
        <dl>
            <dt>{{ __('certificates::messages.verify.learner') }}</dt>
            <dd>{{ $certificate->learner_name }}</dd>
            <dt>{{ __('certificates::messages.verify.course') }}</dt>
            <dd>{{ $certificate->course_title }}</dd>
            <dt>{{ __('certificates::messages.verify.issued_at') }}</dt>
            <dd>{{ $certificate->issued_at->locale(app()->getLocale())->isoFormat('LL') }}</dd>
            @if ($issuerName)
                <dt>{{ __('certificates::messages.verify.issuer') }}</dt>
                <dd>{{ $issuerName }}</dd>
            @endif
            @if ($certificate->revoked_at)
                <dt>{{ __('certificates::messages.verify.revoked_at') }}</dt>
                <dd>{{ $certificate->revoked_at->locale(app()->getLocale())->isoFormat('LL') }}</dd>
            @endif
            <dt>{{ __('certificates::messages.verify.code') }}</dt>
            <dd>{{ $code }}</dd>
        </dl>
    @endif
</main>
</body>
</html>
