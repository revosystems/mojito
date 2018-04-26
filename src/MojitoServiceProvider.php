<?php

namespace BadChoice\Mojito;

use Illuminate\Support\ServiceProvider;

class MojitoServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../database/migrations/' => base_path('database/migrations'),
        ], 'migrations');

        $this->publishes([
            __DIR__.'/../resources/lang/' => base_path('resources/lang'),
        ], 'translations');

        $this->publishes([
            __DIR__.'/../resources/forms/' => base_path('resources/forms'),
        ], 'forms');

        $this->publishes([
            __DIR__.'/../config/mojito.php' => config_path('mojito.php'),
        ], 'config');
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('mojito', function ($app) {
            return new Mojito();
        });
    }
}
