<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PsychosocialMeasure;
use App\Services\PsychosocialMeasureService;
use App\Support\Nr1MeasureCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Limpa sugestões do motor duplicadas (mesma campanha + dimensão normalizada + engine_suggestion). Dry-run por padrão;
 * só --apply altera algo. Sempre exporta o conteúdo completo para storage/app/nr1-exports.
 *
 * Em cada grupo, mantém (nesta ordem): a que saiu de «A aprovar» > a editada por pessoa > a com mais campos
 * preenchidos > a mais antiga. As demais:
 * - sem evidência e só com eventos sem autor: removidas no --apply; a mantida recebe um evento «junção» (sem autor)
 *   com os IDs removidos e o resumo do que saiu. A ação removida só entra no texto da mantida se ela ainda estiver
 *   «A aprovar» e não editada; senão, fica só no evento;
 * - com evidência ou com evento com autor: «requer decisão manual» (nunca removidas).
 * Grupo com mais de uma medida fora de «A aprovar»: «requer decisão manual» por inteiro.
 * Nunca altera eventos existentes nem toca em action_plan_items.
 */
class DedupeEngineMeasuresCommand extends Command
{
    protected $signature = 'nr1:dedupe-engine-measures
        {--apply : Aplica a limpeza (sem esta opção, só lista e exporta)}
        {--survey= : Limita a uma campanha (id)}';

    protected $description = 'Lista (dry-run) ou limpa (--apply) sugestões do motor duplicadas por campanha e dimensão do plano de ação NR-1.';

    private const CONTENT_FIELDS = ['measure_description', 'objective', 'expected_result', 'indicator', 'responsible_text', 'suggested_due_date', 'suggested_due_origin', 'suggested_due_note', 'evidence_text'];

    public function handle(PsychosocialMeasureService $service): int
    {
        $apply = (bool) $this->option('apply');
        $groups = $this->duplicateGroups();
        $this->line($apply ? 'Modo: --apply (altera o banco).' : 'Modo: dry-run (nada é alterado). Use --apply para aplicar.');

        $report = [];
        $removed = 0;
        foreach ($groups as $measures) {
            $plan = $this->plan($measures, $service);
            $report[] = $plan['export'];
            $this->printPlan($plan);

            if ($apply && $plan['remove'] !== []) {
                DB::transaction(function () use ($plan, $service): void {
                    $service->recordMerge($plan['keeper'], $plan['summary'], $plan['merged_description']);
                    PsychosocialMeasure::query()->whereKey(array_map(fn (PsychosocialMeasure $m) => $m->id, $plan['remove']))->get()
                        ->each(fn (PsychosocialMeasure $m) => $m->delete());
                });
                $removed += count($plan['remove']);
            }
        }

        $path = $this->export($report, $apply);
        $this->newLine();
        $this->info(sprintf('Grupos duplicados: %d. %s: %d. Export: %s', count($groups), $apply ? 'Removidas' : 'Seriam removidas', $apply ? $removed : array_sum(array_map(fn ($g) => count($g['remove']), $report)), $path));

        return self::SUCCESS;
    }

    /** @return list<Collection<int, PsychosocialMeasure>> */
    private function duplicateGroups(): array
    {
        return PsychosocialMeasure::query()
            ->where('origin', PsychosocialMeasure::ORIGIN_ENGINE_SUGGESTION)
            ->when($this->option('survey'), fn ($q, $id) => $q->where('survey_id', (int) $id))
            ->orderBy('survey_id')->orderBy('id')
            ->get()
            ->groupBy(fn (PsychosocialMeasure $m) => $m->survey_id.'|'.Nr1MeasureCatalog::dimensionKey((string) $m->factor_label))
            ->filter(fn (Collection $g) => $g->count() > 1)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, PsychosocialMeasure>  $measures
     * @return array<string, mixed>
     */
    private function plan(Collection $measures, PsychosocialMeasureService $service): array
    {
        $info = $measures->map(fn (PsychosocialMeasure $m) => [
            'measure' => $m,
            'advanced' => $m->status !== PsychosocialMeasure::STATUS_TO_APPROVE,
            'edited' => $service->isEditedByTalents($m),
            'evidences' => $m->evidences()->count(),
            'filled' => collect(self::CONTENT_FIELDS)->filter(fn (string $f) => filled($m->getAttribute($f)))->count(),
        ]);
        $first = $measures->first();
        $base = ['survey_id' => $first->survey_id, 'company_id' => $first->company_id, 'dimension' => Nr1MeasureCatalog::v11Dimension((string) $first->factor_label) ?? $first->factor_label];

        if ($info->where('advanced', true)->count() > 1) {
            return [
                'keeper' => null, 'remove' => [], 'merged_description' => null, 'summary' => [],
                'export' => $base + [
                    'decision' => 'requer decisão manual',
                    'reason' => 'Mais de uma medida do grupo já saiu de «A aprovar».',
                    'keep' => null, 'remove' => [], 'manual' => $info->map(fn ($i) => $this->row($i['measure']))->values()->all(),
                ],
            ];
        }

        $ranked = $info->sort(fn ($a, $b) => [$b['advanced'], $b['edited'], $b['filled'], $a['measure']->id] <=> [$a['advanced'], $a['edited'], $a['filled'], $b['measure']->id])->values();
        $keeperInfo = $ranked->first();
        $keeper = $keeperInfo['measure'];
        $remove = [];
        $manual = [];
        foreach ($ranked->slice(1) as $i) {
            if ($i['evidences'] > 0 || $i['edited']) {
                $manual[] = $this->row($i['measure']) + ['reason' => $i['evidences'] > 0 ? 'Tem evidência registrada.' : 'Tem alteração feita por pessoa.'];
            } else {
                $remove[] = $i['measure'];
            }
        }

        $textEditable = ! $keeperInfo['advanced'] && ! $keeperInfo['edited'];
        $merged = null;
        if ($textEditable && $remove !== []) {
            $merged = array_reduce($remove, fn (string $text, PsychosocialMeasure $m) => Nr1MeasureCatalog::mergeDescriptions($text, (string) $m->measure_description), (string) $keeper->measure_description);
        }
        $summary = [
            'removed_ids' => array_map(fn (PsychosocialMeasure $m) => $m->id, $remove),
            'removed_actions' => array_map(fn (PsychosocialMeasure $m) => ['id' => $m->id, 'factor_label' => $m->factor_label, 'measure_description' => $m->measure_description], $remove),
            'text_merged_into_kept' => $merged !== null && $merged !== $keeper->measure_description,
        ];

        return [
            'keeper' => $keeper,
            'remove' => $remove,
            'merged_description' => $merged,
            'summary' => $summary,
            'export' => $base + [
                'decision' => $remove === [] ? 'requer decisão manual' : 'juntar e remover',
                'keep' => $this->row($keeper) + ['merged_description' => $merged, 'text_unchanged_reason' => $textEditable ? null : 'Mantida já confirmada, em andamento ou editada: a ação removida fica só no evento «junção».'],
                'remove' => array_map(fn (PsychosocialMeasure $m) => $this->row($m), $remove),
                'manual' => $manual,
            ],
        ];
    }

    /** @return array<string, mixed> Conteúdo completo da medida, com eventos e evidências (para export). */
    private function row(PsychosocialMeasure $measure): array
    {
        return $measure->attributesToArray() + [
            'events' => $measure->events()->orderBy('id')->get()->map->only(['id', 'user_id', 'field', 'old_value', 'new_value', 'reason', 'created_at'])->all(),
            'evidences' => $measure->evidences()->orderBy('id')->get()->map->only(['id', 'kind', 'description', 'file_name', 'created_at'])->all(),
        ];
    }

    /** @param  array<string, mixed>  $plan */
    private function printPlan(array $plan): void
    {
        $e = $plan['export'];
        $this->newLine();
        $this->line(sprintf('Campanha %d · empresa %d · %s → %s', $e['survey_id'], $e['company_id'], $e['dimension'], $e['decision']));
        if (isset($e['reason'])) {
            $this->line('  '.$e['reason']);
        }
        if ($e['keep']) {
            $this->line(sprintf('  Mantida #%d [%s]: %s', $e['keep']['id'], $e['keep']['status'], $e['keep']['measure_description']));
            if ($e['keep']['merged_description'] !== null && $e['keep']['merged_description'] !== $e['keep']['measure_description']) {
                $this->line('    Texto após a junção: '.$e['keep']['merged_description']);
            } elseif ($e['keep']['text_unchanged_reason'] && $e['remove'] !== []) {
                $this->line('    '.$e['keep']['text_unchanged_reason']);
            }
        }
        foreach ($e['remove'] as $row) {
            $this->line(sprintf('  Remover #%d [%s]: %s', $row['id'], $row['status'], $row['measure_description']));
        }
        foreach ($e['manual'] as $row) {
            $this->line(sprintf('  Requer decisão manual #%d [%s]%s: %s', $row['id'], $row['status'], isset($row['reason']) ? ' ('.$row['reason'].')' : '', $row['measure_description']));
        }
    }

    /** @param  list<array<string, mixed>>  $report */
    private function export(array $report, bool $apply): string
    {
        $dir = storage_path('app/nr1-exports');
        File::ensureDirectoryExists($dir);
        $path = $dir.'/dedupe-engine-measures-'.now()->format('Ymd-His').($apply ? '-apply' : '-dry-run').'.json';
        File::put($path, json_encode(['generated_at' => now()->toIso8601String(), 'apply' => $apply, 'groups' => $report], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
    }
}
