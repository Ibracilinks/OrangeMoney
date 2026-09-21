<?php

namespace Ibracilinks\OrangeMoney\Providers;

use Ibracilinks\OrangeMoney\OrangeMoney;
use Illuminate\Support\ServiceProvider;

class OrangeMoneyServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/orangemoney.php' => config_path('orangemoney.php'),
            ], 'orangemoney-config');
        }
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/orangemoney.php',
            'orangemoney'
        );

        $this->registerFacades();
    }

    /**
     * Register the facades.
     *
     * @return void
     */
    public function registerFacades(): void
    {
        $this->app->singleton(OrangeMoney::class, function ($app) {
            return new OrangeMoney($app['config']->get('orangemoney', []));
        });
        $this->app->alias(OrangeMoney::class, 'OrangeMoney');
    }
}
