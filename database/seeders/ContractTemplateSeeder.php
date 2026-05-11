<?php

namespace Database\Seeders;

use App\Models\CommercialContractTemplate;
use App\Support\CanonicalContractTemplates;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

/**
 * Modelos de contrato com HTML canónico (sem listagens fixas do Word).
 * Os .docx em database/seed_data/contract_templates são apenas arquivo de referência.
 */
class ContractTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach (CanonicalContractTemplates::all() as $name => $html) {
            try {
                CommercialContractTemplate::query()->updateOrCreate(
                    ['name' => $name],
                    [
                        'source_type' => 'html',
                        'body_html' => $html,
                        'docx_path' => null,
                        'is_active' => true,
                    ],
                );

                Log::info('[ContractTemplateSeeder] Modelo canónico aplicado.', ['name' => $name]);
            } catch (\Throwable $e) {
                Log::error('[ContractTemplateSeeder] Falha.', [
                    'name' => $name,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
