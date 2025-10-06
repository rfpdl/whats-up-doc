<?php

declare(strict_types=1);

namespace Rfpdl\WhatsUpDoc;

use Illuminate\Support\ServiceProvider;
use Rfpdl\WhatsUpDoc\Commands\GenerateDocsCommand;
use Rfpdl\WhatsUpDoc\Services\RouteScanner;

class WhatsUpDocServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/whats-up-doc.php',
            'whats-up-doc'
        );

        $this->app->singleton(RouteScanner::class, function ($app) {
            return new RouteScanner($app['router']);
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateDocsCommand::class,
            ]);

            $this->publishes([
                __DIR__ . '/../config/whats-up-doc.php' => config_path('whats-up-doc.php'),
            ], 'config');

            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/whats-up-doc'),
            ], 'views');
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'whats-up-doc');
    }
}
