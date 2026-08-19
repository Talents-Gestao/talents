<?php

declare(strict_types=1);

namespace Tests\Feature\Notices;

use App\Actions\Notices\MarkNoticeRead;
use App\Actions\Notices\PublishCommercialNotice;
use App\Actions\Notices\PublishCompanyNotice;
use App\Actions\Notices\PublishLeadNotice;
use App\Enums\CompanyNoticeAudience;
use App\Enums\CompanyNoticeEventKind;
use App\Models\CommercialProposal;
use App\Models\CommercialSale;
use App\Models\CommercialSaleInstallment;
use App\Models\Company;
use App\Models\CompanyNotice;
use App\Models\LandingInterestSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->superAdmin()->create([
            'email' => 'admin@talents.local',
            'is_owner' => true,
        ]);
    }

    private function publishTalents(string $title, int $sourceId): CompanyNotice
    {
        return app(PublishCompanyNotice::class)->handle(
            companyId: null,
            title: $title,
            body: 'Corpo do aviso.',
            audience: CompanyNoticeAudience::Talents,
            sourceType: 'commercial_proposal',
            sourceId: $sourceId,
            eventKind: CompanyNoticeEventKind::ProposalWon,
            dedupeWithinMinutes: 5,
        );
    }

    public function test_talents_notice_is_created_without_company(): void
    {
        $notice = $this->publishTalents('Proposta fechada', 1);

        $this->assertNull($notice->company_id);
        $this->assertSame(CompanyNoticeAudience::Talents, $notice->audience);
    }

    public function test_publish_dedupes_same_source_within_window(): void
    {
        $first = $this->publishTalents('Proposta fechada', 42);
        $second = $this->publishTalents('Proposta fechada', 42);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, CompanyNotice::query()->count());
    }

    public function test_admin_recent_returns_talents_notices_and_unread_count(): void
    {
        $this->publishTalents('Proposta fechada', 7);

        $response = $this->actingAs($this->admin())->getJson(route('admin.notices.recent'));

        $response->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('notices.0.title', 'Proposta fechada');
    }

    public function test_admin_recent_includes_company_scoped_notices_with_company_name(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Teste',
            'cnpj' => '11.222.333/0001-44',
            'is_active' => true,
        ]);

        $this->publishTalents('Proposta fechada', 7);

        app(PublishCompanyNotice::class)->handle(
            companyId: (int) $company->id,
            title: 'Aviso da empresa',
            body: 'Somente para a empresa.',
            audience: CompanyNoticeAudience::Company,
        );

        $response = $this->actingAs($this->admin())->getJson(route('admin.notices.recent'));

        $response->assertOk()
            ->assertJsonPath('unread_count', 2)
            ->assertJsonCount(2, 'notices');

        $titles = collect($response->json('notices'))->pluck('title')->all();
        $this->assertContains('Proposta fechada', $titles);
        $this->assertContains('Aviso da empresa', $titles);

        $companyNotice = collect($response->json('notices'))
            ->firstWhere('title', 'Aviso da empresa');

        $this->assertSame('Empresa Teste', $companyNotice['company_name'] ?? null);
        $this->assertSame($company->id, $companyNotice['company_id'] ?? null);
    }

    public function test_admin_can_mark_company_notice_as_read(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Lida',
            'cnpj' => '22.333.444/0001-55',
            'is_active' => true,
        ]);

        $notice = app(PublishCompanyNotice::class)->handle(
            companyId: (int) $company->id,
            title: 'Feedback concluído',
            body: 'Corpo.',
            audience: CompanyNoticeAudience::Company,
        );

        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson(route('admin.notices.mark-read', $notice))
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->actingAs($admin)
            ->getJson(route('admin.notices.recent'))
            ->assertJsonPath('unread_count', 0)
            ->assertJsonPath('notices.0.read', true);
    }

    public function test_admin_can_mark_all_notices_as_read_including_company(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Mix',
            'cnpj' => '33.444.555/0001-66',
            'is_active' => true,
        ]);

        $this->publishTalents('Proposta A', 1);
        $this->publishTalents('Proposta B', 2);

        app(PublishCompanyNotice::class)->handle(
            companyId: (int) $company->id,
            title: 'Aviso company',
            body: 'Corpo.',
            audience: CompanyNoticeAudience::Company,
        );

        $admin = $this->admin();

        $this->actingAs($admin)->postJson(route('admin.notices.mark-all-read'))
            ->assertOk()
            ->assertJsonPath('marked', 3)
            ->assertJsonPath('unread_count', 0);

        $this->actingAs($admin)->getJson(route('admin.notices.recent'))
            ->assertJsonPath('unread_count', 0);
    }

    public function test_admin_recent_includes_destination_url_for_proposal(): void
    {
        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-2026-0022',
            'client_name' => 'Daniela',
            'employee_count' => 4,
            'total_final_cents' => 230_400,
            'is_closed' => false,
        ]);

        app(PublishCommercialNotice::class)->proposalCreated($proposal);

        $this->actingAs($this->admin())
            ->getJson(route('admin.notices.recent'))
            ->assertOk()
            ->assertJsonPath('notices.0.url', route('admin.comercial.propostas.edit', $proposal));
    }

    public function test_admin_open_proposal_notice_marks_read_and_redirects_to_edit(): void
    {
        $admin = $this->admin();
        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-OPEN-0001',
            'client_name' => 'Cliente Aviso',
            'employee_count' => 3,
            'total_final_cents' => 10_000,
            'is_closed' => false,
        ]);

        app(PublishCommercialNotice::class)->proposalCreated($proposal);
        $notice = CompanyNotice::query()->latest('id')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.notices.open', $notice))
            ->assertRedirect(route('admin.comercial.propostas.edit', $proposal));

        $this->assertTrue($notice->fresh()->isReadByUser((int) $admin->id));
    }

    public function test_admin_open_sale_and_installment_notices_go_to_sale_show(): void
    {
        $admin = $this->admin();

        $sale = CommercialSale::query()->create([
            'code' => 'VENDA-2026-0003',
            'client_name' => 'Cliente Venda',
            'total_cents' => 188_800,
            'payment_method' => 'pix',
            'installments_count' => 1,
            'status' => CommercialSale::STATUS_ABERTA,
            'sold_at' => now(),
        ]);

        app(PublishCommercialNotice::class)->saleCreated($sale);
        $saleNotice = CompanyNotice::query()->latest('id')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.notices.open', $saleNotice))
            ->assertRedirect(route('admin.financeiro.vendas.show', $sale));

        $installment = CommercialSaleInstallment::query()->create([
            'sale_id' => $sale->id,
            'number' => 1,
            'amount_cents' => 188_800,
            'due_date' => now()->toDateString(),
            'method' => 'pix',
            'status' => CommercialSaleInstallment::STATUS_PAGO,
            'paid_amount_cents' => 188_800,
        ]);

        app(PublishCommercialNotice::class)->installmentPaid($installment->load('sale'));
        $installmentNotice = CompanyNotice::query()->latest('id')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.notices.open', $installmentNotice))
            ->assertRedirect(route('admin.financeiro.vendas.show', $sale));
    }

    public function test_admin_open_lead_notice_goes_to_landing_interest_index(): void
    {
        $admin = $this->admin();
        $lead = LandingInterestSubmission::query()->create([
            'name' => 'Daniela',
            'email' => 'daniela@example.com',
            'phone' => '11999990000',
            'company' => 'Metamorfose pessoal',
            'source' => 'site',
        ]);

        app(PublishLeadNotice::class)->received($lead);
        $notice = CompanyNotice::query()->latest('id')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.notices.open', $notice))
            ->assertRedirect(route('admin.landing-interest.index'));

        $this->assertTrue($notice->fresh()->isReadByUser((int) $admin->id));
    }

    public function test_admin_recent_lists_more_than_eight_unread_notices(): void
    {
        $admin = $this->admin();

        for ($i = 1; $i <= 12; $i++) {
            $this->publishTalents("Aviso {$i}", $i);
        }

        $this->actingAs($admin)
            ->getJson(route('admin.notices.recent'))
            ->assertOk()
            ->assertJsonCount(12, 'notices')
            ->assertJsonPath('unread_count', 12)
            ->assertJsonPath('has_more', false);
    }

    public function test_admin_recent_lists_unread_before_read(): void
    {
        $admin = $this->admin();

        $read = $this->publishTalents('Já lido', 1);
        $read->forceFill(['published_at' => now()])->save();

        $unread = $this->publishTalents('Não lido', 2);
        $unread->forceFill(['published_at' => now()->subDay()])->save();

        app(MarkNoticeRead::class)->handle($read, $admin);

        $this->actingAs($admin)
            ->getJson(route('admin.notices.recent'))
            ->assertOk()
            ->assertJsonPath('notices.0.title', 'Não lido')
            ->assertJsonPath('notices.1.title', 'Já lido');
    }

    public function test_admin_recent_paginates_beyond_page_size(): void
    {
        $admin = $this->admin();

        for ($i = 1; $i <= 51; $i++) {
            $this->publishTalents("Aviso {$i}", $i);
        }

        $this->actingAs($admin)
            ->getJson(route('admin.notices.recent', ['page' => 1]))
            ->assertOk()
            ->assertJsonCount(50, 'notices')
            ->assertJsonPath('unread_count', 51)
            ->assertJsonPath('has_more', true);

        $this->actingAs($admin)
            ->getJson(route('admin.notices.recent', ['page' => 2]))
            ->assertOk()
            ->assertJsonCount(1, 'notices')
            ->assertJsonPath('has_more', false);
    }

    public function test_guest_cannot_delete_notices(): void
    {
        $this->delete(route('admin.notices.destroy', 1))
            ->assertRedirect(route('login'));

        $this->post(route('admin.notices.destroy-all'))
            ->assertRedirect(route('login'));

        $this->delete(route('client.notices.destroy', 1))
            ->assertRedirect(route('login'));

        $this->post(route('client.notices.destroy-all'))
            ->assertRedirect(route('login'));
    }

    public function test_admin_can_delete_one_notice(): void
    {
        $admin = $this->admin();
        $notice = $this->publishTalents('Aviso para excluir', 91);
        $this->publishTalents('Aviso que permanece', 92);

        $this->actingAs($admin)
            ->deleteJson(route('admin.notices.destroy', $notice))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('unread_count', 1);

        $this->assertDatabaseMissing('company_notices', ['id' => $notice->id]);
        $this->assertSame(1, CompanyNotice::query()->count());
    }

    public function test_admin_can_delete_all_visible_notices(): void
    {
        $admin = $this->admin();
        $this->publishTalents('Aviso 1', 101);
        $this->publishTalents('Aviso 2', 102);

        $company = Company::query()->create([
            'name' => 'Empresa Delete All',
            'cnpj' => '44.555.666/0001-77',
            'is_active' => true,
        ]);

        app(PublishCompanyNotice::class)->handle(
            companyId: (int) $company->id,
            title: 'Aviso company',
            body: 'Corpo.',
            audience: CompanyNoticeAudience::Company,
        );

        $this->actingAs($admin)
            ->postJson(route('admin.notices.destroy-all'))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('deleted', 3)
            ->assertJsonPath('unread_count', 0);

        $this->assertSame(0, CompanyNotice::query()->count());
    }

    public function test_client_can_delete_one_notice_of_own_company(): void
    {
        [$company, $user] = $this->companyClient();
        $notice = app(PublishCompanyNotice::class)->handle(
            companyId: (int) $company->id,
            title: 'Aviso da empresa',
            body: 'Corpo.',
            audience: CompanyNoticeAudience::Company,
        );
        $other = app(PublishCompanyNotice::class)->handle(
            companyId: (int) $company->id,
            title: 'Outro aviso',
            body: 'Corpo.',
            audience: CompanyNoticeAudience::Company,
        );

        $this->actingAs($user)
            ->deleteJson(route('client.notices.destroy', $notice))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('unread_count', 1);

        $this->assertDatabaseMissing('company_notices', ['id' => $notice->id]);
        $this->assertDatabaseHas('company_notices', ['id' => $other->id]);
    }

    public function test_client_cannot_delete_notice_of_another_company(): void
    {
        [, $user] = $this->companyClient();
        $otherCompany = Company::query()->create([
            'name' => 'Outra Empresa Avisos',
            'cnpj' => '55.666.777/0001-88',
            'is_active' => true,
        ]);
        $foreign = app(PublishCompanyNotice::class)->handle(
            companyId: (int) $otherCompany->id,
            title: 'Aviso alheio',
            body: 'Corpo.',
            audience: CompanyNoticeAudience::Company,
        );

        $this->actingAs($user)
            ->deleteJson(route('client.notices.destroy', $foreign))
            ->assertNotFound();

        $this->assertDatabaseHas('company_notices', ['id' => $foreign->id]);
    }

    public function test_client_delete_all_only_removes_own_company_notices(): void
    {
        [$company, $user] = $this->companyClient();
        app(PublishCompanyNotice::class)->handle(
            companyId: (int) $company->id,
            title: 'Aviso 1',
            body: 'Corpo.',
            audience: CompanyNoticeAudience::Company,
        );
        app(PublishCompanyNotice::class)->handle(
            companyId: (int) $company->id,
            title: 'Aviso 2',
            body: 'Corpo.',
            audience: CompanyNoticeAudience::Company,
        );

        $otherCompany = Company::query()->create([
            'name' => 'Empresa vizinha',
            'cnpj' => '66.777.888/0001-99',
            'is_active' => true,
        ]);
        $foreign = app(PublishCompanyNotice::class)->handle(
            companyId: (int) $otherCompany->id,
            title: 'Aviso da vizinha',
            body: 'Corpo.',
            audience: CompanyNoticeAudience::Company,
        );

        $this->publishTalents('Aviso interno Talents', 201);

        $this->actingAs($user)
            ->postJson(route('client.notices.destroy-all'))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('deleted', 2)
            ->assertJsonPath('unread_count', 0);

        $this->assertSame(0, CompanyNotice::query()->where('company_id', $company->id)->count());
        $this->assertDatabaseHas('company_notices', ['id' => $foreign->id]);
        $this->assertSame(1, CompanyNotice::query()->whereNull('company_id')->count());
    }

    /**
     * @return array{0: Company, 1: User}
     */
    private function companyClient(): array
    {
        $company = Company::query()->create([
            'name' => 'Empresa cliente avisos',
            'cnpj' => '88.999.000/0001-11',
            'is_active' => true,
        ]);

        $user = User::factory()->companyAdmin($company->id)->create([
            'email' => 'cliente-avisos@example.com',
        ]);

        return [$company, $user];
    }
}
