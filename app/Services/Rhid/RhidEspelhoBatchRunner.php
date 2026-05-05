<?php

namespace App\Services\Rhid;

use App\Exceptions\RhidApiException;
use App\Models\Company;
use App\Models\RhidEspelhoBatch;
use App\Models\User;

class RhidEspelhoBatchRunner
{
    public function __construct(
        private RhidReportService $reports,
        private RhidEspelhoService $espelho,
    ) {}

    public function execute(RhidEspelhoBatch $batch): void
    {
        $meta = $batch->meta_json;
        if (! is_array($meta)) {
            $this->failBatch($batch, 'Metadados do lote invalidos.');

            return;
        }
        $personIds = $meta['person_ids'] ?? null;
        if (! is_array($personIds) || $personIds === []) {
            $this->failBatch($batch, 'Lista de colaboradores vazia.');

            return;
        }

        $company = Company::query()->find($batch->company_id);
        if (! $company) {
            $this->failBatch($batch, 'Empresa não encontrada.');

            return;
        }
        if (! $batch->user_id) {
            $this->failBatch($batch, 'Usuário do lote não informado.');

            return;
        }
        $user = User::query()->findOrFail($batch->user_id);

        $batch->update([
            'status' => 'running',
            'message' => null,
        ]);

        $skippedIds = $batch->skipped_person_ids ?? [];

        foreach ($personIds as $idx => $idPerson) {
            $idPerson = (int) $idPerson;
            if ($idPerson < 1) {
                continue;
            }

            $batch->update([
                'current_id_person' => $idPerson,
            ]);

            try {
                $body = $this->buildPontoEspelhoBody($meta, $idPerson);
                $start = $this->reports->startPontoReport($company, $user, $body);
                $guid = $start['guid'] ?? '';
                if ($guid === '') {
                    throw new RhidApiException('Resposta sem GUID ao iniciar relatorio RHID.');
                }
                $this->waitUntilReportReady($company, $user, $guid);
                $this->espelho->storePdfFromGuid(
                    $company,
                    $user,
                    $guid,
                    $idPerson,
                    (string) $meta['ini'],
                    (string) $meta['fim'],
                );
                $batch->increment('succeeded');
            } catch (RhidApiException $e) {
                if ($this->shouldSkipPerson($e->getMessage())) {
                    $skippedIds[] = $idPerson;
                    $batch->update([
                        'skipped' => count($skippedIds),
                        'skipped_person_ids' => $skippedIds,
                    ]);
                } else {
                    $batch->update([
                        'status' => 'failed',
                        'message' => 'Colaborador '.$idPerson.': '.$e->getMessage(),
                        'processed' => $idx + 1,
                        'current_id_person' => null,
                    ]);

                    return;
                }
            } catch (\Throwable $e) {
                $batch->update([
                    'status' => 'failed',
                    'message' => 'Colaborador '.$idPerson.': '.$e->getMessage(),
                    'processed' => $idx + 1,
                    'current_id_person' => null,
                ]);
                report($e);

                return;
            }

            $batch->update([
                'processed' => $idx + 1,
                'current_id_person' => null,
            ]);

            if ($idx < count($personIds) - 1) {
                usleep(800_000);
            }
        }

        $batch->update([
            'status' => 'completed',
            'current_id_person' => null,
            'message' => $this->summaryMessage($batch->fresh()),
        ]);
    }

    private function failBatch(RhidEspelhoBatch $batch, string $message): void
    {
        $batch->update([
            'status' => 'failed',
            'message' => $message,
            'current_id_person' => null,
        ]);
    }

    private function summaryMessage(RhidEspelhoBatch $batch): string
    {
        $s = $batch->succeeded;
        $k = $batch->skipped;

        return "Importados {$s} PDF(s)".($k > 0 ? "; ignorados {$k} sem espelho válido." : '.');
    }

    private function shouldSkipPerson(string $message): bool
    {
        $m = strtolower($message);

        return str_contains($m, 'arquivo vazio')
            || str_contains($m, 'save_file')
            || str_contains($m, 'nao e um pdf valido')
            || str_contains($m, 'não é um pdf válido');
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function buildPontoEspelhoBody(array $meta, int $singlePersonId): array
    {
        $fields = $meta['list_columns'] ?? ['TODAS_MARCACOES', 'ENTRADAS_SAIDAS'];
        if (! is_array($fields) || $fields === []) {
            $fields = ['TODAS_MARCACOES', 'ENTRADAS_SAIDAS'];
        }
        $filters = is_array($meta['filters'] ?? null) ? $meta['filters'] : [];

        $pdfCartaoPontoParameters = [
            'fontSizeTitle' => 12,
            'fontSizeData' => 8,
            'fontSizeHeader' => 8,
            'fontSizeHeaderSmall' => 8,
            'fontSizeFooter' => 8,
            'fontName' => 'Helvetica',
            'listIdStr' => [$singlePersonId],
            'listCompanyStr' => $filters['list_company_str'] ?? [],
            'listDepartmentStr' => $filters['list_department_str'] ?? [],
            'listCostCenterStr' => $filters['list_cost_center_str'] ?? [],
            'listPersonRoleStr' => $filters['list_person_role_str'] ?? [],
            'listShiftStr' => $filters['list_shift_str'] ?? [],
        ];

        $body = [
            'formatoSaida' => 'PDF',
            'ini' => (string) $meta['ini'],
            'fim' => (string) $meta['fim'],
            'relatorio' => 'espelho',
            'destinoRelatorio' => 'DOWNLOAD',
            'ordenacao' => 'Person',
            'listColumns' => $fields,
            'listPropertyStr' => $fields,
            'pdfCartaoPontoParameters' => $pdfCartaoPontoParameters,
        ];

        $rs = $meta['rhid_status'] ?? null;
        if ($rs === '1' || $rs === '2') {
            $body['status'] = $rs;
        }

        return $body;
    }

    private function waitUntilReportReady(Company $company, User $user, string $guid): void
    {
        for ($i = 0; $i < 120; $i++) {
            $json = $this->reports->guidStatus($company, $user, $guid);
            $p = $json['percent'] ?? $json['Percent'] ?? null;
            $pNum = is_numeric($p) ? (float) $p : 0.0;
            if ($pNum >= 100) {
                usleep(2_000_000);

                return;
            }
            $err = $json['error'] ?? $json['Error'] ?? null;
            if (is_string($err) && $err !== '') {
                throw new RhidApiException($err);
            }
            usleep(1_500_000);
        }

        throw new RhidApiException('Tempo esgotado aguardando o relatorio no RHID (GUID).');
    }
}
