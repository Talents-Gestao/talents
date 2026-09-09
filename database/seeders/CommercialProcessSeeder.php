<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CommercialProcess;
use App\Services\Commercial\DocxToHtmlService;
use App\Support\HtmlSanitizer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class CommercialProcessSeeder extends Seeder
{
    public function run(): void
    {
        $source = database_path('seeders/files/processo-comercial-talents.docx');
        if (! is_readable($source)) {
            $this->command?->warn('DOCX de referência não encontrado; seeder de processos ignorado.');

            return;
        }

        $title = 'Processo Comercial — Contratação de Talentos';
        $existing = CommercialProcess::query()->where('title', $title)->first();
        if ($existing !== null) {
            $this->command?->info('Processo comercial de referência já existe (id '.$existing->id.').');

            return;
        }

        $html = HtmlSanitizer::sanitizeRichText(
            DocxToHtmlService::extractFromAbsolutePath($source),
        );

        $process = CommercialProcess::query()->create([
            'title' => $title,
            'summary' => 'Processo padronizado de contratação de talentos: da abordagem comercial ao pós-venda e melhoria contínua.',
            'body_html' => $html,
            'sort_order' => 1,
            'is_published' => true,
        ]);

        $dir = 'commercial-processes/'.$process->id;
        Storage::disk('local')->makeDirectory($dir);
        $relative = $dir.'/processo-comercial-talents.docx';
        File::copy($source, Storage::disk('local')->path($relative));

        $process->forceFill([
            'file_path' => $relative,
            'file_name' => 'PROCESSO COMERCIAL - TALENTS.docx',
        ])->save();

        $this->command?->info('Processo comercial de referência importado (id '.$process->id.').');
    }
}
