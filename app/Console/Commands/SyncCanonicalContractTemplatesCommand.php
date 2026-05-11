<?php

namespace App\Console\Commands;

use App\Models\CommercialContractTemplate;
use App\Support\CanonicalContractTemplates;
use Illuminate\Console\Command;

class SyncCanonicalContractTemplatesCommand extends Command
{
    protected $signature = 'commercial:sync-canonical-contract-templates
                            {--dry-run : Apenas lista os modelos que seriam atualizados}';

    protected $description = 'Substitui o HTML dos três modelos padrão Talents pelo layout canónico (sem tabelas fixas do Word), alinhado à proposta comercial.';

    public function handle(): int
    {
        $templates = CanonicalContractTemplates::all();

        if ($this->option('dry-run')) {
            foreach ($templates as $name => $_html) {
                $this->line($name);
            }
            $this->info('Dry-run: '.count($templates).' modelo(s).');

            return self::SUCCESS;
        }

        foreach ($templates as $name => $html) {
            CommercialContractTemplate::query()->updateOrCreate(
                ['name' => $name],
                [
                    'source_type' => 'html',
                    'body_html' => $html,
                    'docx_path' => null,
                    'is_active' => true,
                ],
            );
            $this->info("Atualizado: {$name}");
        }

        $this->info('Modelos canónicos aplicados com sucesso.');

        return self::SUCCESS;
    }
}
