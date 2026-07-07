<?php

namespace EQ\LaravelEcommerce;

use EQ\LaravelEcommerce\Contracts\Plu as PluContract;
use EQ\LaravelEcommerce\Contracts\Product as ProductContract;
use EQ\LaravelEcommerce\Contracts\ProductVariant as ProductVariantContract;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;

class EcommerceServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/ecommerce.php',
            'ecommerce'
        );
    }

    public function boot()
    {
        $this->offerPublishing();

        $this->registerCommands();

        $this->registerModelBindings();

        $this->app->singleton(EcommerceRegistrar::class);

        // Load routes, migrations, or other resources if needed
        // $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        // $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
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

        // For now this is like this and we'll be proceeding to register the proper migrations...

        $this->publishes([
            __DIR__ . '/../database/migrations/create_products_table.php' => $this->getMigrationFileName('create_products_table.php'),
            __DIR__ . '/../database/migrations/create_orders_table.php' => $this->getMigrationFileName('create_orders_table.php'),
            __DIR__ . '/../database/migrations/create_order_details_table.php' => $this->getMigrationFileName('create_order_details_table.php'),
        ], 'ecommerce-migrations');
    }

    protected function registerCommands(): void
    {
        // $this->commands([]);
        // 
        // if (! $this->app->runningInConsole()) {
        // return;
        // }
        // 
        // $this->commands([]);
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
