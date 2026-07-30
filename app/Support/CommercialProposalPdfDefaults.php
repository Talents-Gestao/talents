<?php

namespace App\Support;

class CommercialProposalPdfDefaults
{
    /**
     * @return array<string, string>
     */
    public static function defaultServiceDescriptions(): array
    {
        return [
            'pesquisas' => <<<'TXT'
O que contempla:
• Mapeamento da cultura organizacional, estrutura e principais desafios da empresa;
• Identificação de oportunidades de melhoria em processos, gestão e ambiente organizacional;
• Relatório estratégico com análise inicial dos cenários identificados;

Objetivo: Fornecer uma visão clara da situação atual da empresa, apoiando a tomada de decisões e a construção de estratégias de crescimento sustentável.
TXT,
            'profiler' => <<<'TXT'
O que contempla:
• Aplicação de questionário digital de perfil comportamental para cada colaborador;
• Análise individual e consolidada dos perfis da equipe;
• Relatório com mapeamento de competências, pontos fortes e oportunidades de desenvolvimento;

Objetivo: Conhecer profundamente o perfil comportamental dos colaboradores para apoiar decisões de alocação, desenvolvimento e gestão de equipes.
TXT,
            'devolutiva' => <<<'TXT'
O que contempla:
• Sessão de devolutiva dos resultados do diagnóstico comportamental;
• Apresentação dos achados e recomendações práticas para a liderança;
• Orientações para plano de desenvolvimento individual e coletivo;

Objetivo: Transformar os dados do diagnóstico em ações concretas de desenvolvimento de pessoas e fortalecimento da gestão.
TXT,
            'nr1' => <<<'TXT'
O que contempla:
• Mapeamento dos riscos psicossociais conforme atualização da NR-1;
• Aplicação de instrumentos de avaliação junto aos colaboradores;
• Relatório com identificação de riscos, níveis de exposição e recomendações de mitigação;
• Acompanhamento em 12 parcelas mensais;

Objetivo: Atender às exigências legais e promover ambientes de trabalho mais saudáveis, prevenindo riscos psicossociais.
TXT,
            'nr1_implantacao' => <<<'TXT'
O que contempla:
• Implantação do programa de gestão de riscos psicossociais na empresa;
• Capacitação da equipe interna para condução do processo;
• Estruturação de fluxos, documentação e plano de ação inicial;

Objetivo: Estabelecer as bases operacionais para a gestão contínua dos riscos psicossociais previstos na NR-1.
TXT,
            'contratacao' => <<<'TXT'
O que contempla:
• Definição de perfil comportamental e competências para a vaga;
• Divulgação, triagem e condução do processo seletivo;
• Aplicação de ferramentas de avaliação e entrevistas estruturadas;
• Apresentação de candidatos finalistas com parecer técnico;

Objetivo: Identificar e selecionar profissionais alinhados à cultura e às necessidades da empresa, reduzindo turnover e acelerando resultados.
TXT,
            'direcionamento' => <<<'TXT'
O que contempla:
• Diagnóstico estratégico da gestão de pessoas;
• Análise de estrutura organizacional, processos e práticas de RH;
• Plano de ação com recomendações prioritárias e cronograma de implementação;

Objetivo: Orientar a empresa na construção de uma gestão de pessoas estruturada, alinhada aos objetivos de negócio.
TXT,
            'palestras' => <<<'TXT'
O que contempla:
• Palestra prática e estratégica voltada para lideres e gestores;
• Conteúdo customizado conforme necessidade da empresa;
• Material de apoio e certificado de participação;

Objetivo: Conscientizar e desenvolver lideres para atuarem como agentes de um ambiente organizacional mais saudável e produtivo.
TXT,
        ];
    }

    public static function defaultPaymentConditions(): string
    {
        return '• Pagamento no cartão de crédito, com possibilidade de parcelamento;';
    }

    /**
     * Condição padrão de permanência mínima para cancelamento do plano (PDF).
     */
    public static function defaultMinimumStayCondition(): string
    {
        return '• Permanência mínima de 90 (noventa) dias (3 meses) para cancelamento do plano.';
    }

    public static function defaultClosingText(): string
    {
        return "Acreditamos que ouvir as pessoas, fortalecer a liderança e construir ambientes organizacionais saudáveis "
            ."são fatores essenciais para a retenção de talentos, aumento da produtividade e crescimento sustentável da empresa.\n\n"
            ."Será um prazer contribuir com o desenvolvimento da sua equipe e dos seus resultados.";
    }

    /**
     * @return array<string, string>
     */
    public static function serviceDescriptionsForSettings(?array $stored): array
    {
        return array_merge(self::defaultServiceDescriptions(), $stored ?? []);
    }
}
