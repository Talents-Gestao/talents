<?php

namespace App\Enums;

enum PermissionAction: string
{
    case View = 'view';
    case Create = 'create';
    case Edit = 'edit';
    case Delete = 'delete';

    public function label(): string
    {
        return match ($this) {
            self::View => 'Ver',
            self::Create => 'Criar',
            self::Edit => 'Editar',
            self::Delete => 'Apagar',
        };
    }

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return [self::View, self::Create, self::Edit, self::Delete];
    }
}
