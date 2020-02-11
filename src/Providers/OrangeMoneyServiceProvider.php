<?php

namespace Ibracilinks\OrangeMoney\Providers;

use Ibracilinks\OrangeMoney;
use Illuminate\Support\ServiceProvider;

class OrangeMoneyServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/orangemoney.php' => config_path('orangemoney.php'),
        ]);
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/orangemoney.php',
            'orangemoney'
        );
    }

    /**
     * Register the facades.
     *
     * @return void
     */
    public function registerFacades()
    {
        $this->app->singleton('OrangeMoney', function ($app) {
            return new \Ibracilinks\OrangeMoney\OrangeMoney();
        });
    }
}
