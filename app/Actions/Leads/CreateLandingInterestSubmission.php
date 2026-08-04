<?php

declare(strict_types=1);

namespace App\Actions\Leads;

use App\Actions\Notices\PublishLeadNotice;
use App\Enums\LandingInterestSource;
use App\Mail\LandingInterestMail;
use App\Models\LandingInterestSubmission;
use App\Models\MailSetting;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreateLandingInterestSubmission
{
    public function __construct(
        private readonly PublishLeadNotice $notices,
    ) {}

    /**
     * @param  array{
     *     name: string,
     *     email: string,
     *     phone?: string|null,
     *     company?: string|null,
     *     message?: string|null,
     *     source: string|LandingInterestSource
     * }  $data
     */
    public function execute(array $data, ?User $createdBy = null): LandingInterestSubmission
    {
        $source = $data['source'] instanceof LandingInterestSource
            ? $data['source']
            : LandingInterestSource::tryFrom((string) $data['source']);

        if ($source === null) {
            throw new InvalidArgumentException('Origem do lead inválida.');
        }

        $name = trim((string) $data['name']);
        $email = trim((string) $data['email']);
        $phone = isset($data['phone']) && $data['phone'] !== '' ? trim((string) $data['phone']) : null;
        $company = isset($data['company']) && $data['company'] !== '' ? trim((string) $data['company']) : null;
        $message = isset($data['message']) && $data['message'] !== '' ? trim((string) $data['message']) : null;

        $submission = LandingInterestSubmission::query()->create([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'company' => $company,
            'message' => $message,
            'source' => $source->value,
            'created_by' => $createdBy?->id,
        ]);

        $this->notices->received($submission);
        $this->sendNotificationMail($submission);

        return $submission->fresh() ?? $submission;
    }

    private function sendNotificationMail(LandingInterestSubmission $submission): void
    {
        MailSetting::applyToRuntimeConfig();

        $recipients = config('landing.interest_recipients', []);
        if ($recipients === []) {
            Log::warning('Landing interest: lista de destinatários vazia.', ['submission_id' => $submission->id]);
            $submission->update([
                'mail_error' => 'Lista de destinatários vazia (configuração).',
            ]);

            return;
        }

        try {
            Mail::to($recipients)->send(new LandingInterestMail(
                submitterName: $submission->name,
                submitterEmail: $submission->email,
                phone: $submission->phone,
                company: $submission->company,
                submitterMessage: $submission->message,
                sourceLabel: $submission->sourceEnum()->label(),
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
                'mail_error' => $this->humanizeMailError($e->getMessage()),
            ]);
        }
    }

    private function humanizeMailError(string $raw): string
    {
        $clean = Str::limit(mb_scrub($raw, 'UTF-8'), 2000);

        if (str_contains($clean, 'htmlspecialchars()')) {
            return 'Falha ao montar o conteúdo do e-mail de aviso.';
        }

        return $clean;
    }
}
