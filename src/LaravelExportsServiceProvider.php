<?php

namespace Osama\LaravelExports;

use Illuminate\Support\ServiceProvider;

class LaravelExportsServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/exports.php',
            'exports'
        );

        $this->app->scoped(Exports\ExportManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/exports.php' => config_path('exports.php'),
        ], 'exports-config');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
