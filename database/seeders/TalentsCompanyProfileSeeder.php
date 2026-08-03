<?php

namespace Database\Seeders;

use App\Models\CommercialSetting;
use Illuminate\Database\Seeder;

/**
 * Preenche apenas campos vazios em commercial_settings com os dados institucionais da Talents (CONTRATADA).
 * Idempotente: não sobrescreve valores já configurados.
 */
class TalentsCompanyProfileSeeder extends Seeder
{
    public function run(): void
    {
        $settings = CommercialSetting::current();

        $defaults = [
            'company_name' => 'TALENTS PASQUALINO LTDA',
            'company_cnpj' => '59.676.832/0001-75',
            'company_address' => 'Av. Fernão Dias Paes Leme, 1300 – Centro – Várzea Paulista – SP, CEP 13.220-001',
            'company_city_state' => 'Várzea Paulista – SP',
            'company_email' => 'contato@talentsgestao.com',
            'company_phone' => '(11) 97570-3032',
            'company_representative_line' => 'neste ato representada por Suzane G. Pasqualino, CPF 377.425.058-86',
            'company_forum_city_state' => 'Várzea Paulista – SP',
            'company_contract_signatory_name' => 'Suzane G. Pasqualino',
            'company_contract_signatory_cpf' => '377.425.058-86',
        ];

        foreach ($defaults as $key => $value) {
            $current = $settings->{$key} ?? null;
            if ($current === null || $current === '') {
                $settings->{$key} = $value;
            }
        }

        // Migração pontual do e-mail institucional antigo.
        if (($settings->company_email ?? '') === 'talents@pasqualino.com.br') {
            $settings->company_email = 'contato@talentsgestao.com';
        }

        $settings->save();
    }
}
