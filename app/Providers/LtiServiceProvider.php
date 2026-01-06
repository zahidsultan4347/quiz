<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Lti\LtiService;
use App\Services\Lti\LtiDatabase;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class LtiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('lti', function ($app) {
            return new LtiService();
        });
    }

    public function boot(): void
    {
        // Load LTI routes
        $this->loadRoutesFrom(__DIR__.'/../../routes/lti.php');
    }
}