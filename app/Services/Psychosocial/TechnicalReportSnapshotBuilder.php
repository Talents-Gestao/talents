<?php

declare(strict_types=1);

namespace App\Services\Psychosocial;

use App\Models\ActionPlan;
use App\Models\PsychosocialMeasure;
use App\Models\PsychosocialMeasureEvidence;
use App\Models\Survey;
use App\Services\ExecutiveBriefSnapshotBuilder;
use App\Services\PsychosocialDashboardBuilder;
use Illuminate\Support\Str;

/**
 * Snapshot do Inventário de Riscos Psicossocial (documento final centralizado, Caderno §12). Mesma base do resumo
 * executivo (painel com a cascata aplicada, cadastro técnico, medidas), mais o plano completo com sugerido ×
 * confirmado, status e aferição (T18). Dados da empresa entram no PDF; sem Anexo A (instrumento), Anexo B nem
 * controle documental. Tudo congelado: o PDF é a impressão deste snapshot, nunca dado ao vivo.
 */
class TechnicalReportSnapshotBuilder implements DocumentSnapshotBuilder
{
    public const SCHEMA = 'technical_report.v1';

    public const PLAN_NOTE = 'O responsável e o prazo sugeridos são propostas iniciais da Talents. A empresa confirma responsável e prazo, executa, registra evidências e verifica a eficácia. Uma medida só conta como concluída quando a empresa a conclui com evidência; imprimir ou publicar este relatório não implementa nenhuma medida.';

    public const PRIORITY_NOTE = 'A ordem gerencial da pesquisa não é, por si só, prioridade legal de risco ocupacional. A avaliação ocupacional e a sua classificação integram o GRO/PGR, sob as responsabilidades definidas pela organização.';

    public function __construct(
        private readonly ExecutiveBriefSnapshotBuilder $baseBuilder,
        private readonly PsychosocialDashboardBuilder $dashboardBuilder,
    ) {}

    public function schema(): string
    {
        return self::SCHEMA;
    }

    public function assertReleasable(Survey $survey): void {}

    /**
     * @return array<string, mixed>
     */
    public function build(Survey $survey): array
    {
        $survey->loadMissing('company');
        $dashboard = $this->dashboardBuilder->build($survey);
        $company = $survey->company;
        $base = $this->baseBuilder->buildForSchema($survey, self::SCHEMA);
        $opinionHtml = (string) (ActionPlan::query()
            ->where('survey_id', $survey->id)
            ->orderByDesc('id')
            ->value('technical_opinion') ?? '');

        return array_merge($base, [
            'company' => [
                'name' => $company?->name,
                'legal_name' => $company?->legal_name,
                'cnpj' => $company?->cnpj,
                'address_street' => $company?->address_street,
                'address_neighborhood' => $company?->address_neighborhood,
                'address_city' => $company?->address_city,
                'address_state' => $company?->address_state,
                'address_zip' => $company?->address_zip,
            ],
            'technical_opinion_html' => $this->sanitizeOpinionHtml($opinionHtml),
            'technical_opinion' => filled($opinionHtml)
                ? trim(html_entity_decode(strip_tags($opinionHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8'))
                : null,
            'strengths' => $dashboard['strengths'],
            'attention' => array_map(fn (array $d) => [
                'title' => $d['title'],
                'score' => $d['score'],
                'score_label' => $d['score_label'],
                'band_label' => $d['band_label'],
                'n' => $d['n'],
            ], $dashboard['attention']),
            'groups' => $dashboard['groups'],
            'plan' => $this->plan($survey),
            'plan_note' => self::PLAN_NOTE,
            'priority_note' => self::PRIORITY_NOTE,
            // Metadados do instrumento permanecem no snapshot (contagens/metodologia); o PDF não imprime anexos.
            'instrument' => [
                'template_title' => (string) $survey->template?->title,
                'item_count' => count($dashboard['items']),
                'dimension_count' => count($dashboard['dimensions']),
                'scale' => 'Frequência: Nunca, Raramente, Às vezes, Frequentemente, Sempre (1 a 5)',
                'items' => array_map(fn (array $item) => [
                    'position' => $item['position'],
                    'section_title' => $item['section_title'],
                    'body' => $item['body'],
                    'statement' => $item['statement'],
                ], $dashboard['items']),
            ],
            'support_data' => [
                'items' => array_map(fn (array $item) => [
                    'position' => $item['position'],
                    'n' => $item['n'],
                    'counts' => $item['counts'],
                    'favorable_pct_label' => $item['favorable_pct_label'],
                    'unfavorable_pct_label' => $item['unfavorable_pct_label'],
                ], $dashboard['items']),
            ],
        ]);
    }

    private function sanitizeOpinionHtml(string $html): ?string
    {
        $clean = trim(strip_tags($html, '<p><br><strong><b><em><i><ul><ol><li><h2><h3>'));

        return $clean !== '' && ! in_array(Str::lower($clean), ['<p></p>', '<p><br></p>', '<br>'], true)
            ? $clean
            : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function plan(Survey $survey): array
    {
        return PsychosocialMeasure::query()
            ->where('survey_id', $survey->id)
            ->with(['confirmedResponsibleUser:id,name', 'evidences'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (PsychosocialMeasure $measure) => [
                'factor_label' => $measure->factor_label,
                'measure_description' => $measure->measure_description,
                'objective' => $measure->objective,
                'expected_result' => $measure->expected_result,
                'indicator' => $measure->indicator,
                'status' => $measure->status,
                'status_label' => $measure->planLabel(),
                'suggested_responsible' => $measure->responsible_text,
                'suggested_due_date' => $measure->suggested_due_date?->toDateString(),
                'suggested_due_origin' => $measure->suggested_due_origin,
                'suggested_due_note' => $measure->suggested_due_note,
                'confirmed_responsible' => $measure->hasConfirmedResponsible()
                    ? collect([$measure->confirmedResponsibleUser?->name, $measure->confirmed_responsible_text])->filter()->implode(' · ')
                    : null,
                'confirmed_due_date' => $measure->confirmed_due_date?->toDateString(),
                'confirmed_at' => $measure->confirmed_at?->toDateString(),
                'evidence_count' => $measure->evidences->count(),
                'evidences' => $measure->evidences->map(fn (PsychosocialMeasureEvidence $evidence) => [
                    'kind' => $evidence->kind,
                    'description' => $evidence->description,
                    'file_name' => $evidence->file_name,
                    'file_sha256' => $evidence->file_sha256,
                ])->values()->all(),
                'effectiveness_result' => PsychosocialMeasure::EFFECTIVENESS_RESULTS[$measure->effectiveness_result] ?? null,
                'effectiveness_note' => $measure->effectiveness_note,
                'effectiveness_checked_at' => $measure->effectiveness_checked_at?->toDateString(),
            ])
            ->values()
            ->all();
    }
}
