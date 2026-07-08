<?php

namespace App\Console\Commands;

use App\Enums\StrategicCalendarItemKind;
use App\Models\Company;
use App\Models\StrategicCalendarItem;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Throwable;

class ImportStrategicCalendarCsvCommand extends Command
{
    protected $signature = 'calendar:import-csv
                            {file=database/imports/agenda_eventos_2026-05-11_203345.csv : Caminho do CSV (relativo à raiz do projeto ou absoluto)}
                            {--company-id= : ID da empresa (vazio = global)}
                            {--kind=event : event, ritual ou birthday}
                            {--dry-run : Apenas mostra o que seria importado}';

    protected $description = 'Importa linhas de CSV (export agenda) para strategic_calendar_items.';

    public function handle(): int
    {
        $relativeOrAbsolute = $this->argument('file');
        $path = is_file($relativeOrAbsolute) ? $relativeOrAbsolute : base_path($relativeOrAbsolute);

        if (! is_readable($path)) {
            $this->error("Arquivo não encontrado ou ilegível: {$path}");

            return self::FAILURE;
        }

        $kindOption = (string) $this->option('kind');
        $kind = StrategicCalendarItemKind::tryFrom($kindOption);
        if ($kind === null) {
            $this->error("Kind inválido: {$kindOption}. Use event, ritual ou birthday.");

            return self::FAILURE;
        }

        $companyIdRaw = $this->option('company-id');
        $companyId = ($companyIdRaw !== null && $companyIdRaw !== '') ? (int) $companyIdRaw : null;
        if ($companyId !== null && ! Company::query()->whereKey($companyId)->exists()) {
            $this->error("Empresa não encontrada: company_id={$companyId}");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $fh = fopen($path, 'rb');
        if ($fh === false) {
            $this->error('Não foi possível abrir o arquivo.');

            return self::FAILURE;
        }

        $header = fgetcsv($fh, 0, ';');
        if ($header === false) {
            fclose($fh);
            $this->error('CSV vazio ou inválido.');

            return self::FAILURE;
        }

        $created = 0;
        $existing = 0;
        $skipped = 0;
        $failed = 0;
        $lineNo = 1;
        /** @var array<string, true> Chaves já vistas no CSV (dry-run: evita DB) */
        $seenKeys = [];

        while (($row = fgetcsv($fh, 0, ';')) !== false) {
            $lineNo++;
            if ($this->rowIsEmpty($row)) {
                continue;
            }

            try {
                $title = isset($row[1]) ? trim((string) $row[1]) : '';
                $tipo = isset($row[2]) ? trim((string) $row[2]) : '';
                $dataRaw = isset($row[3]) ? trim((string) $row[3]) : '';
                $inicio = isset($row[4]) ? trim((string) $row[4]) : '';
                $fim = isset($row[5]) ? trim((string) $row[5]) : '';
                $empresa = isset($row[6]) ? trim((string) $row[6]) : '';
                $local = isset($row[7]) ? trim((string) $row[7]) : '';
                $observacoes = isset($row[8]) ? trim((string) $row[8]) : '';
                $participantes = isset($row[9]) ? trim((string) $row[9]) : '';

                if ($title === '' || $dataRaw === '') {
                    $this->warn("Linha {$lineNo}: título ou data vazios — ignorada.");
                    $skipped++;

                    continue;
                }

                $occursOn = Carbon::createFromFormat('d/m/Y', $dataRaw)->startOfDay();
                $description = $this->buildDescription($tipo, $inicio, $fim, $empresa, $local, $observacoes, $participantes);

                $attributes = [
                    'title' => $title,
                    'occurs_on' => $occursOn->toDateString(),
                    'kind' => $kind->value,
                    'company_id' => $companyId,
                ];

                if ($dryRun) {
                    $dedupeKey = json_encode($attributes, JSON_THROW_ON_ERROR);
                    if (isset($seenKeys[$dedupeKey])) {
                        $this->warn("[dry-run] duplicata no CSV (ignorada): {$occursOn->toDateString()} — {$title}");
                        $skipped++;

                        continue;
                    }
                    $seenKeys[$dedupeKey] = true;
                    $created++;
                    $this->line("[dry-run] importaria: {$occursOn->toDateString()} — {$title}");
                } else {
                    $item = StrategicCalendarItem::query()->firstOrCreate($attributes, [
                        'description' => $description,
                    ]);

                    if ($item->wasRecentlyCreated) {
                        $created++;
                        $this->info("Criado: {$occursOn->toDateString()} — {$title}");
                    } else {
                        $existing++;
                        $this->line("Já existia: {$occursOn->toDateString()} — {$title}");
                    }
                }
            } catch (Throwable $e) {
                $failed++;
                $this->error("Linha {$lineNo}: {$e->getMessage()}");
            }
        }

        fclose($fh);

        $this->newLine();
        $this->info($dryRun ? 'Resumo (dry-run):' : 'Resumo:');
        if ($dryRun) {
            $this->table(
                ['Métrica', 'Quantidade'],
                [
                    ['Linhas válidas (simulação, sem acesso ao banco)', (string) $created],
                    ['Ignoradas', (string) $skipped],
                    ['Falhas', (string) $failed],
                ],
            );
            $this->comment('Sem --dry-run: grava no banco e reporta itens já existentes (firstOrCreate).');
        } else {
            $this->table(
                ['Métrica', 'Quantidade'],
                [
                    ['Criados', (string) $created],
                    ['Já existentes', (string) $existing],
                    ['Ignoradas', (string) $skipped],
                    ['Falhas', (string) $failed],
                ],
            );
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array<int, string|null>  $row
     */
    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function buildDescription(
        string $tipo,
        string $inicio,
        string $fim,
        string $empresa,
        string $local,
        string $observacoes,
        string $participantes,
    ): string {
        $parts = [];

        if ($tipo !== '') {
            $parts[] = 'Tipo: '.$tipo;
        }
        if ($inicio !== '' && $fim !== '') {
            $parts[] = 'Horário: '.$inicio.' – '.$fim;
        } elseif ($inicio !== '' || $fim !== '') {
            $parts[] = 'Horário: '.trim($inicio.' '.$fim);
        }
        if ($empresa !== '') {
            $parts[] = 'Empresa: '.$empresa;
        }
        if ($local !== '') {
            $parts[] = 'Local: '.$local;
        }
        if ($observacoes !== '') {
            $parts[] = 'Observações: '.$observacoes;
        }
        if ($participantes !== '') {
            $participantesFmt = str_replace('|', ', ', $participantes);
            $parts[] = 'Participantes: '.$participantesFmt;
        }

        return implode("\n\n", $parts);
    }
}
