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
            [$itemTitle, $itemDescription] = $this->buildItem(
                $title,
                $itemKind,
                $row->risk_level,
                (float) $row->average_score
            );

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
                'description' => implode("\n\n", [
                    'Contexto: os indicadores desta campanha permaneceram na faixa favorável. O objetivo agora é consolidar o que funciona e evitar o retrocesso silencioso dos fatores psicossociais.',
                    'O que deve ser feito: (1) manter pesquisas periódicas NR-1 e comparar a evolução de cada dimensão com esta linha de base; (2) registrar no PGR as práticas já adotadas (comunicação, reconhecimento, equilíbrio de demandas e recursos); (3) identificar as duas dimensões com maior potencial de melhoria, mesmo em faixa favorável, e definir uma ação preventiva leve para cada uma; (4) comunicar os resultados à liderança e aos trabalhadores, reforçando canais de escuta (CIPA, RH, canal de denúncias).',
                    'Como conduzir: RH e SST revisam o inventário de riscos psicossociais, validam controles existentes e incluem este monitoramento no ciclo do PGR. A liderança imediata deve continuar feedback regular e acompanhamento de carga e clima.',
                    'Acompanhamento: responsável (RH/SST), prazo trimestral e indicador = média geral e por dimensão na próxima pesquisa. Qualquer piora deve reabrir o plano de ação.',
                ]),
                'status' => 'pending',
                'sort_order' => 0,
            ]);
        }

        return $plan->load('items');
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function buildItem(string $sectionTitle, string $itemKind, ?string $riskLevel, float $averageScore): array
    {
        $playbook = $this->playbookForSection($sectionTitle);
        $scoreLabel = number_format($averageScore, 2, ',', '.');
        $riskLabel = $this->riskLabel($riskLevel);

        $title = $this->titlePrefix($itemKind).' '.$playbook['title'];
        if (mb_strlen($title) > 255) {
            $title = mb_substr($title, 0, 252).'…';
        }

        $description = implode("\n\n", [
            'Contexto: a dimensão '.$sectionTitle.' apresentou média '.$scoreLabel.' ('.$riskLabel.'). '.$playbook['diagnosis'],
            'O que deve ser feito: '.$playbook['actions'],
            'Como conduzir: '.$playbook['how'],
            $this->urgencyBlock($itemKind),
        ]);

        return [$title, $description];
    }

    private function titlePrefix(string $itemKind): string
    {
        return match ($itemKind) {
            'intervencao_imediata' => 'Intervenção imediata:',
            'preventiva_com_acompanhamento' => 'Ação preventiva (acompanhamento obrigatório):',
            default => 'Ação preventiva:',
        };
    }

    private function urgencyBlock(string $itemKind): string
    {
        return match ($itemKind) {
            'intervencao_imediata' => 'Acompanhamento: prioridade máxima. Prazo: curto (primeiras medidas em até 30 dias). Acompanhamento semanal até estabilização do indicador. Acionar SST e liderança já na primeira semana; documentar evidências e atualizar o PGR.',
            'preventiva_com_acompanhamento' => 'Acompanhamento: ação preventiva com acompanhamento obrigatório. Definir responsável nomeado, prazo (60 a 90 dias) e indicador de acompanhamento obrigatório (média da dimensão e evidências de implementação). Reportar evolução à liderança e registrar no PGR.',
            default => 'Acompanhamento: registrar no PGR como medida preventiva, com responsável, prazo (ciclo trimestral recomendado) e evidência de execução. Reavaliar na próxima pesquisa NR-1.',
        };
    }

    private function riskLabel(?string $level): string
    {
        return match ($level) {
            'green' => 'situação favorável',
            'yellow' => 'risco intermediário',
            'red' => 'risco elevado',
            default => 'nível a confirmar',
        };
    }

    /**
     * @return array{title: string, diagnosis: string, actions: string, how: string}
     */
    private function playbookForSection(string $title): array
    {
        $t = mb_strtolower($title);

        if (str_contains($t, 'demanda') || str_contains($t, 'exig')) {
            return [
                'title' => 'Reequilibrar carga, prazos e priorização em '.$title,
                'diagnosis' => 'Esse resultado costuma indicar sobrecarga, prazos apertados, horas extras ou dificuldade de conciliar exigências conflitantes.',
                'actions' => '(1) Mapear com a liderança as atividades, metas e prazos que mais pressionam o time (incluindo horas extras e retrabalho). (2) Rever distribuição de carga, priorizar entregas essenciais e adiar ou eliminar o que não for crítico. (3) Definir critério claro de priorização e um fluxo para renegociar prazos quando a demanda ultrapassar a capacidade. (4) Avaliar reforço de equipe, redistribuição entre áreas ou revisão de SLAs internos. (5) Orientar gestores a não assumir novos compromissos sem checar capacidade real.',
                'how' => 'Reunir RH, liderança imediata e SST. Ouvir os trabalhadores (reunião curta ou grupo focal). Registrar no PGR as medidas, o responsável e a evidência (ex.: nova priorização, redução de horas extras, redistribuição de tarefas).',
            ];
        }

        if (str_contains($t, 'controle') || str_contains($t, 'organiz') || str_contains($t, 'autonom')) {
            return [
                'title' => 'Ampliar autonomia e participação nas decisões em '.$title,
                'diagnosis' => 'Esse resultado costuma indicar pouca influência sobre o ritmo, o método ou o horário de trabalho, com decisões concentradas na gestão.',
                'actions' => '(1) Identificar rotinas em que o trabalhador pode decidir método, ordem das tarefas ou ritmo, sem prejuízo da segurança e da qualidade. (2) Estabelecer espaços regulares de participação (reuniões de área, consulta prévia a mudanças de processo). (3) Avaliar flexibilidade de horário e pausas, quando a operação permitir. (4) Capacitar lideranças para delegar com clareza e acompanhar resultado, não só o modo de execução. (5) Documentar os limites de autonomia para evitar insegurança sobre o que pode ou não ser decidido no dia a dia.',
                'how' => 'RH e liderança desenham o que pode ser decidido localmente. SST valida impactos em saúde e segurança. Comunicar as mudanças ao time e incluir o controle no PGR.',
            ];
        }

        if (str_contains($t, 'colega') || str_contains($t, 'par')) {
            return [
                'title' => 'Fortalecer cooperação, respeito e apoio entre pares em '.$title,
                'diagnosis' => 'Esse resultado costuma indicar pouco apoio mútuo, isolamento ou clima de competição entre colegas.',
                'actions' => '(1) Diagnosticar, com escuta estruturada, onde falta ajuda, respeito ou cooperação (turnos, equipes ou fluxos específicos). (2) Combinar regras claras de convívio e de pedido/oferta de ajuda no trabalho. (3) Promover práticas de trabalho em equipe (pareamento, backup de funções, rituais curtos de alinhamento). (4) Capacitar o time em comunicação não violenta e mediação informal de conflitos. (5) Garantir que a liderança reconheça colaboração e intervenha cedo em atritos recorrentes.',
                'how' => 'RH facilita o diagnóstico e a mediação. A liderança imediata modela o comportamento esperado. Registrar no PGR as medidas de clima e o canal para novos relatos.',
            ];
        }

        if (str_contains($t, 'gestão') || str_contains($t, 'gestao') || (str_contains($t, 'lider') && ! str_contains($t, 'relacion'))) {
            return [
                'title' => 'Fortalecer escuta, feedback e suporte da liderança em '.$title,
                'diagnosis' => 'Esse resultado costuma indicar feedback insuficiente, pouca disponibilidade da gestão ou falta de apoio em situações emocionalmente exigentes.',
                'actions' => '(1) Estabelecer rotina mínima de 1:1 e feedback (o que vai bem, o que precisa de apoio, carga e prazos). (2) Capacitar gestores em escuta ativa, saúde mental no trabalho e encaminhamento adequado (RH/EAP), sem improvisar papel clínico. (3) Definir quem substitui o gestor na ausência e como o time pede ajuda. (4) Acompanhar se a liderança devolve resposta às demandas do time em prazo combinado. (5) Incluir o suporte da gestão como competência avaliada e desenvolvida, não só como discurso.',
                'how' => 'RH estrutura o programa de liderança. SST e, se houver, EAP apoiam os encaminhamentos. Documentar a rotina de feedback e os treinamentos no PGR.',
            ];
        }

        if (str_contains($t, 'papel') || str_contains($t, 'função') || str_contains($t, 'funcao')) {
            return [
                'title' => 'Clarear papéis, responsabilidades e expectativas em '.$title,
                'diagnosis' => 'Esse resultado costuma indicar dúvida sobre o que se espera da função, sobreposição de tarefas ou desalinhamento entre a área e os objetivos da empresa.',
                'actions' => '(1) Revisar descrições de cargo e o que de fato é cobrado no dia a dia; eliminar ambiguidades e sobrecarga de “tarefas invisíveis”. (2) Alinhar metas da área com a liderança e comunicar como o trabalho de cada um se encaixa no objetivo maior. (3) Definir RACI simples (quem executa, quem decide, quem é informado) nos processos que geram mais conflito. (4) Treinar novos e antigos no “como fazer” das rotinas críticas. (5) Reabrir a conversa sempre que houver mudança de sistema, quadro ou meta.',
                'how' => 'RH e gestores imediatos revisam cargos e metas juntos. Validar com os trabalhadores se a descrição reflete a realidade. Registrar a versão atualizada e a comunicação no PGR.',
            ];
        }

        if (str_contains($t, 'relacionamento') || str_contains($t, 'assédio') || str_contains($t, 'assedio') || str_contains($t, 'viol')) {
            return [
                'title' => 'Enfrentar conflitos, assédio e clima relacional em '.$title,
                'diagnosis' => 'Esse resultado é crítico: pode indicar atrito persistente, assédio moral ou ambiente tenso, com impacto direto na saúde mental.',
                'actions' => '(1) Reafirmar, de forma visível, o código de conduta e a tolerância zero a assédio e violência. (2) Garantir canal de denúncias conhecido, acessível e com proteção contra retaliação; comunicar prazos e sigilo. (3) Investigar relatos com rito definido (RH/jurídico/SST), aplicar medidas disciplinares quando cabível e proteger as pessoas envolvidas. (4) Oferecer mediação de conflitos e, quando necessário, realocação temporária ou alteração de equipe. (5) Treinar lideranças e o time para reconhecer e interromper comportamentos inadequados. (6) Encaminhar apoio psicológico (EAP ou equivalente) a quem precisar.',
                'how' => 'RH conduz o rito com apoio jurídico. SST registra o fator no inventário de riscos e as medidas de controle no PGR. A alta liderança deve patrocinar a mensagem e cobrir a execução.',
            ];
        }

        if (str_contains($t, 'mudança') || str_contains($t, 'mudanca')) {
            return [
                'title' => 'Comunicar mudanças e consultar os trabalhadores em '.$title,
                'diagnosis' => 'Esse resultado costuma indicar mudanças mal explicadas, pouca consulta prévia ou falta de clareza sobre o que muda na prática.',
                'actions' => '(1) Mapear mudanças em curso ou previstas (processo, sistema, quadro, local, metas) e quem é afetado. (2) Antes de implementar, consultar representantes dos trabalhadores e explicar o motivo, o que muda no dia a dia e o cronograma. (3) Criar canal para perguntas e devolver respostas em prazo combinado. (4) Preparar lideranças para repetir a mensagem de forma consistente e acolher resistências. (5) Acompanhar as primeiras semanas da mudança (dúvidas, retrabalho, sobrecarga) e ajustar o plano.',
                'how' => 'Patrocínio da liderança responsável pela mudança, com RH na comunicação e SST na avaliação de novos riscos. Registrar no PGR o impacto e os controles adotados.',
            ];
        }

        if (str_contains($t, 'reconhec')) {
            return [
                'title' => 'Instituir reconhecimento justo e critérios claros em '.$title,
                'diagnosis' => 'Esse resultado costuma indicar percepção de injustiça, critérios opacos de avaliação ou falta de valorização do esforço.',
                'actions' => '(1) Explicitar critérios de avaliação, promoção e reconhecimento (o que é medido, por quem e com que frequência). (2) Treinar gestores para reconhecer de forma específica, frequente e equitativa — não só no resultado financeiro. (3) Revisar se metas e recompensas pressionam de forma desproporcional alguma equipe. (4) Abrir espaço para o trabalhador entender sua avaliação e contestar com respeito. (5) Comunicar decisões de reconhecimento com transparência compatível com a política da empresa.',
                'how' => 'RH desenha a política com a liderança. SST observa impactos em estresse e competição nociva. Documentar critérios e a comunicação no PGR.',
            ];
        }

        if (str_contains($t, 'vida') || str_contains($t, 'famíl') || str_contains($t, 'famil')) {
            return [
                'title' => 'Proteger pausas, flexibilidade e desconexão em '.$title,
                'diagnosis' => 'Esse resultado costuma indicar invasão do trabalho na vida pessoal, dificuldade de pausar ou pouco apoio a responsabilidades familiares.',
                'actions' => '(1) Mapear horas extras, mensagens fora do expediente e jornadas que impedem pausas reais. (2) Estabelecer política de desconexão e regras de plantão (quem cobre, como acionar, compensação). (3) Avaliar flexibilidade de horário e, quando viável, trabalho híbrido pontual. (4) Garantir pausas e intervalo; coibir almoço no posto de trabalho como regra. (5) Orientar lideranças a planejar entregas sem empurrar sistematicamente o trabalho para a noite e o fim de semana.',
                'how' => 'RH e liderança definem a política. SST monitora fadiga e queixas. Registrar controles de jornada e desconexão no PGR.',
            ];
        }

        if (str_contains($t, 'bem') || str_contains($t, 'saúde') || str_contains($t, 'saude')) {
            return [
                'title' => 'Ampliar apoio psicológico e monitoramento de saúde mental em '.$title,
                'diagnosis' => 'Esse resultado costuma indicar sofrimento psíquico, pouco acesso a apoio ou estigma para pedir ajuda.',
                'actions' => '(1) Disponibilizar ou reforçar EAP/apoio psicológico confidencial e comunicar como acessar. (2) Capacitação de lideranças para acolher e encaminhar, sem diagnosticar. (3) Campanhas de saúde mental alinhadas à NR-1, evitando ações isoladas de “dia D”. (4) Monitorar absenteísmo, atestados e pedidos de afastamento em conjunto com SST, respeitando sigilo. (5) Integrar os achados desta pesquisa ao PGR e ao PCMSO, quando houver interface.',
                'how' => 'SST e RH articulam a rede de apoio. A comunicação deve deixar claro que buscar ajuda não gera retaliação. Registrar o programa e os indicadores no PGR.',
            ];
        }

        return [
            'title' => 'Tratar fatores psicossociais identificados em '.$title,
            'diagnosis' => 'Os resultados desta dimensão exigem medidas concretas no Programa de Gerenciamento de Riscos, com responsável, prazo e evidência.',
            'actions' => '(1) Analisar, com RH, SST e liderança da área, as perguntas e os setores com pior resultado nesta dimensão. (2) Ouvir os trabalhadores sobre causas (carga, relações, organização do trabalho, mudanças). (3) Escolher de 2 a 4 medidas práticas, factíveis no prazo, em vez de uma lista genérica. (4) Nomear responsável, prazo, recurso necessário e como se saberá que a medida funcionou. (5) Comunicar o plano ao time e registrar no inventário de riscos e no PGR.',
            'how' => 'A equipe Talents sugere este roteiro; a validação técnica é da SST e da liderança da empresa. Manter evidências (atas, comunicados, listas de presença, indicadores) para a próxima auditoria ou pesquisa.',
        ];
    }
}
