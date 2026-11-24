<?php

declare(strict_types=1);

namespace App\Support\Feedback;

use App\Enums\AdminPermissionModule;
use App\Enums\FeedbackSessionStatus;
use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Models\FeedbackRecording;
use App\Models\FeedbackSession;
use App\Support\Feedback\Ai\FeedbackAiGate;
use App\Support\Feedback\Ai\FeedbackAiMonthlyLimit;
use App\Support\Feedback\Ai\FeedbackReportPresenter;
use Illuminate\Http\Request;

/**
 * Quem faz o quê com a gravação (transcrição do Plaud) de um feedback. Vale depois de
 * FeedbackVisibility::authorizeSession (empresa e líder já conferidos):
 * - enviar e excluir: quem acessa a sessão e tem «criar»/«excluir» em Feedbacks (portal ou matriz do admin);
 * - ver o texto original: só o administrador da empresa e a Talents;
 * - sessão concluída ou cancelada: gravação travada (não envia nem exclui por aqui).
 */
final class FeedbackRecordingAccess
{
    public static function isLocked(FeedbackSession $session): bool
    {
        return in_array($session->status, [FeedbackSessionStatus::Completed, FeedbackSessionStatus::Cancelled], true);
    }

    public static function canUpload(Request $request, FeedbackSession $session): bool
    {
        return ! self::isLocked($session) && self::hasAction($request, PermissionAction::Create);
    }

    public static function canDelete(Request $request, FeedbackSession $session): bool
    {
        return ! self::isLocked($session) && self::hasAction($request, PermissionAction::Delete);
    }

    public const REWIND_SIGNATURE_MESSAGE = 'O feedback está em assinatura ou concluído: as etapas da gravação não podem ser refeitas.';

    public const REWIND_PROCESSING_MESSAGE = 'A gravação está sendo processada. Aguarde terminar para voltar uma etapa.';

    /**
     * Voltar etapas da barra «Gravação»: nunca com assinatura solicitada ou concluída (nem com a sessão
     * concluída ou cancelada). Devolve o motivo do bloqueio, ou null se a sessão permite voltar.
     */
    public static function rewindBlockedReason(FeedbackSession $session): ?string
    {
        $blocked = self::isLocked($session)
            || $session->status === FeedbackSessionStatus::AwaitingSignatures
            || $session->signatures()->exists();

        return $blocked ? self::REWIND_SIGNATURE_MESSAGE : null;
    }

    public static function canViewOriginal(Request $request): bool
    {
        return FeedbackVisibility::actsAsCompanyAdmin($request->user());
    }

    /** Mapear falantes, processar, tentar de novo e gerar o relatório novamente: mesma regra do envio. */
    public static function canProcess(Request $request, FeedbackSession $session): bool
    {
        return self::canUpload($request, $session);
    }

    /** Editar a transcrição limpa: administrador e líder da sessão com «editar» em Feedbacks, sessão aberta. */
    public static function canEditClean(Request $request, FeedbackSession $session): bool
    {
        return ! self::isLocked($session) && self::hasAction($request, PermissionAction::Edit);
    }

    /**
     * @return array{canUpload: bool, canDelete: bool, canViewOriginal: bool, canProcess: bool, canEditClean: bool, locked: bool, rewindBlockedReason: string|null}
     */
    public static function flags(Request $request, FeedbackSession $session): array
    {
        return [
            'rewindBlockedReason' => self::rewindBlockedReason($session),
            'canUpload' => self::canUpload($request, $session),
            'canDelete' => self::canDelete($request, $session),
            'canViewOriginal' => self::canViewOriginal($request),
            'canProcess' => self::canProcess($request, $session),
            'canEditClean' => self::canEditClean($request, $session),
            'locked' => self::isLocked($session),
        ];
    }

    /**
     * Estado da IA na sessão: trava do provedor (e quem vai receber o texto), falantes, processamento, transcrição
     * limpa e relatório atual. Só chega a quem já passou por authorizeSession (administrador e líder da sessão).
     *
     * @return array<string, mixed>|null
     */
    public static function aiPayload(?FeedbackRecording $recording): ?array
    {
        if ($recording === null) {
            return null;
        }

        $recording->loadMissing(['speakersMappedBy', 'cleanEditedBy']);
        $gate = app(FeedbackAiGate::class)->status();
        $map = is_array($recording->speaker_map) ? $recording->speaker_map : [];
        $mapByKey = [];
        foreach ($map as $label => $role) {
            $mapByKey[TranscriptSpeakers::key((string) $label)] = $role;
        }

        $speakers = [];
        $suggestion = null;
        if ($recording->has_speaker_labels) {
            $text = $recording->safeOriginalText();
            $utterances = collect(TranscriptSpeakers::utterances($text))->keyBy(fn ($u) => TranscriptSpeakers::key($u['label']));
            // Só a contagem e a primeira fala; as frases de «Ver trechos» vêm sob demanda (samples()), fora do HTML.
            $speakers = array_map(fn (array $s) => $s + [
                'role' => $mapByKey[TranscriptSpeakers::key($s['label'])] ?? null,
            ], TranscriptSpeakers::labels($text));

            // Sugestão sem IA para «Confirmar falantes» (nomes da sessão; só sugere, o usuário confirma).
            $recording->loadMissing('session.leader', 'session.employee');
            $suggestion = SpeakerRoleSuggestion::suggest(
                $utterances->values()->all(),
                $recording->session?->leader?->name,
                $recording->session?->collaboratorDisplayName(),
            );
        }

        $current = $recording->currentReport();
        $company = $recording->session?->company;

        return [
            'gate' => [
                'allowed' => $gate['allowed'],
                'provider_label' => $gate['provider_label'],
                'model' => $gate['model'],
                'message' => $gate['allowed'] ? null : FeedbackAiGate::LOCKED_MESSAGE,
            ],
            'limit_reached' => $company !== null && FeedbackAiMonthlyLimit::usedCents($company->id) >= FeedbackAiMonthlyLimit::limitCents($company),
            'speakers' => $speakers,
            'speaker_suggestion' => $suggestion,
            'speakers_mapped' => $map !== [],
            'speakers_mapped_at' => $recording->speakers_mapped_at?->toIso8601String(),
            'speakers_mapped_by' => $recording->speakersMappedBy?->name,
            'status' => $recording->processing_status,
            'status_label' => $recording->statusLabel(),
            'mode' => $recording->processing_mode,
            'stage' => $recording->processing_stage,
            'chunks_done' => $recording->processing_chunks_done,
            'chunks_total' => $recording->processing_chunks_total,
            'error' => $recording->processing_status === FeedbackRecording::STATUS_FAILED ? $recording->processing_error : null,
            'attempts' => $recording->processing_attempts,
            'processed_at' => $recording->processed_at?->toIso8601String(),
            'clean' => $recording->hasCleanText() ? [
                'text' => $recording->safeCleanText() ?? '[Conteúdo indisponível: chave de criptografia alterada.]',
                'revision' => $recording->clean_revision,
                'generated_at' => $recording->clean_generated_at?->toIso8601String(),
                'edited_at' => $recording->clean_edited_at?->toIso8601String(),
                'edited_by' => $recording->cleanEditedBy?->name,
            ] : null,
            'report' => FeedbackReportPresenter::present($current, $current ? $recording->reports()->count() : 0),
        ];
    }

    /**
     * Metadados exibidos na sessão (sem o texto nem o nome do arquivo).
     *
     * @return array<string, mixed>|null
     */
    public static function payload(?FeedbackRecording $recording): ?array
    {
        if ($recording === null) {
            return null;
        }

        $recording->loadMissing(['uploadedBy', 'consentBy']);

        return [
            'id' => $recording->id,
            'format' => $recording->source_format,
            'format_label' => $recording->formatLabel(),
            'sha256' => $recording->file_sha256,
            'size_bytes' => $recording->file_size_bytes,
            'chars' => $recording->original_chars,
            'has_speaker_labels' => $recording->has_speaker_labels,
            'has_timestamps' => $recording->has_timestamps,
            'uploaded_at' => $recording->created_at?->toIso8601String(),
            'uploaded_by' => $recording->uploadedBy?->name,
            'consent_at' => $recording->consent_confirmed_at?->toIso8601String(),
            'consent_by' => $recording->consentBy?->name,
        ];
    }

    private static function hasAction(Request $request, PermissionAction $action): bool
    {
        $user = $request->user();

        if (app(FeedbackCompanyContext::class)->isAdminContext($request)) {
            return $user->canAccessAdmin(AdminPermissionModule::Feedbacks, $action);
        }

        return $user->canAccess(PermissionModule::Feedbacks, $action);
    }
}
