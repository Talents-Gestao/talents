<?php

namespace App\Providers;

use App\Models\MailSetting;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        try {
            MailSetting::applyToRuntimeConfig();
        } catch (\Throwable) {
            // DB indisponível (ex.: artisan sem driver) ou migration ainda não rodou
        }

        // Evita URLs quebradas (ex.: .../http:/) com Ziggy/Inertia atrás de Nginx/proxy:
        // url('/') e @routes devem usar APP_URL, não headers inconsistentes.
        $appUrl = config('app.url');
        if (is_string($appUrl) && $appUrl !== '') {
            URL::forceRootUrl(rtrim($appUrl, '/'));
        }
        if (is_string($appUrl) && str_starts_with($appUrl, 'https://')) {
            URL::forceScheme('https');
        }

        $this->configurePublicRouteRateLimiting();
    }

    private function configurePublicRouteRateLimiting(): void
    {
        RateLimiter::for('public-survey-submit', function (Request $request) {
            $max = (int) config('public_rate_limits.survey_submit_per_minute', 30);
            $token = (string) $request->route('token', '');

            return Limit::perMinute(max(1, $max))->by($request->ip().':'.$token);
        });

        RateLimiter::for('public-complaint-store', function (Request $request) {
            $max = (int) config('public_rate_limits.complaint_store_per_minute', 10);
            $token = (string) $request->route('token', '');

            return Limit::perMinute(max(1, $max))->by($request->ip().':'.$token);
        });

        RateLimiter::for('public-complaint-track-lookup', function (Request $request) {
            $max = (int) config('public_rate_limits.complaint_track_lookup_per_minute', 20);
            $token = (string) $request->route('token', '');

            return Limit::perMinute(max(1, $max))->by($request->ip().':'.$token);
        });

        RateLimiter::for('public-complaint-reporter-message', function (Request $request) {
            $max = (int) config('public_rate_limits.complaint_reporter_message_per_minute', 30);
            $token = (string) $request->route('token', '');

            return Limit::perMinute(max(1, $max))->by($request->ip().':'.$token);
        });
    }
}
