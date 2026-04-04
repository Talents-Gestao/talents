<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MailSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class MailSettingsController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'encryption' => ['nullable', 'string', Rule::in(['tls', 'ssl', ''])],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:500'],
            'from_address' => ['nullable', 'string', 'max:255', 'email'],
            'from_name' => ['nullable', 'string', 'max:255'],
            'is_enabled' => ['boolean'],
        ]);

        $row = MailSetting::query()->first();
        if (! $row) {
            $row = new MailSetting;
        }

        $password = $data['password'] ?? null;
        unset($data['password']);

        $enc = $data['encryption'] ?? null;
        if ($enc === '') {
            $enc = null;
        }

        $row->fill([
            'host' => $data['host'] ?? null,
            'port' => $data['port'] ?? 587,
            'encryption' => $enc,
            'username' => $data['username'] ?? null,
            'from_address' => $data['from_address'] ?? null,
            'from_name' => $data['from_name'] ?? null,
            'is_enabled' => $request->boolean('is_enabled'),
            'updated_by' => $request->user()->id,
        ]);

        if ($password !== null && $password !== '') {
            $row->password = $password;
        }

        $row->save();

        MailSetting::applyToRuntimeConfig();

        return redirect()->route('admin.settings.edit', ['tab' => 'mail'])->with('success', 'Configurações de e-mail salvas.');
    }

    public function test(Request $request): RedirectResponse
    {
        $request->validate([
            'test_to' => ['required', 'email'],
        ]);

        MailSetting::applyToRuntimeConfig();

        try {
            Mail::raw('Este é um e-mail de teste das configurações SMTP do Talents.', function ($message) use ($request) {
                $message->to($request->string('test_to')->toString())
                    ->subject('Talents — teste de SMTP');
            });

            return back()->with('success', 'E-mail de teste enviado. Verifique a caixa de entrada (e spam).');
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Falha ao enviar: '.$e->getMessage());
        }
    }
}
