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
        $workdir = (string) config('rhid.espelho_parser_workdir');
        $timeout = (float) config('rhid.espelho_parse_timeout_seconds');

        $parserMain = $workdir.DIRECTORY_SEPARATOR.'rhid_espelho_parser'.DIRECTORY_SEPARATOR.'__main__.py';
        if ($workdir === '' || ! is_dir($workdir) || ! is_file($parserMain)) {
            $this->fail($import, 'Diretorio do parser Python invalido: '.$workdir);

            return;
        }

        $command = [
            '__PYTHON__',
            '-m',
            'rhid_espelho_parser',
            $fullPath,
            '--id-person',
            (string) $import->id_person,
            '--period-ini',
            $import->period_ini->format('Y-m-d'),
            '--period-fim',
            $import->period_fim->format('Y-m-d'),
        ];

        $lastErr = '';
        $out = '';
        foreach ($this->pythonBinariesToTry() as $python) {
            $argv = $command;
            $argv[0] = $python;
            $process = new Process($argv, $workdir, null, null, $timeout);
            $process->setIdleTimeout($timeout);
            try {
                $process->mustRun();
                $out = trim($process->getOutput());
                $lastErr = '';
                break;
            } catch (ProcessFailedException $e) {
                $lastErr = trim($process->getErrorOutput() ?: $process->getOutput() ?: $e->getMessage());
                if (! $this->isPythonNotFoundFailure($process, $lastErr)) {
                    $this->fail($import, 'Parser Python falhou: '.$lastErr);

                    return;
                }
            }
        }
        if ($lastErr !== '' && $out === '') {
            $this->fail(
                $import,
                'Python nao encontrado. Instale python3 e pymupdf no servidor ou defina RHID_ESPELHO_PYTHON (ex.: /usr/bin/python3). Detalhe: '.$lastErr,
            );

            return;
        }

        $out = trim($out);
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

    /**
     * @return list<string>
     */
    private function pythonBinariesToTry(): array
    {
        $primary = trim((string) config('rhid.espelho_python'));
        $fallbacks = PHP_OS_FAMILY === 'Windows'
            ? ['python', 'python3', 'py']
            : [
                '/usr/bin/python3',
                '/usr/local/bin/python3',
                'python3',
                '/usr/bin/python3.12',
                '/usr/bin/python3.11',
                'python',
            ];
        $merged = $primary !== '' ? array_merge([$primary], $fallbacks) : $fallbacks;

        return array_values(array_unique(array_filter($merged)));
    }

    private function isPythonNotFoundFailure(Process $process, string $stderrOrMsg): bool
    {
        if ($process->getExitCode() === 127) {
            return true;
        }
        $m = strtolower($stderrOrMsg);

        return str_contains($m, 'not found')
            || str_contains($m, 'no such file or directory')
            || str_contains($m, 'cannot find')
            || str_contains($m, 'exec: line 0:')
            || (bool) preg_match('/\bpython\\d*:?\\s*not found/i', $stderrOrMsg);
    }

    private function fail(RhidEspelhoImport $import, string $message): void
    {
        $import->parse_status = 'failed';
        $import->parse_error = $message;
        $import->parsed_at = now();
        $import->save();
    }
}
