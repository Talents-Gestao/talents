<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\CommercialContract;
use App\Models\CommercialProposal;
use App\Models\CommercialSale;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClosedContractsIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_super_admin_can_list_only_closed_proposals(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $closed = CommercialProposal::query()->create([
            'code' => 'PROP-CLOSE-0001',
            'client_name' => 'Cliente Fechado SA',
            'client_cnpj' => '12.345.678/0001-90',
            'employee_count' => 10,
            'total_final_cents' => 50000,
            'is_closed' => true,
            'closed_at' => now()->subDay(),
        ]);

        CommercialProposal::query()->create([
            'code' => 'PROP-OPEN-0001',
            'client_name' => 'Cliente Aberto',
            'employee_count' => 5,
            'total_final_cents' => 1000,
            'is_closed' => false,
            'closed_at' => null,
        ]);

        $contract = CommercialContract::query()->create([
            'code' => 'CONTR-2026-0001',
            'proposal_id' => $closed->id,
            'template_name_snapshot' => 'Template demo',
            'pdf_path' => 'contracts/demo.pdf',
            'html_snapshot' => '<p>demo</p>',
            'generated_at' => now(),
            'zapsign_status' => 'sent',
            'zapsign_sent_at' => now(),
        ]);

        $sale = CommercialSale::query()->create([
            'code' => 'VENDA-2026-0001',
            'proposal_id' => $closed->id,
            'client_name' => $closed->client_name,
            'client_cnpj' => $closed->client_cnpj,
            'total_cents' => 50000,
            'status' => CommercialSale::STATUS_ABERTA,
            'sold_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.contratos-fechados.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/ClosedContracts/Index')
                ->has('proposals.data', 1)
                ->where('proposals.data.0.code', 'PROP-CLOSE-0001')
                ->where('proposals.data.0.badges.closed', true)
                ->where('proposals.data.0.badges.has_contract', true)
                ->where('proposals.data.0.badges.has_zapsign', true)
                ->where('proposals.data.0.badges.has_sale', true)
                ->where('proposals.data.0.latest_contract_id', $contract->id)
                ->where('proposals.data.0.sale_id', $sale->id));
    }

    public function test_search_filter_matches_client_name(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        CommercialProposal::query()->create([
            'code' => 'PROP-CLOSE-0002',
            'client_name' => 'Alpha Fechada',
            'employee_count' => 3,
            'total_final_cents' => 2000,
            'is_closed' => true,
            'closed_at' => now(),
        ]);

        CommercialProposal::query()->create([
            'code' => 'PROP-CLOSE-0003',
            'client_name' => 'Beta Fechada',
            'employee_count' => 3,
            'total_final_cents' => 3000,
            'is_closed' => true,
            'closed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.contratos-fechados.index', ['search' => 'Alpha']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/ClosedContracts/Index')
                ->has('proposals.data', 1)
                ->where('proposals.data.0.client_name', 'Alpha Fechada'));
    }

    public function test_has_sale_filter_limits_results(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $withSale = CommercialProposal::query()->create([
            'code' => 'PROP-CLOSE-0004',
            'client_name' => 'Com Venda',
            'employee_count' => 2,
            'total_final_cents' => 4000,
            'is_closed' => true,
            'closed_at' => now(),
        ]);

        CommercialProposal::query()->create([
            'code' => 'PROP-CLOSE-0005',
            'client_name' => 'Sem Venda',
            'employee_count' => 2,
            'total_final_cents' => 4000,
            'is_closed' => true,
            'closed_at' => now(),
        ]);

        CommercialSale::query()->create([
            'code' => 'VENDA-2026-0002',
            'proposal_id' => $withSale->id,
            'client_name' => $withSale->client_name,
            'total_cents' => 4000,
            'status' => CommercialSale::STATUS_ABERTA,
            'sold_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.contratos-fechados.index', ['has_sale' => 1]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('proposals.data', 1)
                ->where('proposals.data.0.code', 'PROP-CLOSE-0004'));
    }

    public function test_coming_soon_redirects_to_closed_contracts_index(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get(route('admin.coming-soon.show', 'contratos-fechados'))
            ->assertRedirect(route('admin.contratos-fechados.index'));
    }

    public function test_company_admin_cannot_access_closed_contracts(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa cliente',
            'cnpj' => '55.555.555/0001-55',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
        ]);

        $this->actingAs(User::factory()->companyAdmin($company->id)->create())
            ->get(route('admin.contratos-fechados.index'))
            ->assertForbidden();
    }
}
