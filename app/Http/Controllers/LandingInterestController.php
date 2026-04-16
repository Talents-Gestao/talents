<?php

namespace App\Http\Controllers;

use App\Mail\LandingInterestMail;
use App\Models\MailSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class LandingInterestController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        MailSetting::applyToRuntimeConfig();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:5000'],
        ]);

        $name = trim($data['name']);
        $email = trim($data['email']);
        $company = isset($data['company']) && $data['company'] !== '' ? trim($data['company']) : null;
        $message = isset($data['message']) && $data['message'] !== '' ? trim($data['message']) : null;

        $recipients = config('landing.interest_recipients', []);
        if ($recipients === []) {
            Log::warning('Landing interest: lista de destinatários vazia.');

            return back()
                ->withInput()
                ->with('error', 'Não foi possível enviar sua mensagem no momento. Tente novamente mais tarde.');
        }

        try {
            Mail::to($recipients)->send(new LandingInterestMail(
                submitterName: $name,
                submitterEmail: $email,
                company: $company,
                message: $message,
            ));
        } catch (\Throwable $e) {
            Log::error('Landing interest: falha ao enviar e-mail.', [
                'exception' => $e,
            ]);

            return back()
                ->withInput()
                ->with('error', 'Não foi possível enviar sua mensagem no momento. Tente novamente mais tarde.');
        }

        return back()->with('success', 'Recebemos seu interesse. Em breve entraremos em contato.');
    }
}
