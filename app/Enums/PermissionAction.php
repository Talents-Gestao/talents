<?php

namespace App\Enums;

enum PermissionAction: string
{
    case View = 'view';
    case Create = 'create';
    case Edit = 'edit';
    case Delete = 'delete';
    case Validate = 'validate';

    public function label(): string
    {
        return match ($this) {
            self::View => 'Ver',
            self::Create => 'Criar',
            self::Edit => 'Editar',
            self::Delete => 'Apagar',
            self::Validate => 'Validar',
        };
    }

    /**
     * Ações gerais de CRUD, válidas em todos os módulos e na matriz de admin. Validate fica de fora.
     *
     * @return list<self>
     */
    public static function all(): array
    {
        return [self::View, self::Create, self::Edit, self::Delete];
    }

    /**
     * Colunas da matriz de permissões do cliente: o CRUD mais as ações restritas a um módulo.
     *
     * @return list<self>
     */
    public static function forCompanyMatrix(): array
    {
        return [...self::all(), self::Validate];
    }

    /**
     * Validate existe só em Relatórios (validação de documentos de riscos psicossociais).
     *
     * @return list<PermissionModule>|null  nulo quando a ação vale para qualquer módulo
     */
    public function restrictedToModules(): ?array
    {
        return match ($this) {
            self::Validate => [PermissionModule::Relatorios],
            default => null,
        };
    }

    /**
     * @return array{value: string, label: string, only_modules: list<string>|null}
     */
    public function toMatrixOption(): array
    {
        $modules = $this->restrictedToModules();

        return [
            'value' => $this->value,
            'label' => $this->label(),
            'only_modules' => $modules === null ? null : array_map(fn (PermissionModule $m) => $m->value, $modules),
        ];
    }

    public function allowedFor(PermissionModule $module): bool
    {
        $modules = $this->restrictedToModules();

        return $modules === null || in_array($module, $modules, true);
    }
}
