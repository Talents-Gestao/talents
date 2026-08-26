<?php

declare(strict_types=1);

namespace App\Enums;

enum AdminPermissionModule: string
{
    case Dashboard = 'dashboard';
    case LandingInterest = 'landing_interest';

    /** Clientes (CRUD empresas e rotas do detalhe). */
    case Companies = 'companies';
    case CompaniesDiagnostico = 'companies_diagnostico';
    case CompaniesContratosFechados = 'companies_contratos_fechados';

    case Rhid = 'rhid';
    case Plans = 'plans';
    case SurveyTemplates = 'survey_templates';
    case Methodology = 'methodology';
    case StrategicCalendar = 'strategic_calendar';
    case Tarefas = 'tarefas';

    case ComercialResumo = 'comercial_resumo';
    case ComercialPropostas = 'comercial_propostas';
    case ComercialValoresContratos = 'comercial_valores_contratos';

    case FinanceiroResumo = 'financeiro_resumo';
    case FinanceiroVendas = 'financeiro_vendas';
    case FinanceiroComissoes = 'financeiro_comissoes';
    case FinanceiroContasBancarias = 'financeiro_contas_bancarias';
    case FinanceiroContasAPagar = 'financeiro_contas_a_pagar';
    case FinanceiroContasAReceber = 'financeiro_contas_a_receber';
    case FinanceiroFormasPagamento = 'financeiro_formas_pagamento';

    case EmpresaTalents = 'empresa_talents';

    case SolidesBancoTalentos = 'solides_banco_talentos';
    case SolidesAcompanhamento = 'solides_acompanhamento';
    case SolidesProfiler = 'solides_profiler';

    case Settings = 'settings';
    case Training = 'training';
    case Equipe = 'equipe';

    case EntrevistasIa = 'entrevistas_ia';
    case EntrevistasRoteiros = 'entrevistas_roteiros';
    case EntrevistasReunioes = 'entrevistas_reunioes';

    case Feedbacks = 'feedbacks';
    case Ferias = 'ferias';
    case Desligamento = 'desligamento';
    case Denuncias = 'denuncias';

    /**
     * Módulos legados (pré-granularidade). Mantidos para TryFrom / backfill;
     * não entram na matriz da UI.
     */
    case Financeiro = 'financeiro';
    case Comercial = 'comercial';
    case Solides = 'solides';
    case Entrevistas = 'entrevistas';

    public function label(): string
    {
        return match ($this) {
            self::Dashboard => 'Painel',
            self::LandingInterest => 'Clientes · Leads',
            self::Companies => 'Clientes · Empresas',
            self::CompaniesDiagnostico => 'Clientes · Diagnóstico empresarial',
            self::CompaniesContratosFechados => 'Clientes · Contratos fechados',
            self::Rhid => 'RHID - Empresas',
            self::Plans => 'Comercial · Assinaturas',
            self::SurveyTemplates => 'Voz do Time · Pesquisas / Mapeamentos',
            self::Methodology => 'Direcionamento Estratégico',
            self::StrategicCalendar => 'Calendário estratégico',
            self::Tarefas => 'Tarefas',
            self::ComercialResumo => 'Comercial · Resumo',
            self::ComercialPropostas => 'Comercial · Propostas',
            self::ComercialValoresContratos => 'Comercial · Valores e contratos',
            self::FinanceiroResumo => 'Financeiro · Resumo',
            self::FinanceiroVendas => 'Financeiro · Vendas',
            self::FinanceiroComissoes => 'Financeiro · Comissões',
            self::FinanceiroContasBancarias => 'Financeiro · Contas bancárias',
            self::FinanceiroContasAPagar => 'Financeiro · Contas a pagar',
            self::FinanceiroContasAReceber => 'Financeiro · Contas a receber',
            self::FinanceiroFormasPagamento => 'Financeiro · Formas de pagamento',
            self::EmpresaTalents => 'Configuração · Empresa Talents',
            self::SolidesBancoTalentos => 'Contratação · Banco de talentos',
            self::SolidesAcompanhamento => 'Contratação · Acompanhamento',
            self::SolidesProfiler => 'Contratação · Profiler',
            self::Settings => 'Configuração · Geral',
            self::Training => 'Capacitação',
            self::Equipe => 'Configuração · Equipe',
            self::EntrevistasIa => 'Contratação · Entrevistas IA',
            self::EntrevistasRoteiros => 'Contratação · Roteiros',
            self::EntrevistasReunioes => 'Reuniões',
            self::Feedbacks => 'Feedbacks internos',
            self::Ferias => 'Férias',
            self::Desligamento => 'Voz do Time · Desligamento',
            self::Denuncias => 'Voz do Time · Canal de denúncias',
            self::Financeiro => 'Financeiro (legado)',
            self::Comercial => 'Comercial (legado)',
            self::Solides => 'Sólides (legado)',
            self::Entrevistas => 'Entrevistas (legado)',
        };
    }

    /**
     * Módulos exibidos na matriz de permissões (Equipe).
     *
     * @return list<self>
     */
    public static function all(): array
    {
        return [
            self::Dashboard,
            self::LandingInterest,
            self::Companies,
            self::CompaniesDiagnostico,
            self::CompaniesContratosFechados,
            self::Rhid,
            self::ComercialResumo,
            self::ComercialPropostas,
            self::ComercialValoresContratos,
            self::Plans,
            self::SolidesBancoTalentos,
            self::SolidesAcompanhamento,
            self::SolidesProfiler,
            self::EntrevistasIa,
            self::EntrevistasRoteiros,
            self::EntrevistasReunioes,
            self::Feedbacks,
            self::SurveyTemplates,
            self::Desligamento,
            self::Denuncias,
            self::StrategicCalendar,
            self::Tarefas,
            self::Methodology,
            self::FinanceiroResumo,
            self::FinanceiroVendas,
            self::FinanceiroComissoes,
            self::FinanceiroContasBancarias,
            self::FinanceiroContasAPagar,
            self::FinanceiroContasAReceber,
            self::FinanceiroFormasPagamento,
            self::Settings,
            self::Equipe,
            self::EmpresaTalents,
            self::Training,
            self::Ferias,
        ];
    }

    /**
     * Pais legados → filhos atuais (para backfill e compatibilidade de grants antigos).
     *
     * @return array<string, list<self>>
     */
    public static function legacyExpansionMap(): array
    {
        return [
            self::Financeiro->value => [
                self::FinanceiroResumo,
                self::FinanceiroVendas,
                self::FinanceiroComissoes,
                self::FinanceiroContasBancarias,
                self::FinanceiroContasAPagar,
                self::FinanceiroContasAReceber,
                self::FinanceiroFormasPagamento,
            ],
            self::Comercial->value => [
                self::ComercialResumo,
                self::ComercialPropostas,
                self::ComercialValoresContratos,
            ],
            self::Solides->value => [
                self::SolidesBancoTalentos,
                self::SolidesAcompanhamento,
                self::SolidesProfiler,
            ],
            self::Entrevistas->value => [
                self::EntrevistasIa,
                self::EntrevistasRoteiros,
                self::EntrevistasReunioes,
            ],
            self::Companies->value => [
                self::CompaniesDiagnostico,
                self::CompaniesContratosFechados,
            ],
        ];
    }

    /**
     * @return list<self>
     */
    public function legacyParents(): array
    {
        $parents = [];
        foreach (self::legacyExpansionMap() as $parentValue => $children) {
            foreach ($children as $child) {
                if ($child === $this) {
                    $parents[] = self::from($parentValue);
                    break;
                }
            }
        }

        return $parents;
    }
}
