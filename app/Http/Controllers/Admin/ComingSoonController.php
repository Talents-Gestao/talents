<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\AdminPermissionModule;
use App\Enums\PermissionAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ComingSoonController extends Controller
{
    /**
     * Telas nomeadas no planejamento ainda sem implementação completa.
     *
     * @var array<string, array{title: string, description: string, permission: AdminPermissionModule}>
     */
    private const MODULES = [
        'profiler' => [
            'title' => 'Profiler',
            'description' => 'Mapeamento comportamental integrado à Sólides (profiler) será disponibilizado em breve nesta área de contratação.',
            'permission' => AdminPermissionModule::Solides,
        ],
        'timeline' => [
            'title' => 'Timeline',
            'description' => 'Resumo do funil de contratação (candidatos, desclassificados, entrevistados) em sincronia com a Sólides chegará em uma próxima versão.',
            'permission' => AdminPermissionModule::Solides,
        ],
        'reunioes' => [
            'title' => 'Reuniões',
            'description' => 'Fluxo de gravação, cronômetro, transcrição e ata de reunião será disponibilizado em breve.',
            'permission' => AdminPermissionModule::Entrevistas,
        ],
        'ponto' => [
            'title' => 'Ponto',
            'description' => 'Gestão de ponto dos colaboradores será disponibilizada em uma próxima versão.',
            'permission' => AdminPermissionModule::Rhid,
        ],
        'diagnostico-empresarial' => [
            'title' => 'Diagnóstico empresarial',
            'description' => 'O diagnóstico empresarial completo para clientes estará disponível em breve neste módulo.',
            'permission' => AdminPermissionModule::Companies,
        ],
        'cadastro-colaboradores' => [
            'title' => 'Cadastro de colaboradores',
            'description' => 'Cadastro estruturado de colaboradores (dados em alinhamento com a operação) será liberado em breve.',
            'permission' => AdminPermissionModule::Companies,
        ],
        'contratos-fechados' => [
            'title' => 'Contratos fechados',
            'description' => 'Histórico de contratos fechados por cliente estará disponível em breve.',
            'permission' => AdminPermissionModule::Companies,
        ],
        'regulamento-interno' => [
            'title' => 'Regulamento interno',
            'description' => 'Gestão de regulamento interno por empresa será disponibilizada em breve.',
            'permission' => AdminPermissionModule::Companies,
        ],
        'controle-uniformes' => [
            'title' => 'Controle de uniformes',
            'description' => 'Controle de uniformes por empresa será disponibilizado em breve.',
            'permission' => AdminPermissionModule::Companies,
        ],
        'destaques-mes' => [
            'title' => 'Destaques do mês',
            'description' => 'Destaques do mês (produtividade, pontualidade, engajamento, atendimento, comercial e indicação) chegarão em breve.',
            'permission' => AdminPermissionModule::Companies,
        ],
        'contas-bancarias' => [
            'title' => 'Contas bancárias',
            'description' => 'Cadastro e conciliação de contas bancárias serão disponibilizados em breve no financeiro.',
            'permission' => AdminPermissionModule::Financeiro,
        ],
        'contas-a-pagar' => [
            'title' => 'Contas a pagar',
            'description' => 'Módulo de contas a pagar será disponibilizado em breve.',
            'permission' => AdminPermissionModule::Financeiro,
        ],
        'contas-a-receber' => [
            'title' => 'Contas a receber',
            'description' => 'Módulo dedicado de contas a receber será disponibilizado em breve (hoje o acompanhamento ocorre via vendas e parcelas).',
            'permission' => AdminPermissionModule::Financeiro,
        ],
        'formas-pagamento' => [
            'title' => 'Formas de pagamento',
            'description' => 'Cadastro de formas de pagamento será disponibilizado em breve.',
            'permission' => AdminPermissionModule::Financeiro,
        ],
    ];

    public function show(Request $request, string $module): Response
    {
        $config = self::MODULES[$module] ?? null;
        abort_unless($config !== null, 404);

        $user = $request->user();
        abort_unless(
            $user?->canAccessAdmin($config['permission'], PermissionAction::View) === true,
            403,
            'Sem permissão para esta área.',
        );

        return Inertia::render('Admin/ComingSoon', [
            'title' => $config['title'],
            'description' => $config['description'],
            'module' => $module,
        ]);
    }
}
