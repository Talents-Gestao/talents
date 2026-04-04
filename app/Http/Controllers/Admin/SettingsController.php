<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiSetting;
use App\Models\MailSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        $aiRow = AiSetting::query()->first();
        if (! $aiRow) {
            $aiRow = AiSetting::query()->create([
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'is_enabled' => false,
                'max_tokens' => 2000,
                'temperature' => 0.30,
            ]);
        }

        $mailRow = MailSetting::query()->first();
        if (! $mailRow) {
            $mailRow = MailSetting::query()->create([
                'port' => 587,
                'is_enabled' => false,
            ]);
        }

        return Inertia::render('Admin/Settings/Index', [
            'tab' => $request->query('tab', 'ia'),
            'aiSettings' => [
                'id' => $aiRow->id,
                'provider' => $aiRow->provider,
                'model' => $aiRow->model,
                'is_enabled' => $aiRow->is_enabled,
                'max_tokens' => $aiRow->max_tokens,
                'temperature' => (float) $aiRow->temperature,
                // Não acesse api_key com cast (encrypted): falha com APP_KEY diferente ou payload inválido
                'api_key_set' => filled($aiRow->getRawOriginal('api_key')),
                'api_key_readable' => $aiRow->canDecrypt('api_key'),
            ],
            'mailSettings' => [
                'id' => $mailRow->id,
                'host' => $mailRow->host,
                'port' => $mailRow->port,
                'encryption' => $mailRow->encryption ?? '',
                'username' => $mailRow->username,
                'password_set' => filled($mailRow->getRawOriginal('password')),
                'password_readable' => $mailRow->canDecrypt('password'),
                'from_address' => $mailRow->from_address,
                'from_name' => $mailRow->from_name,
                'is_enabled' => $mailRow->is_enabled,
            ],
        ]);
    }
}
