<?php

declare(strict_types=1);

namespace App\Services\Commercial;

use App\Models\CommercialCommission;
use App\Models\CommercialProposal;
use App\Models\CommercialSale;
use App\Models\CommercialSaleInstallment;
use App\Models\User;
use App\Support\Commercial\OptionalCommission;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProposalSaleConversionService
{
    private const SINGLE_METHODS = ['pix', 'boleto', 'cartao'];

    /**
     * @param  array{
     *     payment_method: string,
     *     installments_count?: int|null,
     *     first_due_date: string,
     *     notes?: string|null,
     *     mix_parts?: list<array{method: string, percent: float|int|string}>|null
     * }  $data
     *
     * Comissão: usa o snapshot já gravado na proposta (percentual fixo do vendedor).
     */
    public function convert(CommercialProposal $proposal, array $data, ?int $createdBy = null): CommercialSale
    {
        return DB::transaction(function () use ($proposal, $data, $createdBy) {
            $locked = CommercialProposal::query()
                ->whereKey($proposal->id)
                ->lockForUpdate()
                ->first();

            if (! $locked) {
                throw ValidationException::withMessages([
                    'proposal' => 'Proposta não encontrada.',
                ]);
            }

            if (! $locked->is_closed) {
                throw ValidationException::withMessages([
                    'proposal' => 'A proposta precisa estar aprovada antes de converter em venda.',
                ]);
            }

            if ($locked->sale()->exists()) {
                throw ValidationException::withMessages([
                    'proposal' => 'Esta proposta já possui uma venda vinculada.',
                ]);
            }

            $paymentMethod = (string) $data['payment_method'];
            $totalCents = (int) $locked->total_final_cents;
            $isRecurring = $locked->isRecurringService();

            if ($isRecurring) {
                if ($paymentMethod === 'misto') {
                    throw ValidationException::withMessages([
                        'payment_method' => 'Propostas recorrentes não aceitam pagamento misto. Escolha PIX, boleto ou cartão.',
                    ]);
                }

                $monthlyCents = (int) $locked->recurring_monthly_cents;
                $months = (int) $locked->recurring_months;
                $totalCents = $months * $monthlyCents;
                $installmentPlan = $this->planFromRecurringMonths($months, $paymentMethod, $monthlyCents);
            } elseif ($paymentMethod === 'misto') {
                $installmentPlan = $this->planFromMixParts($data['mix_parts'] ?? [], $totalCents);
            } else {
                $installmentPlan = $this->planFromEqualInstallments(
                    (int) ($data['installments_count'] ?? 1),
                    $paymentMethod,
                    $totalCents,
                );
            }

            if ($totalCents < 1) {
                throw ValidationException::withMessages([
                    'proposal' => 'Não é possível converter uma proposta sem valor em venda.',
                ]);
            }

            $installmentsCount = count($installmentPlan);
            $commission = OptionalCommission::forConversion(
                (float) $locked->commission_percent,
                (int) $locked->commission_cents,
                $totalCents,
                $isRecurring,
            );

            $sale = CommercialSale::create([
                'code' => CommercialSale::nextCode(),
                'proposal_id' => $locked->id,
                'client_name' => $locked->client_name,
                'client_cnpj' => $locked->client_cnpj,
                'client_email' => $locked->client_email,
                'client_phone' => $locked->client_phone,
                'seller_id' => $locked->seller_id,
                'total_cents' => $totalCents,
                'commission_percent' => $commission['percent'],
                'commission_cents' => $commission['cents'],
                'payment_method' => $paymentMethod,
                'installments_count' => $installmentsCount,
                'is_recurring' => $isRecurring,
                'recurring_months' => $isRecurring ? (int) $locked->recurring_months : null,
                'recurring_monthly_cents' => $isRecurring ? (int) $locked->recurring_monthly_cents : null,
                'status' => CommercialSale::STATUS_ABERTA,
                'sold_at' => now(),
                'created_by' => $createdBy,
                'notes' => $data['notes'] ?? null,
            ]);

            $dueDate = \Carbon\Carbon::parse($data['first_due_date'])->startOfDay();

            foreach ($installmentPlan as $index => $part) {
                CommercialSaleInstallment::create([
                    'sale_id' => $sale->id,
                    'number' => $index + 1,
                    'amount_cents' => $part['amount_cents'],
                    'due_date' => $dueDate->copy()->addMonths($index),
                    'method' => $part['method'],
                    'status' => CommercialSaleInstallment::STATUS_PENDENTE,
                ]);
            }

            $commissionCents = (int) $sale->commission_cents;
            if ($commissionCents > 0) {
                CommercialCommission::create([
                    'sale_id' => $sale->id,
                    'seller_id' => $locked->seller_id,
                    'base_cents' => $totalCents,
                    'percent' => (float) $sale->commission_percent,
                    'amount_cents' => $commissionCents,
                    'status' => CommercialCommission::STATUS_A_PAGAR,
                ]);
            }

            return $sale->load(['installments', 'commission', 'seller:id,name']);
        });
    }

    /**
     * Cria venda avulsa (sem proposta), com parcelas iguais ou misto.
     *
     * @param  array{
     *     client_name: string,
     *     client_cnpj?: string|null,
     *     client_email?: string|null,
     *     client_phone?: string|null,
     *     seller_id?: int|null,
     *     total_cents: int,
     *     commission_percent?: float|int,
     *     payment_method: string,
     *     installments_count?: int|null,
     *     first_due_date: string,
     *     notes?: string|null,
     *     mix_parts?: list<array{method: string, percent: float|int|string}>|null
     * }  $data
     */
    public function createManual(array $data, ?int $createdBy = null): CommercialSale
    {
        $paymentMethod = (string) $data['payment_method'];
        $totalCents = (int) $data['total_cents'];
        if ($totalCents < 1) {
            throw ValidationException::withMessages([
                'total_reais' => 'Informe um valor total maior que zero.',
            ]);
        }

        if ($paymentMethod === 'misto') {
            $installmentPlan = $this->planFromMixParts($data['mix_parts'] ?? [], $totalCents);
        } else {
            $installmentPlan = $this->planFromEqualInstallments(
                (int) ($data['installments_count'] ?? 1),
                $paymentMethod,
                $totalCents,
            );
        }

        $installmentsCount = count($installmentPlan);
        $sellerId = isset($data['seller_id']) ? (int) $data['seller_id'] : null;
        $seller = $sellerId ? User::query()->find($sellerId) : null;
        $commissionPercent = OptionalCommission::resolveForProposal($seller);
        $commissionCents = OptionalCommission::centsFromPercent($totalCents, $commissionPercent);

        return DB::transaction(function () use ($data, $createdBy, $paymentMethod, $installmentsCount, $totalCents, $installmentPlan, $commissionPercent, $commissionCents, $sellerId) {
            $sale = CommercialSale::create([
                'code' => CommercialSale::nextCode(),
                'proposal_id' => null,
                'client_name' => trim((string) $data['client_name']),
                'client_cnpj' => filled($data['client_cnpj'] ?? null) ? trim((string) $data['client_cnpj']) : null,
                'client_email' => filled($data['client_email'] ?? null) ? trim((string) $data['client_email']) : null,
                'client_phone' => filled($data['client_phone'] ?? null) ? trim((string) $data['client_phone']) : null,
                'seller_id' => $sellerId ?: null,
                'total_cents' => $totalCents,
                'commission_percent' => $commissionPercent,
                'commission_cents' => $commissionCents,
                'payment_method' => $paymentMethod,
                'installments_count' => $installmentsCount,
                'status' => CommercialSale::STATUS_ABERTA,
                'sold_at' => now(),
                'created_by' => $createdBy,
                'notes' => $data['notes'] ?? null,
            ]);

            $dueDate = \Carbon\Carbon::parse($data['first_due_date'])->startOfDay();

            foreach ($installmentPlan as $index => $part) {
                CommercialSaleInstallment::create([
                    'sale_id' => $sale->id,
                    'number' => $index + 1,
                    'amount_cents' => $part['amount_cents'],
                    'due_date' => $dueDate->copy()->addMonths($index),
                    'method' => $part['method'],
                    'status' => CommercialSaleInstallment::STATUS_PENDENTE,
                ]);
            }

            if ($commissionCents > 0 && $sellerId) {
                CommercialCommission::create([
                    'sale_id' => $sale->id,
                    'seller_id' => $sellerId,
                    'base_cents' => $totalCents,
                    'percent' => $commissionPercent,
                    'amount_cents' => $commissionCents,
                    'status' => CommercialCommission::STATUS_A_PAGAR,
                ]);
            }

            return $sale->load(['installments', 'commission', 'seller:id,name']);
        });
    }

    /**
     * @param  list<array{method: string, percent: float|int|string}>  $mixParts
     * @return list<array{method: string, amount_cents: int}>
     */
    private function planFromMixParts(array $mixParts, int $totalCents): array
    {
        if (count($mixParts) < 2) {
            throw ValidationException::withMessages([
                'mix_parts' => 'Informe pelo menos 2 partes na composição do pagamento misto.',
            ]);
        }

        if (count($mixParts) > 60) {
            throw ValidationException::withMessages([
                'mix_parts' => 'A composição do pagamento pode ter no máximo 60 partes.',
            ]);
        }

        $percents = [];
        foreach ($mixParts as $index => $part) {
            $method = (string) ($part['method'] ?? '');
            if (! in_array($method, self::SINGLE_METHODS, true)) {
                throw ValidationException::withMessages([
                    "mix_parts.{$index}.method" => 'Selecione PIX, boleto ou cartão em cada parte.',
                ]);
            }

            $percent = (float) ($part['percent'] ?? 0);
            if ($percent <= 0) {
                throw ValidationException::withMessages([
                    "mix_parts.{$index}.percent" => 'Cada parte deve ter percentual maior que zero.',
                ]);
            }

            $percents[] = ['method' => $method, 'percent' => $percent];
        }

        $sumPercent = array_sum(array_column($percents, 'percent'));
        if (abs($sumPercent - 100.0) > 0.05) {
            throw ValidationException::withMessages([
                'mix_parts' => 'A soma dos percentuais deve ser 100%.',
            ]);
        }

        $plan = [];
        $allocated = 0;
        $lastIndex = count($percents) - 1;

        foreach ($percents as $index => $part) {
            if ($index === $lastIndex) {
                $amount = max(0, $totalCents - $allocated);
            } else {
                $amount = (int) round($totalCents * ($part['percent'] / 100));
                $allocated += $amount;
            }

            $plan[] = [
                'method' => $part['method'],
                'amount_cents' => $amount,
            ];
        }

        return $plan;
    }

    /**
     * @return list<array{method: string, amount_cents: int}>
     */
    private function planFromRecurringMonths(int $months, string $paymentMethod, int $monthlyCents): array
    {
        if (! in_array($paymentMethod, self::SINGLE_METHODS, true)) {
            throw ValidationException::withMessages([
                'payment_method' => 'Forma de pagamento inválida.',
            ]);
        }

        if ($months < 1 || $months > 60) {
            throw ValidationException::withMessages([
                'installments_count' => 'A duração recorrente deve ter entre 1 e 60 meses.',
            ]);
        }

        if ($monthlyCents < 1) {
            throw ValidationException::withMessages([
                'proposal' => 'Informe um valor mensal válido na proposta recorrente.',
            ]);
        }

        $plan = [];
        for ($i = 0; $i < $months; $i++) {
            $plan[] = [
                'method' => $paymentMethod,
                'amount_cents' => $monthlyCents,
            ];
        }

        return $plan;
    }

    /**
     * @return list<array{method: string, amount_cents: int}>
     */
    private function planFromEqualInstallments(int $installmentsCount, string $paymentMethod, int $totalCents): array
    {
        if (! in_array($paymentMethod, self::SINGLE_METHODS, true)) {
            throw ValidationException::withMessages([
                'payment_method' => 'Forma de pagamento inválida.',
            ]);
        }

        $installmentsCount = max(1, min(60, $installmentsCount));
        $baseAmount = intdiv($totalCents, $installmentsCount);
        $remainder = $totalCents % $installmentsCount;

        $plan = [];
        for ($i = 1; $i <= $installmentsCount; $i++) {
            $plan[] = [
                'method' => $paymentMethod,
                'amount_cents' => $baseAmount + ($i === 1 ? $remainder : 0),
            ];
        }

        return $plan;
    }
}
