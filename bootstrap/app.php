<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: dirname(__DIR__));

// Support deploying public/ to a separate document root (shared hosting)
// When index.php lives outside the project's public/ folder,
// LARAVEL_PUBLIC_PATH env var or the DOCUMENT_ROOT can override the public path.
if (defined('LARAVEL_ROOT')) {
    // public path is wherever index.php lives (the document root)
    $app->usePublicPath(defined('LARAVEL_PUBLIC_PATH') ? LARAVEL_PUBLIC_PATH : dirname($_SERVER['SCRIPT_FILENAME'] ?? __DIR__.'/../public'));
}

return $app
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->alias([
            'session.check' => \App\Http\Middleware\CheckSessionTerminated::class,
            'admin'         => \App\Http\Middleware\EnsureUserIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            return redirect()->guest(route('login'));
        });
    })->create();
