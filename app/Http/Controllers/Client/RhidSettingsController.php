<?php

namespace App\Http\Controllers\Client;

use App\Exceptions\RhidApiException;
use App\Exceptions\RhidDomainChoiceRequiredException;
use App\Http\Controllers\Controller;
use App\Services\Rhid\RhidAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RhidSettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        $company = $request->user()->company()->firstOrFail();

        return Inertia::render('Client/Rhid/Settings', [
            'settings' => [
                'rhid_base_url' => $company->rhid_base_url,
                'rhid_email' => $company->rhid_email,
                'rhid_domain' => $company->rhid_domain,
                'has_password' => filled($company->rhid_password),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $company = $request->user()->company()->firstOrFail();

        $validated = $request->validate([
            'rhid_base_url' => ['nullable', 'string', 'max:255'],
            'rhid_email' => ['nullable', 'string', 'email', 'max:255'],
            'rhid_password' => ['nullable', 'string', 'max:500'],
            'rhid_domain' => ['nullable', 'string', 'max:120'],
        ]);

        if (array_key_exists('rhid_base_url', $validated)) {
            $company->rhid_base_url = $validated['rhid_base_url'] ?: null;
        }
        if (array_key_exists('rhid_email', $validated)) {
            $company->rhid_email = $validated['rhid_email'] ?: null;
        }
        if (array_key_exists('rhid_domain', $validated)) {
            $company->rhid_domain = $validated['rhid_domain'] ?: null;
        }
        if (! empty($validated['rhid_password'])) {
            $company->rhid_password = $validated['rhid_password'];
        }

        $company->save();

        /** @var RhidAuthService $auth */
        $auth = app(RhidAuthService::class);
        $auth->forgetToken($company);

        return redirect()
            ->route('client.rhid.settings.edit')
            ->with('success', 'Configuracoes RHID salvas.');
    }

    public function test(Request $request, RhidAuthService $auth): JsonResponse
    {
        $company = $request->user()->company()->firstOrFail();

        if (! $company->rhidConfigured()) {
            return response()->json([
                'ok' => false,
                'message' => 'Informe email e senha RHID antes de testar.',
            ], 422);
        }

        $auth->forgetToken($company);

        try {
            $auth->getAccessToken($company, refresh: true);

            return response()->json(['ok' => true, 'message' => 'Conexao OK. Token obtido.']);
        } catch (RhidDomainChoiceRequiredException $e) {
            return response()->json([
                'ok' => false,
                'needs_domain' => true,
                'domains' => $e->listCustomer,
                'message' => $e->getMessage(),
            ], 422);
        } catch (RhidApiException $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
                'payload' => $e->payload,
            ], 422);
        }
    }
}
