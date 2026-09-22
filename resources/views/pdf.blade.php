{{--
    The certificate, rendered by dompdf on A4 landscape.

    Override by publishing: php artisan vendor:publish --tag=certificates-views
    Variables: see Goldnead\Certificates\CertificatePdf::viewData().
    dompdf reads CSS 2.1 and a little more: tables and fixed heights, no flexbox.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $courseTitle }}</title>
    <style>
        @page { size: A4 landscape; margin: 0; }
        body { margin: 0; font-family: 'DejaVu Sans', sans-serif; color: #1f2937; }
        .sheet { position: absolute; top: 14mm; right: 14mm; bottom: 14mm; left: 14mm; border: 2px solid {{ $accentColor }}; padding: 16mm 20mm 0; text-align: center; }
        .logo { height: 18mm; margin-bottom: 6mm; }
        .issuer { font-size: 11pt; letter-spacing: 2px; text-transform: uppercase; color: #4b5563; }
        .title { font-size: 30pt; font-weight: bold; margin: 8mm 0 4mm; color: {{ $accentColor }}; }
        .lead { font-size: 12pt; color: #4b5563; }
        .name { font-size: 26pt; font-weight: bold; margin: 5mm 0; }
        .course { font-size: 17pt; margin: 3mm 0 8mm; }
        .signature-row { width: 100%; margin-top: 6mm; }
        .signature-row td { width: 50%; vertical-align: bottom; font-size: 10pt; color: #4b5563; }
        .signature { height: 16mm; }
        .line { border-top: 1px solid #9ca3af; width: 60mm; margin: 1mm auto 1mm; }
        .meta { position: absolute; left: 20mm; right: 20mm; bottom: 8mm; font-size: 8pt; color: #6b7280; }
    </style>
</head>
<body>
<div class="sheet">
    @if ($logo)
        <img class="logo" src="{{ $logo }}" alt="">
    @endif

    @if ($issuerName)
        <div class="issuer">{{ $issuerName }}</div>
    @endif

    <div class="title">{{ __('certificates::messages.pdf.title') }}</div>
    <div class="lead">{{ __('certificates::messages.pdf.awarded_to') }}</div>
    <div class="name">{{ $learnerName }}</div>
    <div class="lead">{{ __('certificates::messages.pdf.for_completing') }}</div>
    <div class="course">{{ $courseTitle }}</div>

    <table class="signature-row">
        <tr>
            <td>
                {{ $issuedAt->locale(app()->getLocale())->isoFormat('LL') }}
                <div class="line"></div>
                {{ __('certificates::messages.pdf.date') }}
            </td>
            <td>
                @if ($signature)
                    <img class="signature" src="{{ $signature }}" alt="">
                @endif
                <div class="line"></div>
                {{ $signatoryName }}@if ($signatoryName && $signatoryTitle), @endif{{ $signatoryTitle }}
            </td>
        </tr>
    </table>

    <div class="meta">
        @if ($footer)
            <div>{{ $footer }}</div>
        @endif
        <div>
            {{ __('certificates::messages.pdf.code') }}: {{ $code }}
            @if ($verifyUrl)
                · {{ __('certificates::messages.pdf.verify_at') }} {{ $verifyUrl }}
            @endif
        </div>
    </div>
</div>
</body>
</html>
