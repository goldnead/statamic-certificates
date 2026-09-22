<?php

namespace Goldnead\Certificates\Tests;

use Goldnead\Certificates\ServiceProvider;
use Goldnead\Certificates\Tags\Certificates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Statamic\Contracts\Auth\User as UserContract;
use Statamic\Facades\Collection;
use Statamic\Facades\CP\Nav;
use Statamic\Facades\Entry;
use Statamic\Facades\User;
use Statamic\Testing\AddonTestCase;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

abstract class TestCase extends AddonTestCase
{
    use PreventsSavingStacheItemsToDisk;
    use RefreshDatabase;

    protected string $addonServiceProvider = ServiceProvider::class;

    /** @var list<callable> Nav::extend() callbacks bootAddon() registered. */
    protected array $navCallbacks = [];

    protected function setUp(): void
    {
        parent::setUp();

        // AddonTestCase swaps Nav for a strict mock; keep the callback.
        Nav::shouldReceive('extend')->andReturnUsing(function ($callback) {
            $this->navCallbacks[] = $callback;
        });

        $provider = $this->app->getProvider(ServiceProvider::class);
        $provider?->bootAddon();

        // Core wires listeners and tags from its booted callback, which
        // Testbench never fires; do what that discovery would.
        $provider?->bootEvents();
        Certificates::register();

        if (! Collection::find('courses')) {
            Collection::make('courses')->title('Courses')->save();
        }
    }

    /**
     * Registered here, not by a `migrate` call in setUp(): DDL inside
     * RefreshDatabase's transaction commits it implicitly under MySQL.
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../vendor/goldnead/statamic-brand-context/database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../vendor/goldnead/statamic-courses/database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function getPackageProviders($app)
    {
        return [
            ...parent::getPackageProviders($app),
            \Goldnead\BrandContext\ServiceProvider::class,
        ];
    }

    /**
     * The routes as a real site mounts them: web routes inside `web`, CP
     * routes inside core's authenticated CP group.
     */
    protected function defineRoutes($router): void
    {
        $router->middleware('web')->group(__DIR__.'/../routes/web.php');

        $router->middleware(['statamic.cp', 'statamic.cp.authenticated'])
            ->prefix('cp')
            ->name('statamic.cp.')
            ->group(__DIR__.'/../routes/cp.php');
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', $this->testingConnection());
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('app.timezone', 'America/Chicago');
        $app['config']->set('brand-context.multi_brand', false);
        $app['config']->set('brand-context.cp.enabled', false);
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('filesystems.disks.certificates-test', [
            'driver' => 'local',
            'root' => __DIR__.'/__fixtures__/storage',
        ]);
        $app['config']->set('certificates.storage.disk', 'certificates-test');
    }

    /**
     * In-memory SQLite by default; DB_DRIVER=mysql runs the identical suite
     * against a real server, the only place the unique index is really tested.
     *
     * @return array<string, mixed>
     */
    protected function testingConnection(): array
    {
        if (env('DB_DRIVER', 'sqlite') !== 'mysql') {
            return [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ];
        }

        return [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'certificates_test'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
        ];
    }

    protected function tearDown(): void
    {
        $this->app['files']->deleteDirectory(__DIR__.'/__fixtures__/storage');

        parent::tearDown();
    }

    protected function makeCourse(string $title): string
    {
        $entry = Entry::make()->collection('courses')->slug(str($title)->slug()->toString())->data(['title' => $title]);
        $entry->save();

        return (string) $entry->id();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function makeUser(string $email, ?string $name = null, array $data = []): UserContract
    {
        $user = User::make()->email($email)->data(array_filter(['name' => $name, ...$data]));
        $user->save();

        return $user;
    }
}
