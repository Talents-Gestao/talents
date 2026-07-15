<?php

declare(strict_types=1);

namespace App\Support\Desligamento;

/**
 * Roteiro fixo da Pesquisa de Desligamento (preenchimento presencial na empresa ou pela Talents).
 */
final class ExitInterviewScript
{
    /**
     * @return list<array{key: string, title: string, questions: list<array{key: string, body: string, hint?: string}>}>
     */
    public static function sections(): array
    {
        return [
            [
                'key' => 'experiencia',
                'title' => '1. Experiência na empresa',
                'questions' => [
                    [
                        'key' => 'q1',
                        'body' => 'Como você descreveria sua experiência trabalhando na empresa?',
                    ],
                    [
                        'key' => 'q2',
                        'body' => 'O que mais gostou durante o período em que fez parte da organização?',
                    ],
                    [
                        'key' => 'q3',
                        'body' => 'Houve algum momento, projeto ou experiência que tenha sido especialmente positivo para você?',
                    ],
                ],
            ],
            [
                'key' => 'desligamento',
                'title' => '2. Desligamento',
                'questions' => [
                    [
                        'key' => 'q4',
                        'body' => 'Qual foi o principal motivo que levou ao seu desligamento?',
                    ],
                    [
                        'key' => 'q4_demissao',
                        'body' => 'Caso seja pedido de demissão: o que motivou sua decisão de buscar uma nova oportunidade?',
                        'hint' => 'Preencha apenas se o desligamento for pedido de demissão.',
                    ],
                    [
                        'key' => 'q5',
                        'body' => 'Essa decisão foi sendo construída ao longo do tempo ou houve algum fato específico que influenciou sua saída?',
                    ],
                    [
                        'key' => 'q6',
                        'body' => 'Na sua percepção, havia algo que a empresa poderia ter feito para que você permanecesse conosco?',
                    ],
                ],
            ],
            [
                'key' => 'desenvolvimento',
                'title' => '3. Desenvolvimento profissional',
                'questions' => [
                    [
                        'key' => 'q7',
                        'body' => 'Como você percebeu as oportunidades de aprendizado, desenvolvimento e crescimento profissional durante sua trajetória na empresa?',
                    ],
                    [
                        'key' => 'q8',
                        'body' => 'Houve algum fator que tenha dificultado seu desenvolvimento ou crescimento dentro da organização?',
                    ],
                ],
            ],
            [
                'key' => 'lideranca',
                'title' => '4. Liderança',
                'questions' => [
                    [
                        'key' => 'q9',
                        'body' => 'Como foi sua relação com sua liderança durante o período em que trabalhou na empresa?',
                    ],
                    [
                        'key' => 'q10',
                        'body' => 'Na sua percepção, quais foram os principais pontos positivos da atuação da sua liderança?',
                    ],
                    [
                        'key' => 'q11',
                        'body' => 'Existe algo que poderia ter sido diferente na forma como sua liderança conduzia a equipe?',
                    ],
                ],
            ],
            [
                'key' => 'relacionamento',
                'title' => '5. Relacionamento e ambiente de trabalho',
                'questions' => [
                    [
                        'key' => 'q12',
                        'body' => 'Como foi seu relacionamento com sua equipe e com as demais áreas da empresa?',
                    ],
                    [
                        'key' => 'q13',
                        'body' => 'Como você avalia o ambiente de trabalho durante o período em que esteve na empresa?',
                    ],
                    [
                        'key' => 'q14',
                        'body' => 'Houve alguma situação relacionada ao ambiente de trabalho que gostaria de compartilhar?',
                    ],
                ],
            ],
            [
                'key' => 'estrutura',
                'title' => '6. Estrutura e recursos',
                'questions' => [
                    [
                        'key' => 'q15',
                        'body' => 'Como você avalia as condições de trabalho, recursos e estrutura disponibilizados para a realização das suas atividades?',
                    ],
                    [
                        'key' => 'q16',
                        'body' => 'Existe alguma melhoria que considera importante em relação à estrutura física, equipamentos, ferramentas ou processos?',
                    ],
                ],
            ],
            [
                'key' => 'comunicacao',
                'title' => '7. Comunicação',
                'questions' => [
                    [
                        'key' => 'q17',
                        'body' => 'Como você percebeu a comunicação interna da empresa durante sua permanência?',
                    ],
                    [
                        'key' => 'q18',
                        'body' => 'Em sua opinião, o que poderia ser melhorado para que as informações chegassem de forma mais clara aos colaboradores?',
                    ],
                ],
            ],
            [
                'key' => 'rh',
                'title' => '8. Recursos Humanos',
                'questions' => [
                    [
                        'key' => 'q19',
                        'body' => 'Como você avalia o apoio prestado pela área de Recursos Humanos durante sua permanência na empresa?',
                    ],
                    [
                        'key' => 'q20',
                        'body' => 'Existe alguma sugestão para que o RH possa apoiar ainda melhor os colaboradores?',
                    ],
                ],
            ],
            [
                'key' => 'cultura',
                'title' => '9. Cultura organizacional',
                'questions' => [
                    [
                        'key' => 'q21',
                        'body' => 'Na sua percepção, a empresa pratica, no dia a dia, os valores e princípios que propõe?',
                    ],
                    [
                        'key' => 'q22',
                        'body' => 'Quais características da empresa você acredita que deveriam ser mantidas?',
                    ],
                    [
                        'key' => 'q23',
                        'body' => 'Se pudesse sugerir melhorias para a empresa, quais seriam suas principais recomendações?',
                    ],
                ],
            ],
            [
                'key' => 'encerramento',
                'title' => '10. Encerramento',
                'questions' => [
                    [
                        'key' => 'q24',
                        'body' => 'Você voltaria a trabalhar na empresa? Por quê?',
                    ],
                    [
                        'key' => 'q25',
                        'body' => 'Existe algum assunto importante que não abordamos e que você gostaria de compartilhar antes de encerrarmos nossa conversa?',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function answerKeys(): array
    {
        $keys = [];
        foreach (self::sections() as $section) {
            foreach ($section['questions'] as $question) {
                $keys[] = $question['key'];
            }
        }

        return $keys;
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public static function consultantNoteFields(): array
    {
        return [
            [
                'key' => 'main_reasons',
                'label' => 'Principais motivos identificados para o desligamento',
            ],
            [
                'key' => 'recurring_themes',
                'label' => 'Temas recorrentes observados durante a entrevista',
            ],
            [
                'key' => 'strengths_mentioned',
                'label' => 'Pontos fortes mencionados pelo colaborador',
            ],
            [
                'key' => 'improvement_opportunities',
                'label' => 'Oportunidades de melhoria identificadas',
            ],
            [
                'key' => 'consultant_perceptions',
                'label' => 'Percepções da consultora',
            ],
            [
                'key' => 'company_recommendations',
                'label' => 'Recomendações para a empresa (quando aplicável)',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function consultantNoteKeys(): array
    {
        return array_column(self::consultantNoteFields(), 'key');
    }
}
