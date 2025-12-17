<?php

declare(strict_types=1);

namespace Codix\CdxAgent;

use Codix\CdxAgent\Http\Middleware\VerifyHmacSignature;
use Illuminate\Support\ServiceProvider;
use Illuminate\Routing\Router;

class CdxAgentServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/cdx-agent.php',
            'cdx-agent'
        );

        $this->app->singleton(Services\SignatureService::class, function ($app) {
            return new Services\SignatureService(
                config('cdx-agent.secret'),
                config('cdx-agent.timestamp_tolerance', 60)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerRoutes();
        $this->registerMiddleware();
        $this->registerPublishing();
    }

    /**
     * Register the package routes.
     */
    protected function registerRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/Routes/api.php');
    }

    /**
     * Register the package middleware.
     */
    protected function registerMiddleware(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('cdx-agent.hmac', VerifyHmacSignature::class);
    }

    /**
     * Register the package's publishable resources.
     */
    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/cdx-agent.php' => config_path('cdx-agent.php'),
            ], 'cdx-agent-config');
        }
    }
}
