<?php

use App\Http\Middleware\EnsureActiveSubscription;
use App\Http\Middleware\EnsureTechnicianProfile;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'subscription.active' => EnsureActiveSubscription::class,
            'technician.profile' => EnsureTechnicianProfile::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Poruke greske idu klijentu, pa moraju biti na bosanskom.
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Niste prijavljeni.'], 401);
            }

            return null;
        });

        $exceptions->render(function (UnauthorizedException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Nemate pristup ovom dijelu aplikacije.'], 403);
            }

            return null;
        });
    })->create();
