<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\FeedbackTemplate;
use App\Models\FeedbackTemplateQuestion;
use App\Models\FeedbackTemplateSection;
use Illuminate\Database\Seeder;

class FeedbackTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $template = FeedbackTemplate::query()->firstOrCreate(
            [
                'company_id' => null,
                'title' => 'Contrato de Expectativas — Padrão Talents',
            ],
            [
                'description' => 'Modelo estruturado de feedback entre líder e colaborador (Contrato de Expectativas).',
                'is_default' => true,
                'is_active' => true,
                'version' => 1,
            ],
        );

        if ($template->sections()->exists()) {
            return;
        }

        $order = 0;

        $this->introSection($template, 'objetivo', 'Objetivo', 'both', $order++, <<<'TXT'
Promover uma conversa estruturada entre líder e colaborador para reconhecer conquistas, fortalecer competências, alinhar expectativas e definir ações práticas para o próximo ciclo de desenvolvimento.

Este documento serve como guia para a conversa de feedback. Recomendamos uma leitura antecipada para favorecer uma conversa mais produtiva e reflexiva.
TXT);

        $inicio = $this->section($template, 'inicio', 'Início', 'employee', 'questions', $order++);
        $this->textQuestions($inicio, [
            'inicio_felicidade' => 'Você está feliz na sua vida pessoal e profissionalmente?',
            'inicio_gosta' => 'Está fazendo o que realmente gosta?',
            'inicio_1_ano' => 'Como você se vê daqui a 1 ano?',
            'inicio_aproveitamento' => 'Como você poderia ser melhor aproveitado(a)?',
            'inicio_impacto' => 'Existe algo acontecendo hoje que possa estar impactando seu desempenho no trabalho?',
        ]);

        $termo = $this->section($template, 'termometro', 'Termômetro', 'employee', 'questions', $order++);
        FeedbackTemplateQuestion::create([
            'feedback_template_section_id' => $termo->id,
            'key' => 'termometro_nivel',
            'body' => 'Como você avalia seu momento atual na empresa?',
            'question_type' => 'single_choice',
            'options' => [
                ['value' => 'excelente', 'label' => 'Excelente'],
                ['value' => 'muito_bom', 'label' => 'Muito bom'],
                ['value' => 'bom', 'label' => 'Bom'],
                ['value' => 'regular', 'label' => 'Regular'],
                ['value' => 'ruim', 'label' => 'Ruim'],
            ],
            'is_required' => true,
            'sort_order' => 0,
        ]);
        FeedbackTemplateQuestion::create([
            'feedback_template_section_id' => $termo->id,
            'key' => 'termometro_comentario',
            'body' => 'Comentário',
            'question_type' => 'textarea',
            'is_required' => false,
            'sort_order' => 1,
        ]);

        $profiler = $this->section($template, 'profiler', 'Profiler', 'leader', 'questions', $order++);
        $this->textQuestions($profiler, [
            'profiler_perfil' => 'Perfil',
            'profiler_alinhamento' => 'Perfil isolado e estilo de liderança — existe alinhamento entre o perfil natural, a percepção do colaborador e o perfil esperado para a função?',
            'profiler_competencias' => 'Gráfico de competências — quais competências precisam ser desenvolvidas prioritariamente?',
            'profiler_talentos' => 'Gráfico de área de talentos — os principais talentos deste colaborador estão sendo aproveitados na função atual?',
            'profiler_conclusao' => 'Conclusão da análise',
        ]);

        $conquistas = $this->section($template, 'conquistas', 'Conquistas', 'leader', 'questions', $order++);
        FeedbackTemplateQuestion::create([
            'feedback_template_section_id' => $conquistas->id,
            'key' => 'conquistas_lista',
            'body' => 'Registre os principais resultados, entregas e reconhecimentos observados durante o período.',
            'question_type' => 'bullet_list',
            'is_required' => false,
            'sort_order' => 0,
        ]);

        $devLider = $this->section($template, 'dev_lider_colab', 'Perguntas do líder para o colaborador', 'leader', 'questions', $order++);
        $this->textQuestions($devLider, [
            'dev_compromisso_anterior' => 'Existe algum compromisso definido no último feedback que ainda não foi consolidado e que deve permanecer como prioridade para este novo ciclo?',
            'dev_dificuldades' => 'Teve alguma dificuldade ou obstáculo que gostaria de compartilhar?',
            'dev_clareza' => 'Alguma comunicação, tarefa ou processo que ainda não esteja claro para você?',
            'dev_apoio' => 'O que posso fazer para te apoiar melhor?',
            'dev_decisoes' => 'Existe alguma decisão da liderança que você gostaria de compreender melhor?',
            'dev_lideranca' => 'Se você pudesse mudar alguma coisa na minha forma de liderar, qual seria?',
        ]);

        $devColab = $this->section($template, 'dev_colab_lider', 'Perguntas do colaborador para o líder/empresa', 'employee', 'questions', $order++);
        $this->textQuestions($devColab, [
            'colab_expectativa' => 'O que você espera de mim no próximo trimestre?',
            'colab_contribuicao' => 'Como posso contribuir melhor para os objetivos da equipe?',
            'colab_comportamentos' => 'Que comportamentos ou atitudes são mais valorizados aqui?',
            'colab_desempenho' => 'Você acredita que estou indo bem? Há algo que eu deva ajustar?',
            'colab_prioridade_90' => 'Qual seria sua prioridade de desenvolvimento para os próximos 90 dias?',
        ]);

        $devLiderSelf = $this->section($template, 'dev_lider_self', 'Perguntas do líder para o líder', 'leader_self', 'questions', $order++);
        $this->textQuestions($devLiderSelf, [
            'lider_evolucao' => 'Você sente que evoluiu como líder neste período? Em quais pontos?',
            'lider_papel' => 'Como você enxerga hoje seu papel de liderança?',
            'lider_equipe' => 'Como você avalia a evolução da sua equipe e quais são hoje seus principais desafios?',
            'lider_fluxo' => 'O fluxo de trabalho da equipe está claro e organizado?',
            'lider_conquistas_equipe' => 'Quais foram as principais conquistas da sua equipe neste ciclo?',
            'lider_desafio_proximo' => 'Qual será o principal desafio da sua equipe no próximo ciclo?',
            'lider_sucessao' => 'Existe algum colaborador da equipe que demonstra potencial para assumir novos desafios ou desenvolver-se para uma futura sucessão?',
        ]);

        $acoes = $this->section($template, 'acoes', 'Ações', 'both', 'questions', $order++);
        FeedbackTemplateQuestion::create([
            'feedback_template_section_id' => $acoes->id,
            'key' => 'acoes_ccm',
            'body' => 'Começar / Continuar / Melhorar / Cessar',
            'question_type' => 'ccm_block',
            'is_required' => false,
            'sort_order' => 0,
        ]);
        FeedbackTemplateQuestion::create([
            'feedback_template_section_id' => $acoes->id,
            'key' => 'acoes_tabela',
            'body' => 'Ações com responsável e prazo',
            'question_type' => 'action_table',
            'is_required' => false,
            'sort_order' => 1,
        ]);
        FeedbackTemplateQuestion::create([
            'feedback_template_section_id' => $acoes->id,
            'key' => 'acoes_metas',
            'body' => 'Metas',
            'question_type' => 'bullet_list',
            'is_required' => false,
            'sort_order' => 2,
        ]);

        $percepcoes = $this->section($template, 'percepcoes', 'Percepções', 'leader', 'questions', $order++);
        $expectativa = [
            ['value' => 'acima', 'label' => 'Acima da expectativa'],
            ['value' => 'dentro', 'label' => 'Dentro da expectativa'],
            ['value' => 'abaixo', 'label' => 'Abaixo da expectativa'],
        ];
        $simParcial = [
            ['value' => 'sim', 'label' => 'Sim'],
            ['value' => 'parcialmente', 'label' => 'Parcialmente'],
            ['value' => 'nao', 'label' => 'Não'],
        ];
        FeedbackTemplateQuestion::create([
            'feedback_template_section_id' => $percepcoes->id,
            'key' => 'perc_comportamento',
            'body' => 'Como você avalia os comportamentos apresentados por este colaborador durante este ciclo?',
            'question_type' => 'single_choice',
            'options' => $expectativa,
            'is_required' => false,
            'sort_order' => 0,
        ]);
        FeedbackTemplateQuestion::create([
            'feedback_template_section_id' => $percepcoes->id,
            'key' => 'perc_desempenho',
            'body' => 'Como você avalia o desempenho (entregas e resultados) deste colaborador durante este ciclo?',
            'question_type' => 'single_choice',
            'options' => $expectativa,
            'is_required' => false,
            'sort_order' => 1,
        ]);
        FeedbackTemplateQuestion::create([
            'feedback_template_section_id' => $percepcoes->id,
            'key' => 'perc_potencial',
            'body' => 'O potencial deste colaborador está sendo bem aproveitado?',
            'question_type' => 'single_choice',
            'options' => $simParcial,
            'is_required' => false,
            'sort_order' => 2,
        ]);
        FeedbackTemplateQuestion::create([
            'feedback_template_section_id' => $percepcoes->id,
            'key' => 'perc_desafios',
            'body' => 'O colaborador está preparado para assumir desafios maiores? Se sim, quais?',
            'question_type' => 'single_choice',
            'options' => $simParcial,
            'is_required' => false,
            'sort_order' => 3,
        ]);
        FeedbackTemplateQuestion::create([
            'feedback_template_section_id' => $percepcoes->id,
            'key' => 'perc_comentario',
            'body' => 'Comentário',
            'question_type' => 'textarea',
            'is_required' => false,
            'sort_order' => 4,
        ]);

        $this->introSection($template, 'encerramento', 'Encerramento', 'both', $order, <<<'TXT'
Este alinhamento representa um compromisso mútuo entre colaborador e liderança. Mais do que avaliar resultados, buscamos promover desenvolvimento, fortalecer competências e construir uma trajetória profissional cada vez mais alinhada aos objetivos individuais e organizacionais.
TXT);
    }

    private function section(
        FeedbackTemplate $template,
        string $key,
        string $title,
        string $audience,
        string $type,
        int $sortOrder,
    ): FeedbackTemplateSection {
        return FeedbackTemplateSection::create([
            'feedback_template_id' => $template->id,
            'key' => $key,
            'title' => $title,
            'section_type' => $type,
            'audience' => $audience,
            'sort_order' => $sortOrder,
        ]);
    }

    private function introSection(
        FeedbackTemplate $template,
        string $key,
        string $title,
        string $audience,
        int $sortOrder,
        string $description,
    ): void {
        FeedbackTemplateSection::create([
            'feedback_template_id' => $template->id,
            'key' => $key,
            'title' => $title,
            'description' => $description,
            'section_type' => 'intro',
            'audience' => $audience,
            'sort_order' => $sortOrder,
        ]);
    }

    /**
     * @param  array<string, string>  $questions
     */
    private function textQuestions(FeedbackTemplateSection $section, array $questions): void
    {
        $i = 0;
        foreach ($questions as $key => $body) {
            FeedbackTemplateQuestion::create([
                'feedback_template_section_id' => $section->id,
                'key' => $key,
                'body' => $body,
                'question_type' => 'textarea',
                'is_required' => false,
                'sort_order' => $i++,
            ]);
        }
    }
}
