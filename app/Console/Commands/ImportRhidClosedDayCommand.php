<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Rhid\RhidEspelhoAutoImportService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use InvalidArgumentException;

class ImportRhidClosedDayCommand extends Command
{
    protected $signature = 'rhid:import-closed-day
                            {--company= : ID da empresa (omitir para todas com RHID pronto)}
                            {--date= : Dia fechado YYYY-MM-DD ou YYYYMMDD (padrão: ontem)}';

    protected $description = 'Enfileira a importação automática do espelho do dia já fechado (ontem, após 23h59).';

    public function handle(RhidEspelhoAutoImportService $imports): int
    {
        try {
            $closedDay = $this->resolveClosedDay();
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $companyOption = $this->option('company');
        $companyId = ($companyOption !== null && $companyOption !== '')
            ? (int) $companyOption
            : null;

        $this->info('Dia fechado: '.$closedDay->toDateString());

        $results = $imports->enqueueClosedDay($companyId, $closedDay);
        $queued = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($results as $row) {
            $label = $row['company_name'] !== ''
                ? $row['company_name'].' (#'.$row['company_id'].')'
                : 'geral';

            if ($row['action'] === 'queued') {
                $queued++;
                $period = ($row['ini'] ?? '').' → '.($row['fim'] ?? '');
                $this->line(sprintf(
                    '  enfileirado %s — %d colaborador(es), lote %s (%s)',
                    $label,
                    $row['people'],
                    (string) $row['batch_id'],
                    $period,
                ));
                continue;
            }

            if ($row['action'] === 'failed') {
                $failed++;
                $this->error('  falhou '.$label.': '.($row['reason'] ?? 'erro desconhecido'));
                continue;
            }

            $skipped++;
            $this->comment('  ignorada '.$label.': '.($row['reason'] ?? ''));
        }

        $this->info("Resumo: {$queued} lote(s), {$skipped} ignorada(s), {$failed} falha(s).");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function resolveClosedDay(): Carbon
    {
        $tz = (string) config('app.timezone', 'America/Sao_Paulo');
        $raw = trim((string) $this->option('date'));
        if ($raw === '') {
            return Carbon::now($tz)->subDay()->startOfDay();
        }

        $digits = preg_replace('/\D/', '', $raw) ?? '';
        if (strlen($digits) !== 8) {
            throw new InvalidArgumentException('Use --date=YYYY-MM-DD ou YYYYMMDD.');
        }

        $parsed = Carbon::createFromFormat('Ymd', $digits, $tz);
        if ($parsed === false) {
            throw new InvalidArgumentException('Data inválida em --date.');
        }

        return $parsed->startOfDay();
    }
}
