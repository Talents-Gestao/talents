<?php

declare(strict_types=1);

namespace App\Actions\Notices;

use App\Enums\CompanyNoticeAudience;
use App\Enums\CompanyNoticeEventKind;
use App\Enums\UserRole;
use App\Enums\WorkspaceType;
use App\Mail\Nr1NoticeMail;
use App\Models\CompanyNotice;
use App\Models\MailSetting;
use App\Models\Nr1Opportunity;
use App\Models\Nr1Training;
use App\Models\PsychosocialReviewRound;
use App\Models\User;
use App\Support\Notices\NoticeVisibility;
use Illuminate\Support\Facades\Mail;

/**
 * Avisos do NR-1 no sistema de Avisos existente: «nova oportunidade» (Comercial), «nova solicitação de proposta ou
 * conversa» (Talents) e «relatório publicado» (empresa). Vão para o sino e, havendo SMTP configurado no painel, por
 * e-mail; sempre só a quem pode ver o aviso (NoticeVisibility). O aviso nunca interrompe o fluxo que o gerou.
 */
class PublishNr1Notice
{
    private const DEDUPE_MINUTES = 5;

    public function __construct(private readonly PublishCompanyNotice $publish) {}

    /** Oportunidade nova na fila (por pedido da empresa ou por sugestão automática). */
    public function opportunityCreated(Nr1Opportunity $opportunity): void
    {
        $opportunity->loadMissing(['company:id,name', 'survey:id,title']);
        $what = $this->describe($opportunity);

        $this->talents(
            CompanyNoticeEventKind::Nr1OpportunityCreated,
            'Nova oportunidade NR-1',
            "{$opportunity->company?->name}: {$what} (campanha «{$opportunity->survey?->title}»). Origem: ".(Nr1Opportunity::ORIGIN_LABELS[$opportunity->origin] ?? $opportunity->origin).'.',
            $opportunity,
        );
    }

    /** A empresa pediu proposta de treinamento ou conversa sobre uma solução. */
    public function proposalRequested(Nr1Opportunity $opportunity): void
    {
        $opportunity->loadMissing(['company:id,name', 'survey:id,title', 'requester:id,name']);

        $this->talents(
            CompanyNoticeEventKind::Nr1ProposalRequested,
            $opportunity->origin === Nr1Opportunity::ORIGIN_TRAINING ? 'Nova solicitação de proposta' : 'Nova solicitação de conversa',
            "{$opportunity->company?->name} pediu: {$this->describe($opportunity)} (campanha «{$opportunity->survey?->title}»)"
                .($opportunity->requester ? ', por '.$opportunity->requester->name : '').'.',
            $opportunity,
        );
    }

    /** Os dois relatórios foram publicados para a empresa. */
    public function reportPublished(PsychosocialReviewRound $round): void
    {
        $round->loadMissing('survey:id,title');
        $title = 'Relatório publicado';
        $body = "Os relatórios da campanha «{$round->survey?->title}» foram publicados (protocolo {$round->protocol}). Consulte em Documentos.";

        $notice = $this->safely(fn () => $this->publish->handle(
            companyId: (int) $round->company_id,
            title: $title,
            body: $body,
            audience: CompanyNoticeAudience::Company,
            actor: null,
            sourceType: 'psychosocial_review_round',
            sourceId: (int) $round->id,
            eventKind: CompanyNoticeEventKind::Nr1ReportPublished,
            dedupeWithinMinutes: self::DEDUPE_MINUTES,
        ));

        if ($notice !== null) {
            $recipients = User::query()
                ->where('is_active', true)
                ->whereHas('workspaces', fn ($q) => $q->where('workspace_type', WorkspaceType::Company)->where('company_id', $round->company_id)->where('is_active', true))
                ->get()
                ->filter(fn (User $user) => $this->canReceiveCompany($user, (int) $round->company_id));

            $this->email($recipients, $title, $body);
        }
    }

    private function talents(CompanyNoticeEventKind $kind, string $title, string $body, Nr1Opportunity $opportunity): void
    {
        $notice = $this->safely(fn () => $this->publish->handle(
            companyId: null,
            title: $title,
            body: $body,
            audience: CompanyNoticeAudience::Talents,
            actor: null,
            sourceType: 'nr1_opportunity',
            sourceId: (int) $opportunity->id,
            eventKind: $kind,
            dedupeWithinMinutes: self::DEDUPE_MINUTES,
        ));

        if ($notice === null) {
            return;
        }

        $recipients = User::query()
            ->where('is_active', true)
            ->where('role', UserRole::SuperAdmin)
            ->whereHas('workspaces', fn ($q) => $q->where('workspace_type', WorkspaceType::Talents)->where('is_active', true))
            ->get()
            ->filter(fn (User $user) => NoticeVisibility::canSee($user, $kind));

        $this->email($recipients, $title, $body);
    }

    private function canReceiveCompany(User $user, int $companyId): bool
    {
        $workspace = $user->workspaces()
            ->where('workspace_type', WorkspaceType::Company)->where('company_id', $companyId)->where('is_active', true)->first();
        if ($workspace === null) {
            return false;
        }

        $user->setActiveWorkspace($workspace);

        return NoticeVisibility::canSee($user, CompanyNoticeEventKind::Nr1ReportPublished);
    }

    /**
     * @param  iterable<User>  $recipients
     */
    private function email(iterable $recipients, string $subject, string $body): void
    {
        if (! MailSetting::smtpConfigured()) {
            return;
        }

        foreach ($recipients as $user) {
            $this->safely(fn () => Mail::to($user->email)->send(new Nr1NoticeMail($subject, $body)));
        }
    }

    private function describe(Nr1Opportunity $opportunity): string
    {
        if ($opportunity->origin === Nr1Opportunity::ORIGIN_TRAINING) {
            $audience = $opportunity->audience === 'lideres' ? 'Líderes' : 'Time';

            return ($opportunity->modality === Nr1Training::MODALITY_IN_PERSON ? 'treinamento presencial' : 'treinamento online').' para '.$audience;
        }

        return 'solução «'.$opportunity->service_key.'»';
    }

    /**
     * Aviso e e-mail nunca derrubam o fluxo que os gerou.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T|null
     */
    private function safely(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (\Throwable $exception) {
            report($exception);

            return null;
        }
    }
}
