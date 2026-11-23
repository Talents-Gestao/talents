<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PsychosocialMeasure;
use App\Models\Survey;
use App\Services\PsychosocialMeasureService;
use App\Support\Nr1MeasureSuggestionFiller;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Preenche, com o catálogo V11 (Nr1MeasureCatalog), só os campos vazios das sugestões do motor de campanhas V11 ainda
 * «A aprovar»: indicador, objetivo, resultado esperado, responsável sugerido (área/função), origem e justificativa do
 * prazo (N = dias entre a criação e o prazo gravado). Nunca sobrescreve campo preenchido. Dry-run por padrão; só
 * --apply grava, com um evento sem autor por campo («Preenchimento automático do catálogo»). Exporta para
 * storage/app/nr1-exports. Modelo anterior (COPSOQ) e medidas manuais ficam de fora.
 */
class BackfillEngineMeasuresCommand extends Command
{
    protected $signature = 'nr1:backfill-engine-measures
        {--apply : Grava o preenchimento (sem esta opção, só lista e exporta)}
        {--survey= : Limita a uma campanha (id)}';

    protected $description = 'Lista (dry-run) ou preenche (--apply) campos vazios das sugestões do motor V11 «A aprovar» com o catálogo por dimensão.';

    public function handle(PsychosocialMeasureService $service): int
    {
        $apply = (bool) $this->option('apply');
        $this->line($apply ? 'Modo: --apply (altera o banco).' : 'Modo: dry-run (nada é alterado). Use --apply para aplicar.');

        $report = [];
        $surveys = Survey::query()
            ->whereIn('id', PsychosocialMeasure::query()
                ->where('origin', PsychosocialMeasure::ORIGIN_ENGINE_SUGGESTION)
                ->where('status', PsychosocialMeasure::STATUS_TO_APPROVE)
                ->select('survey_id'))
            ->when($this->option('survey'), fn ($q, $id) => $q->whereKey((int) $id))
            ->orderBy('id')
            ->get()
            ->filter(fn (Survey $survey) => Nr1MeasureSuggestionFiller::appliesTo($survey));

        foreach ($surveys as $survey) {
            $bands = Nr1MeasureSuggestionFiller::bands($survey);
            $measures = PsychosocialMeasure::query()
                ->where('survey_id', $survey->id)
                ->where('origin', PsychosocialMeasure::ORIGIN_ENGINE_SUGGESTION)
                ->where('status', PsychosocialMeasure::STATUS_TO_APPROVE)
                ->orderBy('id')
                ->get();

            foreach ($measures as $measure) {
                $fields = Nr1MeasureSuggestionFiller::onlyEmpty($measure, Nr1MeasureSuggestionFiller::catalogFields(
                    (string) $measure->factor_label,
                    $bands,
                    Nr1MeasureSuggestionFiller::daysToDue($measure),
                ));
                if ($fields === []) {
                    continue;
                }

                $this->line(sprintf('Campanha %d · empresa %d · medida #%d · %s', $survey->id, $survey->company_id, $measure->id, $measure->factor_label));
                foreach ($fields as $field => $value) {
                    $this->line("  {$field}: {$value}");
                }
                $report[] = ['survey_id' => $survey->id, 'company_id' => $survey->company_id, 'measure_id' => $measure->id, 'factor_label' => $measure->factor_label, 'fill' => $fields];

                if ($apply) {
                    $service->fillFromCatalog($measure, $fields);
                }
            }
        }

        $dir = storage_path('app/nr1-exports');
        File::ensureDirectoryExists($dir);
        $path = $dir.'/backfill-engine-measures-'.now()->format('Ymd-His').($apply ? '-apply' : '-dry-run').'.json';
        File::put($path, json_encode(['generated_at' => now()->toIso8601String(), 'apply' => $apply, 'measures' => $report], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->newLine();
        $this->info(sprintf('Medidas %s: %d. Export: %s', $apply ? 'preenchidas' : 'a preencher', count($report), str_replace(base_path().DIRECTORY_SEPARATOR, '', $path)));

        return self::SUCCESS;
    }
}
