<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Survey;
use App\Models\SurveyAudienceLink;
use App\Models\SurveyInvitationToken;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Services\SurveyDuplicateGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CreatesSurveyFixtures;
use Tests\TestCase;

/**
 * Etapa 3: links por público (Time/Líderes) com janela própria, QR code, política de envio duplicado
 * atrás de configuração (padrão: nenhuma) e rascunho só no navegador, desligado por padrão.
 */
class Nr1V11CollectionTest extends TestCase
{
    use CreatesSurveyFixtures;
    use RefreshDatabase;

    /**
     * @return object{fx: object, user: User, team: SurveyAudienceLink, leaders: SurveyAudienceLink}
     */
    private function campaignWithAudiences(): object
    {
        $fx = $this->createSurveyFixture();
        $user = User::factory()->companyAdmin($fx->company->id)->create();

        $this->actingAs($user)->post(route('client.surveys.audiences.store', $fx->survey))->assertRedirect();

        return (object) [
            'fx' => $fx,
            'user' => $user,
            'team' => SurveyAudienceLink::query()->where('survey_id', $fx->survey->id)->where('audience', 'time')->firstOrFail(),
            'leaders' => SurveyAudienceLink::query()->where('survey_id', $fx->survey->id)->where('audience', 'lideres')->firstOrFail(),
        ];
    }

    private function answers(object $fx, int $value = 4): array
    {
        return ['answers' => [$fx->question->id => $value]];
    }

    public function test_company_creates_one_link_per_audience_and_it_is_idempotent(): void
    {
        $campaign = $this->campaignWithAudiences();
        $this->actingAs($campaign->user)->post(route('client.surveys.audiences.store', $campaign->fx->survey))->assertRedirect();

        $this->assertSame(2, SurveyAudienceLink::query()->where('survey_id', $campaign->fx->survey->id)->count());
        $this->assertNotSame($campaign->team->token, $campaign->leaders->token);
        $this->assertNotSame($campaign->fx->survey->public_token, $campaign->team->token);

        $this->actingAs($campaign->user)
            ->get(route('client.surveys.show', $campaign->fx->survey))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Client/Surveys/Show')
                ->where('publicUrl', null)
                ->has('audienceLinks', 2)
                ->where('audienceLinks.0.audience', 'lideres')
                ->where('audienceLinks.1.audience', 'time'));
    }

    public function test_general_link_stops_working_once_audience_links_exist(): void
    {
        $campaign = $this->campaignWithAudiences();

        $this->get(route('survey.public', $campaign->fx->survey->public_token))->assertNotFound();
        $this->post(route('survey.public.submit', $campaign->fx->survey->public_token), $this->answers($campaign->fx))->assertNotFound();
        $this->assertSame(0, SurveyResponse::query()->count());
    }

    public function test_each_audience_link_records_its_own_audience(): void
    {
        $campaign = $this->campaignWithAudiences();

        $this->get(route('survey.public', $campaign->team->token))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Survey/Take')
                ->where('audience', 'time')
                ->where('audienceLabel', 'Time')
                ->where('entryToken', $campaign->team->token));

        $this->post(route('survey.public.submit', $campaign->team->token), $this->answers($campaign->fx))->assertRedirect(route('survey.public.thanks', $campaign->team->token));
        $this->post(route('survey.public.submit', $campaign->leaders->token), $this->answers($campaign->fx))->assertRedirect();

        $this->assertSame(['lideres', 'time'], SurveyResponse::query()->orderBy('audience')->pluck('audience')->all());
        $this->get(route('survey.public.thanks', $campaign->leaders->token))->assertOk();
    }

    public function test_audience_link_respects_its_own_window_and_switch(): void
    {
        $campaign = $this->campaignWithAudiences();

        $campaign->team->update(['opens_at' => now()->addDay()]);
        $this->get(route('survey.public', $campaign->team->token))
            ->assertInertia(fn (Assert $page) => $page->component('Survey/Closed')->where('message', 'Esta pesquisa ainda não iniciou.'));
        $this->post(route('survey.public.submit', $campaign->team->token), $this->answers($campaign->fx))->assertForbidden();

        $campaign->team->update(['opens_at' => now()->subDays(3), 'closes_at' => now()->subDay()]);
        $this->get(route('survey.public', $campaign->team->token))
            ->assertInertia(fn (Assert $page) => $page->component('Survey/Closed')->where('message', 'Esta pesquisa já foi encerrada.'));

        $campaign->team->update(['opens_at' => null, 'closes_at' => null, 'enabled' => false]);
        $this->get(route('survey.public', $campaign->team->token))
            ->assertInertia(fn (Assert $page) => $page->component('Survey/Closed'));
        $this->post(route('survey.public.submit', $campaign->team->token), $this->answers($campaign->fx))->assertForbidden();

        // Os outros públicos não são afetados pela janela do Time.
        $this->get(route('survey.public', $campaign->leaders->token))
            ->assertInertia(fn (Assert $page) => $page->component('Survey/Take'));
        $this->assertSame(0, SurveyResponse::query()->count());
    }

    public function test_campaign_window_still_closes_every_audience_link(): void
    {
        $campaign = $this->campaignWithAudiences();
        $campaign->fx->survey->update(['status' => 'closed']);

        $this->get(route('survey.public', $campaign->leaders->token))
            ->assertInertia(fn (Assert $page) => $page->component('Survey/Closed'));
        $this->post(route('survey.public.submit', $campaign->leaders->token), $this->answers($campaign->fx))->assertForbidden();
    }

    public function test_company_updates_link_window_with_validation_and_isolation(): void
    {
        $campaign = $this->campaignWithAudiences();
        $url = route('client.surveys.audiences.update', [$campaign->fx->survey, $campaign->team]);

        $this->actingAs($campaign->user)
            ->patch($url, ['enabled' => true, 'opens_at' => '2026-11-10T09:00', 'closes_at' => '2026-11-01T09:00'])
            ->assertSessionHasErrors('closes_at');

        $this->actingAs($campaign->user)
            ->patch($url, ['enabled' => false, 'opens_at' => '2026-11-01T09:00', 'closes_at' => '2026-11-10T09:00'])
            ->assertSessionHasNoErrors();
        $this->assertFalse($campaign->team->fresh()->enabled);
        $this->assertSame('2026-11-10 09:00', $campaign->team->fresh()->closes_at->format('Y-m-d H:i'));

        $outsiderFx = $this->createSurveyFixture();
        $outsider = User::factory()->companyAdmin($outsiderFx->company->id)->create();
        $this->actingAs($outsider)->patch($url, ['enabled' => true])->assertNotFound();
        $this->actingAs($outsider)->post(route('client.surveys.audiences.store', $campaign->fx->survey))->assertNotFound();

        // Link de outra campanha da mesma empresa também não é aceito pela rota desta campanha.
        $sibling = Survey::query()->create([
            'company_id' => $campaign->fx->company->id,
            'survey_template_id' => $campaign->fx->template->id,
            'title' => 'Outra campanha',
            'public_token' => (string) Str::uuid(),
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'status' => 'active',
        ]);
        $foreign = SurveyAudienceLink::query()->create(['survey_id' => $sibling->id, 'audience' => 'time', 'token' => Str::random(40)]);

        $this->actingAs($campaign->user)
            ->patch(route('client.surveys.audiences.update', [$campaign->fx->survey, $foreign]), ['enabled' => false])
            ->assertNotFound();
        $this->assertTrue($foreign->fresh()->enabled);
    }

    public function test_qr_code_is_an_svg_for_the_audience_link_and_isolated_by_company(): void
    {
        $campaign = $this->campaignWithAudiences();
        $url = route('client.surveys.audiences.qr', [$campaign->fx->survey, $campaign->team]);

        $response = $this->actingAs($campaign->user)->get($url)->assertOk();
        $this->assertStringContainsString('image/svg+xml', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('<svg', $response->getContent());
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $outsiderFx = $this->createSurveyFixture();
        $outsider = User::factory()->companyAdmin($outsiderFx->company->id)->create();
        $this->actingAs($outsider)->get($url)->assertNotFound();
    }

    public function test_default_policy_collects_nothing_new_and_allows_repeated_submission(): void
    {
        $this->assertSame(SurveyDuplicateGuard::POLICY_NONE, SurveyDuplicateGuard::policy());
        $this->assertFalse(config('nr1.collection.client_draft'));

        $fx = $this->createSurveyFixture();
        $this->post(route('survey.public.submit', $fx->survey->public_token), $this->answers($fx))->assertRedirect();
        $this->post(route('survey.public.submit', $fx->survey->public_token), $this->answers($fx))->assertRedirect();

        $this->assertSame(2, SurveyResponse::query()->count());
    }

    public function test_unknown_policy_value_falls_back_to_none(): void
    {
        config(['nr1.collection.duplicate_policy' => 'qualquer-coisa']);

        $this->assertSame(SurveyDuplicateGuard::POLICY_NONE, SurveyDuplicateGuard::policy());
    }

    public function test_device_cookie_policy_blocks_a_second_submission_from_the_same_browser(): void
    {
        config(['nr1.collection.duplicate_policy' => 'device_cookie']);
        $fx = $this->createSurveyFixture();
        $cookie = SurveyDuplicateGuard::cookieName($fx->survey);

        $first = $this->post(route('survey.public.submit', $fx->survey->public_token), $this->answers($fx));
        $first->assertRedirect(route('survey.public.thanks', $fx->survey->public_token));
        $first->assertCookie($cookie);

        // Outro navegador (sem o cookie) continua podendo abrir o questionário.
        $this->get(route('survey.public', $fx->survey->public_token))
            ->assertInertia(fn (Assert $page) => $page->component('Survey/Take'));

        $this->withUnencryptedCookie($cookie, '1')
            ->post(route('survey.public.submit', $fx->survey->public_token), $this->answers($fx))
            ->assertRedirect(route('survey.public', $fx->survey->public_token));

        $this->withUnencryptedCookie($cookie, '1')
            ->get(route('survey.public', $fx->survey->public_token))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Survey/Closed')
                ->where('message', 'Já recebemos uma resposta enviada deste dispositivo. Obrigado pela participação.'));

        $this->assertSame(1, SurveyResponse::query()->count());

    }

    public function test_one_time_invitation_policy_requires_and_consumes_the_code(): void
    {
        config(['nr1.collection.duplicate_policy' => 'one_time_token']);
        $campaign = $this->campaignWithAudiences();

        $this->assertSame(0, Artisan::call('nr1:generate-invites', ['survey' => $campaign->fx->survey->id, 'count' => 2, '--audience' => 'time']));
        $lines = array_values(array_filter(explode("\n", Artisan::output())));
        $this->assertCount(2, $lines);
        preg_match('/\?convite=([A-Za-z0-9]+)$/', $lines[0], $first);
        preg_match('/\?convite=([A-Za-z0-9]+)$/', $lines[1], $second);
        $this->assertStringStartsWith(route('survey.public', $campaign->team->token), $lines[0]);

        // Só o hash existe no banco; o código nunca é gravado em claro.
        $stored = SurveyInvitationToken::query()->pluck('token_hash')->all();
        $this->assertContains(hash('sha256', $first[1]), $stored);
        $this->assertNotContains($first[1], $stored);
        $this->assertFalse(Schema::hasColumn('survey_invitation_tokens', 'survey_response_id'));
        $this->assertFalse(Schema::hasColumn('survey_responses', 'invitation_id'));

        // Sem convite: indisponível.
        $this->get(route('survey.public', $campaign->team->token))
            ->assertInertia(fn (Assert $page) => $page->component('Survey/Closed')
                ->where('message', 'Este endereço exige um convite válido. Use o link completo que você recebeu.'));
        $this->post(route('survey.public.submit', $campaign->team->token), $this->answers($campaign->fx))->assertRedirect();
        $this->assertSame(0, SurveyResponse::query()->count());

        // Com convite: abre e grava; o uso registra só o mês.
        $this->get(route('survey.public', [$campaign->team->token, 'convite' => $first[1]]))
            ->assertInertia(fn (Assert $page) => $page->component('Survey/Take')->where('invitation', $first[1]));
        $this->post(route('survey.public.submit', $campaign->team->token), $this->answers($campaign->fx) + ['invitation' => $first[1]])
            ->assertRedirect(route('survey.public.thanks', $campaign->team->token));
        $this->assertSame(1, SurveyResponse::query()->count());
        $used = SurveyInvitationToken::query()->where('token_hash', hash('sha256', $first[1]))->firstOrFail();
        $this->assertSame(now()->startOfMonth()->toDateString(), $used->used_month->toDateString());

        // O mesmo convite não vale duas vezes.
        $this->post(route('survey.public.submit', $campaign->team->token), $this->answers($campaign->fx) + ['invitation' => $first[1]])->assertRedirect();
        $this->assertSame(1, SurveyResponse::query()->count());

        // Convite do Time não abre o link de Líderes.
        $this->post(route('survey.public.submit', $campaign->leaders->token), $this->answers($campaign->fx) + ['invitation' => $second[1]])->assertRedirect();
        $this->assertSame(1, SurveyResponse::query()->count());

        // O segundo convite ainda vale no link certo.
        $this->post(route('survey.public.submit', $campaign->team->token), $this->answers($campaign->fx) + ['invitation' => $second[1]])->assertRedirect();
        $this->assertSame(2, SurveyResponse::query()->count());
    }

    public function test_invalid_submission_does_not_consume_the_invitation(): void
    {
        config(['nr1.collection.duplicate_policy' => 'one_time_token']);
        $fx = $this->createSurveyFixture();
        $this->assertSame(0, Artisan::call('nr1:generate-invites', ['survey' => $fx->survey->id, 'count' => 1]));
        preg_match('/\?convite=([A-Za-z0-9]+)/', Artisan::output(), $code);

        // Resposta inválida (falta a pergunta obrigatória) falha na validação antes de consumir o convite.
        $this->post(route('survey.public.submit', $fx->survey->public_token), ['answers' => [], 'invitation' => $code[1]])
            ->assertSessionHasErrors();

        $this->assertNull(SurveyInvitationToken::query()->firstOrFail()->used_month);
    }

    public function test_invite_command_validates_its_input(): void
    {
        $campaign = $this->campaignWithAudiences();
        $survey = $campaign->fx->survey->id;

        $this->artisan('nr1:generate-invites', ['survey' => 999999, 'count' => 1])->assertFailed();
        $this->artisan('nr1:generate-invites', ['survey' => $survey, 'count' => 0])->assertFailed();
        $this->artisan('nr1:generate-invites', ['survey' => $survey, 'count' => 1])->assertFailed();
        $this->artisan('nr1:generate-invites', ['survey' => $survey, 'count' => 1, '--audience' => 'diretoria'])->assertFailed();
        $this->assertSame(0, SurveyInvitationToken::query()->count());
    }

    public function test_client_draft_flag_reaches_the_page_only_when_enabled(): void
    {
        $fx = $this->createSurveyFixture();

        $this->get(route('survey.public', $fx->survey->public_token))
            ->assertInertia(fn (Assert $page) => $page->where('clientDraft', false));

        config(['nr1.collection.client_draft' => true]);
        $this->get(route('survey.public', $fx->survey->public_token))
            ->assertInertia(fn (Assert $page) => $page->where('clientDraft', true));
    }

    public function test_legacy_campaign_without_audience_links_keeps_working(): void
    {
        $fx = $this->createSurveyFixture();

        $this->get(route('survey.public', $fx->survey->public_token))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Survey/Take')
                ->where('audience', null)
                ->where('entryToken', $fx->survey->public_token));

        $this->post(route('survey.public.submit', $fx->survey->public_token), $this->answers($fx))->assertRedirect();
        $this->assertNull(SurveyResponse::query()->firstOrFail()->audience);
    }
}
