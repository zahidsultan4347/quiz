<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([   // Custom middleware
            'lti.auth' => \App\Http\Middleware\LtiAuth::class,
        ]);
         // Exclude LTI routes from CSRF
         $middleware->validateCsrfTokens(except: [
            'lti/*',
            'lti/login',
            'lti/launch',
            'lti/token',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        
    })->create();
