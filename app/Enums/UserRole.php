<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case CompanyAdmin = 'company_admin';
    case CompanyUser = 'company_user';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Administrador Talents',
            self::CompanyAdmin => 'Administrador da empresa',
            self::CompanyUser => 'Usuário da empresa',
        };
    }
}
