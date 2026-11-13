<?php

// Cartão do quadro SUPORTE: «LEADS» (cadastrar lead manualmente e converter o lead em cliente/empresa).
// Já coberto: cadastro manual com aviso e e-mail (Admin/LandingInterestAdminStoreTest::test_super_admin_can_store_lead_manually).

namespace Tests\Feature\Acceptance\Support;

use App\Actions\SyncAdminUserPermissions;
use App\Enums\LandingInterestSource;
use App\Mail\CompanyAdminInvitationMail;
use App\Models\Company;
use App\Models\LandingInterestSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class LeadsTest extends TestCase
{
    use RefreshDatabase;

    private function manualLead(User $admin): LandingInterestSubmission
    {
        Mail::fake();
        $this->actingAs($admin)->post(route('admin.landing-interest.store'), [
            'name' => 'Maria Compras',
            'email' => 'maria@empresa-lead.test',
            'phone' => '(11) 90000-0000',
            'company' => 'Empresa Lead',
            'message' => 'Quer NR-1',
            'source' => LandingInterestSource::Phone->value,
        ])->assertRedirect(route('admin.landing-interest.index'));

        return LandingInterestSubmission::query()->where('email', 'maria@empresa-lead.test')->firstOrFail();
    }

    public function test_manual_lead_appears_in_the_list_and_opens_a_prefilled_new_proposal(): void
    {
        $this->withoutVite();
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $lead = $this->manualLead($admin);

        $this->get(route('admin.landing-interest.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('submissions.data.0.id', $lead->id));

        $this->get(route('admin.comercial.propostas.index', ['form' => 'create', 'lead_id' => $lead->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('formModal.mode', 'create')
                ->where('formModal.fromLead.client_name', 'Empresa Lead')
                ->where('formModal.fromLead.client_email', 'maria@empresa-lead.test'));
    }

    public function test_lead_is_converted_into_a_client_company(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $lead = $this->manualLead($admin);

        $this->assertTrue(
            Route::has('admin.landing-interest.convert'),
            'Não existe rota para converter lead em empresa.',
        );

        $this->post(route('admin.landing-interest.convert', $lead))->assertRedirect();
        $this->assertDatabaseHas('companies', ['name' => 'Empresa Lead', 'contact_email' => 'maria@empresa-lead.test']);
    }

    public function test_conversion_invites_the_first_company_admin_marks_the_lead_and_cannot_be_repeated(): void
    {
        $this->withoutVite();
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $lead = $this->manualLead($admin);

        $this->post(route('admin.landing-interest.convert', $lead))->assertSessionHas('success');

        $company = Company::query()->where('name', 'Empresa Lead')->firstOrFail();
        $companyAdmin = User::query()->where('email', 'maria@empresa-lead.test')->firstOrFail();
        $this->assertSame('Maria Compras', $companyAdmin->name);
        $this->assertNotNull($companyAdmin->companyWorkspace($company->id));
        Mail::assertSent(CompanyAdminInvitationMail::class, fn ($mail) => $mail->hasTo('maria@empresa-lead.test'));

        $lead->refresh();
        $this->assertSame($company->id, (int) $lead->converted_company_id);
        $this->assertNotNull($lead->converted_at);

        $this->get(route('admin.landing-interest.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('submissions.data.0.converted_company.id', $company->id));

        // Segunda conversão: recusada, sem criar outra empresa.
        $this->post(route('admin.landing-interest.convert', $lead))
            ->assertRedirect(route('admin.landing-interest.index'))
            ->assertSessionHas('error');
        $this->assertSame(1, Company::query()->where('name', 'Empresa Lead')->count());
    }

    public function test_conversion_needs_both_leads_and_companies_permissions_and_a_free_email(): void
    {
        $owner = User::factory()->superAdmin()->create(['is_owner' => true]);
        $lead = $this->manualLead($owner);

        $grant = function (array $permissions): User {
            $user = User::factory()->superAdmin()->create();
            app(SyncAdminUserPermissions::class)->execute(
                $user->talentsWorkspace(),
                array_map(fn (array $pair) => ['module' => $pair[0], 'action' => $pair[1]], $permissions),
            );

            return User::query()->findOrFail($user->id);
        };

        $onlyLeads = $grant([['landing_interest', 'view'], ['landing_interest', 'create']]);
        $this->actingAs($onlyLeads)->post(route('admin.landing-interest.convert', $lead))->assertForbidden();

        $onlyCompanies = $grant([['companies', 'view'], ['companies', 'create']]);
        $this->actingAs($onlyCompanies)->post(route('admin.landing-interest.convert', $lead))->assertForbidden();
        $this->assertDatabaseMissing('companies', ['name' => 'Empresa Lead']);

        // E-mail do lead já pertence a um usuário: não converte e explica.
        User::factory()->create(['email' => 'maria@empresa-lead.test']);
        $this->actingAs($owner)->post(route('admin.landing-interest.convert', $lead))
            ->assertRedirect(route('admin.landing-interest.index'))
            ->assertSessionHas('error');
        $this->assertDatabaseMissing('companies', ['name' => 'Empresa Lead']);
        $this->assertNull($lead->fresh()->converted_at);

        $both = $grant([['landing_interest', 'view'], ['landing_interest', 'create'], ['companies', 'view'], ['companies', 'create']]);
        $other = LandingInterestSubmission::query()->create([
            'name' => 'João Outro',
            'email' => 'joao@outro-lead.test',
            'source' => LandingInterestSource::Phone,
        ]);
        $this->actingAs($both)->post(route('admin.landing-interest.convert', $other))->assertSessionHas('success');
        $this->assertDatabaseHas('companies', ['name' => 'João Outro', 'contact_email' => 'joao@outro-lead.test']);
    }
}
