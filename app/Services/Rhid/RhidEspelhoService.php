<?php

namespace App\Services\Rhid;

use App\Exceptions\RhidApiException;
use App\Jobs\ProcessRhidEspelhoParseJob;
use App\Models\Company;
use App\Models\RhidEspelhoImport;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class RhidEspelhoService
{
    public function __construct(
        private RhidReportService $reports,
    ) {}

    /**
     * Baixa o PDF do RHID após o GUID estar pronto (100%) e grava no disco privado.
     * Dispara o parse Python em fila.
     *
     * @throws RhidApiException
     */
    public function storePdfFromGuid(
        Company $company,
        User $user,
        string $guid,
        int $idPerson,
        string $iniCompact,
        string $fimCompact,
    ): RhidEspelhoImport {
        $iniCompact = preg_replace('/\D/', '', $iniCompact) ?? '';
        $fimCompact = preg_replace('/\D/', '', $fimCompact) ?? '';
        if (strlen($iniCompact) !== 8 || strlen($fimCompact) !== 8) {
            throw new RhidApiException('Periodo ini/fim deve usar 8 digitos (yyyyMMdd).');
        }
        $iniDate = Carbon::createFromFormat('Ymd', $iniCompact)->startOfDay();
        $fimDate = Carbon::createFromFormat('Ymd', $fimCompact)->startOfDay();
        if ($fimDate->lt($iniDate)) {
            throw new RhidApiException('Data final anterior a inicial.');
        }
        if ($iniDate->diffInDays($fimDate) + 1 > 31) {
            throw new RhidApiException('Periodo maximo de 31 dias.');
        }

        $binary = $this->reports->downloadSaveFileBody($company, $user, 'PDF', $guid);
        if (str_starts_with($binary, '{')) {
            $unwrapped = $this->reports->unwrapSaveFilePayload($binary);
            if ($unwrapped !== $binary) {
                $binary = $unwrapped;
            }
        }
        if (substr($binary, 0, 4) !== '%PDF') {
            throw new RhidApiException('Resposta do RHID nao e um PDF valido. Tente novamente ou use outro formato no portal.');
        }

        $relativePath = sprintf(
            'rhid-espelhos/%d/%d_%s_%s_%s.pdf',
            $company->id,
            $idPerson,
            $iniCompact,
            $fimCompact,
            preg_replace('/[^\w\-]/', '', $guid),
        );

        Storage::disk('local')->put($relativePath, $binary);
        $hash = hash('sha256', $binary);

        $import = RhidEspelhoImport::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'id_person' => $idPerson,
            'period_ini' => $iniDate->toDateString(),
            'period_fim' => $fimDate->toDateString(),
            'guid' => $guid,
            'storage_path' => $relativePath,
            'file_hash' => $hash,
            'source' => 'api',
            'parse_status' => 'pending',
            'parse_error' => null,
            'parsed_at' => null,
            'raw_extract_json' => null,
        ]);

        ProcessRhidEspelhoParseJob::dispatch($import->id);

        return $import->fresh();
    }
}
