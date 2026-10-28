<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Models\Company;
use App\Models\PurposeMapCampaign;
use App\Models\PurposeMapResponse;
use App\Models\User;
use App\Services\PurposeMapThemeAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\CreatesSurveyFixtures;
use Tests\TestCase;

class PurposeMapTest extends TestCase
{
    use CreatesSurveyFixtures;
    use RefreshDatabase;

    private function makeCompanyWithAdmin(): object
    {
        $company = Company::query()->create([
            'name' => 'Escritório Pasqualino',
            'cnpj' => '22.222.222/0001-22',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
        ]);
        $this->subscribeCompanyToNr1($company);

        $admin = User::factory()->companyAdmin($company->id)->create();

        return (object) compact('company', 'admin');
    }

    public function test_client_index_opens_dashboard_with_questions_button(): void
    {
        $fx = $this->makeCompanyWithAdmin();

        $this->actingAs($fx->admin)
            ->get(route('client.mapa-proposito.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Client/PurposeMap/Show')
                ->has('dashboard')
                ->has('questionsUrl')
            );

        $campaign = PurposeMapCampaign::query()->firstOrFail();
        $this->assertSame($fx->company->id, $campaign->company_id);
    }

    public function test_public_form_accepts_anonymous_response(): void
    {
        $fx = $this->makeCompanyWithAdmin();
        $campaign = PurposeMapCampaign::query()->create([
            'company_id' => $fx->company->id,
            'title' => 'Mapa teste',
            'public_token' => (string) Str::uuid(),
            'status' => 'active',
            'starts_at' => now()->subHour(),
            'ends_at' => null,
        ]);

        $this->get(route('purpose-map.public', $campaign->public_token))->assertOk();

        $this->post(route('purpose-map.public.submit', $campaign->public_token), [
            'sector' => 'COMERCIAL',
            'why_work' => 'Para crescer e ajudar clientes todos os dias.',
            'dream' => 'Construir um time de referência no mercado.',
            'pain_self' => 'Resolve a falta de clareza comercial.',
            'pain_sector' => 'Resolve a conversão inconsistente.',
            'pain_company' => 'Resolve a desorganização do crescimento.',
        ])->assertRedirect(route('purpose-map.public.thanks', $campaign->public_token));

        $this->assertDatabaseHas('purpose_map_responses', [
            'purpose_map_campaign_id' => $campaign->id,
            'sector' => 'COMERCIAL',
        ]);
    }

    public function test_theme_analyzer_fallback_creates_themes_and_insights(): void
    {
        $fx = $this->makeCompanyWithAdmin();
        $campaign = PurposeMapCampaign::query()->create([
            'company_id' => $fx->company->id,
            'title' => 'Mapa análise',
            'public_token' => (string) Str::uuid(),
            'status' => 'active',
            'starts_at' => now()->subHour(),
            'ends_at' => null,
        ]);

        PurposeMapResponse::query()->create([
            'purpose_map_campaign_id' => $campaign->id,
            'sector' => 'FINANCEIRO',
            'why_work' => 'Sustento familiar e realização profissional no financeiro.',
            'dream' => 'Liderar o financeiro com excelência e tranquilidade.',
            'pain_self' => 'Dores de fluxo de caixa e cobrança.',
            'pain_sector' => 'Dores de atraso e previsibilidade financeira.',
            'pain_company' => 'Dores de sustentabilidade do negócio.',
            'respondent_cookie' => Str::random(40),
        ]);

        app(PurposeMapThemeAnalyzer::class)->analyze($campaign->fresh());

        $this->assertTrue($campaign->fresh()->themes()->exists());
        $this->assertTrue($campaign->fresh()->insights()->exists());
        $this->assertNotNull($campaign->fresh()->themes_analyzed_at);
    }
}
