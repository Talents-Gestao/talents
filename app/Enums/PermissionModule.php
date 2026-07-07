<?php

namespace App\Enums;

enum PermissionModule: string
{
    case Pesquisas = 'pesquisas';
    case PlanosAcao = 'planos_acao';
    case Denuncias = 'denuncias';
    case Metodologia = 'metodologia';
    case CalendarioEstrategico = 'calendario_estrategico';
    case Rhid = 'rhid';
    case DepartamentosCargos = 'departamentos_cargos';
    case Relatorios = 'relatorios';
    case ConfiguracoesEmpresa = 'configuracoes_empresa';
    case Usuarios = 'usuarios';
    case Capacitacao = 'capacitacao';
    case Tarefas = 'tarefas';
    case Feedbacks = 'feedbacks';

    public function label(): string
    {
        return match ($this) {
            self::Pesquisas => 'Pesquisas NR1',
            self::PlanosAcao => 'Planos de ação',
            self::Denuncias => 'Denúncias',
            self::Metodologia => 'Direcionamento Estratégico',
            self::CalendarioEstrategico => 'Calendário estratégico',
            self::Rhid => 'RHID / Ponto',
            self::DepartamentosCargos => 'Setores e cargos',
            self::Relatorios => 'Relatórios e exportações',
            self::ConfiguracoesEmpresa => 'Configurações da empresa',
            self::Usuarios => 'Utilizadores',
            self::Capacitacao => 'Capacitação',
            self::Tarefas => 'Tarefas',
            self::Feedbacks => 'Feedbacks internos',
        };
    }

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return [
            self::Pesquisas,
            self::PlanosAcao,
            self::Denuncias,
            self::Metodologia,
            self::CalendarioEstrategico,
            self::Rhid,
            self::DepartamentosCargos,
            self::Relatorios,
            self::ConfiguracoesEmpresa,
            self::Usuarios,
            self::Capacitacao,
            self::Tarefas,
            self::Feedbacks,
        ];
    }
}
