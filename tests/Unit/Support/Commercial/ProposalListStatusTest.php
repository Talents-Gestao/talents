<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Commercial;

use App\Models\CommercialProposal;
use App\Models\CommercialSale;
use App\Support\Commercial\ProposalListStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProposalListStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_when_no_sale_and_not_closed(): void
    {
        $proposal = $this->makeProposal(isClosed: false);

        $this->assertSame(ProposalListStatus::OPEN, ProposalListStatus::for($proposal));
        $this->assertSame('Em aberto', ProposalListStatus::labelFor($proposal));
    }

    public function test_closed_when_is_closed_without_sale(): void
    {
        $proposal = $this->makeProposal(isClosed: true);

        $this->assertSame(ProposalListStatus::CLOSED, $proposal->list_status);
        $this->assertSame(ProposalListStatus::CLOSED, ProposalListStatus::for($proposal));
        $this->assertSame('Fechada', ProposalListStatus::labelFor($proposal));
    }

    public function test_persisted_list_status_is_primary_source(): void
    {
        $proposal = $this->makeProposal(isClosed: true, listStatus: ProposalListStatus::IN_PROGRESS);
        $this->attachSale($proposal, CommercialSale::STATUS_ABERTA);

        $this->assertSame(ProposalListStatus::IN_PROGRESS, ProposalListStatus::for($proposal->fresh()));
    }

    public function test_persisted_open_not_overridden_by_parcial_sale(): void
    {
        $proposal = $this->makeProposal(isClosed: false, listStatus: ProposalListStatus::OPEN);
        $this->attachSale($proposal, CommercialSale::STATUS_PARCIAL);

        $this->assertSame(ProposalListStatus::OPEN, ProposalListStatus::for($proposal->fresh()));
    }

    public function test_legacy_null_in_progress_when_sale_parcial(): void
    {
        $proposal = $this->makeProposal(isClosed: false);
        $this->attachSale($proposal, CommercialSale::STATUS_PARCIAL);
        $proposal->forceFill(['list_status' => null])->saveQuietly();

        $this->assertSame(ProposalListStatus::IN_PROGRESS, ProposalListStatus::for($proposal->fresh()));
        $this->assertSame('Em andamento', ProposalListStatus::labelFor($proposal->fresh()));
    }

    public function test_legacy_null_in_progress_prevails_when_is_closed_and_parcial(): void
    {
        $proposal = $this->makeProposal(isClosed: true);
        $this->attachSale($proposal, CommercialSale::STATUS_PARCIAL);
        $proposal->forceFill(['list_status' => null])->saveQuietly();

        $this->assertSame(ProposalListStatus::IN_PROGRESS, ProposalListStatus::for($proposal->fresh()));
    }

    public function test_legacy_null_closed_when_sale_quitada(): void
    {
        $proposal = $this->makeProposal(isClosed: false);
        $this->attachSale($proposal, CommercialSale::STATUS_QUITADA);
        $proposal->forceFill(['list_status' => null])->saveQuietly();

        $this->assertSame(ProposalListStatus::CLOSED, ProposalListStatus::for($proposal->fresh()));
    }

    public function test_model_list_status_delegates_to_helper(): void
    {
        $proposal = $this->makeProposal(isClosed: true);

        $this->assertSame(ProposalListStatus::CLOSED, $proposal->listStatus());
        $this->assertSame('Fechada', $proposal->listStatusLabel());
    }

    private function makeProposal(bool $isClosed, ?string $listStatus = null): CommercialProposal
    {
        $payload = [
            'code' => 'PROP-2026-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'client_name' => 'Cliente Teste',
            'employee_count' => 10,
            'total_final_cents' => 10000,
            'is_closed' => $isClosed,
            'closed_at' => $isClosed ? now() : null,
        ];

        if ($listStatus !== null) {
            $payload['list_status'] = $listStatus;
        }

        return CommercialProposal::create($payload);
    }

    private function attachSale(CommercialProposal $proposal, string $status): CommercialSale
    {
        return CommercialSale::create([
            'proposal_id' => $proposal->id,
            'code' => 'VENDA-2026-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'client_name' => $proposal->client_name,
            'total_cents' => $proposal->total_final_cents,
            'status' => $status,
            'installments_count' => 2,
            'sold_at' => now(),
        ]);
    }
}
