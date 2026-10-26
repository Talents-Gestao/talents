<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Nr1Opportunity;
use App\Models\Nr1OpportunityEvent;
use App\Models\Nr1Training;
use App\Models\Survey;
use App\Models\SurveyAudienceLink;

/**
 * Visão do Comercial limitado: metadados da campanha e resultados agregados já filtrados pela política
 * de grupo mínimo (a mesma do painel). Lista branca explícita: nunca resposta, respondente, item,
 * demografia ou texto livre, e nada de campanha que não tenha sido liberada.
 */
class Nr1CommercialSerializer
{
    public function __construct(private readonly PsychosocialDashboardBuilder $builder) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(Survey $survey): array
    {
        return [
            'id' => $survey->id,
            'title' => $survey->title,
            'company_name' => $survey->company?->name,
            'status' => $survey->status,
            'starts_at' => $survey->starts_at?->format('d/m/Y'),
            'ends_at' => $survey->ends_at?->format('d/m/Y'),
            // «Publicado» (Etapa 7) é o critério de visibilidade do Comercial; a liberação manual antiga saiu.
            'published_at' => $survey->published_at?->format('d/m/Y'),
        ];
    }

    /**
     * Linha da fila de oportunidades. Lista branca: metadados da oportunidade, nunca resposta nem dado de respondente.
     *
     * @return array<string, mixed>
     */
    public function queueRow(Nr1Opportunity $opportunity): array
    {
        $opportunity->loadMissing(['company:id,name', 'survey:id,title', 'requester:id,name', 'assignee:id,name']);

        return [
            'id' => $opportunity->id,
            'origin' => $opportunity->origin,
            'origin_label' => Nr1Opportunity::ORIGIN_LABELS[$opportunity->origin] ?? $opportunity->origin,
            'company_name' => $opportunity->company?->name,
            'survey_title' => $opportunity->survey?->title,
            'service' => $this->serviceLabel($opportunity),
            'status' => $opportunity->status,
            'status_label' => Nr1Opportunity::STATUS_LABELS[$opportunity->status] ?? $opportunity->status,
            'open' => $opportunity->isOpen(),
            'requester' => $opportunity->requester?->name,
            'assignee' => $opportunity->assignee?->name,
            'assigned_to' => $opportunity->assigned_to,
            'created_at' => $opportunity->created_at?->format('d/m/Y H:i'),
            'last_activity_at' => $opportunity->last_activity_at?->format('d/m/Y H:i'),
            'next_action' => $opportunity->next_action,
            'next_action_at' => $opportunity->next_action_at?->format('d/m/Y'),
        ];
    }

    /**
     * Detalhe da oportunidade: a linha, o histórico e, só se a campanha estiver «Publicada», o resultado agregado liberado.
     *
     * @return array<string, mixed>
     */
    public function opportunityDetail(Nr1Opportunity $opportunity): array
    {
        $opportunity->loadMissing('survey');

        return array_merge($this->queueRow($opportunity), [
            'need' => $opportunity->need,
            'lost_reason' => $opportunity->lost_reason,
            'history' => $opportunity->events()->with('user:id,name')->get()->map(fn (Nr1OpportunityEvent $event): array => [
                'id' => $event->id,
                'action' => $event->action,
                'from' => $event->from_status !== null ? (Nr1Opportunity::STATUS_LABELS[$event->from_status] ?? $event->from_status) : null,
                'to' => $event->to_status !== null ? (Nr1Opportunity::STATUS_LABELS[$event->to_status] ?? $event->to_status) : null,
                'body' => $event->body,
                'author' => $event->user?->name ?? 'Sistema',
                'at' => $event->created_at?->format('d/m/Y H:i'),
            ])->all(),
            'aggregate' => $opportunity->survey !== null && $opportunity->survey->published_at !== null
                ? $this->detail($opportunity->survey->loadMissing('company:id,name'))
                : null,
        ]);
    }

    private function serviceLabel(Nr1Opportunity $opportunity): string
    {
        if ($opportunity->origin === Nr1Opportunity::ORIGIN_TRAINING) {
            return (Nr1Training::MODALITY_LABELS[$opportunity->modality] ?? (string) $opportunity->modality)
                .' · '.(SurveyAudienceLink::LABELS[$opportunity->audience] ?? (string) $opportunity->audience);
        }

        return (string) ($opportunity->service_key ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(Survey $survey): array
    {
        $dashboard = $this->builder->build($survey);

        return array_merge($this->summary($survey), [
            'min_group_size' => $dashboard['min_group_size'],
            'respondents' => $dashboard['respondents'],
            'method_label' => $dashboard['method']['label'],
            'overall' => $this->cut($dashboard['overall']),
            'dimensions' => array_map(fn (array $d) => $this->dimension($d), $dashboard['dimensions']),
            'sectors' => array_map(fn (array $s) => array_merge(
                ['name' => $s['department_name']],
                $this->cut($s),
                ['dimensions' => array_map(fn (array $d) => $this->dimension($d), $s['dimensions'])],
            ), $dashboard['sectors']),
            'others' => $dashboard['others'] === null ? null : array_merge(
                ['name' => $dashboard['others']['name']],
                $this->cut($dashboard['others']),
                ['dimensions' => array_map(fn (array $d) => $this->dimension($d), $dashboard['others']['dimensions'])],
            ),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $row
     * @return array{n: int, score: string, band_label: string}|null
     */
    private function cut(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }

        return [
            'n' => (int) $row['n'],
            'score' => (string) $row['score'],
            'band_label' => (string) $row['band_label'],
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function dimension(array $row): array
    {
        return array_merge(['title' => (string) $row['title']], $this->cut($row));
    }
}
