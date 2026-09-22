<?php

namespace Goldnead\Certificates\Support;

use Goldnead\BrandContext\Contracts\ProvidesSettings;

/**
 * The fields an operator may change per brand, through the shared settings
 * layer of statamic-brand-context (the one settings mechanism of the suite).
 * Only overrides are stored; unset values keep following config/certificates.php.
 *
 * **Not here, on purpose:** `routes.*` and `cp.enabled` are read while routes
 * and nav are registered, before the layer applies its values, so a change
 * there would never arrive. `storage.*` is a deployment detail.
 * `issue_on_completion` stays in config as well: switching issuing off for one
 * brand of several is not a case anyone has.
 */
class Settings implements ProvidesSettings
{
    public static function settingsNamespace(): string
    {
        return 'certificates';
    }

    public static function settingsConfigPath(): string
    {
        return 'certificates';
    }

    public static function settingsPermission(): string
    {
        return 'manage certificates settings';
    }

    /**
     * @return array<int, array{title: string, description: string, fields: array<int, array<string, mixed>>}>
     */
    public static function settingsGroups(): array
    {
        return [
            [
                'title' => __('certificates::settings.groups.template.title'),
                'description' => __('certificates::settings.groups.template.description'),
                'fields' => [
                    static::field('template.issuer_name', 'string'),
                    static::field('template.logo', 'string'),
                    static::field('template.signature', 'string'),
                    static::field('template.signatory_name', 'string'),
                    static::field('template.signatory_title', 'string'),
                    static::field('template.accent_color', 'string', ['max' => 7]),
                    static::field('template.footer', 'text', ['max' => 500]),
                ],
            ],
            [
                'title' => __('certificates::settings.groups.delivery.title'),
                'description' => __('certificates::settings.groups.delivery.description'),
                'fields' => [
                    static::field('mail.enabled', 'boolean'),
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected static function field(string $key, string $type, array $extra = []): array
    {
        $handle = str_replace('.', '_', $key);

        return array_merge([
            'key' => $key,
            'type' => $type,
            'label' => __("certificates::settings.fields.{$handle}.label"),
            'description' => __("certificates::settings.fields.{$handle}.description"),
            'nullable' => true,
        ], $extra);
    }
}
