<?php

namespace Goldnead\Certificates\Models;

use Goldnead\BrandContext\Concerns\HasBrand;
use Goldnead\Certificates\Support\CertificateCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * An issued certificate.
 *
 * Brand-scoped through HasBrand: the Control Panel and the Antlers tags see the
 * current brand's certificates. The verification page and the download route
 * look a code up across brands ({@see self::findByCode()}): the code is unique
 * over the whole table, and a visitor holding one has no brand in the session.
 *
 * @property int $id
 * @property int $brand_id
 * @property string $code
 * @property string $subject_type
 * @property string $subject_id
 * @property string $course_id
 * @property string $learner_name
 * @property string $course_title
 * @property string|null $issuer_name
 * @property string|null $signatory_name
 * @property string|null $signatory_title
 * @property string|null $brand_handle
 * @property Carbon $issued_at
 * @property Carbon|null $revoked_at
 * @property string|null $revoked_reason
 */
class Certificate extends Model
{
    use HasBrand;

    protected $table = 'certificates_issued';

    protected $fillable = [
        'brand_id',
        'code',
        'subject_type',
        'subject_id',
        'course_id',
        'learner_name',
        'course_title',
        'issuer_name',
        'signatory_name',
        'signatory_title',
        'brand_handle',
        'issued_at',
        'revoked_at',
        'revoked_reason',
    ];

    protected function casts(): array
    {
        return [
            'brand_id' => 'integer',
            'issued_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /**
     * Across brands, by the public code. Null for anything that is not a code
     * this table holds, malformed input included.
     */
    public static function findByCode(string $code): ?self
    {
        $code = CertificateCode::normalize($code);

        if ($code === null) {
            return null;
        }

        return static::query()->acrossBrands()->where('code', $code)->first();
    }
}
