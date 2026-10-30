<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyMethodVersion;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Services\SurveyResultCalculator;
use App\Support\Nr1BandLabels;
use App\Support\Nr1Scoring;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesSurveyFixtures;
use Tests\Support\CreatesV11Campaign;
use Tests\TestCase;

/**
 * Cada campanha mostra o vocabulário do SEU relatório: mapa único por método e nenhuma tela com o rótulo do outro método.
 * Documentos congelados ficam fora: o texto deles vem do snapshot.
 */
class Nr1BandLabelsByMethodTest extends TestCase
{
    use CreatesSurveyFixtures;
    use CreatesV11Campaign;
    use RefreshDatabase;

    private const V11_ONLY = '/Menor sinalização|Maior sinalização/iu';

    private const LEGACY_ONLY = '/\b(Leve|Moderado|Grave)\b/iu';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /** @return array<string, array{0: ?string, 1: string, 2: string}> */
    public static function bands(): array
    {
        return [
            'legado green' => [SurveyMethodVersion::CODE_PRODUCTION_V1, 'green', 'Leve'],
            'legado yellow' => [SurveyMethodVersion::CODE_PRODUCTION_V1, 'yellow', 'Moderado'],
            'legado red' => [SurveyMethodVersion::CODE_PRODUCTION_V1, 'red', 'Grave'],
            'sem método green' => [null, 'green', 'Leve'],
            'V11 green' => [SurveyMethodVersion::CODE_TALENTS39_V1, 'green', 'Menor sinalização'],
            'V11 yellow' => [SurveyMethodVersion::CODE_TALENTS39_V1, 'yellow', 'Atenção'],
            'V11 red' => [SurveyMethodVersion::CODE_TALENTS39_V1, 'red', 'Maior sinalização'],
        ];
    }

    #[DataProvider('bands')]
    public function test_each_band_has_the_label_of_its_method(?string $method, string $band, string $label): void
    {
        $this->assertSame($label, Nr1BandLabels::label($band, $method));
        $this->assertSame($label, Nr1Scoring::riskLabel($band, $method));
        $this->assertSame($label, collect(Nr1BandLabels::bands($method))->firstWhere('risk', $band)['label']);
    }

    public function test_the_band_explanations_stay_in_the_vocabulary_of_the_method(): void
    {
        $this->assertDoesNotMatchRegularExpression(self::V11_ONLY, json_encode(Nr1BandLabels::bands(SurveyMethodVersion::CODE_PRODUCTION_V1), JSON_UNESCAPED_UNICODE));
        $this->assertDoesNotMatchRegularExpression(self::LEGACY_ONLY, json_encode(Nr1BandLabels::bands(SurveyMethodVersion::CODE_TALENTS39_V1), JSON_UNESCAPED_UNICODE));
    }

    public function test_the_legacy_campaign_screens_use_the_labels_of_the_legacy_report(): void
    {
        $f = $this->createSurveyFixture();
        $department = Department::query()->create(['company_id' => $f->company->id, 'name' => 'Produção']);
        for ($i = 0; $i < 6; $i++) {
            $response = SurveyResponse::query()->create(['survey_id' => $f->survey->id, 'session_token' => (string) Str::uuid(), 'department_id' => $department->id, 'completed_at' => now()]);
            SurveyAnswer::query()->create(['survey_response_id' => $response->id, 'survey_template_question_id' => $f->question->id, 'value' => 3]);
        }
        app(SurveyResultCalculator::class)->recalculate($f->survey->fresh());
        $survey = $f->survey->fresh();
        $owner = User::factory()->talentsOwner()->create();
        $admin = User::factory()->companyAdmin($f->company->id)->create();
        $query = ['empresa' => $f->company->id, 'campanha' => $survey->id];

        $this->actingAs($owner)->get(route('nr1.visao-geral', $query))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('section.legacy.overview.dimensions.0.band_label', 'Moderado')
            ->where('section.legacy.overview.cards.overall.band_label', 'Moderado')
            ->where('section.legacy.overview.cards.overall.band_labels', Nr1BandLabels::LEGACY));
        $this->actingAs($owner)->get(route('nr1.resultados', $query))->assertInertia(fn (Assert $p) => $p
            ->where('section.legacy.results.panel.bandLabels', Nr1BandLabels::LEGACY));

        foreach (['nr1.visao-geral', 'nr1.resultados', 'nr1.parecer', 'nr1.plano-de-acao', 'nr1.solucoes', 'nr1.documentos', 'nr1.campanha'] as $screen) {
            $this->assertScreenHasNo(self::V11_ONLY, $this->actingAs($owner)->get(route($screen, $query)), $screen);
        }

        $this->assertScreenHasNo(self::V11_ONLY, $this->actingAs($admin)->get(route('client.surveys.results', $survey)), 'resultados da empresa');
        $this->assertScreenHasNo(self::V11_ONLY, $this->actingAs($admin)->get(route('client.dashboard')), 'painel da empresa');
    }

    public function test_the_v11_campaign_screens_keep_the_perception_labels(): void
    {
        $campaign = $this->v11Campaign(true);
        $campaign->survey->forceFill(['requires_publication' => true, 'published_at' => now()])->save();
        $survey = $campaign->survey->fresh();
        $owner = User::factory()->talentsOwner()->mfaVerified()->create();
        $admin = User::factory()->companyAdmin($campaign->company->id)->create();
        $query = ['empresa' => $campaign->company->id, 'campanha' => $survey->id];

        $this->actingAs($owner)->get(route('nr1.visao-geral', $query))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('section.overview.cards.overall.band_labels', Nr1BandLabels::V11));
        $this->actingAs($owner)->get(route('nr1.resultados', $query))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('section.results.bands.0.label', 'Menor sinalização')
            ->where('section.results.bands.0.text', 'Menor sinalização de fatores desfavoráveis.'));

        foreach (['nr1.visao-geral', 'nr1.resultados', 'nr1.parecer', 'nr1.plano-de-acao', 'nr1.solucoes', 'nr1.documentos', 'nr1.campanha'] as $screen) {
            // Seção fora do perfil redireciona para a primeira disponível: vale a tela que abre.
            $this->assertScreenHasNo(self::LEGACY_ONLY, $this->actingAs($owner)->followingRedirects()->get(route($screen, $query)), $screen);
        }

        foreach (['nr1.visao-geral', 'nr1.resultados', 'nr1.plano-de-acao', 'nr1.solucoes', 'nr1.documentos'] as $screen) {
            $this->assertScreenHasNo(self::LEGACY_ONLY, $this->actingAs($admin)->get(route($screen)), $screen.' (empresa)');
        }

        $this->assertScreenHasNo(self::LEGACY_ONLY, $this->actingAs($admin)->get(route('client.dashboard')), 'painel da empresa');
    }

    private function assertScreenHasNo(string $pattern, mixed $response, string $screen): void
    {
        $response->assertOk();
        $this->assertDoesNotMatchRegularExpression(
            $pattern,
            json_encode($response->viewData('page')['props'], JSON_UNESCAPED_UNICODE),
            "Rótulo do outro método em {$screen}",
        );
    }
}
