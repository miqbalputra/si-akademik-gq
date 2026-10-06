<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use App\Http\Middleware\ApplySecurityHeaders;
use App\Http\Middleware\ResetDefaultGuard;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $trustedProxies = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('TRUSTED_PROXIES', '')),
        )));
        if ($trustedProxies !== []) {
            $middleware->trustProxies(at: count($trustedProxies) === 1 && $trustedProxies[0] === '*'
                ? '*'
                : $trustedProxies);
        }
        $middleware->trustHosts(at: [
            '^'.preg_quote((string) parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST), '/').'$'
        ]);
        $middleware->alias([
            'role' => RoleMiddleware::class,
        ]);
        $middleware->prepend(ResetDefaultGuard::class);
        $middleware->append(ApplySecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
