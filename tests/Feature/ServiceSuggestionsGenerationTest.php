<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CommercialProductPricingType;
use App\Models\AiSetting;
use App\Models\CommercialProduct;
use App\Models\Nr1Opportunity;
use App\Models\SurveyResult;
use App\Models\SurveyServiceSuggestion;
use App\Models\User;
use App\Services\ServiceSuggestionsGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CreatesSurveyFixtures;
use Tests\TestCase;

class ServiceSuggestionsGenerationTest extends TestCase
{
    use CreatesSurveyFixtures;
    use RefreshDatabase;

    public function test_parser_keeps_only_catalog_products(): void
    {
        $rows = app(ServiceSuggestionsGenerator::class)->parseSuggestions(
            '{"suggestions":[{"product_id":7,"rationale":"Cabe no cenário."},{"product_id":99,"rationale":"Inventado."}]}',
            [['id' => 7, 'name' => 'Diagnóstico', 'description' => null]],
        );

        $this->assertCount(1, $rows);
        $this->assertSame('Diagnóstico', $rows[0]['product_name']);
    }

    public function test_generation_stays_pending_until_talents_releases_it_to_the_company(): void
    {
        $fx = $this->createSurveyFixture(['requires_publication' => false, 'status' => 'closed', 'ends_at' => now()->subDay()]);
        SurveyResult::query()->create([
            'survey_id' => $fx->survey->id,
            'survey_template_section_id' => null,
            'department_id' => null,
            'average_score' => '3.400',
            'risk_level' => 'yellow',
            'respondent_count' => 12,
        ]);
        $product = CommercialProduct::query()->create([
            'name' => 'Diagnóstico empresarial',
            'slug' => 'diagnostico-empresarial',
            'description' => 'Leitura da gestão de pessoas.',
            'pricing_type' => CommercialProductPricingType::Fixed,
            'pricing_config' => ['amount_cents' => 1000],
            'is_active' => true,
        ]);
        AiSetting::query()->create([
            'provider' => 'openai',
            'model' => 'gpt-test',
            'is_enabled' => true,
            'api_key' => 'chave-de-teste',
            'max_tokens' => 800,
            'temperature' => 0.2,
        ]);
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"suggestions":[{"product_id":'.$product->id.',"rationale":"O resultado pede uma leitura mais funda da gestão."}]}',
                    ],
                ]],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 20],
                'model' => 'gpt-test',
            ]),
        ]);

        $count = app(ServiceSuggestionsGenerator::class)->generate($fx->survey->fresh(), AiSetting::current());

        $this->assertSame(1, $count);
        $suggestion = SurveyServiceSuggestion::query()->where('survey_id', $fx->survey->id)->sole();
        $this->assertSame(SurveyServiceSuggestion::STATUS_PENDING, $suggestion->status);

        $owner = User::factory()->talentsOwner()->create();
        $admin = User::factory()->companyAdmin($fx->company->id)->create();
        $q = ['empresa' => $fx->company->id, 'campanha' => $fx->survey->id];

        $this->actingAs($owner)->get(route('nr1.solucoes', $q))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('section.solutions.serviceSuggestions.items.0.product_name', 'Diagnóstico empresarial')
            ->where('section.solutions.serviceSuggestions.items.0.status', 'pending'));

        $this->actingAs($admin)->get(route('nr1.solucoes'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('section.solutions.serviceSuggestions.items', []));

        $this->actingAs($owner)->post(route('admin.companies.surveys.service-suggestions.release', [$fx->company->id, $fx->survey->id]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->actingAs($admin)->get(route('nr1.solucoes'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('section.solutions.serviceSuggestions.items.0.status', 'approved')
            ->where('section.solutions.serviceSuggestions.items.0.rationale', 'O resultado pede uma leitura mais funda da gestão.'));

        $this->actingAs($admin)->post(route('client.surveys.service-suggestions.request', [$fx->survey->id, $suggestion->id]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTrue(Nr1Opportunity::query()->where('survey_id', $fx->survey->id)->where('service_key', 'Diagnóstico empresarial')->exists());
    }
}
