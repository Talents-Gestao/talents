<?php

namespace App\Http\Controllers;

use App\Actions\Notices\PublishLeadNotice;
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
    public function store(Request $request, PublishLeadNotice $notices): RedirectResponse
    {
        MailSetting::applyToRuntimeConfig();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'company' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:5000'],
        ]);

        $name = trim($data['name']);
        $email = trim($data['email']);
        $phone = isset($data['phone']) && $data['phone'] !== '' ? trim($data['phone']) : null;
        $company = isset($data['company']) && $data['company'] !== '' ? trim($data['company']) : null;
        $message = isset($data['message']) && $data['message'] !== '' ? trim($data['message']) : null;

        $submission = LandingInterestSubmission::query()->create([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'company' => $company,
            'message' => $message,
        ]);

        $notices->received($submission);

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
                phone: $phone,
                company: $company,
                submitterMessage: $message,
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
                'mail_error' => self::humanizeMailError($e->getMessage()),
            ]);
        }

        return back()->with('success', 'Recebemos seu interesse. Em breve entraremos em contato.');
    }

    /**
     * Mensagem amigável para a lista admin (detalhe técnico fica no log).
     */
    private static function humanizeMailError(string $raw): string
    {
        $clean = Str::limit(mb_scrub($raw, 'UTF-8'), 2000);

        if (str_contains($clean, 'htmlspecialchars()')) {
            return 'Falha ao montar o conteúdo do e-mail de aviso.';
        }

        return $clean;
    }
}
