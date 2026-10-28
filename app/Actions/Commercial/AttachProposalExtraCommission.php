<?php

declare(strict_types=1);

namespace App\Actions\Commercial;

use App\Models\CommercialCommission;
use App\Models\CommercialProposal;
use App\Models\CommercialProposalExtraCommission;
use App\Models\User;
use App\Support\Commercial\OptionalCommission;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttachProposalExtraCommission
{
    public function handle(CommercialProposal $proposal, User $beneficiary, ?string $notes = null): CommercialProposalExtraCommission
    {
        if (! $beneficiary->is_active) {
            throw ValidationException::withMessages([
                'user_id' => 'Este utilizador está inativo e não pode receber comissão extra.',
            ]);
        }

        $percent = OptionalCommission::percentForSeller($beneficiary);
        if ($percent <= 0) {
            throw ValidationException::withMessages([
                'user_id' => 'Este utilizador não tem percentual de comissão definido na Equipe.',
            ]);
        }

        if ($proposal->seller_id !== null && (int) $proposal->seller_id === (int) $beneficiary->id) {
            throw ValidationException::withMessages([
                'user_id' => 'Esta pessoa já é a vendedora da proposta e recebe a comissão automática.',
            ]);
        }

        return DB::transaction(function () use ($proposal, $beneficiary, $percent, $notes): CommercialProposalExtraCommission {
            $extra = CommercialProposalExtraCommission::query()->updateOrCreate(
                [
                    'proposal_id' => $proposal->id,
                    'user_id' => $beneficiary->id,
                ],
                [
                    'percent' => $percent,
                    'notes' => $notes,
                ],
            );

            $sale = $proposal->sale;
            if ($sale === null) {
                return $extra;
            }

            $alreadyOnSale = CommercialCommission::query()
                ->where('sale_id', $sale->id)
                ->where('seller_id', $beneficiary->id)
                ->exists();

            if ($alreadyOnSale) {
                throw ValidationException::withMessages([
                    'user_id' => 'Esta pessoa já possui comissão nesta venda.',
                ]);
            }

            $baseCents = (int) $sale->total_cents;
            $amountCents = OptionalCommission::centsFromPercent($baseCents, $percent);
            if ($amountCents < 1) {
                throw ValidationException::withMessages([
                    'user_id' => 'O percentual deste utilizador não gera valor de comissão nesta venda.',
                ]);
            }

            CommercialCommission::query()->create([
                'sale_id' => $sale->id,
                'seller_id' => $beneficiary->id,
                'base_cents' => $baseCents,
                'percent' => $percent,
                'amount_cents' => $amountCents,
                'status' => CommercialCommission::STATUS_A_PAGAR,
                'notes' => $notes,
            ]);

            return $extra;
        });
    }
}
