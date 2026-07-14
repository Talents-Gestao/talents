<?php

namespace App\Enums;

enum AdminPermissionModule: string
{
    case Dashboard = 'dashboard';
    case LandingInterest = 'landing_interest';
    case Companies = 'companies';
    case Rhid = 'rhid';
    case Plans = 'plans';
    case SurveyTemplates = 'survey_templates';
    case Methodology = 'methodology';
    case StrategicCalendar = 'strategic_calendar';
    case Tarefas = 'tarefas';
    case Comercial = 'comercial';
    case Financeiro = 'financeiro';
    case EmpresaTalents = 'empresa_talents';
    case Solides = 'solides';
    case Settings = 'settings';
    case Training = 'training';
    case Equipe = 'equipe';
    case Entrevistas = 'entrevistas';
    case Feedbacks = 'feedbacks';
    case Ferias = 'ferias';
    case Desligamento = 'desligamento';
    case Denuncias = 'denuncias';

    public function label(): string
    {
        return match ($this) {
            self::Dashboard => 'Painel',
            self::LandingInterest => 'Interessados',
            self::Companies => 'Empresas',
            self::Rhid => 'RHID - Empresas',
            self::Plans => 'Planos',
            self::SurveyTemplates => 'Mapeamentos',
            self::Methodology => 'Direcionamento Estratégico',
            self::StrategicCalendar => 'Calendário estratégico',
            self::Tarefas => 'Tarefas',
            self::Comercial => 'Comercial',
            self::Financeiro => 'Financeiro',
            self::EmpresaTalents => 'Empresa Talents',
            self::Solides => 'Sólides — Currículos',
            self::Settings => 'Configurações',
            self::Training => 'Capacitação',
            self::Equipe => 'Equipe',
            self::Entrevistas => 'Entrevistas (IA)',
            self::Feedbacks => 'Feedbacks internos',
            self::Ferias => 'Férias',
            self::Desligamento => 'Pesquisa de Desligamento',
            self::Denuncias => 'Canal de denúncias',
        };
    }

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return [
            self::Dashboard,
            self::LandingInterest,
            self::Companies,
            self::Rhid,
            self::Plans,
            self::SurveyTemplates,
            self::Methodology,
            self::StrategicCalendar,
            self::Tarefas,
            self::Comercial,
            self::Financeiro,
            self::EmpresaTalents,
            self::Solides,
            self::Settings,
            self::Training,
            self::Equipe,
            self::Entrevistas,
            self::Feedbacks,
            self::Ferias,
            self::Desligamento,
            self::Denuncias,
        ];
    }
}
