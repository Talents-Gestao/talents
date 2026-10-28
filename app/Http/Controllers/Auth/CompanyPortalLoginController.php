<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Company;
use App\Support\CompanyPortal;
use App\Support\WorkspaceManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CompanyPortalLoginController extends Controller
{
    public function __construct(
        private WorkspaceManager $workspaceManager,
    ) {}

    public function create(Request $request, string $slug): Response
    {
        $company = $this->enabledCompany($slug);

        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
            'sessionExpired' => $request->boolean('session_expired'),
            'portal' => $this->loginPayload($company),
        ]);
    }

    public function store(LoginRequest $request, string $slug): RedirectResponse
    {
        $company = $this->enabledCompany($slug);

        $request->authenticate();

        $user = $request->user();
        $workspace = $user?->companyWorkspace($company->id);

        if ($user === null || $workspace === null || ! $workspace->is_active) {
            Auth::guard('web')->logout();
            $request->session()->regenerateToken();
            RateLimiter::hit($request->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        $request->session()->regenerate();
        $request->session()->put(CompanyPortal::SESSION_KEY, $company->portal_slug);

        $this->workspaceManager->selectWorkspace($user, $workspace, $request);

        return redirect()->route('client.dashboard');
    }

    private function enabledCompany(string $slug): Company
    {
        $company = CompanyPortal::findEnabled($slug);

        abort_unless($company instanceof Company, 404);

        return $company;
    }

    /**
     * @return array{slug: string, name: string, logo_url: string|null, primary_color: string|null, login_url: string}
     */
    private function loginPayload(Company $company): array
    {
        return [
            'slug' => (string) $company->portal_slug,
            'name' => $company->portalBrandName(),
            'logo_url' => $company->logo_url,
            'primary_color' => $company->brand_primary_color,
            'login_url' => route('portal.login', ['slug' => $company->portal_slug], absolute: false),
        ];
    }
}
