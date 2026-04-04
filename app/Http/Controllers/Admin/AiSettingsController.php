<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiSetting;
use App\Services\Nr1AiAnalyzer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AiSettingsController extends Controller
{
    public function edit(): RedirectResponse
    {
        return redirect()->route('admin.settings.edit', ['tab' => 'ia']);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'provider' => ['required', 'in:openai,anthropic'],
            'model' => ['required', 'string', 'max:128'],
            'api_key' => ['nullable', 'string', 'max:4000'],
            'max_tokens' => ['required', 'integer', 'min:100', 'max:16000'],
            'temperature' => ['required', 'numeric', 'min:0', 'max:2'],
        ]);

        $row = AiSetting::query()->firstOrFail();
        $apiKey = $data['api_key'] ?? null;
        unset($data['api_key']);

        $row->fill([
            'provider' => $data['provider'],
            'model' => $data['model'],
            'is_enabled' => $request->boolean('is_enabled'),
            'max_tokens' => $data['max_tokens'],
            'temperature' => $data['temperature'],
        ]);

        if ($apiKey !== null && $apiKey !== '') {
            $row->api_key = $apiKey;
        }

        $row->updated_by = $request->user()->id;
        $row->save();

        return redirect()->route('admin.settings.edit', ['tab' => 'ia'])->with('success', 'Configurações de IA salvas.');
    }

    public function test(Request $request, Nr1AiAnalyzer $analyzer): RedirectResponse
    {
        $data = $request->validate([
            'provider' => ['required', 'in:openai,anthropic'],
            'model' => ['required', 'string', 'max:128'],
            'api_key' => ['nullable', 'string', 'max:4000'],
            'max_tokens' => ['required', 'integer', 'min:100', 'max:16000'],
            'temperature' => ['required', 'numeric', 'min:0', 'max:2'],
        ]);

        $setting = new AiSetting([
            'provider' => $data['provider'],
            'model' => $data['model'],
            'max_tokens' => $data['max_tokens'],
            'temperature' => $data['temperature'],
        ]);

        $key = $data['api_key'] ?? null;
        if ($key === null || $key === '') {
            $existing = AiSetting::current();
            $plain = $existing?->safeApiKey();
            if ($plain === null || $plain === '') {
                return back()->with('error', 'Informe a chave da API para testar (ou salve uma chave válida se a anterior foi criptografada com outra APP_KEY).');
            }
            $setting->api_key = $plain;
        } else {
            $setting->api_key = $key;
        }

        $result = $analyzer->testConnection($setting);

        if ($result['ok']) {
            return back()->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
    }
}
