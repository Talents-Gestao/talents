<?php

namespace App\Jobs;

use App\Models\RhidEspelhoImport;
use App\Services\Rhid\EspelhoPdfIngestService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessRhidEspelhoParseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout;

    public function __construct(
        public int $importId,
    ) {
        $this->timeout = max(60, (int) config('rhid.espelho_parse_timeout_seconds', 120) + 30);
    }

    public function handle(EspelhoPdfIngestService $ingest): void
    {
        $import = RhidEspelhoImport::find($this->importId);
        if (! $import) {
            return;
        }

        $ingest->parseAndPersist($import);
    }

    public function failed(?\Throwable $e): void
    {
        $import = RhidEspelhoImport::query()->find($this->importId);
        if (! $import || $import->parse_status === 'ok') {
            return;
        }

        $import->parse_status = 'failed';
        $import->parse_error = $e
            ? 'Falha na fila de parse do espelho: '.$e->getMessage()
            : 'Falha na fila de parse do espelho. Confira se o worker e o Python estão ativos.';
        $import->parsed_at = now();
        $import->save();
    }
}
