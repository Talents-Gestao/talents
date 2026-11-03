<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ActionPlan;
use App\Models\AiSetting;
use App\Models\Department;
use App\Models\PsychosocialDocument;
use App\Models\PsychosocialReviewRound;
use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyMethodVersion;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Services\Nr1AiAnalyzer;
use App\Services\SurveyResultCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\CreatesSurveyFixtures;
use Tests\Support\CreatesV11Campaign;
use Tests\TestCase;

/**
 * Padrões conservadores até as respostas externas: parecer do V11 fora da empresa, recálculo travado depois de
 * publicado, envio sem duplicar e sem identificar ninguém, e Mia só com agregados acima do grupo mínimo.
 */
class Nr1SafetyAdjustmentsTest extends TestCase
{
    use CreatesSurveyFixtures;
    use CreatesV11Campaign;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /** @return object{company: mixed, survey: Survey, owner: User, admin: User} */
    private function v11(bool $published = true): object
    {
        $c = $this->v11Campaign(true);
        $c->survey->forceFill(['requires_publication' => true, 'published_at' => $published ? now() : null])->save();

        return (object) [
            'company' => $c->company,
            'survey' => $c->survey->fresh(),
            'owner' => User::factory()->talentsOwner()->mfaVerified()->create(),
            'admin' => User::factory()->companyAdmin($c->company->id)->create(),
        ];
    }

    /**
     * Setores com o número de respondentes pedido, sobre a pergunta única do fixture.
     *
     * @param  array<string, int>  $sectors
     */
    private function surveyWithSectors(array $sectors): object
    {
        $f = $this->createSurveyFixture();
        foreach ($sectors as $name => $count) {
            $department = Department::query()->create(['company_id' => $f->company->id, 'name' => $name]);
            for ($i = 0; $i < $count; $i++) {
                $response = SurveyResponse::query()->create(['survey_id' => $f->survey->id, 'session_token' => (string) Str::uuid(), 'department_id' => $department->id, 'completed_at' => now()]);
                SurveyAnswer::query()->create(['survey_response_id' => $response->id, 'survey_template_question_id' => $f->question->id, 'value' => 4]);
            }
        }
        app(SurveyResultCalculator::class)->recalculate($f->survey->fresh());

        return $f;
    }

    private function setting(): AiSetting
    {
        return AiSetting::query()->create(['provider' => 'openai', 'model' => 'gpt-test', 'is_enabled' => true, 'api_key' => 'chave-de-teste']);
    }

    public function test_v11_technical_opinion_never_reaches_the_company(): void
    {
        $v = $this->v11();
        ActionPlan::query()->create(['company_id' => $v->company->id, 'survey_id' => $v->survey->id, 'technical_opinion' => '<p>Parecer reservado.</p>', 'admin_published_at' => now(), 'technical_opinion_file_path' => 'x/parecer.pdf', 'technical_opinion_file_name' => 'parecer.pdf']);

        $page = $this->actingAs($v->admin)->get(route('nr1.documentos'))->assertOk()->assertInertia(fn (Assert $p) => $p->missing('section.documents.opinion'));
        $this->assertStringNotContainsString('Parecer reservado', $page->getContent());
        $this->actingAs($v->admin)->get(route('client.surveys.action-plan.technical-opinion-file', $v->survey))->assertNotFound();

        $this->actingAs($v->owner)->get(route('nr1.documentos', ['empresa' => $v->company->id, 'campanha' => $v->survey->id]))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('section.documents.opinion.html', '<p>Parecer reservado.</p>'));
    }

    public function test_legacy_opinion_stays_with_the_company_after_publication(): void
    {
        $f = $this->surveyWithSectors(['Produção' => 6]);
        $admin = User::factory()->companyAdmin($f->company->id)->create();
        ActionPlan::query()->create(['company_id' => $f->company->id, 'survey_id' => $f->survey->id, 'technical_opinion' => '<p>Parecer do laudo.</p>', 'admin_published_at' => now()]);

        $this->actingAs($admin)->get(route('nr1.parecer'))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('section.legacy.documents.actionPlan.plan.technical_opinion', '<p>Parecer do laudo.</p>'));
    }

    public function test_company_cannot_recalculate_a_published_campaign(): void
    {
        $published = $this->v11();
        $this->actingAs($published->admin)->post(route('client.surveys.recalculate', $published->survey))->assertForbidden();
        $this->actingAs($published->admin)->get(route('nr1.resultados'))->assertInertia(fn (Assert $p) => $p->where('section.results.recalculateUrl', null));

        // Modelo anterior: «publicado» também é o parecer publicado.
        $f = $this->surveyWithSectors(['Produção' => 6]);
        $admin = User::factory()->companyAdmin($f->company->id)->create();
        $this->actingAs($admin)->post(route('client.surveys.recalculate', $f->survey))->assertRedirect();
        ActionPlan::query()->create(['company_id' => $f->company->id, 'survey_id' => $f->survey->id, 'admin_published_at' => now()]);
        $this->actingAs($admin)->post(route('client.surveys.recalculate', $f->survey))->assertForbidden();
    }

    public function test_talents_recalculates_a_published_campaign_and_opens_a_new_version(): void
    {
        config(['mfa.step_up_required' => false]);
        $v = $this->v11();
        $url = route('admin.companies.surveys.recalculate', [$v->company->id, $v->survey->id]);

        $this->actingAs($v->owner)->get(route('nr1.resultados', ['empresa' => $v->company->id, 'campanha' => $v->survey->id]))
            ->assertInertia(fn (Assert $p) => $p->where('section.results.recalculateUrl', $url));
        $this->actingAs($v->owner)->post($url)->assertRedirect()->assertSessionHas('success');

        $round = PsychosocialReviewRound::query()->where('survey_id', $v->survey->id)->sole();
        $this->assertSame(PsychosocialReviewRound::STATUS_DRAFT, $round->status);
        $this->assertNotNull($round->technical_version_id);
        $this->assertNotNull($v->survey->fresh()->published_at);
        $this->assertSame(2, PsychosocialDocument::query()->where('survey_id', $v->survey->id)->count());
    }

    public function test_talents_recalculation_asks_for_recent_mfa(): void
    {
        config(['mfa.step_up_required' => true]);
        $v = $this->v11();

        $this->actingAs($v->owner)->post(route('admin.companies.surveys.recalculate', [$v->company->id, $v->survey->id]))->assertRedirect(route('mfa.challenge'));
    }

    public function test_double_click_saves_one_response(): void
    {
        $f = $this->createSurveyFixture();
        $payload = ['answers' => [$f->question->id => 3], 'submission_key' => 'chave-do-formulario'];

        $this->post(route('survey.public.submit', $f->survey->public_token), $payload)->assertRedirect(route('survey.public.thanks', $f->survey->public_token));
        $this->post(route('survey.public.submit', $f->survey->public_token), $payload)->assertRedirect(route('survey.public.thanks', $f->survey->public_token));

        $this->assertSame(1, SurveyResponse::query()->where('survey_id', $f->survey->id)->count());
    }

    public function test_resubmission_in_the_same_session_is_blocked_and_a_new_session_is_not(): void
    {
        $f = $this->createSurveyFixture();
        $url = route('survey.public.submit', $f->survey->public_token);

        $this->post($url, ['answers' => [$f->question->id => 3], 'submission_key' => 'primeira'])->assertRedirect();
        $this->get(route('survey.public', $f->survey->public_token))->assertInertia(fn (Assert $p) => $p->component('Survey/Closed'));
        $this->post($url, ['answers' => [$f->question->id => 2], 'submission_key' => 'segunda'])->assertRedirect(route('survey.public', $f->survey->public_token));
        $this->assertSame(1, SurveyResponse::query()->where('survey_id', $f->survey->id)->count());

        // Outra pessoa, outro navegador: a política formal (NR1_DUPLICATE_POLICY) segue a mesma.
        $this->flushSession();
        $this->post($url, ['answers' => [$f->question->id => 2], 'submission_key' => 'terceira'])->assertRedirect(route('survey.public.thanks', $f->survey->public_token));
        $this->assertSame(2, SurveyResponse::query()->where('survey_id', $f->survey->id)->count());
        $this->assertSame('none', config('nr1.collection.duplicate_policy'));
    }

    /** @return array<string, array{0: bool}> */
    public static function methods(): array
    {
        return ['modelo anterior' => [false], 'V11' => [true]];
    }

    #[DataProvider('methods')]
    public function test_mia_receives_only_aggregates_of_published_groups(bool $v11): void
    {
        $f = $this->surveyWithSectors(['Grande' => 9, 'Medio' => 6, 'Minusculo' => 2]);
        if ($v11) {
            $f->survey->forceFill(['method_version_id' => SurveyMethodVersion::idForCode(SurveyMethodVersion::CODE_TALENTS39_V1)])->save();
        }
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => 'ok']]], 'usage' => [], 'model' => 'gpt-test'])]);

        app(Nr1AiAnalyzer::class)->generateNarrative($f->survey->fresh(), $this->setting());

        Http::assertSent(function (HttpRequest $request) {
            $this->assertSame('https://api.openai.com/v1/chat/completions', $request->url());
            $body = collect($request['messages'])->firstWhere('role', 'user')['content'];
            $payload = json_decode($body, true);

            $this->assertStringNotContainsString('Minusculo', $body);
            // Com a cascata, o setor seguinte também sai: 2 + 6 reúnem 8, sem revelar nenhum por subtração.
            $this->assertStringNotContainsString('Medio', $body);
            $this->assertSame(['Grande'], array_column($payload['by_department_summary'], 'department'));
            foreach ($payload['by_department_and_dimension'] as $row) {
                $this->assertGreaterThanOrEqual(5, $row['respondent_count']);
            }
            foreach (['session_token', 'answers', 'age_range', 'tenure_range', 'value', 'completed_at', 'survey_response_id'] as $raw) {
                $this->assertStringNotContainsString('"'.$raw.'"', $body);
            }

            return true;
        });
    }

    public function test_mia_receives_nothing_below_the_minimum_and_the_company_does_not_see_it(): void
    {
        $f = $this->surveyWithSectors(['Pequeno' => 4]);
        $setting = $this->setting();
        Http::fake();

        try {
            app(Nr1AiAnalyzer::class)->generateNarrative($f->survey->fresh(), $setting);
            $this->fail('A análise deveria ser recusada.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('grupo mínimo', $exception->getMessage());
        }
        Http::assertNothingSent();

        $admin = User::factory()->companyAdmin($f->company->id)->create();
        $this->actingAs($admin)->get(route('nr1.resultados'))->assertOk()->assertInertia(fn (Assert $p) => $p->where('section.legacy.results.client.aiEnabled', false));
        $this->actingAs($admin)->post(route('client.surveys.ai-analysis', $f->survey))->assertForbidden();
    }
}
