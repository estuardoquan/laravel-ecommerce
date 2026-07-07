<?php

namespace EQ\LaravelEcommerce;

use EQ\LaravelEcommerce\Commands\CacheResetCommand;
use EQ\LaravelEcommerce\Contracts\Plu as PluContract;
use EQ\LaravelEcommerce\Contracts\Product as ProductContract;
use EQ\LaravelEcommerce\Contracts\ProductVariant as ProductVariantContract;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;
use Laravel\Octane\Contracts\OperationTerminated;

class EcommerceServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/ecommerce.php',
            'ecommerce'
        );

        // Container bindings belong in register(), not boot():
        // anything resolving the registrar during another provider's
        // register() phase must already get the singleton.
        $this->app->singleton(EcommerceRegistrar::class);
    }

    public function boot()
    {
        $this->offerPublishing();

        $this->registerCommands();

        $this->registerModelBindings();

        $this->registerOctaneListener();
    }

    protected function offerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        if (! function_exists('config_path')) {
            // function not available and 'publish' not relevant in Lumen
            return;
        }

        $this->publishes([
            __DIR__ . '/../config/ecommerce.php' => config_path('ecommerce.php'),
        ], 'ecommerce-config');

        $this->publishes([
            __DIR__ . '/../database/migrations/create_products_table.php' => $this->getMigrationFileName('create_products_table.php'),
            __DIR__ . '/../database/migrations/create_orders_table.php' => $this->getMigrationFileName('create_orders_table.php'),
            __DIR__ . '/../database/migrations/create_order_details_table.php' => $this->getMigrationFileName('create_order_details_table.php'),
        ], 'ecommerce-migrations');
    }

    protected function registerCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            CacheResetCommand::class,
        ]);
    }

    protected function registerModelBindings(): void
    {
        $this->app->bind(
            PluContract::class,
            fn($app) => $app->make($app->config['ecommerce.models.plu'])
        );

        $this->app->bind(
            ProductContract::class,
            fn($app) => $app->make($app->config['ecommerce.models.product'])
        );

        $this->app->bind(
            ProductVariantContract::class,
            fn($app) => $app->make($app->config['ecommerce.models.product_variant'])
        );
    }

    /**
     * On Octane/Swoole the registrar singleton outlives the request, so its
     * in-memory $products collection would otherwise go stale: a flush from
     * another worker (or another server) clears the shared cache store, but
     * this worker would keep serving its hydrated copy forever.
     * Clearing the collection after each operation forces the next request
     * to re-read the cache store (cheap on a hit — no DB involved).
     */
    protected function registerOctaneListener(): void
    {
        if ($this->app->runningInConsole() || ! $this->app['config']->get('octane.listeners')) {
            return;
        }

        if (! $this->app['config']->get('ecommerce.cache.register_octane_reset_listener')) {
            return;
        }

        $this->app[Dispatcher::class]->listen(function (OperationTerminated $event) {
            $event->sandbox()->make(EcommerceRegistrar::class)->clearProductsCollection();
        });
    }

    /**
     * Returns existing migration file if found, else uses the current timestamp.
     */
    protected function getMigrationFileName(string $migrationFileName): string
    {
        $timestamp = date('Y_m_d_His');

        $filesystem = $this->app->make(Filesystem::class);

        return Collection::make([$this->app->databasePath() . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR])
            ->flatMap(fn($path) => $filesystem->glob($path . '*_' . $migrationFileName))
            ->push($this->app->databasePath() . "/migrations/{$timestamp}_{$migrationFileName}")
            ->first();
    }
}
