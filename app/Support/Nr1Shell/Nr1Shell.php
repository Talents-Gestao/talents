<?php

declare(strict_types=1);

namespace App\Support\Nr1Shell;

use App\Enums\AdminPermissionModule;
use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Psychosocial\DocumentShareService;

/**
 * Shell NR-1 (/nr1): perfis, matriz de seções por perfil (contrato do HTML aprovado) e a decisão de acesso.
 *
 * A matriz diz o que o perfil PODE ver; a decisão final (allows) também confere a permissão real do usuário
 * (matriz de admin, módulos da empresa, revisão externa ligada). O menu e as rotas usam a mesma decisão, então
 * esconder o item nunca é a única barreira: a rota responde 403/404 igual.
 */
final class Nr1Shell
{
    public const PROFILE_TALENTS = 'talents';

    public const PROFILE_EMPRESA = 'empresa';

    public const PROFILE_COMERCIAL = 'comercial';

    public const PROFILE_PASSEG = 'passeg';

    public const PROFILE_COLABORADOR = 'colaborador';

    /** Seções na ordem do menu do protótipo: chave => [rótulo, ícone]. */
    public const SECTIONS = [
        'visao-geral' => ['Visão geral', '◫'],
        'campanha' => ['Campanha NR-1', '▶'],
        'minha-jornada' => ['Minha jornada', '♡'],
        'resultados' => ['Resultados da pesquisa', '▥'],
        'plano-de-acao' => ['Plano de ação', '☷'],
        'documentos' => ['Documentos', '▤'],
        'solucoes' => ['Soluções Talents', '✦'],
        'oportunidades' => ['Oportunidades', '↗'],
    ];

    /** PERMISSIONS do HTML aprovado (colaborador: Campanha NR-1 e Minha jornada, conforme a diretriz do produto). */
    public const MATRIX = [
        self::PROFILE_TALENTS => ['visao-geral', 'campanha', 'resultados', 'plano-de-acao', 'documentos', 'solucoes', 'oportunidades'],
        self::PROFILE_EMPRESA => ['visao-geral', 'campanha', 'resultados', 'plano-de-acao', 'documentos', 'solucoes'],
        self::PROFILE_PASSEG => ['documentos'],
        self::PROFILE_COMERCIAL => ['solucoes', 'oportunidades'],
        self::PROFILE_COLABORADOR => ['campanha', 'minha-jornada'],
    ];

    /** Perfil do usuário autenticado no shell (colaborador não tem login: entra pelo link da campanha). */
    public static function profileFor(User $user): ?string
    {
        if (! $user->isActive()) {
            return null;
        }

        if ($user->role === UserRole::ExternalReviewer) {
            return self::PROFILE_PASSEG;
        }

        if ($user->isSuperAdmin()) {
            if ($user->canAccessAdmin(AdminPermissionModule::Companies, PermissionAction::View)) {
                return self::PROFILE_TALENTS;
            }

            return $user->canAccessAdmin(AdminPermissionModule::Nr1Oportunidades, PermissionAction::View)
                ? self::PROFILE_COMERCIAL
                : null;
        }

        if (in_array($user->role, [UserRole::CompanyAdmin, UserRole::CompanyUser], true)) {
            return self::PROFILE_EMPRESA;
        }

        return null;
    }

    public static function allows(User $user, string $section, ?string $profile = null): bool
    {
        $profile ??= self::profileFor($user);
        if ($profile === null || ! in_array($section, self::MATRIX[$profile] ?? [], true)) {
            return false;
        }

        return match ($profile) {
            self::PROFILE_TALENTS, self::PROFILE_COMERCIAL => match ($section) {
                'oportunidades' => $user->canAccessAdmin(AdminPermissionModule::Nr1Oportunidades, PermissionAction::View),
                'solucoes' => $user->canAccessAdmin(AdminPermissionModule::Methodology, PermissionAction::View),
                default => $user->canAccessAdmin(AdminPermissionModule::Companies, PermissionAction::View),
            },
            // Mesmo módulo das rotas client antigas: plano = planos_acao; documentos e painel/KPIs (visão geral e
            // resultados leem o painel psicossocial) = relatorios; campanha e soluções = pesquisas.
            self::PROFILE_EMPRESA => $user->canAccess(match ($section) {
                'plano-de-acao' => PermissionModule::PlanosAcao,
                'documentos', 'visao-geral', 'resultados' => PermissionModule::Relatorios,
                default => PermissionModule::Pesquisas,
            }, PermissionAction::View),
            self::PROFILE_PASSEG => DocumentShareService::enabled(),
            default => false,
        };
    }

    /**
     * Itens do menu do usuário, na ordem do protótipo.
     *
     * @return list<array{key: string, label: string, icon: string}>
     */
    public static function sectionsFor(User $user): array
    {
        $profile = self::profileFor($user);
        if ($profile === null) {
            return [];
        }

        $items = [];
        foreach (self::SECTIONS as $key => [$label, $icon]) {
            if (self::allows($user, $key, $profile)) {
                $items[] = ['key' => $key, 'label' => $label, 'icon' => $icon];
            }
        }

        return $items;
    }

    /** Seção em que o perfil abre (HTML: Documentos para a PASSEG, Oportunidades para o Comercial). */
    public static function landingSection(User $user): ?string
    {
        $profile = self::profileFor($user);
        $keys = array_column(self::sectionsFor($user), 'key');
        $preferred = match ($profile) {
            self::PROFILE_PASSEG => 'documentos',
            self::PROFILE_COMERCIAL => 'oportunidades',
            default => 'visao-geral',
        };

        return in_array($preferred, $keys, true) ? $preferred : ($keys[0] ?? null);
    }

    /** Entrada «Oportunidades NR-1» do grupo Comercial do menu do Admin (permissão nr1_oportunidades:view). */
    public static function opportunitiesMenuUrl(?User $user): ?string
    {
        if ($user === null || ! $user->isSuperAdmin() || ! self::allows($user, 'oportunidades')) {
            return null;
        }

        return route('nr1.oportunidades', absolute: false);
    }

    /** URL do item único «NR-1» do menu principal, ou null (sem acesso). */
    public static function menuUrl(?User $user): ?string
    {
        if ($user === null || self::landingSection($user) === null) {
            return null;
        }

        return route('nr1.index', absolute: false);
    }
}
