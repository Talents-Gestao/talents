<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PsychosocialMeasure;
use App\Services\PsychosocialMeasureDeduplicator;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

/**
 * Lista (dry-run, padrão) ou unifica (--apply) medidas duplicadas do plano de ação: mesma campanha + dimensão
 * normalizada, em qualquer origem (sugestão do motor e medida manual). Mesma regra da ação «Unificar sugestões
 * duplicadas» da tela (PsychosocialMeasureDeduplicator): só sai sugestão do motor intocada; manual, editada,
 * confirmada ou com evidência nunca sai e, quando não é a mantida, fica como «requer decisão manual».
 * Sempre exporta o conteúdo completo dos grupos (antes da junção) para storage/app/nr1-exports; o evento
 * «Junção de sugestões duplicadas» na mantida guarda só os IDs. Nunca toca em action_plan_items.
 */
class DedupeEngineMeasuresCommand extends Command
{
    protected $signature = 'nr1:dedupe-engine-measures
        {--apply : Aplica a unificação (sem esta opção, só lista e exporta)}
        {--survey= : Limita a uma campanha (id)}';

    protected $description = 'Lista (dry-run) ou unifica (--apply) medidas duplicadas por campanha e dimensão do plano de ação NR-1 (sugestões do motor e medidas manuais).';

    public function handle(PsychosocialMeasureDeduplicator $deduplicator): int
    {
        $apply = (bool) $this->option('apply');
        $groups = $deduplicator->groups(
            PsychosocialMeasure::query()->when($this->option('survey'), fn ($q, $id) => $q->where('survey_id', (int) $id))
        );
        $this->line($apply ? 'Modo: --apply (altera o banco).' : 'Modo: dry-run (nada é alterado). Use --apply para aplicar.');

        $report = [];
        $toRemove = 0;
        $removed = 0;
        foreach ($groups as $group) {
            $plan = $deduplicator->plan($group);
            $export = $this->exportRow($plan, $group);
            $report[] = $export;
            $toRemove += count($plan['remove']);
            $this->printPlan($export);

            if ($apply) {
                $removed += count($deduplicator->apply($plan));
            }
        }

        $path = $this->export($report, $apply);
        $this->newLine();
        $this->info(sprintf('Grupos duplicados: %d. %s: %d. Export: %s', count($groups), $apply ? 'Removidas' : 'Seriam removidas', $apply ? $removed : $toRemove, $path));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $plan
     * @param  Collection<int, PsychosocialMeasure>  $group
     * @return array<string, mixed>
     */
    private function exportRow(array $plan, Collection $group): array
    {
        $keeper = $plan['keeper'];
        $textUnchanged = $plan['merged_description'] === null && $plan['remove'] !== []
            ? 'Mantida manual, editada, confirmada ou em andamento: o texto dela não muda e a ação removida fica só neste export.'
            : null;

        return [
            'survey_id' => $keeper->survey_id,
            'company_id' => $keeper->company_id,
            'dimension' => $plan['dimension'],
            'decision' => $plan['decision'],
            'reason' => $plan['remove'] === [] ? 'Nenhuma medida do grupo pode ser removida (manual, editada, confirmada ou com evidência).' : null,
            'keep' => $this->row($keeper) + ['merged_description' => $plan['merged_description'], 'text_unchanged_reason' => $textUnchanged],
            'remove' => array_map(fn (PsychosocialMeasure $m) => $this->row($m), $plan['remove']),
            'manual' => array_map(fn (array $item) => $this->row($item['measure']) + ['reason' => $item['reason']], $plan['manual']),
            'group_ids' => $group->pluck('id')->all(),
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

    /** @param  array<string, mixed>  $e */
    private function printPlan(array $e): void
    {
        $this->newLine();
        $this->line(sprintf('Campanha %d · empresa %d · %s → %s', $e['survey_id'], $e['company_id'], $e['dimension'], $e['decision']));
        if ($e['reason'] !== null) {
            $this->line('  '.$e['reason']);
        }
        $this->line(sprintf('  Mantida #%d [%s · %s]: %s', $e['keep']['id'], $e['keep']['origin'], $e['keep']['status'], $e['keep']['measure_description']));
        if ($e['keep']['merged_description'] !== null && $e['keep']['merged_description'] !== $e['keep']['measure_description']) {
            $this->line('    Texto após a junção: '.$e['keep']['merged_description']);
        } elseif ($e['keep']['text_unchanged_reason'] !== null) {
            $this->line('    '.$e['keep']['text_unchanged_reason']);
        }
        foreach ($e['remove'] as $row) {
            $this->line(sprintf('  Remover #%d [%s · %s]: %s', $row['id'], $row['origin'], $row['status'], $row['measure_description']));
        }
        foreach ($e['manual'] as $row) {
            $this->line(sprintf('  Requer decisão manual #%d [%s · %s] (%s): %s', $row['id'], $row['origin'], $row['status'], $row['reason'], $row['measure_description']));
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
