<?php

namespace Database\Seeders;

use App\Models\MethodologyFormQuestion;
use App\Models\MethodologyFormSection;
use App\Models\MethodologyFormTemplate;
use App\Models\User;
use Illuminate\Database\Seeder;

class MethodologyFormTemplateSeeder extends Seeder
{
    public function run(): void
    {
        if (MethodologyFormTemplate::query()->exists()) {
            return;
        }

        $userId = User::query()->orderBy('id')->value('id');

        $template = MethodologyFormTemplate::create([
            'title' => 'Pesquisa de Satisfação — Padrão Talents',
            'description' => 'Formulário padrão de percepção dos colaboradores (ambiente, liderança, comunicação, cultura, reconhecimento, carreira, benefícios e engajamento).',
            'step_number' => 2,
            'is_active' => true,
            'created_by' => $userId,
        ]);

        $sections = $this->defaultSections();

        foreach ($sections as $si => $def) {
            $sec = MethodologyFormSection::create([
                'methodology_form_template_id' => $template->id,
                'title' => $def['title'],
                'description' => $def['description'] ?? null,
                'sort_order' => $si,
            ]);

            foreach ($def['questions'] as $qi => $q) {
                MethodologyFormQuestion::create([
                    'methodology_form_section_id' => $sec->id,
                    'body' => $q['body'],
                    'type' => $q['type'],
                    'is_required' => $q['required'] ?? true,
                    'scale_min' => $q['scale_min'] ?? 0,
                    'scale_max' => $q['scale_max'] ?? 5,
                    'sort_order' => $qi,
                ]);
            }
        }
    }

    /**
     * @return list<array{title: string, description?: string, questions: list<array<string, mixed>>}>
     */
    private function defaultSections(): array
    {
        return [
            [
                'title' => 'Ambiente e Condições de Trabalho',
                'questions' => [
                    ['type' => 'scale', 'body' => 'O ambiente físico de trabalho (espaço, limpeza, ergonomia, recursos) é adequado para o desempenho das suas atividades?'],
                    ['type' => 'scale', 'body' => 'Você considera que possui os recursos e ferramentas necessários para realizar seu trabalho da melhor forma?'],
                    ['type' => 'scale', 'body' => 'Você sente que a empresa se preocupa com a sua saúde, segurança e bem-estar?'],
                    ['type' => 'text', 'body' => 'O que poderia ser melhorado no ambiente de trabalho para aumentar seu bem-estar?'],
                ],
            ],
            [
                'title' => 'Liderança e Gestão',
                'questions' => [
                    ['type' => 'scale', 'body' => 'Você sente que sua liderança está aberta ao diálogo e valoriza suas opiniões?'],
                    ['type' => 'scale', 'body' => 'Sua liderança dá feedbacks de forma clara, construtiva e com frequência adequada?'],
                    ['type' => 'scale', 'body' => 'Você sente que recebe apoio e orientação da liderança quando precisa?'],
                    ['type' => 'text', 'body' => 'Quais pontos você destacaria como positivos e quais poderiam ser melhorados na liderança?'],
                ],
            ],
            [
                'title' => 'Comunicação Interna',
                'questions' => [
                    ['type' => 'scale', 'body' => 'A comunicação da empresa é clara e transparente?'],
                    ['type' => 'scale', 'body' => 'Você sente que é informado(a) sobre decisões importantes que afetam seu trabalho?'],
                    ['type' => 'scale', 'body' => 'Há facilidade para esclarecer dúvidas e obter informações dentro da empresa?'],
                    ['type' => 'text', 'body' => 'O que poderia ser feito para melhorar a comunicação interna?'],
                ],
            ],
            [
                'title' => 'Relacionamento e Cultura Organizacional',
                'questions' => [
                    ['type' => 'scale', 'body' => 'O relacionamento entre os colaboradores é respeitoso e colaborativo?'],
                    ['type' => 'scale', 'body' => 'Você se sente parte da equipe e da empresa?'],
                    ['type' => 'scale', 'body' => 'Você se identifica com os valores e a cultura organizacional da empresa?'],
                    ['type' => 'text', 'body' => 'O que você mais valoriza na cultura e ambiente da empresa? E o que poderia ser diferente?'],
                ],
            ],
            [
                'title' => 'Reconhecimento e Valorização',
                'questions' => [
                    ['type' => 'scale', 'body' => 'Você sente que seu trabalho é reconhecido e valorizado pela empresa?'],
                    ['type' => 'scale', 'body' => 'A empresa demonstra interesse em reter e valorizar seus talentos?'],
                    ['type' => 'scale', 'body' => 'Os feedbacks recebidos contribuem para seu desenvolvimento?'],
                    ['type' => 'text', 'body' => 'O que a empresa poderia fazer para que você se sinta mais reconhecido(a)?'],
                ],
            ],
            [
                'title' => 'Desenvolvimento e Carreira',
                'questions' => [
                    ['type' => 'scale', 'body' => 'Você enxerga oportunidades de crescimento dentro da empresa?'],
                    ['type' => 'scale', 'body' => 'A empresa investe em treinamentos e capacitações que contribuem para o seu desenvolvimento?'],
                    ['type' => 'scale', 'body' => 'Você tem clareza sobre quais competências são necessárias para evoluir na sua carreira aqui?'],
                    ['type' => 'text', 'body' => 'Quais treinamentos, cursos ou ações de desenvolvimento você considera importantes?'],
                ],
            ],
            [
                'title' => 'Benefícios e Remuneração',
                'questions' => [
                    ['type' => 'scale', 'body' => 'Você considera sua remuneração justa em relação ao mercado e às suas responsabilidades?'],
                    ['type' => 'scale', 'body' => 'Os benefícios oferecidos pela empresa atendem às suas necessidades?'],
                    ['type' => 'scale', 'body' => 'Você sente clareza e transparência sobre a política de salários e benefícios da empresa?'],
                    ['type' => 'text', 'body' => 'Quais benefícios você considera mais importantes e gostaria que a empresa oferecesse ou melhorasse?'],
                ],
            ],
            [
                'title' => 'Satisfação Geral e Engajamento',
                'questions' => [
                    ['type' => 'scale', 'body' => 'Em geral, você está satisfeito(a) em trabalhar nesta empresa?'],
                    ['type' => 'scale', 'body' => 'Você sente orgulho em fazer parte da empresa?'],
                    ['type' => 'scale', 'body' => 'Qual a probabilidade de você recomendar esta empresa como um bom lugar para trabalhar?'],
                    ['type' => 'text', 'body' => 'O que a empresa poderia fazer para aumentar sua satisfação e engajamento?'],
                ],
            ],
        ];
    }
}
