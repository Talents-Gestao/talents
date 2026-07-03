<?php

namespace App\Services;

use App\Models\ActionPlan;
use App\Models\ActionPlanItem;
use App\Models\Survey;
use App\Models\SurveyResult;
use App\Support\Nr1RiskScenarioResolver;

class ActionPlanGenerator
{
    public function generate(Survey $survey): ActionPlan
    {
        $survey->load('template.sections', 'company');

        $scenario = Nr1RiskScenarioResolver::forSurvey($survey) ?? 'green';
        $scenarioConfig = Nr1RiskScenarioResolver::scenarioConfig($scenario);
        $itemKind = $scenarioConfig['action_plan']['item_kind'] ?? 'preventiva';

        $plan = ActionPlan::updateOrCreate(
            [
                'company_id' => $survey->company_id,
                'survey_id' => $survey->id,
            ],
            ['status' => 'open']
        );

        $plan->items()->delete();

        $sections = SurveyResult::query()
            ->where('survey_id', $survey->id)
            ->whereNotNull('survey_template_section_id')
            ->whereNull('department_id')
            ->orderByDesc('average_score')
            ->get();

        $sort = 0;
        foreach ($sections as $row) {
            if ($scenario === 'green' && $row->risk_level === 'green') {
                continue;
            }

            $title = $row->meta['section_title'] ?? 'Dimensão '.$row->survey_template_section_id;
            [$itemTitle, $itemDescription] = $this->buildItem($title, $itemKind, $row->risk_level);

            ActionPlanItem::create([
                'action_plan_id' => $plan->id,
                'title' => $itemTitle,
                'description' => $itemDescription,
                'status' => 'pending',
                'sort_order' => $sort++,
            ]);
        }

        if ($sort === 0) {
            ActionPlanItem::create([
                'action_plan_id' => $plan->id,
                'title' => 'Manter práticas e monitorar indicadores',
                'description' => 'Níveis dentro da faixa aceitável. Continue pesquisas periódicas, ações preventivas leves e comunicação com equipes.',
                'status' => 'pending',
                'sort_order' => 0,
            ]);
        }

        return $plan->load('items');
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function buildItem(string $sectionTitle, string $itemKind, ?string $riskLevel): array
    {
        $baseDescription = $this->suggestionForSection($sectionTitle);

        return match ($itemKind) {
            'intervencao_imediata' => [
                'Intervenção imediata: Revisar '.$sectionTitle,
                $baseDescription.' Prazo: curto. Acompanhamento semanal até estabilização do indicador.',
            ],
            'preventiva_com_acompanhamento' => [
                'Ação preventiva (acompanhamento obrigatório): Revisar '.$sectionTitle,
                $baseDescription.' Definir responsável, prazo e indicador de acompanhamento obrigatório.',
            ],
            default => [
                'Ação preventiva: Revisar '.$sectionTitle,
                $baseDescription.' Registrar no PGR como medida preventiva.',
            ],
        };
    }

    private function suggestionForSection(string $title): string
    {
        $t = strtolower($title);

        if (str_contains($t, 'demanda') || str_contains($t, 'exig')) {
            return 'Rever distribuição de carga, prazos e priorização de tarefas; envolver liderança na priorização.';
        }
        if (str_contains($t, 'controle') || str_contains($t, 'organiz') || str_contains($t, 'autonom')) {
            return 'Aumentar autonomia, participação nas decisões de trabalho e flexibilidade de horário quando possível.';
        }
        if (str_contains($t, 'gestão') || str_contains($t, 'gestao') || str_contains($t, 'lider')) {
            return 'Fortalecer feedback, escuta e apoio da liderança; capacitar gestores em saúde mental.';
        }
        if (str_contains($t, 'colega') || str_contains($t, 'par')) {
            return 'Promover trabalho em equipe, mediação de conflitos e ambiente de respeito entre pares.';
        }
        if (str_contains($t, 'papel') || str_contains($t, 'função') || str_contains($t, 'funcao')) {
            return 'Revisar descrições de cargo, alinhamento de metas e comunicação de expectativas.';
        }
        if (str_contains($t, 'mudança')) {
            return 'Melhorar comunicação de mudanças, consulta aos trabalhadores e clareza dos impactos.';
        }
        if (str_contains($t, 'relacionamento')) {
            return 'Combater assédio e conflitos; reforçar código de conduta, canal de denúncias e mediação.';
        }
        if (str_contains($t, 'rela') || str_contains($t, 'lider')) {
            return 'Fortalecer feedback, escuta e mediação de conflitos; capacitar lideranças.';
        }
        if (str_contains($t, 'reconhec')) {
            return 'Implementar práticas de reconhecimento justo e comunicação de critérios.';
        }
        if (str_contains($t, 'vida') || str_contains($t, 'famil')) {
            return 'Avaliar flexibilidade, pausas e políticas de desconexão.';
        }
        if (str_contains($t, 'bem') || str_contains($t, 'saúde')) {
            return 'Oferecer apoio psicológico/EAP e campanhas de saúde mental; monitorar absenteísmo.';
        }
        if (str_contains($t, 'assédio') || str_contains($t, 'viol')) {
            return 'Reforçar canal de denúncias, código de conduta e treinamentos obrigatórios.';
        }

        return 'Definir responsáveis, prazo e indicadores de acompanhamento no PGR.';
    }
}
