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
    ->withMiddleware(function (Middleware $middleware): void {
        // Behind a TLS-terminating proxy the app sees plain HTTP unless it is
        // told to believe the forwarding headers — and then route() emits
        // http:// URLs and session cookies lose their Secure flag.
        //
        // Read from the environment directly: the config repository is not
        // bound yet when this closure runs, so config() throws here. In the
        // Docker image TRUSTED_PROXIES arrives as a real environment variable,
        // which is exactly what env() reads once config is cached.
        //
        // Opt-in rather than trusting everything: a client that can reach the
        // app directly could otherwise forge X-Forwarded-For.
        $middleware->trustProxies(at: env('TRUSTED_PROXIES'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
