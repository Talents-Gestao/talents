<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SolidesSetting;
use App\Services\Solides\SolidesClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SolidesSettingsController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'base_url' => ['required', 'url', 'max:255'],
            'locale' => ['required', 'in:pt-BR,es,en'],
            'api_token' => ['nullable', 'string', 'max:4000'],
            'is_enabled' => ['boolean'],
        ]);

        $row = SolidesSetting::query()->first();
        if (! $row) {
            $row = new SolidesSetting;
        }

        $apiToken = $data['api_token'] ?? null;
        unset($data['api_token']);

        $row->fill([
            'base_url' => $data['base_url'],
            'locale' => $data['locale'],
            'is_enabled' => $request->boolean('is_enabled'),
            'updated_by' => $request->user()->id,
        ]);

        if ($apiToken !== null && $apiToken !== '') {
            $row->api_token = $apiToken;
        }

        $row->save();

        return redirect()->route('admin.settings.edit', ['tab' => 'solides'])
            ->with('success', 'Configurações da integração Sólides salvas.');
    }

    public function test(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'base_url' => ['required', 'url', 'max:255'],
            'locale' => ['required', 'in:pt-BR,es,en'],
            'api_token' => ['nullable', 'string', 'max:4000'],
        ]);

        $existing = SolidesSetting::current();

        $setting = new SolidesSetting([
            'base_url' => $data['base_url'],
            'locale' => $data['locale'],
            'is_enabled' => true,
        ]);

        $token = $data['api_token'] ?? null;
        if ($token === null || $token === '') {
            $token = $existing?->safeApiToken();
        }

        if (! $token) {
            return back()->with('error', 'Informe o token da API Sólides para testar (ou salve um token válido).');
        }

        $setting->api_token = $token;
        $result = (new SolidesClient($setting))->testConnection();

        $row = $existing ?? new SolidesSetting;
        $row->base_url = $data['base_url'];
        $row->locale = $data['locale'];
        $row->last_tested_at = now();
        $row->last_test_status = $result['ok'] ? 'ok' : 'fail';
        $row->last_test_message = $result['message'];
        $row->updated_by = $request->user()->id;
        $row->save();

        if ($result['ok']) {
            return back()->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
    }
}
