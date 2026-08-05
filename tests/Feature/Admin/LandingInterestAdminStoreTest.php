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
                ->has('sourceOptions'));
    }
}
