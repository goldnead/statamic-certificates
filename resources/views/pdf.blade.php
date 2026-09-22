{{--
    The certificate, rendered by dompdf on one A4 landscape page.

    Override by publishing: php artisan vendor:publish --tag=certificates-views
    Variables: see Goldnead\Certificates\CertificatePdf::viewData().

    dompdf reads CSS 2.1 and a little more. The layout is therefore one table
    of fixed height, no absolute positioning (dompdf draws absolutely placed
    boxes below the page, where no viewer shows them), no flexbox, no grid.
    Long names and titles step down in size so the whole certificate, code and
    verification URL included, stays inside the frame on one page. Name and
    title are capped at 255 characters when issued.
--}}
@php
    $nameLength = mb_strlen($learnerName);
    $titleLength = mb_strlen($courseTitle);
    $nameSize = $nameLength > 120 ? 14 : ($nameLength > 60 ? 17 : ($nameLength > 35 ? 21 : 25));
    $titleSize = $titleLength > 180 ? 10.5 : ($titleLength > 110 ? 12 : ($titleLength > 60 ? 14 : 17));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $courseTitle }}</title>
    <style>
        @page { size: A4 landscape; margin: 10mm; }
        {{-- Not on html: dompdf carries the @page margin on the root element. --}}
        body { margin: 0; padding: 0; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #1f2937; }
        table { border-collapse: collapse; }
        td { padding: 0; }
        .frame { width: 100%; height: 186mm; border: 2px solid {{ $accentColor }}; }
        .frame > tbody > tr > td, .frame > tr > td { text-align: center; padding-left: 22mm; padding-right: 22mm; }
        .head { height: 34mm; vertical-align: bottom; }
        {{-- dompdf does not stretch rows to a table height: every row carries its own, summing to the frame. --}}
        .body { height: 98mm; vertical-align: middle; }
        .sign { height: 36mm; vertical-align: bottom; }
        .meta { height: 16mm; vertical-align: middle; font-size: 7.5pt; color: #6b7280; line-height: 1.5; }
        .logo { height: 16mm; }
        .issuer { font-size: 10pt; letter-spacing: 2px; text-transform: uppercase; color: #4b5563; margin-top: 3mm; }
        .title { font-size: 28pt; font-weight: bold; color: {{ $accentColor }}; margin: 0 0 3mm; }
        .lead { font-size: 11pt; color: #4b5563; }
        .name { font-size: {{ $nameSize }}pt; font-weight: bold; margin: 3mm 0 4mm; line-height: 1.25; }
        .course { font-size: {{ $titleSize }}pt; margin: 2mm 0 0; line-height: 1.35; }
        .columns { width: 100%; }
        .column { width: 50%; text-align: center; vertical-align: bottom; }
        .value { height: 16mm; vertical-align: bottom; text-align: center; font-size: 10.5pt; }
        .signature { height: 15mm; }
        .line { width: 62mm; margin: 1.5mm auto 1.5mm; border-top: 1px solid #9ca3af; }
        .label { font-size: 9pt; color: #4b5563; }
    </style>
</head>
<body>
<table class="frame">
    <tr>
        <td class="head">
            @if ($logo)
                <img class="logo" src="{{ $logo }}" alt="">
            @endif
            @if ($issuerName)
                <div class="issuer">{{ $issuerName }}</div>
            @endif
        </td>
    </tr>
    <tr>
        <td class="body">
            <div class="title">{{ __('certificates::messages.pdf.title') }}</div>
            <div class="lead">{{ __('certificates::messages.pdf.awarded_to') }}</div>
            <div class="name">{{ $learnerName }}</div>
            <div class="lead">{{ __('certificates::messages.pdf.for_completing') }}</div>
            <div class="course">{{ $courseTitle }}</div>
        </td>
    </tr>
    <tr>
        <td class="sign">
            {{-- Each block is one centred column: value, line, label. --}}
            <table class="columns">
                <tr>
                    <td class="column">
                        <table style="width: 100%;"><tr><td class="value">{{ $issuedAt->locale(app()->getLocale())->isoFormat('LL') }}</td></tr></table>
                        <div class="line"></div>
                        <div class="label">{{ __('certificates::messages.pdf.date') }}</div>
                    </td>
                    <td class="column">
                        <table style="width: 100%;"><tr><td class="value">
                            @if ($signature)
                                <img class="signature" src="{{ $signature }}" alt="">
                            @endif
                        </td></tr></table>
                        <div class="line"></div>
                        <div class="label">{{ $signatoryName }}@if ($signatoryName && $signatoryTitle), @endif{{ $signatoryTitle }}</div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td class="meta">
            @if ($footer)
                <div>{{ $footer }}</div>
            @endif
            <div>
                {{ __('certificates::messages.pdf.code') }}: {{ $code }}@if ($verifyUrl) · {{ __('certificates::messages.pdf.verify_at') }} {{ $verifyUrl }}@endif
            </div>
        </td>
    </tr>
</table>
</body>
</html>
