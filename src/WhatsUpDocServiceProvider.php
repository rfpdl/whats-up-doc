<?php

declare(strict_types=1);

namespace Rfpdl\WhatsUpDoc;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Rfpdl\WhatsUpDoc\Commands\GenerateDocsCommand;
use Rfpdl\WhatsUpDoc\Http\Controllers\DocumentationController;
use Rfpdl\WhatsUpDoc\Services\DataClassScanner;
use Rfpdl\WhatsUpDoc\Services\DocumentationGenerator;
use Rfpdl\WhatsUpDoc\Services\RouteScanner;
use Rfpdl\WhatsUpDoc\Support\AttributeReader;
use Rfpdl\WhatsUpDoc\Support\DocblockParser;
use Rfpdl\WhatsUpDoc\Support\SchemaRegistry;
use Rfpdl\WhatsUpDoc\Support\TypeResolver;

class WhatsUpDocServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/whats-up-doc.php',
            'whats-up-doc'
        );

        $this->registerSupportClasses();
        $this->registerServices();
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

        $this->registerRoutes();
    }

    private function registerRoutes(): void
    {
        if (!config('whats-up-doc.ui.enabled', true)) {
            return;
        }

        $path = config('whats-up-doc.ui.path', 'docs/api');
        $middleware = config('whats-up-doc.ui.middleware', ['web']);

        Route::middleware($middleware)->group(function () use ($path) {
            Route::get($path, [DocumentationController::class, 'index'])
                ->name('whats-up-doc.index');
            Route::get("{$path}/openapi.json", [DocumentationController::class, 'spec'])
                ->name('whats-up-doc.spec');
        });
    }

    /**
     * Register support/utility classes
     */
    private function registerSupportClasses(): void
    {
        // These are simple utility classes without dependencies
        $this->app->singleton(TypeResolver::class, function () {
            return new TypeResolver();
        });

        $this->app->singleton(DocblockParser::class, function () {
            return new DocblockParser();
        });

        $this->app->singleton(AttributeReader::class, function () {
            return new AttributeReader();
        });

        $this->app->singleton(SchemaRegistry::class, function () {
            return new SchemaRegistry();
        });
    }

    /**
     * Register main service classes
     */
    private function registerServices(): void
    {
        $this->app->singleton(DataClassScanner::class, function ($app) {
            return new DataClassScanner(
                $app->make(DocblockParser::class),
            );
        });

        $this->app->singleton(RouteScanner::class, function ($app) {
            return new RouteScanner(
                $app['router'],
                $app->make(DocblockParser::class),
                $app->make(TypeResolver::class),
            );
        });

        $this->app->singleton(DocumentationGenerator::class, function ($app) {
            return new DocumentationGenerator(
                $app->make(TypeResolver::class),
                $app->make(DocblockParser::class),
                $app->make(AttributeReader::class),
                $app->make(RouteScanner::class),
                $app->make(SchemaRegistry::class),
            );
        });
    }
}
