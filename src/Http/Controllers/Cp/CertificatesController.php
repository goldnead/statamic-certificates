<?php

namespace Goldnead\Certificates\Http\Controllers\Cp;

use Goldnead\Certificates\Certificates;
use Goldnead\Certificates\Models\Certificate;
use Goldnead\Certificates\Support\CertificateCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Statamic\CP\Column;
use Statamic\Http\Controllers\CP\CpController;

/**
 * Certificates: the current brand's issued certificates, search, revoke.
 *
 * Client-mode listing: the rows travel with the page and search and sort
 * happen in the browser. A course site issues hundreds of certificates, not
 * hundreds of thousands; past {@see self::LIMIT} the page says so.
 */
class CertificatesController extends CpController
{
    public const LIMIT = 2000;

    public function index(Certificates $certificates): Response
    {
        Gate::authorize('manage certificates');

        if (! Schema::hasTable((new Certificate)->getTable())) {
            Log::warning('statamic-certificates: the certificates_issued table is missing; run php artisan migrate.');

            return Inertia::render('certificates::Certificates/Index', [
                'rows' => [],
                'initialColumns' => [],
                'setupRequired' => true,
            ]);
        }

        $query = Certificate::query()->orderByDesc('issued_at')->orderByDesc('id');
        $total = (clone $query)->count();

        $rows = $query->limit(self::LIMIT)->get()->map(fn (Certificate $c) => [
            'id' => $c->id,
            'learner_name' => $c->learner_name,
            'course_title' => $c->course_title,
            'code' => CertificateCode::format($c->code),
            'issued_at' => $c->issued_at->toIso8601String(),
            'status' => $c->isRevoked() ? 'revoked' : 'valid',
            'revoked_reason' => $c->revoked_reason,
            'verify_url' => $certificates->verifyUrl($c),
            'revoke_url' => $c->isRevoked() ? null : cp_route('certificates.revoke', $c->id),
        ])->all();

        return Inertia::render('certificates::Certificates/Index', [
            'rows' => $rows,
            'initialColumns' => collect($this->columns())->map->toArray()->all(),
            'truncated' => $total > self::LIMIT,
            'total' => $total,
            'locale' => str_replace('_', '-', app()->getLocale()),
        ]);
    }

    public function revoke(Request $request, Certificates $certificates, int $certificate): RedirectResponse
    {
        Gate::authorize('manage certificates');

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        // Through the brand scope: a certificate of another brand is not found.
        $model = Certificate::query()->findOrFail($certificate);

        $certificates->revoke($model, $validated['reason']);

        return redirect()
            ->to(cp_route('certificates.index'))
            ->with('success', __('certificates::cp.revoked', ['code' => CertificateCode::format($model->code)]));
    }

    /**
     * @return list<Column>
     */
    protected function columns(): array
    {
        return [
            Column::make('learner_name')->label(__('certificates::cp.col_learner')),
            Column::make('course_title')->label(__('certificates::cp.col_course')),
            Column::make('issued_at')->label(__('certificates::cp.col_issued_at')),
            Column::make('status')->label(__('certificates::cp.col_status')),
            Column::make('code')->label(__('certificates::cp.col_code')),
        ];
    }
}
