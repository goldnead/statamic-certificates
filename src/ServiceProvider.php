<?php

namespace Goldnead\Certificates;

use Goldnead\BrandContext\Settings\SettingsRegistry;
use Goldnead\Certificates\Support\Settings;
use Statamic\Facades\CP\Nav;
use Statamic\Facades\Permission;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    /**
     * The Control Panel bundle. The three values must byte-match `laravel()`
     * in vite.config.js.
     *
     * Untyped on purpose: the parent declares it without a type.
     */
    protected $vite = [
        'hotFile' => __DIR__.'/../dist/hot',
        'publicDirectory' => 'dist',
        'input' => ['resources/js/cp.js'],
    ];

    /**
     * The sibling that owns the shared "Suite" nav section, when installed.
     */
    public const SUITE_NAV = '\Goldnead\StatamicPayments\Cp\SuiteNav';

    public function register(): void
    {
        parent::register();

        $this->mergeConfigFrom(__DIR__.'/../config/certificates.php', 'certificates');

        // By class name, never under a short slug.
        $this->app->singleton(Certificates::class);
        $this->app->singleton(CertificatePdf::class);

        // On the resolving translator rather than in boot: nav and permission
        // labels are built before bootAddon() runs.
        $langPath = __DIR__.'/../resources/lang';
        $this->app->resolving('translator', fn ($translator) => $translator->addNamespace('certificates', $langPath));

        if ($this->app->resolved('translator')) {
            $this->app['translator']->addNamespace('certificates', $langPath);
        }
    }

    public function bootAddon(): void
    {
        $this->bootMigrations()
            ->bootViews()
            ->bootCommands()
            ->bootSettings()
            ->bootPermissions()
            ->bootNavigation()
            ->bootPublishables();
    }

    protected function bootMigrations(): self
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        return $this;
    }

    protected function bootViews(): self
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'certificates');

        return $this;
    }

    /**
     * Registered by hand: core's command discovery runs after Statamic's boot
     * sequence, which a plain console context never reaches.
     */
    protected function bootCommands(): self
    {
        if ($this->app->runningInConsole()) {
            $this->commands([Console\Commands\Issue::class]);
        }

        return $this;
    }

    /**
     * The template fields per brand, through the suite's one settings layer.
     */
    protected function bootSettings(): self
    {
        $this->app->make(SettingsRegistry::class)->register(Settings::class);

        return $this;
    }

    protected function bootPermissions(): self
    {
        Permission::extend(function (): void {
            Permission::group('certificates', __('certificates::cp.nav'), function (): void {
                Permission::register('manage certificates')
                    ->label(__('certificates::cp.permission_manage'));
                Permission::register(Settings::settingsPermission())
                    ->label(__('certificates::settings.permission'));
            });
        });

        return $this;
    }

    /**
     * Under the suite's shared section when statamic-payments provides one,
     * under Content otherwise.
     */
    protected function bootNavigation(): self
    {
        if (! config('certificates.cp.enabled', true)) {
            return $this;
        }

        Nav::extend(function ($nav): void {
            $suiteNav = self::SUITE_NAV;
            $section = class_exists($suiteNav) ? $suiteNav::section() : 'Content';

            $nav->create(__('certificates::cp.nav'))
                ->section($section)
                ->icon('document-certificate')
                ->route('certificates.index')
                ->can('manage certificates');
        });

        return $this;
    }

    protected function bootPublishables(): self
    {
        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'certificates-migrations');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/certificates'),
        ], 'certificates-views');

        $this->publishes([
            __DIR__.'/../resources/lang' => lang_path('vendor/certificates'),
        ], 'certificates-translations');

        return $this;
    }
}
