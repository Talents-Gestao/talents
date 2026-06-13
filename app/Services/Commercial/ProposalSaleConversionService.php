<?php

namespace App\Services\Commercial;

use App\Models\CommercialCommission;
use App\Models\CommercialProposal;
use App\Models\CommercialSale;
use App\Models\CommercialSaleInstallment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProposalSaleConversionService
{
    /**
     * @param  array{payment_method: string, installments_count: int, first_due_date: string, notes?: string|null}  $data
     */
    public function convert(CommercialProposal $proposal, array $data, ?int $createdBy = null): CommercialSale
    {
        if (! $proposal->is_closed) {
            throw ValidationException::withMessages([
                'proposal' => 'A proposta precisa estar marcada como ganha antes de converter em venda.',
            ]);
        }

        if ($proposal->sale()->exists()) {
            throw ValidationException::withMessages([
                'proposal' => 'Esta proposta já possui uma venda vinculada.',
            ]);
        }

        $installmentsCount = max(1, min(60, (int) $data['installments_count']));
        $totalCents = (int) $proposal->total_final_cents;
        $baseAmount = intdiv($totalCents, $installmentsCount);
        $remainder = $totalCents % $installmentsCount;

        return DB::transaction(function () use ($proposal, $data, $createdBy, $installmentsCount, $totalCents, $baseAmount, $remainder) {
            $sale = CommercialSale::create([
                'code' => CommercialSale::nextCode(),
                'proposal_id' => $proposal->id,
                'client_name' => $proposal->client_name,
                'client_cnpj' => $proposal->client_cnpj,
                'client_email' => $proposal->client_email,
                'client_phone' => $proposal->client_phone,
                'seller_id' => $proposal->seller_id,
                'total_cents' => $totalCents,
                'commission_percent' => (float) $proposal->commission_percent,
                'commission_cents' => (int) $proposal->commission_cents,
                'payment_method' => $data['payment_method'],
                'installments_count' => $installmentsCount,
                'status' => CommercialSale::STATUS_ABERTA,
                'sold_at' => now(),
                'created_by' => $createdBy,
                'notes' => $data['notes'] ?? null,
            ]);

            $dueDate = \Carbon\Carbon::parse($data['first_due_date'])->startOfDay();

            for ($i = 1; $i <= $installmentsCount; $i++) {
                $amount = $baseAmount + ($i === 1 ? $remainder : 0);

                CommercialSaleInstallment::create([
                    'sale_id' => $sale->id,
                    'number' => $i,
                    'amount_cents' => $amount,
                    'due_date' => $dueDate->copy()->addMonths($i - 1),
                    'method' => $data['payment_method'] === 'misto' ? 'pix' : $data['payment_method'],
                    'status' => CommercialSaleInstallment::STATUS_PENDENTE,
                ]);
            }

            if ((int) $proposal->commission_cents > 0) {
                CommercialCommission::create([
                    'sale_id' => $sale->id,
                    'seller_id' => $proposal->seller_id,
                    'base_cents' => $totalCents,
                    'percent' => (float) $proposal->commission_percent,
                    'amount_cents' => (int) $proposal->commission_cents,
                    'status' => CommercialCommission::STATUS_A_PAGAR,
                ]);
            }

            return $sale->load(['installments', 'commission', 'seller:id,name']);
        });
    }
}
