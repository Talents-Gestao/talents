<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\Rhid\RhidCompanyCredentialsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RhidSettingsController extends Controller
{
    public function edit(Request $request, RhidCompanyCredentialsService $credentials): Response
    {
        $company = $request->user()->company()->firstOrFail();

        return Inertia::render('Client/Rhid/Settings', [
            'configured' => $company->rhidConfigured(),
            'settings' => $credentials->settingsPayload($company),
        ]);
    }

    public function update(Request $request, RhidCompanyCredentialsService $credentials): RedirectResponse
    {
        $company = $request->user()->company()->firstOrFail();
        $validated = $request->validate(RhidCompanyCredentialsService::validationRules());
        $credentials->update($company, $validated);

        return redirect()
            ->route('client.rhid.settings.edit')
            ->with('success', 'Configurações RHID salvas.');
    }

    public function test(Request $request, RhidCompanyCredentialsService $credentials): JsonResponse
    {
        $company = $request->user()->company()->firstOrFail();
        $result = $credentials->test($company);
        $status = ($result['ok'] ?? false) === true ? 200 : 422;

        return response()->json($result, $status);
    }
}
