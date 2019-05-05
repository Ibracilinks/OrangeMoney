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
        $this->app->singleton(OrangeMoney::class, function ($app) {
            return new OrangeMoney();
        });
    }

    /**
     * Merges user's and paypal's configs.
     *
     * @return void
     */
    private function mergeConfig()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/orangemoney.php',
            'orangemoney'
        );
    }
}
