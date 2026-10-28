<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Garante que os vendedores oficiais do escritório (Karen e Luciana,
 * conforme a planilha TALENTS - COMERCIAL.xlsx) existam como SuperAdmin
 * com a flag `is_commercial = true`. Roda de forma idempotente.
 */
class CommercialSellersSeeder extends Seeder
{
    public function run(): void
    {
        $sellers = [
            ['name' => 'Karen', 'email' => 'karen@talents.local'],
            ['name' => 'Luciana', 'email' => 'luciana@talents.local'],
        ];

        foreach ($sellers as $data) {
            User::query()->updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make('password'),
                    'role' => UserRole::SuperAdmin,
                    'company_id' => null,
                    'is_active' => true,
                    'is_commercial' => true,
                    'commission_percent' => $data['email'] === 'karen@talents.local' ? 10 : 8,
                ],
            );
        }
    }
}
