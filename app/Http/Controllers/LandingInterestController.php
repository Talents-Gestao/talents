<?php

namespace App\Http\Controllers;

use App\Mail\LandingInterestMail;
use App\Models\LandingInterestSubmission;
use App\Models\MailSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

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

        $submission = LandingInterestSubmission::query()->create([
            'name' => $name,
            'email' => $email,
            'company' => $company,
            'message' => $message,
        ]);

        $recipients = config('landing.interest_recipients', []);
        if ($recipients === []) {
            Log::warning('Landing interest: lista de destinatários vazia.', ['submission_id' => $submission->id]);
            $submission->update([
                'mail_error' => 'Lista de destinatários vazia (configuração).',
            ]);

            return back()->with('success', 'Recebemos seu interesse. Em breve entraremos em contato.');
        }

        try {
            Mail::to($recipients)->send(new LandingInterestMail(
                submitterName: $name,
                submitterEmail: $email,
                company: $company,
                message: $message,
            ));
            $submission->forceFill([
                'mail_sent_at' => now(),
                'mail_error' => null,
            ])->save();
        } catch (\Throwable $e) {
            Log::error('Landing interest: falha ao enviar e-mail.', [
                'submission_id' => $submission->id,
                'exception' => $e,
            ]);
            $submission->update([
                'mail_error' => Str::limit(mb_scrub((string) $e->getMessage(), 'UTF-8'), 2000),
            ]);
        }

        return back()->with('success', 'Recebemos seu interesse. Em breve entraremos em contato.');
    }
}
