<?php

namespace Goldnead\Certificates\Support;

/**
 * The public code on a certificate: 20 characters of Crockford base32, 100 bits
 * from random_bytes(). Not derived from the row id, the learner or the date, so
 * nothing about one code tells anything about another.
 *
 * Printed in groups of four for reading aloud (`ABCD-EFGH-…`). Normalising
 * accepts what a person types: lower case, spaces, dashes, and the letters
 * Crockford maps onto digits (O → 0, I and L → 1).
 */
final class CertificateCode
{
    public const LENGTH = 20;

    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public static function generate(): string
    {
        $bytes = random_bytes(self::LENGTH);
        $code = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[ord($bytes[$i]) & 31];
        }

        return $code;
    }

    /**
     * The stored form, or null when the input cannot be a code at all.
     */
    public static function normalize(string $input): ?string
    {
        $code = strtoupper((string) preg_replace('/[\s\-]+/', '', $input));
        $code = strtr($code, ['O' => '0', 'I' => '1', 'L' => '1']);

        if (strlen($code) !== self::LENGTH || strspn($code, self::ALPHABET) !== self::LENGTH) {
            return null;
        }

        return $code;
    }

    public static function format(string $code): string
    {
        return implode('-', str_split($code, 4));
    }
}
