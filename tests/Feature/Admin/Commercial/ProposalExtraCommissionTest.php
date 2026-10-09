<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Commercial;

use App\Models\CommercialCommission;
use App\Models\CommercialProposal;
use App\Models\CommercialProposalExtraCommission;
use App\Models\CommercialSale;
use App\Models\CommercialSaleInstallment;
use App\Models\User;
use App\Services\Commercial\ProposalSaleConversionService;
use App\Support\Commercial\ProposalListStatus;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProposalExtraCommissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_kanban_menu_reserves_extra_commission_until_sale_conversion(): void
    {
        [$admin, $fernanda, $isa] = $this->team();

        $proposal = $this->closedProposal($fernanda, [
            'code' => 'PROP-EXTRA-0001',
            'total_final_cents' => 10_000,
            'commission_percent' => 10,
            'commission_cents' => 1_000,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.comercial.propostas.comissoes-extra', $proposal), [
                'user_id' => $isa->id,
                'percent' => 99,
                'notes' => 'Seguiu o fechamento após a proposta',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('commercial_proposal_extra_commissions', [
            'proposal_id' => $proposal->id,
            'user_id' => $isa->id,
            'percent' => 5,
        ]);
        $this->assertDatabaseCount('commercial_commissions', 0);

        $sale = app(ProposalSaleConversionService::class)->convert($proposal->fresh(), [
            'payment_method' => 'pix',
            'installments_count' => 1,
            'first_due_date' => now()->toDateString(),
        ]);

        $this->assertSame(2, $sale->commissions()->count());
        $this->assertDatabaseHas('commercial_commissions', [
            'sale_id' => $sale->id,
            'seller_id' => $fernanda->id,
            'percent' => 10,
            'amount_cents' => 1_000,
        ]);
        $this->assertDatabaseHas('commercial_commissions', [
            'sale_id' => $sale->id,
            'seller_id' => $isa->id,
            'percent' => 5,
            'amount_cents' => 500,
            'notes' => 'Seguiu o fechamento após a proposta',
        ]);
    }

    public function test_http_convert_creates_seller_and_extra_commissions(): void
    {
        [$admin, $fernanda, $isa] = $this->team();
        $proposal = $this->closedProposal($fernanda, [
            'code' => 'PROP-EXTRA-HTTP',
            'total_final_cents' => 20_000,
            'commission_percent' => 10,
            'commission_cents' => 2_000,
        ]);

        $this->actingAs($admin)->post(route('admin.comercial.propostas.comissoes-extra', $proposal), [
            'user_id' => $isa->id,
        ])->assertRedirect();

        $this->actingAs($admin)
            ->post(route('admin.comercial.propostas.converter', $proposal), [
                'payment_method' => 'pix',
                'installments_count' => 1,
                'first_due_date' => now()->toDateString(),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $sale = CommercialSale::query()->where('proposal_id', $proposal->id)->first();
        $this->assertNotNull($sale);
        $this->assertSame(2, $sale->commissions()->count());
        $this->assertSame(2_000, (int) $sale->commissions()->where('seller_id', $fernanda->id)->value('amount_cents'));
        $this->assertSame(1_000, (int) $sale->commissions()->where('seller_id', $isa->id)->value('amount_cents'));
    }

    public function test_extra_commission_is_created_immediately_when_sale_already_exists(): void
    {
        [$admin, $fernanda, $isa] = $this->team(['isa_percent' => 8]);
        $proposal = $this->closedProposal($fernanda, [
            'code' => 'PROP-EXTRA-0002',
            'total_final_cents' => 20_000,
            'commission_percent' => 10,
            'commission_cents' => 2_000,
        ]);
        $sale = $this->saleFromProposal($proposal, $fernanda, 20_000, 2_000);

        $this->actingAs($admin)
            ->post(route('admin.comercial.propostas.comissoes-extra', $proposal), [
                'user_id' => $isa->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('commercial_commissions', [
            'sale_id' => $sale->id,
            'seller_id' => $isa->id,
            'percent' => 8,
            'amount_cents' => 1_600,
        ]);
        $this->assertSame(2, $sale->commissions()->count());
    }

    public function test_conversion_keeps_extra_percent_snapshot_if_equipe_changes(): void
    {
        [$admin, $fernanda, $isa] = $this->team();
        $proposal = $this->closedProposal($fernanda, [
            'code' => 'PROP-EXTRA-SNAP',
            'total_final_cents' => 10_000,
            'commission_percent' => 10,
            'commission_cents' => 1_000,
        ]);

        $this->actingAs($admin)->post(route('admin.comercial.propostas.comissoes-extra', $proposal), [
            'user_id' => $isa->id,
        ])->assertRedirect();

        $isa->update(['commission_percent' => 20]);

        $sale = app(ProposalSaleConversionService::class)->convert($proposal->fresh(), [
            'payment_method' => 'pix',
            'installments_count' => 1,
            'first_due_date' => now()->toDateString(),
        ]);

        $this->assertDatabaseHas('commercial_commissions', [
            'sale_id' => $sale->id,
            'seller_id' => $isa->id,
            'percent' => 5,
            'amount_cents' => 500,
        ]);
    }

    public function test_allows_multiple_extra_beneficiaries_on_the_same_sale(): void
    {
        [$admin, $fernanda, $isa] = $this->team();
        $carla = User::factory()->superAdmin()->create([
            'name' => 'Carla',
            'commission_percent' => 3,
        ]);
        $proposal = $this->closedProposal($fernanda, [
            'code' => 'PROP-EXTRA-MULTI',
            'total_final_cents' => 10_000,
            'commission_percent' => 10,
            'commission_cents' => 1_000,
        ]);

        $this->actingAs($admin)->post(route('admin.comercial.propostas.comissoes-extra', $proposal), [
            'user_id' => $isa->id,
        ])->assertRedirect();
        $this->actingAs($admin)->post(route('admin.comercial.propostas.comissoes-extra', $proposal), [
            'user_id' => $carla->id,
        ])->assertRedirect();

        $sale = app(ProposalSaleConversionService::class)->convert($proposal->fresh(), [
            'payment_method' => 'pix',
            'installments_count' => 1,
            'first_due_date' => now()->toDateString(),
        ]);

        $this->assertSame(3, $sale->commissions()->count());
        $this->assertSame(300, (int) $sale->commissions()->where('seller_id', $carla->id)->value('amount_cents'));
    }

    public function test_cannot_attach_same_extra_person_after_conversion(): void
    {
        [$admin, $fernanda, $isa] = $this->team();
        $proposal = $this->closedProposal($fernanda, [
            'code' => 'PROP-EXTRA-DUP',
            'total_final_cents' => 10_000,
            'commission_percent' => 10,
            'commission_cents' => 1_000,
        ]);

        $this->actingAs($admin)->post(route('admin.comercial.propostas.comissoes-extra', $proposal), [
            'user_id' => $isa->id,
        ])->assertRedirect();

        app(ProposalSaleConversionService::class)->convert($proposal->fresh(), [
            'payment_method' => 'pix',
            'installments_count' => 1,
            'first_due_date' => now()->toDateString(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.comercial.propostas.comissoes-extra', $proposal), [
                'user_id' => $isa->id,
            ])
            ->assertSessionHasErrors('user_id');

        $this->assertSame(1, CommercialProposalExtraCommission::query()->where('proposal_id', $proposal->id)->count());
        $this->assertSame(2, CommercialCommission::query()->count());
    }

    public function test_reattach_before_conversion_updates_notes_without_duplicating_row(): void
    {
        [$admin, $fernanda, $isa] = $this->team();
        $proposal = $this->closedProposal($fernanda, [
            'code' => 'PROP-EXTRA-NOTE',
            'total_final_cents' => 10_000,
            'commission_percent' => 10,
            'commission_cents' => 1_000,
        ]);

        $this->actingAs($admin)->post(route('admin.comercial.propostas.comissoes-extra', $proposal), [
            'user_id' => $isa->id,
            'notes' => 'Primeira observação',
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('admin.comercial.propostas.comissoes-extra', $proposal), [
            'user_id' => $isa->id,
            'notes' => 'Observação atualizada',
        ])->assertRedirect();

        $this->assertSame(1, CommercialProposalExtraCommission::query()->where('proposal_id', $proposal->id)->count());
        $this->assertDatabaseHas('commercial_proposal_extra_commissions', [
            'proposal_id' => $proposal->id,
            'user_id' => $isa->id,
            'notes' => 'Observação atualizada',
            'percent' => 5,
        ]);
    }

    public function test_cannot_attach_proposal_seller_as_extra_commission(): void
    {
        [$admin, $fernanda] = $this->team();
        $proposal = $this->closedProposal($fernanda, ['code' => 'PROP-EXTRA-0003']);

        $this->actingAs($admin)
            ->post(route('admin.comercial.propostas.comissoes-extra', $proposal), [
                'user_id' => $fernanda->id,
            ])
            ->assertSessionHasErrors('user_id');
    }

    public function test_cannot_attach_user_without_defined_commission_percent(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $isa = User::factory()->superAdmin()->create([
            'name' => 'Isa',
            'commission_percent' => 0,
        ]);
        $proposal = $this->closedProposal(null, ['code' => 'PROP-EXTRA-0004']);

        $this->actingAs($admin)
            ->post(route('admin.comercial.propostas.comissoes-extra', $proposal), [
                'user_id' => $isa->id,
            ])
            ->assertSessionHasErrors('user_id');
    }

    public function test_cannot_attach_inactive_user(): void
    {
        [$admin, $fernanda, $isa] = $this->team();
        $isa->update(['is_active' => false]);
        $proposal = $this->closedProposal($fernanda, ['code' => 'PROP-EXTRA-INACTIVE']);

        $this->actingAs($admin)
            ->post(route('admin.comercial.propostas.comissoes-extra', $proposal), [
                'user_id' => $isa->id,
            ])
            ->assertSessionHasErrors('user_id');
    }

    public function test_guest_cannot_attach_extra_commission(): void
    {
        [, $fernanda, $isa] = $this->team();
        $proposal = $this->closedProposal($fernanda, ['code' => 'PROP-EXTRA-GUEST']);

        $this->post(route('admin.comercial.propostas.comissoes-extra', $proposal), [
            'user_id' => $isa->id,
        ])->assertRedirect();

        $this->assertDatabaseCount('commercial_proposal_extra_commissions', 0);
    }

    public function test_requires_user_id(): void
    {
        [$admin, $fernanda] = $this->team();
        $proposal = $this->closedProposal($fernanda, ['code' => 'PROP-EXTRA-REQ']);

        $this->actingAs($admin)
            ->post(route('admin.comercial.propostas.comissoes-extra', $proposal), [])
            ->assertSessionHasErrors('user_id');
    }

    public function test_index_exposes_extra_commissions_and_eligible_equipe_users(): void
    {
        [$admin, $fernanda, $isa] = $this->team();
        $zero = User::factory()->superAdmin()->create([
            'name' => 'Sem Comissão',
            'commission_percent' => 0,
        ]);
        $proposal = $this->closedProposal($fernanda, ['code' => 'PROP-EXTRA-INDEX']);

        $this->actingAs($admin)->post(route('admin.comercial.propostas.comissoes-extra', $proposal), [
            'user_id' => $isa->id,
        ])->assertRedirect();

        $this->actingAs($admin)
            ->get(route('admin.comercial.propostas.index', ['view' => 'list']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Commercial/Proposals/Index')
                ->where('proposals.data.0.code', 'PROP-EXTRA-INDEX')
                ->where('proposals.data.0.extra_commissions.0.user_id', $isa->id)
                ->missing('proposals.data.0.extra_commissions.0.percent')
                ->where('proposals.data.0.extra_commissions.0.name', 'Isa')
                ->where('commissionUsers', fn ($users) => collect($users)->contains('id', $isa->id)
                    && collect($users)->contains('id', $fernanda->id)
                    && ! collect($users)->contains('id', $zero->id)
                    && collect($users)->every(fn ($user) => ! array_key_exists('commission_percent', (array) $user))));
    }

    public function test_sale_show_lists_seller_and_extra_commissions(): void
    {
        [$admin, $fernanda, $isa] = $this->team();
        $proposal = $this->closedProposal($fernanda, [
            'code' => 'PROP-EXTRA-SHOW',
            'total_final_cents' => 10_000,
            'commission_percent' => 10,
            'commission_cents' => 1_000,
        ]);
        $this->actingAs($admin)->post(route('admin.comercial.propostas.comissoes-extra', $proposal), [
            'user_id' => $isa->id,
        ])->assertRedirect();
        $sale = app(ProposalSaleConversionService::class)->convert($proposal->fresh(), [
            'payment_method' => 'pix',
            'installments_count' => 1,
            'first_due_date' => now()->toDateString(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.financeiro.vendas.show', $sale))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Finance/Sales/Show')
                ->has('sale.commissions', 2)
                ->where('sale.commissions', function ($commissions) use ($fernanda, $isa) {
                    $bySeller = collect($commissions)->keyBy('seller_id');

                    return (int) ($bySeller[$fernanda->id]['amount_cents'] ?? 0) === 1_000
                        && (int) ($bySeller[$isa->id]['amount_cents'] ?? 0) === 500;
                }));
    }

    public function test_sales_index_counts_all_payable_commissions_for_destroy_warning(): void
    {
        [$admin, $fernanda, $isa] = $this->team();
        $proposal = $this->closedProposal($fernanda, [
            'code' => 'PROP-EXTRA-COUNT',
            'total_final_cents' => 10_000,
            'commission_percent' => 10,
            'commission_cents' => 1_000,
        ]);
        $this->actingAs($admin)->post(route('admin.comercial.propostas.comissoes-extra', $proposal), [
            'user_id' => $isa->id,
        ])->assertRedirect();
        app(ProposalSaleConversionService::class)->convert($proposal->fresh(), [
            'payment_method' => 'pix',
            'installments_count' => 1,
            'first_due_date' => now()->toDateString(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.financeiro.vendas.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Finance/Sales/Index')
                ->where('sales.data.0.payable_commissions_count', 2));
    }

    public function test_commissions_index_lists_both_people_and_includes_extra_in_filter(): void
    {
        [$admin, $fernanda, $isa] = $this->team();
        $proposal = $this->closedProposal($fernanda, [
            'code' => 'PROP-EXTRA-FIN',
            'client_name' => 'Cliente Extra Fin',
            'total_final_cents' => 10_000,
            'commission_percent' => 10,
            'commission_cents' => 1_000,
        ]);
        $this->actingAs($admin)->post(route('admin.comercial.propostas.comissoes-extra', $proposal), [
            'user_id' => $isa->id,
        ])->assertRedirect();
        app(ProposalSaleConversionService::class)->convert($proposal->fresh(), [
            'payment_method' => 'pix',
            'installments_count' => 1,
            'first_due_date' => now()->toDateString(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.financeiro.comissoes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Finance/Commissions/Index')
                ->has('commissions.data', 2)
                ->where('summary.pending_cents', 1_500)
                ->where('summary.pending_count', 2)
                ->where('sellers', fn ($sellers) => collect($sellers)->contains('id', $isa->id)
                    && collect($sellers)->contains('id', $fernanda->id)));

        $this->actingAs($admin)
            ->get(route('admin.financeiro.comissoes.index', ['seller_id' => $isa->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('commissions.data', 1)
                ->where('commissions.data.0.seller.id', $isa->id)
                ->where('summary.pending_cents', 500));
    }

    public function test_extra_commission_cannot_be_marked_paid_until_sale_is_settled(): void
    {
        [$admin, $fernanda, $isa] = $this->team();
        $proposal = $this->closedProposal($fernanda, [
            'code' => 'PROP-EXTRA-PAY',
            'total_final_cents' => 10_000,
            'commission_percent' => 10,
            'commission_cents' => 1_000,
        ]);
        $this->actingAs($admin)->post(route('admin.comercial.propostas.comissoes-extra', $proposal), [
            'user_id' => $isa->id,
        ])->assertRedirect();
        $sale = app(ProposalSaleConversionService::class)->convert($proposal->fresh(), [
            'payment_method' => 'pix',
            'installments_count' => 1,
            'first_due_date' => now()->toDateString(),
        ]);
        $extra = $sale->commissions()->where('seller_id', $isa->id)->first();
        $this->assertNotNull($extra);

        $this->actingAs($admin)
            ->from(route('admin.financeiro.comissoes.index'))
            ->patch(route('admin.financeiro.comissoes.update', $extra), [
                'status' => CommercialCommission::STATUS_PAGA,
                'paid_at' => now()->toDateString(),
            ])
            ->assertRedirect(route('admin.financeiro.comissoes.index'))
            ->assertSessionHasErrors('status');

        $this->settleSale($sale);

        $this->actingAs($admin)
            ->from(route('admin.financeiro.comissoes.index'))
            ->patch(route('admin.financeiro.comissoes.update', $extra), [
                'status' => CommercialCommission::STATUS_PAGA,
                'paid_at' => now()->toDateString(),
            ])
            ->assertRedirect(route('admin.financeiro.comissoes.index'))
            ->assertSessionHas('success');

        $this->assertSame(CommercialCommission::STATUS_PAGA, $extra->fresh()->status);
        $this->assertSame(
            CommercialCommission::STATUS_A_PAGAR,
            $sale->commissions()->where('seller_id', $fernanda->id)->value('status'),
        );
    }

    public function test_finance_dashboard_kpi_includes_extra_commission(): void
    {
        [$admin, $fernanda, $isa] = $this->team();
        $proposal = $this->closedProposal($fernanda, [
            'code' => 'PROP-EXTRA-KPI',
            'total_final_cents' => 10_000,
            'commission_percent' => 10,
            'commission_cents' => 1_000,
        ]);
        $this->actingAs($admin)->post(route('admin.comercial.propostas.comissoes-extra', $proposal), [
            'user_id' => $isa->id,
        ])->assertRedirect();
        app(ProposalSaleConversionService::class)->convert($proposal->fresh(), [
            'payment_method' => 'pix',
            'installments_count' => 1,
            'first_due_date' => now()->toDateString(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.financeiro.dashboard', ['period' => 'all']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Finance/Dashboard')
                ->where('kpis.commissions_pending_cents', 1_500));
    }

    public function test_recurring_conversion_applies_extra_percent_on_period_total(): void
    {
        [$admin, $fernanda, $isa] = $this->team();
        $proposal = $this->closedProposal($fernanda, [
            'code' => 'PROP-EXTRA-REC',
            'total_final_cents' => 18_000_00,
            'commission_percent' => 10,
            'commission_cents' => 1_800_00,
            'is_recurring' => true,
            'recurring_months' => 6,
            'recurring_monthly_cents' => 3_000_00,
        ]);

        $this->actingAs($admin)->post(route('admin.comercial.propostas.comissoes-extra', $proposal), [
            'user_id' => $isa->id,
        ])->assertRedirect();

        $sale = app(ProposalSaleConversionService::class)->convert($proposal->fresh(), [
            'payment_method' => 'pix',
            'first_due_date' => '2026-09-01',
        ]);

        $this->assertSame(18_000_00, (int) $sale->total_cents);
        $this->assertSame(1_800_00, (int) $sale->commissions()->where('seller_id', $fernanda->id)->value('amount_cents'));
        $this->assertSame(90_000, (int) $sale->commissions()->where('seller_id', $isa->id)->value('amount_cents'));
    }

    public function test_deleting_sale_keeps_extra_reservation_and_reconvert_recreates_both(): void
    {
        [$admin, $fernanda, $isa] = $this->team();
        $proposal = $this->closedProposal($fernanda, [
            'code' => 'PROP-EXTRA-DEL',
            'total_final_cents' => 10_000,
            'commission_percent' => 10,
            'commission_cents' => 1_000,
        ]);
        $this->actingAs($admin)->post(route('admin.comercial.propostas.comissoes-extra', $proposal), [
            'user_id' => $isa->id,
        ])->assertRedirect();

        $sale = app(ProposalSaleConversionService::class)->convert($proposal->fresh(), [
            'payment_method' => 'pix',
            'installments_count' => 1,
            'first_due_date' => now()->toDateString(),
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.financeiro.vendas.destroy', $sale))
            ->assertRedirect(route('admin.financeiro.vendas.index'));

        $this->assertDatabaseCount('commercial_commissions', 0);
        $this->assertDatabaseHas('commercial_proposal_extra_commissions', [
            'proposal_id' => $proposal->id,
            'user_id' => $isa->id,
            'percent' => 5,
        ]);

        $again = app(ProposalSaleConversionService::class)->convert($proposal->fresh(), [
            'payment_method' => 'pix',
            'installments_count' => 1,
            'first_due_date' => now()->toDateString(),
        ]);

        $this->assertSame(2, $again->commissions()->count());
    }

    public function test_unique_constraint_allows_two_sellers_and_blocks_same_seller_twice(): void
    {
        [, $fernanda, $isa] = $this->team();
        $proposal = $this->closedProposal($fernanda, [
            'code' => 'PROP-EXTRA-UNIQ',
            'total_final_cents' => 10_000,
            'commission_percent' => 10,
            'commission_cents' => 1_000,
        ]);
        $sale = $this->saleFromProposal($proposal, $fernanda, 10_000, 1_000);

        CommercialCommission::query()->create([
            'sale_id' => $sale->id,
            'seller_id' => $isa->id,
            'base_cents' => 10_000,
            'percent' => 5,
            'amount_cents' => 500,
            'status' => CommercialCommission::STATUS_A_PAGAR,
        ]);

        $this->assertSame(2, $sale->commissions()->count());

        $this->expectException(QueryException::class);
        CommercialCommission::query()->create([
            'sale_id' => $sale->id,
            'seller_id' => $isa->id,
            'base_cents' => 10_000,
            'percent' => 5,
            'amount_cents' => 500,
            'status' => CommercialCommission::STATUS_A_PAGAR,
        ]);
    }

    public function test_extra_commission_is_created_even_when_originator_has_no_payable_commission(): void
    {
        [$admin, $fernanda, $isa] = $this->team();
        $proposal = $this->closedProposal($fernanda, [
            'code' => 'PROP-EXTRA-ZERO',
            'total_final_cents' => 10_000,
            'commission_percent' => 0,
            'commission_cents' => 0,
        ]);

        $this->actingAs($admin)->post(route('admin.comercial.propostas.comissoes-extra', $proposal), [
            'user_id' => $isa->id,
        ])->assertRedirect();

        $sale = app(ProposalSaleConversionService::class)->convert($proposal->fresh(), [
            'payment_method' => 'pix',
            'installments_count' => 1,
            'first_due_date' => now()->toDateString(),
        ]);

        $this->assertSame(1, $sale->commissions()->count());
        $this->assertDatabaseHas('commercial_commissions', [
            'sale_id' => $sale->id,
            'seller_id' => $isa->id,
            'amount_cents' => 500,
        ]);
        $this->assertDatabaseMissing('commercial_commissions', [
            'sale_id' => $sale->id,
            'seller_id' => $fernanda->id,
        ]);
    }

    /**
     * @param  array{isa_percent?: float}  $overrides
     * @return array{0: User, 1: User, 2: User}
     */
    private function team(array $overrides = []): array
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $fernanda = User::factory()->superAdmin()->create([
            'name' => 'Fernanda',
            'is_commercial' => true,
            'commission_percent' => 10,
        ]);
        $isa = User::factory()->superAdmin()->create([
            'name' => 'Isa',
            'is_commercial' => false,
            'commission_percent' => $overrides['isa_percent'] ?? 5,
        ]);

        return [$admin, $fernanda, $isa];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function closedProposal(?User $seller, array $overrides = []): CommercialProposal
    {
        return CommercialProposal::query()->create(array_merge([
            'code' => 'PROP-EXTRA-BASE',
            'client_name' => 'Cliente Extra',
            'seller_id' => $seller?->id,
            'employee_count' => 10,
            'total_final_cents' => 5_000,
            'commission_percent' => $seller ? 10 : 0,
            'commission_cents' => $seller ? 500 : 0,
            'is_closed' => true,
            'closed_at' => now(),
            'list_status' => ProposalListStatus::CLOSED,
        ], $overrides));
    }

    private function saleFromProposal(
        CommercialProposal $proposal,
        User $seller,
        int $totalCents,
        int $commissionCents,
    ): CommercialSale {
        $sale = CommercialSale::query()->create([
            'code' => CommercialSale::nextCode(),
            'proposal_id' => $proposal->id,
            'client_name' => $proposal->client_name,
            'seller_id' => $seller->id,
            'total_cents' => $totalCents,
            'commission_percent' => 10,
            'commission_cents' => $commissionCents,
            'payment_method' => 'pix',
            'installments_count' => 1,
            'status' => CommercialSale::STATUS_ABERTA,
            'sold_at' => now(),
        ]);

        CommercialCommission::query()->create([
            'sale_id' => $sale->id,
            'seller_id' => $seller->id,
            'base_cents' => $totalCents,
            'percent' => 10,
            'amount_cents' => $commissionCents,
            'status' => CommercialCommission::STATUS_A_PAGAR,
        ]);

        return $sale;
    }

    private function settleSale(CommercialSale $sale): void
    {
        foreach ($sale->installments as $installment) {
            $installment->update([
                'status' => CommercialSaleInstallment::STATUS_PAGO,
                'paid_at' => now(),
                'paid_amount_cents' => $installment->amount_cents,
            ]);
        }

        $sale->recalculateStatus();
    }
}
