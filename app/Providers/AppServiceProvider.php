<?php

namespace App\Providers;

use App\Models\MailSetting;
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
    }
}
