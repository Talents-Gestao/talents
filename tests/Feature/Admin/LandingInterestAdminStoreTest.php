<?php

namespace Tests\Feature\Admin;

use App\Enums\CompanyNoticeAudience;
use App\Enums\CompanyNoticeEventKind;
use App\Enums\LandingInterestSource;
use App\Mail\LandingInterestMail;
use App\Models\Company;
use App\Models\CompanyNotice;
use App\Models\LandingInterestSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class LandingInterestAdminStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_store_lead_manually(): void
    {
        Mail::fake();
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $response = $this->actingAs($admin)->post(route('admin.landing-interest.store'), [
            'name' => 'Lead Telefone',
            'email' => 'lead@example.com',
            'phone' => '(11) 90000-0000',
            'company' => 'Empresa X',
            'message' => 'Ligou pedindo proposta',
            'source' => LandingInterestSource::Phone->value,
        ]);

        $response->assertRedirect(route('admin.landing-interest.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('landing_interest_submissions', [
            'name' => 'Lead Telefone',
            'email' => 'lead@example.com',
            'source' => 'phone',
            'created_by' => $admin->id,
        ]);

        Mail::assertSent(LandingInterestMail::class);

        $notice = CompanyNotice::query()->latest('id')->first();
        $this->assertNotNull($notice);
        $this->assertNull($notice->target_user_id);
    }

    public function test_manual_lead_requires_source(): void
    {
        Mail::fake();
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $this->actingAs($admin)->post(route('admin.landing-interest.store'), [
            'name' => 'Sem origem',
            'email' => 'sem@example.com',
        ])->assertSessionHasErrors('source');

        Mail::assertNothingSent();
        $this->assertDatabaseCount('landing_interest_submissions', 0);
    }

    public function test_company_admin_cannot_store_admin_lead(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa cliente',
            'cnpj' => '55.555.555/0001-55',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
        ]);

        $this->actingAs(User::factory()->companyAdmin($company->id)->create())
            ->post(route('admin.landing-interest.store'), [
                'name' => 'Hack',
                'email' => 'hack@example.com',
                'source' => LandingInterestSource::Site->value,
            ])
            ->assertForbidden();
    }

    public function test_index_lists_source_label(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        LandingInterestSubmission::query()->create([
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'source' => LandingInterestSource::Event,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.landing-interest.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/LandingInterest/Index')
                ->has('submissions.data', 1)
                ->where('submissions.data.0.source', 'event')
                ->where('submissions.data.0.source_label', 'Evento')
                ->where('submissions.data.0.admin_notes', null)
                ->has('sourceOptions')
                ->has('correspondentUsers')
                ->where('filters.search', '')
                ->where('filters.source', '')
                ->where('filters.qualified', '')
                ->where('filters.created_from', '')
                ->where('filters.created_to', ''));
    }

    public function test_super_admin_can_update_admin_notes(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $lead = LandingInterestSubmission::query()->create([
            'name' => 'Bruno',
            'email' => 'bruno@example.com',
            'source' => LandingInterestSource::Site,
            'message' => 'Quero proposta',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.landing-interest.update', $lead), [
                'admin_notes' => 'Retornar na sexta com valores de NR-1.',
            ])
            ->assertRedirect(route('admin.landing-interest.index'))
            ->assertSessionHas('success');

        $this->assertSame(
            'Retornar na sexta com valores de NR-1.',
            $lead->fresh()->admin_notes,
        );

        $this->actingAs($admin)
            ->get(route('admin.landing-interest.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('submissions.data.0.admin_notes', 'Retornar na sexta com valores de NR-1.')
            );
    }

    public function test_super_admin_can_destroy_lead(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $lead = LandingInterestSubmission::query()->create([
            'name' => 'Carla',
            'email' => 'carla@example.com',
            'source' => LandingInterestSource::Phone,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.landing-interest.destroy', $lead))
            ->assertRedirect(route('admin.landing-interest.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('landing_interest_submissions', [
            'id' => $lead->id,
        ]);
    }

    public function test_company_admin_cannot_update_or_destroy_lead(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa cliente',
            'cnpj' => '66.666.666/0001-66',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
        ]);

        $lead = LandingInterestSubmission::query()->create([
            'name' => 'Diego',
            'email' => 'diego@example.com',
            'source' => LandingInterestSource::Site,
        ]);

        $user = User::factory()->companyAdmin($company->id)->create();

        $this->actingAs($user)
            ->patch(route('admin.landing-interest.update', $lead), [
                'admin_notes' => 'Hack',
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('admin.landing-interest.destroy', $lead))
            ->assertForbidden();

        $this->assertDatabaseHas('landing_interest_submissions', [
            'id' => $lead->id,
            'email' => 'diego@example.com',
        ]);
    }

    public function test_super_admin_can_mark_lead_as_qualified(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $lead = LandingInterestSubmission::query()->create([
            'name' => 'Qualificar',
            'email' => 'qualificar@example.com',
            'source' => LandingInterestSource::Site,
            'is_qualified' => null,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.landing-interest.update', $lead), [
                'admin_notes' => 'Reunião ok',
                'is_qualified' => true,
            ])
            ->assertRedirect(route('admin.landing-interest.index'));

        $lead->refresh();
        $this->assertTrue($lead->is_qualified);
        $this->assertSame('Reunião ok', $lead->admin_notes);

        $this->actingAs($admin)
            ->patch(route('admin.landing-interest.update', $lead), [
                'admin_notes' => 'Reunião ok',
                'is_qualified' => null,
            ])
            ->assertRedirect(route('admin.landing-interest.index'));

        $this->assertNull($lead->fresh()->is_qualified);
    }

    public function test_index_lists_active_commercial_users_and_current_admin(): void
    {
        $this->withoutVite();
        $admin = User::factory()->superAdmin()->create(['is_owner' => true, 'name' => 'Admin Leads']);
        $seller = User::factory()->superAdmin()->create([
            'is_commercial' => true,
            'name' => 'Vendedor Leads',
        ]);
        User::factory()->superAdmin()->create([
            'is_commercial' => false,
            'is_active' => false,
            'name' => 'Inativo',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.landing-interest.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/LandingInterest/Index')
                ->has('correspondentUsers')
                ->where('correspondentUsers', function ($users) use ($admin, $seller): bool {
                    $ids = collect($users)->pluck('id')->all();

                    return in_array($admin->id, $ids, true)
                        && in_array($seller->id, $ids, true);
                }));
    }

    public function test_admin_can_assign_lead_to_another_commercial_user_and_notifies_team(): void
    {
        Mail::fake();
        $admin = User::factory()->superAdmin()->create(['is_owner' => true, 'name' => 'Admin Talents']);
        $seller = User::factory()->superAdmin()->create([
            'is_commercial' => true,
            'is_active' => true,
            'name' => 'Vendedor Destino',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.landing-interest.store'), [
            'name' => 'Lead Delegado',
            'email' => 'delegado@example.com',
            'source' => LandingInterestSource::Phone->value,
            'assigned_to' => $seller->id,
        ]);

        $response->assertRedirect(route('admin.landing-interest.index'));
        $response->assertSessionHas(
            'success',
            fn ($value) => str_contains((string) $value, 'Vendedor Destino'),
        );

        $this->assertDatabaseHas('landing_interest_submissions', [
            'email' => 'delegado@example.com',
            'created_by' => $admin->id,
            'assigned_to' => $seller->id,
        ]);

        $notice = CompanyNotice::query()
            ->where('event_kind', CompanyNoticeEventKind::LeadReceived)
            ->latest('id')
            ->first();

        $this->assertNotNull($notice);
        $this->assertSame(CompanyNoticeAudience::Talents, $notice->audience);
        $this->assertSame($seller->id, $notice->target_user_id);
        $this->assertSame('Lead cadastrado para Vendedor Destino', $notice->title);
        $this->assertStringContainsString('Admin Talents', (string) $notice->body);
        $this->assertStringContainsString('Vendedor Destino', (string) $notice->body);

        $this->actingAs($seller)
            ->getJson(route('admin.notices.recent'))
            ->assertOk()
            ->assertJsonPath('unread_assigned_leads_count', 1);

        $this->actingAs($admin)
            ->getJson(route('admin.notices.recent'))
            ->assertOk()
            ->assertJsonPath('unread_assigned_leads_count', 0);
    }

    public function test_cannot_assign_lead_to_non_commercial_user(): void
    {
        Mail::fake();
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $other = User::factory()->superAdmin()->create([
            'is_commercial' => false,
            'name' => 'Operações',
        ]);

        $this->actingAs($admin)->post(route('admin.landing-interest.store'), [
            'name' => 'Lead Inválido',
            'email' => 'invalido@example.com',
            'source' => LandingInterestSource::Phone->value,
            'assigned_to' => $other->id,
        ])->assertSessionHasErrors('assigned_to');

        Mail::assertNothingSent();
        $this->assertDatabaseCount('landing_interest_submissions', 0);
    }
}
