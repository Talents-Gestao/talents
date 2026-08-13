<?php

namespace Tests\Feature\Admin;

use App\Enums\LandingInterestSource;
use App\Mail\LandingInterestMail;
use App\Models\Company;
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
        $admin = User::factory()->superAdmin()->create();

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
    }

    public function test_manual_lead_requires_source(): void
    {
        Mail::fake();
        $admin = User::factory()->superAdmin()->create();

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
        $admin = User::factory()->superAdmin()->create();
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
                ->has('sourceOptions'));
    }

    public function test_super_admin_can_update_admin_notes(): void
    {
        $admin = User::factory()->superAdmin()->create();
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
        $admin = User::factory()->superAdmin()->create();
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
}
