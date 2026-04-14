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
}
