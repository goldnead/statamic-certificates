<?php

namespace Goldnead\Certificates;

use Dompdf\Adapter\CPDF;
use Dompdf\Dompdf;
use Dompdf\Options;
use Goldnead\BrandContext\BrandManager;
use Goldnead\Certificates\Exceptions\CertificateIsRevoked;
use Goldnead\Certificates\Models\Certificate;
use Goldnead\Certificates\Support\CertificateCode;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Statamic\Facades\Asset;
use Throwable;

/**
 * Renders a certificate to PDF (dompdf, A4 landscape) and keeps the file on
 * the configured disk.
 *
 * **Rendered in the certificate's brand.** The template values are per-brand
 * settings that brand-context writes into `config('certificates.template')`
 * for the current brand. A download or a queued mail may run under another
 * brand or none, so the render switches to the brand stamped on the row and
 * back.
 *
 * **Nothing remote.** Logo and signature are read from Statamic assets and
 * embedded as data URIs; dompdf's remote loading stays off. A certificate that
 * fetches its logo over the network is a broken document once that host moves.
 */
class CertificatePdf
{
    /**
     * @throws CertificateIsRevoked
     */
    public function get(Certificate $certificate): string
    {
        $this->refuseRevoked($certificate);

        $disk = $this->disk();
        $path = $this->path($certificate);

        if ($disk->exists($path)) {
            return (string) $disk->get($path);
        }

        $pdf = $this->render($certificate);
        $disk->put($path, $pdf);

        return $pdf;
    }

    public function forget(Certificate $certificate): void
    {
        $this->disk()->delete($this->path($certificate));
    }

    /**
     * @throws CertificateIsRevoked
     */
    public function render(Certificate $certificate): string
    {
        $this->refuseRevoked($certificate);

        /** @var view-string $view */
        $view = 'certificates::pdf';

        return $this->inBrandOf($certificate, function () use ($certificate, $view): string {
            $dompdf = new Dompdf($this->options());
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->loadHtml(view($view, $this->viewData($certificate))->render(), 'UTF-8');
            $dompdf->render();

            $this->makeReproducible($dompdf, $certificate);

            return (string) $dompdf->output();
        });
    }

    public function path(Certificate $certificate): string
    {
        return trim((string) config('certificates.storage.path', 'certificates'), '/').'/'.$certificate->code.'.pdf';
    }

    /**
     * What the Blade view receives. Public so a host overriding the view can
     * see the whole contract in one place.
     *
     * @return array<string, mixed>
     */
    public function viewData(Certificate $certificate): array
    {
        $template = (array) config('certificates.template', []);

        return [
            'certificate' => $certificate,
            'learnerName' => $certificate->learner_name,
            'courseTitle' => $certificate->course_title,
            'issuedAt' => $certificate->issued_at,
            'code' => CertificateCode::format($certificate->code),
            'verifyUrl' => Route::has('certificates.verify')
                ? route('certificates.verify', ['code' => $certificate->code])
                : null,
            // Snapshotted on the row at issue time: statements, not looks.
            'issuerName' => $this->string($certificate->issuer_name),
            'signatoryName' => $this->string($certificate->signatory_name),
            'signatoryTitle' => $this->string($certificate->signatory_title),
            // Live from the brand's settings: looks, not statements.
            'footer' => $this->string($template['footer'] ?? null),
            'accentColor' => $this->color($template['accent_color'] ?? null),
            'logo' => $this->embed($template['logo'] ?? null, 'logo'),
            'signature' => $this->embed($template['signature'] ?? null, 'signature'),
        ];
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function inBrandOf(Certificate $certificate, callable $callback): mixed
    {
        if ($certificate->brand_id > 0 && app()->bound('brand-context')) {
            $brands = app('brand-context');

            if ($brands instanceof BrandManager && $brands->multiBrandEnabled()) {
                return $brands->runFor($certificate->brand_id, $callback);
            }
        }

        return $callback();
    }

    protected function refuseRevoked(Certificate $certificate): void
    {
        if ($certificate->isRevoked()) {
            throw new CertificateIsRevoked($certificate->code);
        }
    }

    protected function disk(): Filesystem
    {
        return Storage::disk((string) config('certificates.storage.disk', 'local'));
    }

    protected function options(): Options
    {
        $options = new Options;
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setDefaultMediaType('print');
        $options->setDefaultFont('DejaVu Sans');

        return $options;
    }

    /**
     * The same certificate renders to the same bytes: dompdf otherwise stamps
     * "now" and a random file id into every file.
     */
    protected function makeReproducible(Dompdf $dompdf, Certificate $certificate): void
    {
        $canvas = $dompdf->getCanvas();

        if (! $canvas instanceof CPDF) {
            return;
        }

        $pdf = $canvas->get_cpdf();
        $stamp = 'D:'.$certificate->issued_at->clone()->utc()->format('YmdHis')."+00'00'";

        $pdf->addInfo('CreationDate', $stamp);
        $pdf->addInfo('ModDate', $stamp);
        $pdf->addInfo('Title', $certificate->course_title);
        $pdf->fileIdentifier = md5('certificate:'.$certificate->code.':'.$stamp);
    }

    protected function string(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * Only a hex colour reaches the stylesheet; anything else would be CSS
     * injected from a settings field.
     */
    protected function color(mixed $value): string
    {
        return is_string($value) && preg_match('/^#(?:[0-9a-fA-F]{3}){1,2}$/', $value) ? $value : '#1f2937';
    }

    /**
     * An asset reference as a data URI, or null. A reference that does not
     * resolve is logged: the PDF still renders, without the image.
     */
    protected function embed(mixed $reference, string $what): ?string
    {
        $reference = $this->string(is_array($reference) ? ($reference[0] ?? null) : $reference);

        if ($reference === null) {
            return null;
        }

        try {
            $asset = Asset::find($reference);

            if ($asset instanceof \Statamic\Assets\Asset && $asset->isImage()) {
                return 'data:'.$asset->mimeType().';base64,'.base64_encode((string) $asset->contents());
            }
        } catch (Throwable $e) {
            Log::warning("statamic-certificates: the {$what} asset [{$reference}] could not be read.", ['exception' => $e]);

            return null;
        }

        Log::warning("statamic-certificates: the {$what} asset [{$reference}] is not an image asset; the PDF renders without it.");

        return null;
    }
}
