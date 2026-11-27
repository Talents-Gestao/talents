<?php

declare(strict_types=1);

namespace App\Services\Hiring;

use App\Enums\CompanyContactTopic;
use App\Enums\HiringCommunicationChannel;
use App\Enums\HiringCommunicationKind;
use App\Enums\HiringCommunicationStatus;
use App\Mail\HiringCommunicationMail;
use App\Models\CompanyContact;
use App\Models\HiringApplication;
use App\Models\HiringCommunication;
use App\Models\HiringFollowupInvitation;
use App\Models\HiringVacancy;
use App\Models\HiringVacancyStage;
use App\Models\MailSetting;
use App\Models\TalentCandidate;
use App\Support\Hiring\CompanyContactEligibility;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Comunicações da vaga aos contatos da empresa (tópico «vagas»):
 * - só contatos elegíveis (CompanyContactEligibility) da própria empresa da vaga;
 * - idempotência por hash de tipo + vaga + etapa/candidatura + contato + canal + texto: o mesmo conteúdo
 *   não sai duas vezes; «Enviar novamente» gera e registra uma chave nova;
 * - e-mail pelo SMTP do painel, no máximo DAILY_EMAIL_LIMIT por dia (fuso America/Sao_Paulo); falha vira «failed»;
 * - WhatsApp só gera o texto (rascunho) e registra «copied» quando o usuário copia: nunca «enviado» nem «entregue».
 */
final class HiringCommunicationService
{
    /** Limite fixo de e-mails de comunicação (etapa + dossiê) aceitos pelo servidor por dia. */
    public const DAILY_EMAIL_LIMIT = 20;

    /**
     * Texto-modelo da atualização de etapa: nome da vaga, etapa e quantidade de candidatos na etapa.
     * Nunca leva nome nem dado de candidato.
     *
     * @return array{subject: string, body: string}
     */
    public function stageTemplate(HiringVacancy $vacancy, HiringVacancyStage $stage): array
    {
        $count = $stage->applications()->count();
        $candidates = $count === 1 ? '1 candidato' : $count.' candidatos';

        return [
            'subject' => 'Atualização da vaga '.$vacancy->title.' — '.$stage->name,
            'body' => "Olá,\n\n"
                ."A vaga {$vacancy->title} está na etapa «{$stage->name}», com {$candidates} nesta etapa.\n\n"
                ."Seguimos à disposição para qualquer dúvida sobre o processo.\n\n"
                .'Equipe Talents Gestão de Pessoas',
        ];
    }

    /**
     * @return array{subject: string, body: string}
     */
    public function dossierTemplate(HiringApplication $application): array
    {
        $vacancy = $application->vacancy;

        return [
            'subject' => 'Dossiê da candidatura — '.$vacancy->title,
            'body' => "Olá,\n\n"
                ."Segue em anexo o dossiê aprovado de uma candidatura da vaga {$vacancy->title}.\n\n"
                ."O documento é de uso restrito da sua empresa no processo de contratação.\n\n"
                .'Equipe Talents Gestão de Pessoas',
        ];
    }

    /** E-mails de comunicação aceitos pelo servidor hoje (fuso America/Sao_Paulo). */
    /**
     * E-mails de Contratação aceitos pelo servidor hoje (fuso America/Sao_Paulo): comunicações da vaga,
     * dossiês e convites do pós-contratação contam no mesmo limite.
     */
    public function emailsSentToday(): int
    {
        $start = now('America/Sao_Paulo')->startOfDay()->setTimezone(config('app.timezone'));

        $communications = HiringCommunication::query()
            ->where('channel', HiringCommunicationChannel::Email->value)
            ->where('status', HiringCommunicationStatus::Sent->value)
            ->where('sent_at', '>=', $start)
            ->count();

        $invitations = HiringFollowupInvitation::query()
            ->where('emailed_at', '>=', $start)
            ->count();

        return $communications + $invitations;
    }

    public function emailsRemainingToday(): int
    {
        return max(0, self::DAILY_EMAIL_LIMIT - $this->emailsSentToday());
    }

    /**
     * Revisão antes do envio: para cada contato, destinatário, elegibilidade e se o mesmo texto já saiu.
     *
     * @param  list<int>  $contactIds
     * @return list<array<string, mixed>>
     */
    public function review(
        HiringVacancy $vacancy,
        HiringCommunicationKind $kind,
        ?HiringVacancyStage $stage,
        ?HiringApplication $application,
        array $contactIds,
        HiringCommunicationChannel $channel,
        string $subject,
        string $body,
    ): array {
        $contacts = $this->contactsOfVacancyCompany($vacancy, $contactIds);
        $scope = $this->scopeKey($kind, $stage, $application);

        return $contacts->map(function (CompanyContact $contact) use ($vacancy, $kind, $scope, $channel, $subject, $body): array {
            $key = $this->baseKey($vacancy, $kind, $scope, $contact, $channel, $subject, $body);
            $previous = HiringCommunication::query()->where('idempotency_key', $key)->first();

            return [
                'contact_id' => $contact->id,
                'name' => $contact->name,
                'recipient' => $this->recipient($contact, $channel),
                'reasons' => CompanyContactEligibility::reasons($contact, CompanyContactTopic::Vagas, $channel),
                'already_done' => $previous !== null && $previous->status->isDone(),
                'previous_status' => $previous?->status->label(),
                'previous_at' => ($previous?->sent_at ?? $previous?->updated_at)?->toIso8601String(),
            ];
        })->values()->all();
    }

    /**
     * @param  list<int>  $contactIds
     * @return Collection<int, HiringCommunication>
     */
    public function sendStageUpdate(
        HiringVacancy $vacancy,
        HiringVacancyStage $stage,
        array $contactIds,
        HiringCommunicationChannel $channel,
        string $subject,
        string $body,
        bool $resend,
        ?int $userId,
    ): Collection {
        $this->assertNoCandidateData($vacancy, $subject."\n".$body);

        return $this->dispatch(
            $vacancy,
            HiringCommunicationKind::StageUpdate,
            $stage,
            null,
            $contactIds,
            $channel,
            $subject,
            $body,
            $resend,
            $userId,
        );
    }

    /**
     * Dossiê aprovado: só por e-mail, PDF gerado do conteúdo aprovado no momento do envio.
     *
     * @param  list<int>  $contactIds
     * @return Collection<int, HiringCommunication>
     */
    public function sendDossier(
        HiringApplication $application,
        array $contactIds,
        string $subject,
        string $body,
        bool $resend,
        ?int $userId,
    ): Collection {
        $application->loadMissing(['vacancy', 'assessment', 'candidate']);
        if ($application->assessment?->isApproved() !== true) {
            throw ValidationException::withMessages(['dossier' => 'O dossiê só pode ser enviado depois de aprovado.']);
        }

        $pdf = fn (): array => [
            app(HiringDossierRenderer::class)->pdf($application)->output(),
            'dossie-candidatura-'.$application->id.'.pdf',
        ];

        return $this->dispatch(
            $application->vacancy,
            HiringCommunicationKind::Dossier,
            null,
            $application,
            $contactIds,
            HiringCommunicationChannel::Email,
            $subject,
            $body,
            $resend,
            $userId,
            $pdf,
        );
    }

    /** «Copiar» no WhatsApp: registra que o texto foi copiado. Nunca marca como enviado nem entregue. */
    public function markCopied(HiringCommunication $communication): HiringCommunication
    {
        if ($communication->channel !== HiringCommunicationChannel::Whatsapp) {
            throw ValidationException::withMessages(['communication' => 'Só o texto do WhatsApp é registrado como copiado.']);
        }

        if ($communication->status !== HiringCommunicationStatus::Copied) {
            $communication->status = HiringCommunicationStatus::Copied;
            $communication->save();
        }

        return $communication;
    }

    /**
     * @param  list<int>  $contactIds
     * @param  (callable(): array{0: string, 1: string})|null  $attachment
     * @return Collection<int, HiringCommunication>
     */
    private function dispatch(
        HiringVacancy $vacancy,
        HiringCommunicationKind $kind,
        ?HiringVacancyStage $stage,
        ?HiringApplication $application,
        array $contactIds,
        HiringCommunicationChannel $channel,
        string $subject,
        string $body,
        bool $resend,
        ?int $userId,
        ?callable $attachment = null,
    ): Collection {
        $subject = trim($subject);
        $body = $this->normalizeText($body);
        if ($body === '' || ($channel === HiringCommunicationChannel::Email && $subject === '')) {
            throw ValidationException::withMessages(['body' => 'Preencha o assunto e o texto da comunicação.']);
        }

        $contacts = $this->contactsOfVacancyCompany($vacancy, $contactIds);
        if ($contacts->isEmpty()) {
            throw ValidationException::withMessages(['contact_ids' => 'Escolha ao menos um contato.']);
        }

        $ineligible = $contacts
            ->map(fn (CompanyContact $c) => [$c, CompanyContactEligibility::reasons($c, CompanyContactTopic::Vagas, $channel)])
            ->filter(fn (array $pair) => $pair[1] !== []);
        if ($ineligible->isNotEmpty()) {
            throw ValidationException::withMessages([
                'contact_ids' => $ineligible
                    ->map(fn (array $pair) => $pair[0]->name.': '.implode(' ', $pair[1]))
                    ->implode(' '),
            ]);
        }

        $scope = $this->scopeKey($kind, $stage, $application);

        // Idempotência: o mesmo conteúdo não sai duas vezes sem «Enviar novamente».
        $plans = $contacts->map(function (CompanyContact $contact) use ($vacancy, $kind, $scope, $channel, $subject, $body, $resend): array {
            $base = $this->baseKey($vacancy, $kind, $scope, $contact, $channel, $subject, $body);
            $existing = HiringCommunication::query()->where('idempotency_key', $base)->first();

            return ['contact' => $contact, 'base' => $base, 'existing' => $existing, 'duplicate' => ! $resend && $existing?->status->isDone()];
        });

        $duplicates = $plans->filter(fn (array $p) => $p['duplicate']);
        if ($duplicates->isNotEmpty()) {
            throw ValidationException::withMessages([
                'duplicate' => 'Este mesmo texto já saiu para '
                    .$duplicates->map(fn (array $p) => $p['contact']->name)->implode(', ')
                    .'. Mude o texto ou confirme «Enviar novamente».',
            ]);
        }

        if ($channel === HiringCommunicationChannel::Email) {
            if (! MailSetting::smtpConfigured()) {
                throw ValidationException::withMessages([
                    'channel' => 'Configure e ligue o SMTP em Configuração → Geral para enviar e-mails.',
                ]);
            }
            $remaining = $this->emailsRemainingToday();
            if ($contacts->count() > $remaining) {
                throw ValidationException::withMessages([
                    'limit' => 'Limite de '.self::DAILY_EMAIL_LIMIT.' e-mails de comunicação por dia. Hoje restam '.$remaining.'.',
                ]);
            }
        }

        $attachmentData = null;
        $attachmentName = null;
        if ($attachment !== null) {
            [$attachmentData, $attachmentName] = $attachment();
        }

        return $plans->map(function (array $plan) use ($vacancy, $kind, $stage, $application, $channel, $subject, $body, $resend, $userId, $attachmentData, $attachmentName): HiringCommunication {
            /** @var CompanyContact $contact */
            $contact = $plan['contact'];
            /** @var HiringCommunication|null $existing */
            $existing = $plan['existing'];

            $attributes = [
                'hiring_vacancy_id' => $vacancy->id,
                'hiring_application_id' => $application?->id,
                'company_contact_id' => $contact->id,
                'kind' => $kind,
                'channel' => $channel,
                'stage_name' => $stage?->name ?? $application?->stage?->name,
                'recipient' => $this->recipient($contact, $channel),
                'subject' => $subject !== '' ? $subject : null,
                'body' => $body,
                'status' => HiringCommunicationStatus::Draft,
                'sent_at' => null,
                'failure_reason' => null,
                'created_by' => $userId,
            ];

            // Rascunho ou falha anterior com o mesmo conteúdo: tenta de novo no mesmo registro.
            if (! $resend && $existing !== null) {
                $existing->fill($attributes)->save();
                $communication = $existing;
            } else {
                $key = $resend ? hash('sha256', $plan['base'].'|reenvio|'.Str::uuid()->toString()) : $plan['base'];
                try {
                    $communication = HiringCommunication::query()->create([...$attributes, 'idempotency_key' => $key]);
                } catch (UniqueConstraintViolationException) {
                    throw ValidationException::withMessages([
                        'duplicate' => 'Este mesmo texto já está registrado para '.$contact->name.'. Mude o texto ou confirme «Enviar novamente».',
                    ]);
                }
            }

            if ($channel === HiringCommunicationChannel::Email) {
                $this->sendEmail($communication, $contact, $subject, $body, $attachmentData, $attachmentName);
            }

            return $communication;
        })->values();
    }

    private function sendEmail(
        HiringCommunication $communication,
        CompanyContact $contact,
        string $subject,
        string $body,
        ?string $attachmentData,
        ?string $attachmentName,
    ): void {
        try {
            Mail::to($contact->email, $contact->name)->send(
                new HiringCommunicationMail($subject, $body, $attachmentData, $attachmentName),
            );
            $communication->status = HiringCommunicationStatus::Sent;
            $communication->sent_at = now();
            $communication->failure_reason = null;
        } catch (\Throwable $e) {
            report($e);
            $communication->status = HiringCommunicationStatus::Failed;
            $communication->failure_reason = mb_substr('O servidor de e-mail recusou o envio: '.$e->getMessage(), 0, 500);
        }

        $communication->save();
    }

    /**
     * Contatos pedidos, só da empresa da vaga: contato de outra empresa é recusado.
     *
     * @param  list<int>  $contactIds
     * @return Collection<int, CompanyContact>
     */
    private function contactsOfVacancyCompany(HiringVacancy $vacancy, array $contactIds): Collection
    {
        $ids = array_values(array_unique(array_map('intval', $contactIds)));
        $contacts = CompanyContact::query()->whereKey($ids)->get();

        if ($contacts->count() !== count($ids) || $contacts->contains(fn (CompanyContact $c) => (int) $c->company_id !== (int) $vacancy->company_id)) {
            throw ValidationException::withMessages([
                'contact_ids' => 'Escolha só contatos da empresa desta vaga.',
            ]);
        }

        return $contacts->sortBy('name')->values();
    }

    /**
     * O texto da etapa não pode ter nome, e-mail nem telefone de candidato da vaga.
     */
    private function assertNoCandidateData(HiringVacancy $vacancy, string $text): void
    {
        $haystack = mb_strtolower($text);
        $digitsText = preg_replace('/\D+/', '', $text) ?? '';

        $candidates = TalentCandidate::query()
            ->whereIn('id', HiringApplication::query()->select('talent_candidate_id')->where('hiring_vacancy_id', $vacancy->id))
            ->get(['id', 'name', 'email', 'phone_digits']);

        foreach ($candidates as $candidate) {
            $name = mb_strtolower(trim((string) $candidate->name));
            $email = mb_strtolower(trim((string) $candidate->email));
            $phone = (string) $candidate->phone_digits;

            if ((mb_strlen($name) >= 3 && str_contains($haystack, $name))
                || ($email !== '' && str_contains($haystack, $email))
                || (strlen($phone) >= 8 && str_contains($digitsText, $phone))) {
                throw ValidationException::withMessages([
                    'body' => 'A comunicação de etapa não pode citar nome, e-mail ou telefone de candidato.',
                ]);
            }
        }
    }

    private function scopeKey(HiringCommunicationKind $kind, ?HiringVacancyStage $stage, ?HiringApplication $application): string
    {
        if ($kind === HiringCommunicationKind::Dossier) {
            // Nova aprovação (conteúdo alterado e aprovado de novo) muda a chave.
            return 'application:'.$application?->id.':approved:'.$application?->assessment?->approved_at?->getTimestamp();
        }

        return 'stage:'.$stage?->id;
    }

    private function baseKey(
        HiringVacancy $vacancy,
        HiringCommunicationKind $kind,
        string $scope,
        CompanyContact $contact,
        HiringCommunicationChannel $channel,
        string $subject,
        string $body,
    ): string {
        return hash('sha256', implode('|', [
            $kind->value,
            'vacancy:'.$vacancy->id,
            $scope,
            'contact:'.$contact->id,
            $channel->value,
            hash('sha256', $this->normalizeText($subject)."\n".$this->normalizeText($body)),
        ]));
    }

    private function recipient(CompanyContact $contact, HiringCommunicationChannel $channel): ?string
    {
        return $channel === HiringCommunicationChannel::Email ? $contact->email : $contact->phone;
    }

    private function normalizeText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+$/m', '', $text) ?? $text;

        return trim($text);
    }
}
