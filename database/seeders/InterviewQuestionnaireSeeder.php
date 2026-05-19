<?php

namespace Database\Seeders;

use App\Models\InterviewQuestionnaire;
use App\Models\InterviewQuestionnaireQuestion;
use App\Models\InterviewQuestionnaireSection;
use Illuminate\Database\Seeder;

class InterviewQuestionnaireSeeder extends Seeder
{
    public static function ensureDefault(): void
    {
        (new self)->run();
    }

    public function run(): void
    {
        if (InterviewQuestionnaire::query()->where('is_default', true)->exists()) {
            return;
        }

        $questionnaire = InterviewQuestionnaire::query()->create([
            'name' => 'Entrevista RH — Padrão Talents',
            'description' => 'Roteiro padrão de entrevista comportamental e técnica para processos seletivos.',
            'is_default' => true,
            'created_by' => null,
        ]);

        $sections = [
            [
                'title' => '1. Informações Pessoais',
                'questions' => [
                    ['key' => 'info_ultimo_salario', 'text' => 'Último salário:'],
                    ['key' => 'info_estado_civil', 'text' => 'Estado civil:'],
                    ['key' => 'info_filhos', 'text' => 'Possui filhos? Quantos? Idades:'],
                    ['key' => 'info_tempo_deslocamento', 'text' => 'Tempo de deslocamento até a empresa:'],
                ],
            ],
            [
                'title' => '2. Formação e Qualificações',
                'questions' => [
                    ['key' => 'formacao_tecnica_superior', 'text' => 'Possui formação técnica ou superior? Em qual área?'],
                    ['key' => 'formacao_cursos_complementares', 'text' => 'Já realizou cursos ou treinamentos complementares na área da vaga?'],
                    ['key' => 'formacao_cursando_desenvolvimento', 'text' => 'Está cursando algo atualmente? Tem interesse em se desenvolver profissionalmente?'],
                    ['key' => 'formacao_idiomas', 'text' => 'Curso de Idiomas? Básico, Intermediário ou Avançado'],
                ],
            ],
            [
                'title' => '3. Experiência Profissional',
                'questions' => [
                    ['key' => 'exp_responsabilidades_rotina', 'text' => 'Fale sobre sua experiência anterior: quais eram suas responsabilidades e rotina?'],
                    ['key' => 'exp_motivo_saida', 'text' => 'Por que decidiu sair da última empresa?'],
                    ['key' => 'exp_empresa_setor', 'text' => 'O que pra você faz uma empresa de / setor de?'],
                    ['key' => 'exp_atividades_vaga', 'text' => 'Já atuou com as atividades específicas da função (relacionar com a vaga)?'],
                    ['key' => 'exp_explicar_departamento', 'text' => 'Se você tivesse que explicar para alguém o que faz esse departamento, como explicaria com suas palavras?'],
                    ['key' => 'exp_ferramentas', 'text' => 'Quais ferramentas, sistemas ou plataformas já utilizou no trabalho?'],
                    ['key' => 'exp_prazos_metas', 'text' => 'Já trabalhou com prazos, metas ou indicadores de desempenho?'],
                ],
            ],
            [
                'title' => '4. Perfil Comportamental e Organização',
                'questions' => [
                    ['key' => 'perfil_sobre_voce', 'text' => 'Fale um pouco sobre você: rotina, hobbies, estrutura familiar, onde e com quem mora?'],
                    ['key' => 'perfil_organizacao_tarefas', 'text' => 'Como organiza suas tarefas e prioridades no trabalho? Utiliza alguma ferramenta?'],
                    ['key' => 'perfil_apoiar_empresa', 'text' => 'Como você acredita que pode apoiar da melhor maneira a empresa?'],
                    ['key' => 'perfil_maior_diferencial', 'text' => 'Qual habilidade ou competência você considera seu maior diferencial?'],
                    ['key' => 'perfil_maior_responsabilidade', 'text' => 'Qual foi a maior responsabilidade que já confiaram a você?'],
                    ['key' => 'perfil_situacao_desafiadora', 'text' => 'Me conte sobre a situação mais desafiadora que enfrentou?'],
                    ['key' => 'perfil_principal_erro', 'text' => 'Qual foi o principal erro que você já cometeu na sua vida?'],
                    ['key' => 'perfil_atualizacao', 'text' => 'Como você se mantém atualizado?'],
                    ['key' => 'perfil_lider_nao_consegue', 'text' => 'Seu líder pede para você fazer algo e você não consegue fazer! Qual a sua atitude?'],
                    ['key' => 'perfil_enfrentamento', 'text' => 'Em uma situação de enfrentamento com outra pessoa, como você costuma reagir?'],
                ],
            ],
            [
                'title' => '5. Motivação, Valores e Cultura',
                'questions' => [
                    ['key' => 'motiv_gostos_ultimo_emprego', 'text' => 'O que mais gostava (e o que menos gostava) no seu último emprego?'],
                    ['key' => 'motiv_desmotivacao', 'text' => 'O que já te desmotivou em experiências anteriores?'],
                    ['key' => 'motiv_valores_sonho', 'text' => 'O que você mais valoriza em uma empresa? Qual é o emprego dos seus sonhos?'],
                    ['key' => 'motiv_candidatura_vaga', 'text' => 'O que te motivou a se candidatar a essa vaga? O que mais chamou a sua atenção?'],
                    ['key' => 'motiv_crescimento', 'text' => 'Qual sua expectativa em relação ao crescimento profissional?'],
                ],
            ],
            [
                'title' => '6. Objetivos e Futuro',
                'questions' => [
                    ['key' => 'objetivos_proximos_anos', 'text' => 'Quais são seus objetivos profissionais para os próximos anos?'],
                    ['key' => 'objetivos_proximo_passo', 'text' => 'Qual é o seu próximo passo na sua carreira e quando você pretende dar esse passo?'],
                    ['key' => 'objetivos_iniciativa_propria', 'text' => 'Qual foi a última coisa que você fez, que dependia somente de você e que ninguém te pediu para fazer e que te aproximou desse próximo passo?'],
                    ['key' => 'objetivos_maior_conquista', 'text' => 'Qual foi sua maior conquista nos últimos anos?'],
                    ['key' => 'objetivos_decisao_dificil', 'text' => 'Qual a decisão mais importante ou difícil que precisou tomar?'],
                    ['key' => 'objetivos_sonho', 'text' => 'Tem algum sonho que gostaria de realizar? Pessoal ou profissional.'],
                ],
            ],
            [
                'title' => '7. Finalizando',
                'questions' => [
                    ['key' => 'final_como_soube_vaga', 'text' => 'Como ficou sabendo desta vaga?'],
                    ['key' => 'final_disponibilidade', 'text' => 'Tem disponibilidade para início imediato?'],
                    ['key' => 'final_acrescentar', 'text' => 'Tem algo que gostaria de acrescentar?'],
                ],
            ],
        ];

        foreach ($sections as $sectionIndex => $sectionData) {
            $section = InterviewQuestionnaireSection::query()->create([
                'questionnaire_id' => $questionnaire->id,
                'title' => $sectionData['title'],
                'position' => $sectionIndex + 1,
            ]);

            foreach ($sectionData['questions'] as $questionIndex => $question) {
                InterviewQuestionnaireQuestion::query()->create([
                    'section_id' => $section->id,
                    'question_key' => $question['key'],
                    'text' => $question['text'],
                    'position' => $questionIndex + 1,
                ]);
            }
        }
    }
}
