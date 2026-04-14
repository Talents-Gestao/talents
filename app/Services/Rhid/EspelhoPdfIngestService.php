<?php

namespace App\Services\Rhid;

use App\Models\RhidEspelhoDay;
use App\Models\RhidEspelhoImport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class EspelhoPdfIngestService
{
    /**
     * Executa o parser Python e persiste dias + JSON bruto no import.
     */
    public function parseAndPersist(RhidEspelhoImport $import): void
    {
        $import->parse_status = 'pending';
        $import->parse_error = null;
        $import->save();

        $disk = Storage::disk('local');
        if (! $disk->exists($import->storage_path)) {
            $this->fail($import, 'Arquivo PDF nao encontrado no storage.');

            return;
        }

        $fullPath = $disk->path($import->storage_path);
        $python = (string) config('rhid.espelho_python');
        $workdir = (string) config('rhid.espelho_parser_workdir');
        $timeout = (float) config('rhid.espelho_parse_timeout_seconds');

        $parserMain = $workdir.DIRECTORY_SEPARATOR.'rhid_espelho_parser'.DIRECTORY_SEPARATOR.'__main__.py';
        if ($workdir === '' || ! is_dir($workdir) || ! is_file($parserMain)) {
            $this->fail($import, 'Diretorio do parser Python invalido: '.$workdir);

            return;
        }

        $process = new Process(
            [
                $python,
                '-m',
                'rhid_espelho_parser',
                $fullPath,
                '--id-person',
                (string) $import->id_person,
                '--period-ini',
                $import->period_ini->format('Y-m-d'),
                '--period-fim',
                $import->period_fim->format('Y-m-d'),
            ],
            $workdir,
            null,
            null,
            $timeout,
        );
        $process->setIdleTimeout($timeout);

        try {
            $process->mustRun();
        } catch (ProcessFailedException $e) {
            $err = trim($process->getErrorOutput() ?: $process->getOutput() ?: $e->getMessage());
            $this->fail($import, 'Parser Python falhou: '.$err);

            return;
        }

        $out = trim($process->getOutput());
        if ($out === '') {
            $this->fail($import, 'Parser Python nao retornou JSON em stdout.');

            return;
        }

        $data = json_decode($out, true);
        if (! is_array($data)) {
            $this->fail($import, 'JSON invalido retornado pelo parser.');

            return;
        }

        $schema = $data['schema_version'] ?? null;
        if ($schema !== 1 && $schema !== '1') {
            $this->fail($import, 'schema_version do parser nao suportado.');

            return;
        }

        $days = $data['days'] ?? null;
        if (! is_array($days)) {
            $this->fail($import, 'Campo days ausente ou invalido no JSON do parser.');

            return;
        }

        DB::transaction(function () use ($import, $days, $out): void {
            RhidEspelhoDay::where('import_id', $import->id)->delete();

            foreach ($days as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $date = $row['date'] ?? $row['ref_date'] ?? null;
                if (! is_string($date) || $date === '') {
                    continue;
                }
                try {
                    $ref = Carbon::parse($date)->toDateString();
                } catch (\Throwable) {
                    continue;
                }
                RhidEspelhoDay::create([
                    'import_id' => $import->id,
                    'ref_date' => $ref,
                    'row_json' => $row,
                ]);
            }

            $import->parse_status = 'ok';
            $import->parse_error = null;
            $import->parsed_at = now();
            $import->raw_extract_json = $out;
            $import->save();
        });
    }

    private function fail(RhidEspelhoImport $import, string $message): void
    {
        $import->parse_status = 'failed';
        $import->parse_error = $message;
        $import->parsed_at = now();
        $import->save();
    }
}
