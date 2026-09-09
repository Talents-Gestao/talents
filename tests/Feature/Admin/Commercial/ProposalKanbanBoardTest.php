<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Commercial;

use App\Enums\ProposalLostReason;
use App\Models\CommercialContract;
use App\Models\CommercialProposal;
use App\Models\User;
use App\Support\Commercial\ProposalKanbanBoard;
use App\Support\Commercial\ProposalListStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProposalKanbanBoardTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_without_view_query_defaults_to_kanban(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        CommercialProposal::query()->create([
            'code' => 'PROP-KANBAN-DEFAULT',
            'client_name' => 'Padrão Kanban',
            'employee_count' => 5,
            'total_final_cents' => 1_200,
            'is_closed' => false,
            'list_status' => ProposalListStatus::OPEN,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.comercial.propostas.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Commercial/Proposals/Index')
                ->where('view', ProposalKanbanBoard::VIEW_KANBAN)
                ->has('kanban.columns', 4)
                ->where('kanban.columns.0.key', 'leads')
                ->where('kanban.columns.1.items.0.code', 'PROP-KANBAN-DEFAULT')
                ->where('proposals.data', []));
    }

    public function test_list_view_is_available_via_query_string(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        CommercialProposal::query()->create([
            'code' => 'PROP-LIST-VIEW',
            'client_name' => 'Lista explícita',
            'employee_count' => 5,
            'total_final_cents' => 800,
            'is_closed' => false,
            'list_status' => ProposalListStatus::OPEN,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.comercial.propostas.index', ['view' => 'list']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Commercial/Proposals/Index')
                ->where('view', ProposalKanbanBoard::VIEW_LIST)
                ->where('kanban', null)
                ->has('proposals.data', 1)
                ->where('proposals.data.0.code', 'PROP-LIST-VIEW'));
    }

    public function test_index_kanban_view_groups_proposals_by_list_status(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        CommercialProposal::query()->create([
            'code' => 'PROP-KANBAN-OPEN',
            'client_name' => 'Aberta Kanban',
            'employee_count' => 5,
            'total_final_cents' => 1_000,
            'is_closed' => false,
            'list_status' => ProposalListStatus::OPEN,
        ]);

        CommercialProposal::query()->create([
            'code' => 'PROP-KANBAN-CLOSED',
            'client_name' => 'Fechada Kanban',
            'employee_count' => 5,
            'total_final_cents' => 2_500,
            'is_closed' => true,
            'closed_at' => now(),
            'list_status' => ProposalListStatus::CLOSED,
        ]);

        CommercialProposal::query()->create([
            'code' => 'PROP-KANBAN-ENDED',
            'client_name' => 'Perdida Kanban',
            'employee_count' => 5,
            'total_final_cents' => 3_000,
            'is_closed' => false,
            'list_status' => ProposalListStatus::ENDED,
            'lost_reason' => ProposalLostReason::Preco->value,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.comercial.propostas.index', ['view' => 'kanban']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Commercial/Proposals/Index')
                ->where('view', ProposalKanbanBoard::VIEW_KANBAN)
                ->has('kanban.columns', 4)
                ->where('kanban.columns.0.key', 'leads')
                ->where('kanban.columns.1.key', ProposalListStatus::OPEN)
                ->where('kanban.columns.1.count', 1)
                ->where('kanban.columns.1.total_cents', 1_000)
                ->where('kanban.columns.1.items.0.code', 'PROP-KANBAN-OPEN')
                ->where('kanban.columns.2.key', ProposalListStatus::CLOSED)
                ->where('kanban.columns.2.count', 1)
                ->where('kanban.columns.2.total_cents', 2_500)
                ->where('kanban.columns.3.key', ProposalListStatus::ENDED)
                ->where('kanban.columns.3.count', 1)
                ->where('kanban.pipeline_open_cents', 1_000)
                ->where('proposals.data', []));
    }

    public function test_kanban_view_shows_ended_even_when_hide_ended_would_apply_in_list(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        CommercialProposal::query()->create([
            'code' => 'PROP-KANBAN-HIDE',
            'client_name' => 'Perdida Visível',
            'employee_count' => 5,
            'total_final_cents' => 900,
            'is_closed' => false,
            'list_status' => ProposalListStatus::ENDED,
            'lost_reason' => ProposalLostReason::Preco->value,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.comercial.propostas.index', ['view' => 'kanban']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('view', 'kanban')
                ->where('kanban.columns.3.count', 1)
                ->where('kanban.columns.3.items.0.code', 'PROP-KANBAN-HIDE'));
    }

    public function test_status_patch_with_view_kanban_redirects_back_to_kanban(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-KANBAN-MOVE',
            'client_name' => 'Mover no board',
            'employee_count' => 5,
            'total_final_cents' => 1_500,
            'is_closed' => false,
            'list_status' => ProposalListStatus::OPEN,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.comercial.propostas.status', $proposal), [
                'status' => ProposalListStatus::CLOSED,
                'view' => 'kanban',
            ])
            ->assertRedirect(route('admin.comercial.propostas.index', ['view' => 'kanban']));

        $this->assertSame(ProposalListStatus::CLOSED, $proposal->fresh()->list_status);
    }

    public function test_kanban_move_to_ended_requires_lost_reason(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-KANBAN-LOST',
            'client_name' => 'Perder no board',
            'employee_count' => 5,
            'total_final_cents' => 800,
            'is_closed' => false,
            'list_status' => ProposalListStatus::OPEN,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.comercial.propostas.index', ['view' => 'kanban']))
            ->patch(route('admin.comercial.propostas.status', $proposal), [
                'status' => ProposalListStatus::ENDED,
                'view' => 'kanban',
            ])
            ->assertSessionHasErrors('lost_reason');

        $this->actingAs($admin)
            ->patch(route('admin.comercial.propostas.status', $proposal), [
                'status' => ProposalListStatus::ENDED,
                'lost_reason' => ProposalLostReason::Preco->value,
                'view' => 'kanban',
            ])
            ->assertRedirect(route('admin.comercial.propostas.index', ['view' => 'kanban']));

        $this->assertSame(ProposalListStatus::ENDED, $proposal->fresh()->list_status);
    }

    public function test_kanban_cards_expose_operational_alerts(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $stagnant = CommercialProposal::query()->create([
            'code' => 'PROP-KANBAN-STAG',
            'client_name' => 'Estagnada',
            'employee_count' => 5,
            'total_final_cents' => 1_000,
            'is_closed' => false,
            'list_status' => ProposalListStatus::OPEN,
        ]);
        $stagnant->forceFill([
            'updated_at' => now()->subDays(ProposalKanbanBoard::STAGNANT_DAYS + 1),
        ])->saveQuietly();

        $closedNoSale = CommercialProposal::query()->create([
            'code' => 'PROP-KANBAN-NOSALE',
            'client_name' => 'Fechada sem venda',
            'employee_count' => 5,
            'total_final_cents' => 2_000,
            'is_closed' => true,
            'closed_at' => now(),
            'list_status' => ProposalListStatus::CLOSED,
        ]);

        $withZap = CommercialProposal::query()->create([
            'code' => 'PROP-KANBAN-ZAP',
            'client_name' => 'ZapSign pendente',
            'employee_count' => 5,
            'total_final_cents' => 3_000,
            'is_closed' => false,
            'list_status' => ProposalListStatus::OPEN,
        ]);

        \App\Models\CommercialContract::query()->create([
            'proposal_id' => $withZap->id,
            'code' => 'CTR-KANBAN-ZAP',
            'template_name_snapshot' => 'Template demo',
            'pdf_path' => 'contracts/kanban-zap.pdf',
            'html_snapshot' => '<p>demo</p>',
            'generated_at' => now(),
            'zapsign_document_token' => 'tok-pendente',
            'zapsign_status' => 'pending',
            'zapsign_sent_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.comercial.propostas.index', ['view' => 'kanban']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('kanban.columns.1.items', fn ($items) => collect($items)->contains(
                    fn ($item) => $item['code'] === 'PROP-KANBAN-STAG' && $item['is_stagnant'] === true,
                ))
                ->where('kanban.columns.1.items', fn ($items) => collect($items)->contains(
                    fn ($item) => $item['code'] === 'PROP-KANBAN-ZAP'
                        && $item['zapsign_pending'] === true
                        && $item['contract_signed'] === false,
                ))
                ->where('kanban.columns.2.items.0.code', 'PROP-KANBAN-NOSALE')
                ->where('kanban.columns.2.items.0.closed_without_sale', true)
                ->where('kanban.columns.2.items.0.contract_signed', false));
    }

    public function test_kanban_marks_proposal_when_contract_is_signed(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $signed = CommercialProposal::query()->create([
            'code' => 'PROP-KANBAN-SIGNED',
            'client_name' => 'Contrato assinado',
            'employee_count' => 5,
            'total_final_cents' => 4_000,
            'is_closed' => true,
            'closed_at' => now(),
            'list_status' => ProposalListStatus::CLOSED,
        ]);

        CommercialContract::query()->create([
            'proposal_id' => $signed->id,
            'code' => 'CTR-KANBAN-SIGNED',
            'template_name_snapshot' => 'Template demo',
            'pdf_path' => 'contracts/kanban-signed.pdf',
            'html_snapshot' => '<p>demo</p>',
            'generated_at' => now(),
            'zapsign_document_token' => 'tok-assinado',
            'zapsign_status' => 'signed',
            'zapsign_sent_at' => now()->subDay(),
        ]);

        $unsigned = CommercialProposal::query()->create([
            'code' => 'PROP-KANBAN-UNSIGNED',
            'client_name' => 'Sem contrato',
            'employee_count' => 5,
            'total_final_cents' => 1_500,
            'is_closed' => false,
            'list_status' => ProposalListStatus::OPEN,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.comercial.propostas.index', ['view' => 'kanban']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('kanban.columns.1.items', fn ($items) => collect($items)->contains(
                    fn ($item) => $item['id'] === $unsigned->id
                        && $item['contract_signed'] === false
                        && $item['zapsign_pending'] === false,
                ))
                ->where('kanban.columns.2.items', fn ($items) => collect($items)->contains(
                    fn ($item) => $item['id'] === $signed->id
                        && $item['contract_signed'] === true
                        && $item['zapsign_pending'] === false,
                )));
    }

    public function test_kanban_leads_column_includes_landing_interest_submissions(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $lead = \App\Models\LandingInterestSubmission::query()->create([
            'name' => 'Maria Lead',
            'email' => 'maria.lead@example.com',
            'phone' => '11999990000',
            'company' => 'Empresa Lead LTDA',
            'message' => 'Quero uma proposta',
            'source' => 'site',
            'is_qualified' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.comercial.propostas.index', ['view' => 'kanban']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('kanban.columns.0.key', 'leads')
                ->where('kanban.columns.0.count', 1)
                ->where('kanban.columns.0.items.0.card_kind', 'lead')
                ->where('kanban.columns.0.items.0.id', $lead->id)
                ->where('kanban.columns.0.items.0.client_name', 'Empresa Lead LTDA')
                ->where('kanban.columns.0.items.0.client_email', 'maria.lead@example.com'));
    }

    public function test_create_proposal_prefills_from_lead_query(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $lead = \App\Models\LandingInterestSubmission::query()->create([
            'name' => 'João Contato',
            'email' => 'joao@example.com',
            'phone' => '11988887777',
            'company' => 'Contato SA',
            'message' => 'Interesse em NR-1',
            'source' => 'whatsapp',
            'is_qualified' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.comercial.propostas.create', ['lead_id' => $lead->id]))
            ->assertRedirect(route('admin.comercial.propostas.index', [
                'form' => 'create',
                'lead_id' => $lead->id,
            ]));

        $this->actingAs($admin)
            ->get(route('admin.comercial.propostas.index', [
                'form' => 'create',
                'lead_id' => $lead->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Commercial/Proposals/Index')
                ->where('formModal.mode', 'create')
                ->where('formModal.fromLead.id', $lead->id)
                ->where('formModal.fromLead.client_name', 'Contato SA')
                ->where('formModal.fromLead.client_email', 'joao@example.com')
                ->where('formModal.fromLead.client_phone', '11988887777')
                ->where('formModal.fromLead.client_representative', 'João Contato')
                ->where('formModal.fromLead.indication', 'Lead · WhatsApp'));
    }
}
