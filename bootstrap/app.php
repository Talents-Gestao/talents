<?php

use App\Http\Middleware\EnsureCompanyAccess;
use App\Http\Middleware\EnsureCompanyAdmin;
use App\Http\Middleware\EnsureModulePermission;
use App\Http\Middleware\EnsureStrategicCalendarAccess;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\LogInertiaRequestTiming;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')->group(base_path('routes/admin.php'));
            Route::middleware('web')->group(base_path('routes/client.php'));
            Route::middleware('web')->group(base_path('routes/survey.php'));
            Route::middleware('web')->group(base_path('routes/complaint.php'));
            Route::middleware('web')->group(base_path('routes/methodology.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Traefik / proxy reverso (Coolify): HTTPS e IPs corretos
        $middleware->trustProxies(at: '*');

        $middleware->web(prepend: [
            LogInertiaRequestTiming::class,
        ]);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'super_admin' => EnsureSuperAdmin::class,
            'company' => EnsureCompanyAccess::class,
            'company_admin' => EnsureCompanyAdmin::class,
            'strategic_calendar' => EnsureStrategicCalendarAccess::class,
            'can.module' => EnsureModulePermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
