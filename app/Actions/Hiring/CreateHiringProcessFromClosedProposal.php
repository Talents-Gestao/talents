<?php

declare(strict_types=1);

namespace App\Actions\Hiring;

use App\Actions\Notices\PublishCommercialNotice;
use App\Enums\HiringProcessStage;
use App\Models\CommercialProposal;
use App\Models\Company;
use App\Models\HiringProcess;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Ao fechar/aprovar uma proposta, abre Acompanhamento em Engenharia de Cargo.
 *
 * Empresa: resolve por CNPJ da proposta → companies (dígitos). Sem match: não cria
 * (evita processo órfão); devolve aviso para flash + log.
 *
 * Reabertura da proposta: não apaga o HiringProcess (sem regra clara de sincronização).
 */
final class CreateHiringProcessFromClosedProposal
{
    public const STATUS_CREATED = 'created';

    public const STATUS_ALREADY_EXISTS = 'already_exists';

    public const STATUS_COMPANY_MISSING = 'company_missing';

    public const STATUS_SKIPPED = 'skipped';

    public function __construct(
        private readonly PublishCommercialNotice $notices,
    ) {}

    /**
     * @return array{
     *     status: string,
     *     process: HiringProcess|null,
     *     flash: string|null
     * }
     */
    public function handle(CommercialProposal $proposal, ?User $actor = null): array
    {
        if (! $proposal->is_closed) {
            return [
                'status' => self::STATUS_SKIPPED,
                'process' => null,
                'flash' => null,
            ];
        }

        $existing = HiringProcess::query()
            ->where('commercial_proposal_id', $proposal->id)
            ->first();

        if ($existing !== null) {
            return [
                'status' => self::STATUS_ALREADY_EXISTS,
                'process' => $existing,
                'flash' => null,
            ];
        }

        $company = $this->resolveCompanyByCnpj($proposal->client_cnpj);
        if ($company === null) {
            $message = 'Proposta fechada, mas falta empresa cadastrada com este CNPJ para abrir o acompanhamento.';

            Log::warning('hiring_process.from_closed_proposal.company_missing', [
                'proposal_id' => $proposal->id,
                'proposal_code' => $proposal->code,
                'client_cnpj' => $proposal->client_cnpj,
            ]);

            return [
                'status' => self::STATUS_COMPANY_MISSING,
                'process' => null,
                'flash' => $message,
            ];
        }

        $process = DB::transaction(function () use ($proposal, $company, $actor) {
            // Corrida: unique na FK garante no máximo um processo por proposta.
            $existing = HiringProcess::query()
                ->where('commercial_proposal_id', $proposal->id)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $stage = HiringProcessStage::EngenhariaCargo;
            $nextOrder = (int) HiringProcess::query()
                ->where('current_stage', $stage->value)
                ->max('sort_order');

            return HiringProcess::query()->create([
                'company_id' => $company->id,
                'commercial_proposal_id' => $proposal->id,
                'title' => $this->titleFor($proposal),
                'current_stage' => $stage,
                'sort_order' => $nextOrder + 1,
                'updated_by' => $actor?->id,
            ]);
        });

        if ($process->wasRecentlyCreated) {
            $this->notices->hiringFollowUpFromProposal($proposal, $process, $actor);
        }

        return [
            'status' => $process->wasRecentlyCreated ? self::STATUS_CREATED : self::STATUS_ALREADY_EXISTS,
            'process' => $process,
            'flash' => null,
        ];
    }

    public function titleFor(CommercialProposal $proposal): string
    {
        $client = trim((string) ($proposal->client_name ?? ''));
        $code = trim((string) ($proposal->code ?? ''));

        if ($client !== '' && $code !== '') {
            return "Contratação — {$client} ({$code})";
        }

        if ($client !== '') {
            return "Contratação — {$client}";
        }

        return $code !== '' ? "Contratação — {$code}" : 'Contratação — Proposta fechada';
    }

    private function resolveCompanyByCnpj(mixed $cnpj): ?Company
    {
        $digits = preg_replace('/\D+/', '', (string) ($cnpj ?? '')) ?? '';
        if ($digits === '' || strlen($digits) < 11) {
            return null;
        }

        $driver = Company::query()->getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            return Company::query()
                ->whereRaw("regexp_replace(coalesce(cnpj, ''), '\\D', '', 'g') = ?", [$digits])
                ->first();
        }

        // sqlite (testes) / mysql: compara variantes comuns + fallback por dígitos.
        $formatted = $this->formatCnpj($digits);
        $candidates = Company::query()
            ->whereNotNull('cnpj')
            ->where(function ($q) use ($cnpj, $digits, $formatted) {
                $q->where('cnpj', (string) $cnpj)
                    ->orWhere('cnpj', $digits)
                    ->orWhere('cnpj', $formatted);
            })
            ->get(['id', 'cnpj', 'name']);

        $match = $candidates->first(
            fn (Company $c) => (preg_replace('/\D+/', '', (string) $c->cnpj) ?? '') === $digits,
        );

        if ($match !== null) {
            return $match;
        }

        return Company::query()
            ->whereNotNull('cnpj')
            ->get(['id', 'cnpj', 'name'])
            ->first(fn (Company $c) => (preg_replace('/\D+/', '', (string) $c->cnpj) ?? '') === $digits);
    }

    private function formatCnpj(string $digits): string
    {
        if (strlen($digits) !== 14) {
            return $digits;
        }

        return substr($digits, 0, 2).'.'
            .substr($digits, 2, 3).'.'
            .substr($digits, 5, 3).'/'
            .substr($digits, 8, 4).'-'
            .substr($digits, 12, 2);
    }
}
