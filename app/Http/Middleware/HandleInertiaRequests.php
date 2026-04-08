<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $shareStartedAt = microtime(true);

        $user = $request->user();

        $companyPayload = null;
        if ($user && $user->company_id) {
            $company = $user->relationLoaded('company')
                ? $user->company
                : $user->company()->first();

            if ($company) {
                $companyPayload = array_merge($company->toArray(), [
                    'has_methodology' => $company->hasMethodologyEnabled(),
                ]);
            }
        }

        $shared = [
            ...parent::share($request),
            'auth' => [
                'user' => $user
                    ? [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role->value,
                        'company_id' => $user->company_id,
                        'company' => $companyPayload,
                    ]
                    : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'info' => fn () => $request->session()->get('info'),
            ],
        ];

        if (config('app.debug') && $request->headers->has('X-Inertia')) {
            Log::debug('[Inertia] HandleInertiaRequests::share()', [
                'path' => $request->path(),
                'share_ms' => round((microtime(true) - $shareStartedAt) * 1000, 2),
            ]);
        }

        return $shared;
    }
}
