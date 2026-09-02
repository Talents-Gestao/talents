<?php

namespace Tests\Feature\Admin;

use App\Enums\LandingInterestSource;
use App\Models\LandingInterestSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class LandingInterestIndexFilterTest extends TestCase
{
    use RefreshDatabase;
    public function test_index_filters_by_search_name_email_company_or_phone(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $matchName = LandingInterestSubmission::query()->create([
            'name' => 'Ana Silva',
            'email' => 'ana@example.com',
            'phone' => '(11) 90000-0001',
            'company' => 'Acme Ltda',
            'source' => LandingInterestSource::Site,
        ]);
        LandingInterestSubmission::query()->create([
            'name' => 'Bruno Costa',
            'email' => 'bruno@example.com',
            'phone' => '(11) 90000-0002',
            'company' => 'Outra Empresa',
            'source' => LandingInterestSource::Phone,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.landing-interest.index', ['search' => 'Ana Silva']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('submissions.data', 1)
                ->where('submissions.data.0.id', $matchName->id)
                ->where('filters.search', 'Ana Silva'));

        $this->actingAs($admin)
            ->get(route('admin.landing-interest.index', ['search' => 'bruno@example.com']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('submissions.data', 1)
                ->where('submissions.data.0.email', 'bruno@example.com'));

        $this->actingAs($admin)
            ->get(route('admin.landing-interest.index', ['search' => 'Acme']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('submissions.data', 1)
                ->where('submissions.data.0.id', $matchName->id));

        $this->actingAs($admin)
            ->get(route('admin.landing-interest.index', ['search' => '90000-0002']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('submissions.data', 1)
                ->where('submissions.data.0.email', 'bruno@example.com'));
    }

    public function test_index_filters_by_source_and_qualified(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $eventQualified = LandingInterestSubmission::query()->create([
            'name' => 'Evento Sim',
            'email' => 'evento-sim@example.com',
            'source' => LandingInterestSource::Event,
            'is_qualified' => true,
        ]);
        LandingInterestSubmission::query()->create([
            'name' => 'Evento Não',
            'email' => 'evento-nao@example.com',
            'source' => LandingInterestSource::Event,
            'is_qualified' => false,
        ]);
        LandingInterestSubmission::query()->create([
            'name' => 'Site Pendente',
            'email' => 'site-pendente@example.com',
            'source' => LandingInterestSource::Site,
            'is_qualified' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.landing-interest.index', [
                'source' => LandingInterestSource::Event->value,
                'qualified' => 'yes',
            ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('submissions.data', 1)
                ->where('submissions.data.0.id', $eventQualified->id)
                ->where('filters.source', 'event')
                ->where('filters.qualified', 'yes'));

        $this->actingAs($admin)
            ->get(route('admin.landing-interest.index', ['qualified' => 'pending']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('submissions.data', 1)
                ->where('submissions.data.0.email', 'site-pendente@example.com'));
    }

    public function test_index_filters_by_created_period(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $inside = LandingInterestSubmission::query()->create([
            'name' => 'Dentro',
            'email' => 'dentro@example.com',
            'source' => LandingInterestSource::Site,
        ]);
        $inside->forceFill([
            'created_at' => '2026-03-15 10:00:00',
            'updated_at' => '2026-03-15 10:00:00',
        ])->save();

        $outside = LandingInterestSubmission::query()->create([
            'name' => 'Fora',
            'email' => 'fora@example.com',
            'source' => LandingInterestSource::Site,
        ]);
        $outside->forceFill([
            'created_at' => '2026-01-05 10:00:00',
            'updated_at' => '2026-01-05 10:00:00',
        ])->save();

        $this->actingAs($admin)
            ->get(route('admin.landing-interest.index', [
                'created_from' => '2026-03-01',
                'created_to' => '2026-03-31',
            ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('submissions.data', 1)
                ->where('submissions.data.0.id', $inside->id)
                ->where('filters.created_from', '2026-03-01')
                ->where('filters.created_to', '2026-03-31'));
    }

    public function test_index_rejects_invalid_created_period(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $this->actingAs($admin)
            ->from(route('admin.landing-interest.index'))
            ->get(route('admin.landing-interest.index', [
                'created_from' => '2026-04-10',
                'created_to' => '2026-04-01',
            ]))
            ->assertRedirect(route('admin.landing-interest.index'))
            ->assertSessionHasErrors(['created_to']);
    }
}
