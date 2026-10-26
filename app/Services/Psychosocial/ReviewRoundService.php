<?php

declare(strict_types=1);

namespace App\Services\Psychosocial;

use App\Exceptions\PsychosocialDocumentException;
use App\Models\DocumentShare;
use App\Models\DocumentShareReview;
use App\Models\PsychosocialDocument;
use App\Models\PsychosocialDocumentVersion;
use App\Models\PsychosocialPassegOptionChange;
use App\Models\PsychosocialPgrIntegration;
use App\Models\PsychosocialReviewAcknowledgement;
use App\Models\PsychosocialReviewEvent;
use App\Models\PsychosocialReviewRound;
use App\Models\Survey;
use App\Models\User;
use App\Policies\PsychosocialDocumentVersionPolicy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Fluxo de documentos da campanha V11 (Caderno §12 e §13): dois documentos finais (relatório executivo e relatório
 * técnico + plano de ação) revisados e publicados JUNTOS, numa rodada.
 *
 * Em edição → Em revisão PASSEG → Alteração solicitada | Aprovado pela PASSEG → Publicado.
 * Talents: iniciar nova revisão, enviar à PASSEG, publicar. Revisor externo: aprovar, solicitar alteração (texto obrigatório).
 * Publicar define o "Publicado" que o SurveyPublicationGate já usa. A rodada publicada é imutável (T20).
 */
class ReviewRoundService
{
    public const MIN_CHANGE_REQUEST_LENGTH = 8;

    public const APPROVAL_MEANING = '«Aprovado pela PASSEG» significa revisão documental. A integração ao PGR é um campo separado e só é confirmada com evidência de SST.';

    public function __construct(
        private readonly PsychosocialDocumentService $documents,
        private readonly DocumentShareService $shares,
        private readonly DocumentPublicationChecklist $checklist,
    ) {}

    public function currentRound(Survey $survey): ?PsychosocialReviewRound
    {
        return PsychosocialReviewRound::query()->where('survey_id', $survey->id)->orderByDesc('round_number')->first();
    }

    public function publishedRound(Survey $survey): ?PsychosocialReviewRound
    {
        return PsychosocialReviewRound::query()
            ->where('survey_id', $survey->id)
            ->where('status', PsychosocialReviewRound::STATUS_PUBLISHED)
            ->orderByDesc('round_number')
            ->first();
    }

    /**
     * Versão que a EMPRESA enxerga: sempre a da última rodada publicada, nunca um rascunho em andamento.
     */
    public function publishedVersion(Survey $survey, string $type): ?PsychosocialDocumentVersion
    {
        $round = $this->publishedRound($survey);

        return match ($type) {
            PsychosocialDocument::TYPE_EXECUTIVE_BRIEF => $round?->executiveVersion,
            PsychosocialDocument::TYPE_TECHNICAL_REPORT => $round?->technicalVersion,
            default => null,
        };
    }

    /** "Iniciar nova revisão": abre uma rodada em edição. Só existe uma rodada aberta por vez. */
    public function start(Survey $survey, User $actor): PsychosocialReviewRound
    {
        $this->assertTalents($actor);
        $this->assertReviewFlow($survey);

        return DB::transaction(function () use ($survey, $actor): PsychosocialReviewRound {
            $latest = PsychosocialReviewRound::query()->where('survey_id', $survey->id)->orderByDesc('round_number')->lockForUpdate()->first();

            if ($latest !== null && ! $latest->isPublished()) {
                throw new PsychosocialDocumentException('Já existe uma revisão em andamento nesta campanha.');
            }

            $round = PsychosocialReviewRound::query()->create([
                'survey_id' => $survey->id,
                'company_id' => $survey->company_id,
                'round_number' => ($latest?->round_number ?? 0) + 1,
                'status' => PsychosocialReviewRound::STATUS_DRAFT,
                'created_by' => $actor->id,
            ]);

            $this->event($round, $actor, 'created', null, $round->status, $latest === null ? 'Primeira revisão da campanha.' : 'Nova versão aberta a partir da rodada '.$latest->round_number.' (publicada).');

            return $round;
        });
    }

    /**
     * Gera (ou gera de novo) os DOIS documentos a partir dos dados atuais, congelando snapshot e PDF com hash.
     * Cada geração é uma versão nova; as anteriores ficam preservadas.
     */
    public function regenerate(PsychosocialReviewRound $round, User $actor): PsychosocialReviewRound
    {
        $this->assertTalents($actor);
        $this->assertEditable($round);

        $survey = $round->survey;
        $this->assertReviewFlow($survey);

        $executive = $this->documents->release($survey, PsychosocialDocument::TYPE_EXECUTIVE_BRIEF);
        $technical = $this->documents->release($survey, PsychosocialDocument::TYPE_TECHNICAL_REPORT);

        $round->fill([
            'executive_version_id' => $executive->id,
            'technical_version_id' => $technical->id,
        ])->save();

        $this->event(
            $round,
            $actor,
            'regenerated',
            $round->status,
            $round->status,
            "Relatório executivo v{$executive->version_number} e relatório técnico v{$technical->version_number}.",
        );

        return $round->fresh();
    }

    /**
     * Pendências (campos vazios ou fictícios, PDF não gerado) dos dois documentos da rodada.
     *
     * @return list<array{field: string, label: string, problem: string, where: string}>
     */
    public function pending(PsychosocialReviewRound $round): array
    {
        if ($round->executive_version_id === null || $round->technical_version_id === null) {
            return [[
                'field' => 'documents',
                'label' => 'Documentos',
                'problem' => 'ainda não foram gerados',
                'where' => 'Documentos',
            ]];
        }

        return $this->checklist->pendingForVersions([$round->executiveVersion, $round->technicalVersion]);
    }

    /** "Enviar à PASSEG": congela os dois documentos para o revisor externo. Exige a revisão externa ligada. */
    public function send(PsychosocialReviewRound $round, User $actor, User $reviewer, int $shareDays = 30): PsychosocialReviewRound
    {
        $this->assertTalents($actor);

        if (! $round->survey->requires_passeg_review) {
            throw new PsychosocialDocumentException('Esta campanha não exige revisão PASSEG: a Talents publica diretamente.');
        }

        if (! in_array($round->status, [PsychosocialReviewRound::STATUS_DRAFT, PsychosocialReviewRound::STATUS_CHANGES_REQUESTED], true)) {
            $this->assertTransition($round, PsychosocialReviewRound::STATUS_IN_REVIEW);
        }

        if (! DocumentShareService::enabled()) {
            throw new PsychosocialDocumentException('A revisão externa de documentos está desligada: não é possível enviar à PASSEG.');
        }

        $pending = $this->pending($round);
        if ($pending !== []) {
            throw new PsychosocialDocumentException('Pendências antes de enviar: '.$this->describe($pending).'.');
        }

        if ($round->status === PsychosocialReviewRound::STATUS_CHANGES_REQUESTED) {
            $lastRequest = (int) $round->events()->where('action', 'change_requested')->max('id');
            $lastRegeneration = (int) $round->events()->where('action', 'regenerated')->max('id');
            if ($lastRegeneration < $lastRequest) {
                throw new PsychosocialDocumentException('Gere novas versões dos documentos antes de reenviar: a mudança exige nova versão.');
            }
        }

        return DB::transaction(function () use ($round, $actor, $reviewer, $shareDays): PsychosocialReviewRound {
            $round = PsychosocialReviewRound::query()->lockForUpdate()->findOrFail($round->id);

            foreach ([$round->executiveVersion, $round->technicalVersion] as $version) {
                $this->shareWith($version, $reviewer, $actor, $shareDays);
            }

            $from = $round->status;
            $round->fill([
                'status' => PsychosocialReviewRound::STATUS_IN_REVIEW,
                'sent_at' => now(),
                'sent_by' => $actor->id,
            ])->save();

            $this->event($round, $actor, 'sent', $from, $round->status, 'Enviado a '.$reviewer->name.'.');

            return $round;
        });
    }

    /** Revisor externo: devolve à Talents. O texto é obrigatório e fica no histórico (T04). */
    public function requestChanges(PsychosocialReviewRound $round, User $reviewer, string $text): PsychosocialReviewRound
    {
        $share = $this->reviewerShare($round, $reviewer);
        $this->assertTransition($round, PsychosocialReviewRound::STATUS_CHANGES_REQUESTED);

        $text = trim($text);
        if (mb_strlen($text) < self::MIN_CHANGE_REQUEST_LENGTH) {
            throw new PsychosocialDocumentException('Descreva a alteração solicitada (mínimo de '.self::MIN_CHANGE_REQUEST_LENGTH.' caracteres).');
        }

        return DB::transaction(function () use ($round, $reviewer, $share, $text): PsychosocialReviewRound {
            $round = PsychosocialReviewRound::query()->lockForUpdate()->findOrFail($round->id);

            DocumentShareReview::query()->create([
                'document_share_id' => $share->id,
                'user_id' => $reviewer->id,
                'kind' => DocumentShareReview::KIND_CHANGE_REQUEST,
                'body' => $text,
            ]);

            $from = $round->status;
            $round->fill(['status' => PsychosocialReviewRound::STATUS_CHANGES_REQUESTED])->save();
            $this->event($round, $reviewer, 'change_requested', $from, $round->status, $text);

            return $round;
        });
    }

    /** Revisor externo: aprova a versão enviada (revisão documental). Mudança depois disso exige nova rodada. */
    public function approve(PsychosocialReviewRound $round, User $reviewer): PsychosocialReviewRound
    {
        $share = $this->reviewerShare($round, $reviewer);
        $this->assertTransition($round, PsychosocialReviewRound::STATUS_APPROVED);

        return DB::transaction(function () use ($round, $reviewer, $share): PsychosocialReviewRound {
            $round = PsychosocialReviewRound::query()->lockForUpdate()->findOrFail($round->id);

            $this->shares->approve($share, $reviewer);

            $from = $round->status;
            $round->fill([
                'status' => PsychosocialReviewRound::STATUS_APPROVED,
                'approved_at' => now(),
                'approved_by' => $reviewer->id,
            ])->save();
            $this->event($round, $reviewer, 'approved', $from, $round->status, self::APPROVAL_MEANING);

            return $round;
        });
    }

    /**
     * Publica os dois documentos juntos e define o "Publicado" que o gate usa. Registra data, responsável e o
     * protocolo de encaminhamento. As pendências (T19) são conferidas de novo sobre os snapshots congelados.
     */
    public function publish(PsychosocialReviewRound $round, User $actor): PsychosocialReviewRound
    {
        $this->assertTalents($actor);
        $this->assertTransition($round, PsychosocialReviewRound::STATUS_PUBLISHED);

        // Publicação direta (sem PASSEG) só quando a campanha não exige a revisão; com a revisão exigida só sai de «Aprovado».
        $direct = $round->status === PsychosocialReviewRound::STATUS_DRAFT;
        if ($direct && $round->survey->requires_passeg_review) {
            throw new PsychosocialDocumentException('Esta campanha exige revisão PASSEG: envie à PASSEG e publique depois da aprovação.');
        }

        $pending = $this->pending($round);
        if ($pending !== []) {
            throw new PsychosocialDocumentException('Não é possível publicar. Pendências: '.$this->describe($pending).'.');
        }

        return DB::transaction(function () use ($round, $actor, $direct): PsychosocialReviewRound {
            $round = PsychosocialReviewRound::query()->lockForUpdate()->findOrFail($round->id);
            $survey = $round->survey;

            $from = $round->status;
            $protocol = $this->protocol($round);

            $round->fill([
                'status' => PsychosocialReviewRound::STATUS_PUBLISHED,
                'published_at' => now(),
                'published_by' => $actor->id,
                'protocol' => $protocol,
                'published_without_review' => $direct,
            ])->save();

            if ($survey->published_at === null) {
                $survey->forceFill(['published_at' => now(), 'published_by' => $actor->id])->save();
            }

            $this->event(
                $round,
                $actor,
                'published',
                $from,
                $round->status,
                ($direct ? 'Publicação direta pela Talents, sem revisão PASSEG (não exigida nesta campanha). ' : '')."Protocolo de encaminhamento {$protocol}.",
            );

            return $round;
        });
    }

    /**
     * "Exigir revisão PASSEG" da campanha. Só muda sem revisão em andamento com a PASSEG (enviada, com alteração
     * solicitada ou aprovada), para não trocar o caminho no meio de uma revisão. Vale para as próximas publicações.
     */
    public function setPassegReviewRequired(Survey $survey, User $actor, bool $required): Survey
    {
        $this->assertTalents($actor);
        $this->assertReviewFlow($survey);

        $current = $this->currentRound($survey);
        if ($current !== null && ! in_array($current->status, [PsychosocialReviewRound::STATUS_DRAFT, PsychosocialReviewRound::STATUS_PUBLISHED], true)) {
            throw new PsychosocialDocumentException('Há uma revisão em andamento com a PASSEG: conclua a publicação antes de mudar essa configuração.');
        }

        $previous = (bool) $survey->requires_passeg_review;
        if ($previous === $required) {
            return $survey;
        }

        DB::transaction(function () use ($survey, $actor, $previous, $required): void {
            $survey->forceFill(['requires_passeg_review' => $required])->save();

            PsychosocialPassegOptionChange::query()->create([
                'survey_id' => $survey->id,
                'user_id' => $actor->id,
                'previous_value' => $previous,
                'new_value' => $required,
                'changed_at' => now(),
            ]);
        });

        return $survey;
    }

    /** Ciência da empresa (Caderno §13): uma por publicação, os dois documentos juntos. */
    public function acknowledge(PsychosocialReviewRound $round, User $actor): PsychosocialReviewAcknowledgement
    {
        $this->assertCompanyActor($round, $actor);

        if (! $round->isPublished()) {
            throw new PsychosocialDocumentException('A ciência só é registrada depois que os documentos forem publicados.');
        }

        return DB::transaction(function () use ($round, $actor): PsychosocialReviewAcknowledgement {
            if (PsychosocialReviewAcknowledgement::query()->where('round_id', $round->id)->lockForUpdate()->exists()) {
                throw new PsychosocialDocumentException('A ciência desta publicação já foi registrada.');
            }

            $ack = PsychosocialReviewAcknowledgement::query()->create([
                'round_id' => $round->id,
                'user_id' => $actor->id,
                'protocol' => (string) $round->protocol,
                'acknowledged_at' => now(),
            ]);

            $this->event($round, $actor, 'acknowledged', $round->status, $round->status, "Protocolo {$round->protocol}.");

            return $ack;
        });
    }

    /** "Integração ao PGR confirmada": campo separado da aprovação da PASSEG, só com a evidência (documento de SST). */
    public function confirmPgrIntegration(PsychosocialReviewRound $round, User $actor, ?string $note, ?UploadedFile $evidence): PsychosocialPgrIntegration
    {
        $this->assertCompanyActor($round, $actor);

        if (! $round->isPublished()) {
            throw new PsychosocialDocumentException('A integração ao PGR só é confirmada depois da publicação.');
        }
        if ($evidence === null) {
            throw new PsychosocialDocumentException('A integração ao PGR só é confirmada com a evidência (documento de SST).');
        }
        if ($round->pgrIntegration()->exists()) {
            throw new PsychosocialDocumentException('A integração ao PGR desta publicação já foi confirmada.');
        }

        $bytes = (string) file_get_contents($evidence->getRealPath());
        $hash = hash('sha256', $bytes);
        $extension = strtolower($evidence->getClientOriginalExtension() ?: 'bin');
        $path = "psychosocial/pgr-integrations/{$round->id}/{$hash}.{$extension}";
        Storage::disk(PsychosocialDocumentService::DISK)->put($path, $bytes);

        return DB::transaction(function () use ($round, $actor, $note, $evidence, $path, $hash): PsychosocialPgrIntegration {
            $integration = PsychosocialPgrIntegration::query()->create([
                'round_id' => $round->id,
                'user_id' => $actor->id,
                'note' => filled($note) ? trim((string) $note) : null,
                'evidence_path' => $path,
                'evidence_name' => $evidence->getClientOriginalName(),
                'evidence_sha256' => $hash,
                'confirmed_at' => now(),
            ]);

            $this->event($round, $actor, 'pgr_integration_confirmed', $round->status, $round->status, $evidence->getClientOriginalName());

            return $integration;
        });
    }

    /**
     * @return list<array{id: int, action: string, label: string, from: ?string, to: ?string, body: ?string, author: string, at: string}>
     */
    public function history(PsychosocialReviewRound $round): array
    {
        return $round->events()->with('user:id,name')->get()
            ->map(fn (PsychosocialReviewEvent $event) => [
                'id' => $event->id,
                'action' => $event->action,
                'label' => PsychosocialReviewEvent::ACTION_LABELS[$event->action] ?? $event->action,
                'from' => $event->from_status !== null ? (PsychosocialReviewRound::STATUS_LABELS[$event->from_status] ?? $event->from_status) : null,
                'to' => $event->to_status !== null ? (PsychosocialReviewRound::STATUS_LABELS[$event->to_status] ?? $event->to_status) : null,
                'body' => $event->body,
                'author' => $event->user?->name ?? 'Sistema',
                'at' => $event->created_at?->format('d/m/Y H:i') ?? '',
            ])
            ->all();
    }

    /* ----- regras de quem pode agir e quando ----- */

    private function assertTalents(User $actor): void
    {
        if (! $actor->isSuperAdmin() && ! $actor->isOwner()) {
            throw new PsychosocialDocumentException('Só a Talents conduz a revisão dos documentos.');
        }
    }

    private function assertCompanyActor(PsychosocialReviewRound $round, User $actor): void
    {
        if ($actor->isSuperAdmin() || $actor->isOwner()) {
            throw new PsychosocialDocumentException('Este registro é da empresa.');
        }

        if ((int) $actor->contextCompanyId() !== (int) $round->company_id) {
            throw new PsychosocialDocumentException('Esta publicação não pertence à sua empresa.');
        }
    }

    private function assertReviewFlow(Survey $survey): void
    {
        if (! $survey->usesReviewFlow()) {
            throw new PsychosocialDocumentException('O fluxo de revisão vale para campanhas V11; as campanhas antigas mantêm os documentos que já têm.');
        }
    }

    private function assertEditable(PsychosocialReviewRound $round): void
    {
        if (! in_array($round->status, [PsychosocialReviewRound::STATUS_DRAFT, PsychosocialReviewRound::STATUS_CHANGES_REQUESTED], true)) {
            throw new PsychosocialDocumentException('Os documentos só são gerados com a revisão «Em edição» ou «Alteração solicitada». Para mudar uma versão publicada, inicie uma nova revisão.');
        }
    }

    private function assertTransition(PsychosocialReviewRound $round, string $to): void
    {
        if (! in_array($to, PsychosocialReviewRound::TRANSITIONS[$round->status] ?? [], true)) {
            throw new PsychosocialDocumentException(sprintf(
                'Não é possível passar de «%s» para «%s».',
                PsychosocialReviewRound::STATUS_LABELS[$round->status] ?? $round->status,
                PsychosocialReviewRound::STATUS_LABELS[$to] ?? $to,
            ));
        }
    }

    /**
     * O revisor só age sobre o que foi explicitamente compartilhado com ele e ainda está dentro da validade.
     */
    private function reviewerShare(PsychosocialReviewRound $round, User $reviewer): DocumentShare
    {
        $share = $round->technical_version_id === null
            ? null
            : app(PsychosocialDocumentVersionPolicy::class)->activeShare($reviewer, $round->technicalVersion);

        if ($share === null) {
            throw new PsychosocialDocumentException('Esta revisão não foi compartilhada com você.');
        }

        return $share;
    }

    private function shareWith(PsychosocialDocumentVersion $version, User $reviewer, User $actor, int $days): void
    {
        $existing = DocumentShare::query()->where('document_version_id', $version->id)->where('reviewer_id', $reviewer->id)->first();

        if ($existing === null) {
            $this->shares->share($version, $reviewer, $actor, now()->addDays($days));

            return;
        }

        if (! $existing->isActive()) {
            throw new PsychosocialDocumentException('O acesso deste revisor a uma das versões foi encerrado: gere uma nova versão antes de reenviar.');
        }
    }

    /**
     * @param  list<array{field: string, label: string, problem: string, where: string}>  $pending
     */
    private function describe(array $pending): string
    {
        return implode('; ', array_map(fn (array $item) => "{$item['label']} ({$item['problem']}; {$item['where']})", $pending));
    }

    private function protocol(PsychosocialReviewRound $round): string
    {
        return sprintf('NR1-%d-R%02d-%s-%s', $round->survey_id, $round->round_number, now()->format('Ymd'), Str::upper(Str::random(6)));
    }

    private function event(PsychosocialReviewRound $round, ?User $actor, string $action, ?string $from, ?string $to, ?string $body): void
    {
        PsychosocialReviewEvent::query()->create([
            'round_id' => $round->id,
            'user_id' => $actor?->id,
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'body' => $body,
        ]);
    }
}
