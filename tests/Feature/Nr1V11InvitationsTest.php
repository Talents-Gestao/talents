<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Survey;
use App\Models\SurveyAudienceLink;
use App\Models\SurveyInvitationToken;
use App\Models\User;
use App\Services\SurveyDuplicateGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CreatesSurveyFixtures;
use Tests\Support\CreatesV11Campaign;
use Tests\TestCase;

/**
 * «Convite único» por campanha (opção da Talents). O convite fica em tabela própria, só com hash e mês de uso, sem nenhum
 * vínculo com a resposta (anonimato). O padrão global segue `none` até o jurídico validar.
 */
class Nr1V11InvitationsTest extends TestCase
{
    use CreatesSurveyFixtures;
    use CreatesV11Campaign;
    use RefreshDatabase;

    /**
     * @return object{survey: Survey, talents: User, admin: User, time: SurveyAudienceLink, leaders: SurveyAudienceLink, answers: array<int, int>}
     */
    private function campaign(): object
    {
        $campaign = $this->v11Campaign(false);
        $campaign->survey->forceFill(['requires_publication' => true, 'published_at' => now()])->save();
        $links = [];
        foreach (SurveyAudienceLink::AUDIENCES as $audience) {
            $links[$audience] = SurveyAudienceLink::query()->create(['survey_id' => $campaign->survey->id, 'audience' => $audience, 'token' => Str::random(40), 'enabled' => true]);
        }

        return (object) [
            'survey' => $campaign->survey->fresh(),
            'talents' => User::factory()->talentsOwner()->create(),
            'admin' => User::factory()->companyAdmin($campaign->company->id)->create(),
            'time' => $links['time'],
            'leaders' => $links['lideres'],
            'answers' => array_fill_keys(array_values($campaign->questionIds), 3),
        ];
    }

    private function optionUrl(object $c): string
    {
        return route('admin.companies.surveys.campaign.invitation-option', [$c->survey->company_id, $c->survey->id]);
    }

    private function generateUrl(object $c): string
    {
        return route('admin.companies.surveys.campaign.invitations', [$c->survey->company_id, $c->survey->id]);
    }

    /** @return list<string> */
    private function generate(object $c, string $audience, int $count): array
    {
        $this->actingAs($c->talents)->post($this->generateUrl($c), ['audience' => $audience, 'count' => $count])->assertSessionHas('success');

        return session('invitation_urls');
    }

    public function test_the_default_stays_none_and_the_option_is_off(): void
    {
        $c = $this->campaign();

        $this->assertFalse($c->survey->uses_invitations);
        $this->assertSame(SurveyDuplicateGuard::POLICY_NONE, SurveyDuplicateGuard::policy());
        $this->assertSame(SurveyDuplicateGuard::POLICY_NONE, SurveyDuplicateGuard::policyFor($c->survey));

        $this->get(route('survey.public', $c->time->token))->assertInertia(fn (Assert $page) => $page->component('Survey/Take'));
    }

    public function test_talents_turns_the_option_on_and_the_campaign_then_needs_an_invitation(): void
    {
        $c = $this->campaign();

        $this->actingAs($c->talents)->post($this->optionUrl($c), ['enabled' => true])->assertSessionHas('success');
        $this->assertTrue($c->survey->fresh()->uses_invitations);
        $this->assertSame(SurveyDuplicateGuard::POLICY_ONE_TIME_TOKEN, SurveyDuplicateGuard::policyFor($c->survey->fresh()));
        $this->assertSame(SurveyDuplicateGuard::POLICY_NONE, SurveyDuplicateGuard::policy(), 'O padrão global não muda.');

        $this->get(route('survey.public', $c->time->token))->assertInertia(fn (Assert $page) => $page->component('Survey/Closed'));
        $this->get(route('survey.public', [$c->time->token, 'convite' => 'codigo-invalido']))->assertInertia(fn (Assert $page) => $page->component('Survey/Closed'));

        $this->actingAs($c->talents)->post($this->optionUrl($c), ['enabled' => false])->assertSessionHas('success');
        $this->get(route('survey.public', $c->time->token))->assertInertia(fn (Assert $page) => $page->component('Survey/Take'));
    }

    public function test_an_invitation_opens_the_questionnaire_and_is_consumed_once(): void
    {
        $c = $this->campaign();
        $this->actingAs($c->talents)->post($this->optionUrl($c), ['enabled' => true]);
        [$url] = $this->generate($c, 'time', 2);
        $code = Str::after($url, '?convite=');

        $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->component('Survey/Take')->where('invitation', $code));

        $this->post(route('survey.public.submit', $c->time->token), ['answers' => $c->answers, 'invitation' => $code])
            ->assertRedirect(route('survey.public.thanks', ['token' => $c->time->token]));
        $this->assertSame(1, $c->survey->responses()->whereNotNull('completed_at')->count());

        // Mesmo convite de novo: recusado, sem nova resposta.
        $this->post(route('survey.public.submit', $c->time->token), ['answers' => $c->answers, 'invitation' => $code]);
        $this->assertSame(1, $c->survey->responses()->count());
        $this->get($url)->assertInertia(fn (Assert $page) => $page->component('Survey/Closed'));

        // O outro convite continua válido.
        $this->get($this->generate($c, 'time', 1)[0])->assertInertia(fn (Assert $page) => $page->component('Survey/Take'));
    }

    public function test_an_invitation_of_one_audience_does_not_open_the_other(): void
    {
        $c = $this->campaign();
        $this->actingAs($c->talents)->post($this->optionUrl($c), ['enabled' => true]);
        $code = Str::after($this->generate($c, 'lideres', 1)[0], '?convite=');

        $this->get(route('survey.public', [$c->time->token, 'convite' => $code]))->assertInertia(fn (Assert $page) => $page->component('Survey/Closed'));
        $this->get(route('survey.public', [$c->leaders->token, 'convite' => $code]))->assertInertia(fn (Assert $page) => $page->component('Survey/Take'));
    }

    public function test_codes_are_shown_once_and_only_the_hash_is_stored(): void
    {
        $c = $this->campaign();
        $urls = $this->generate($c, 'time', 3);
        $this->assertCount(3, $urls);
        $this->assertCount(3, array_unique($urls));
        $this->assertStringStartsWith(route('survey.public', $c->time->token).'?convite=', $urls[0]);

        foreach ($urls as $url) {
            $code = Str::after($url, '?convite=');
            $this->assertSame(32, strlen($code));
            $this->assertSame(1, SurveyInvitationToken::query()->where('token_hash', hash('sha256', $code))->count());
            $this->assertSame(0, DB::table('survey_invitation_tokens')->where('token_hash', $code)->count(), 'O código não é guardado em claro.');
        }

        // A página mostra os códigos na resposta que os gera e nunca mais; a lista é só de contagens.
        $page = $this->actingAs($c->talents)->get(route('admin.companies.surveys.campaign.show', [$c->survey->company_id, $c->survey->id]))->assertOk();
        $page->assertInertia(fn (Assert $p) => $p->has('invitations.generated', 3)->where('invitations.stats.0.total', 3)->where('invitations.stats.0.used', 0)->where('invitations.stats.0.available', 3));
        // Os códigos aparecem na resposta que os gera (uma vez) e na próxima abertura da página já não existem.
        $first = $this->actingAs($c->talents)->get(route('admin.companies.surveys.campaign.show', [$c->survey->company_id, $c->survey->id]))->assertOk();
        $this->assertStringNotContainsString(Str::after($urls[0], '?convite='), $first->getContent(), 'Os códigos não podem reaparecer.');
    }

    public function test_anonymity_no_column_or_key_links_an_invitation_to_a_response(): void
    {
        // Estrutura: a tabela do convite só tem campanha, público, hash, mês de uso e criação.
        $columns = Schema::getColumnListing('survey_invitation_tokens');
        $this->assertEqualsCanonicalizing(['id', 'survey_id', 'audience', 'token_hash', 'used_month', 'created_at'], $columns);
        foreach ($columns as $column) {
            $this->assertDoesNotMatchRegularExpression('/response|answer|session|user|ip|device|cookie|email|name/i', $column);
        }

        // A resposta e as respostas por pergunta não guardam convite nem hash.
        foreach (['survey_responses', 'survey_answers', 'survey_results', 'survey_group_results'] as $table) {
            foreach (Schema::getColumnListing($table) as $column) {
                $this->assertDoesNotMatchRegularExpression('/invit|convite|token_hash/i', $column, "{$table}.{$column}");
            }
        }

        // Nenhuma chave estrangeira entre as duas pontas além da campanha.
        $foreign = collect(Schema::getForeignKeys('survey_invitation_tokens'));
        $this->assertSame([['survey_id']], $foreign->map(fn (array $key) => $key['columns'])->all());
        foreach (['survey_responses', 'survey_answers'] as $table) {
            foreach (Schema::getForeignKeys($table) as $key) {
                $this->assertNotSame('survey_invitation_tokens', $key['foreign_table'], "{$table} aponta para convites.");
            }
        }
    }

    public function test_anonymity_after_submissions_nothing_in_the_response_data_derives_from_the_invitation(): void
    {
        $c = $this->campaign();
        $this->actingAs($c->talents)->post($this->optionUrl($c), ['enabled' => true]);
        $codes = collect($this->generate($c, 'time', 3))->map(fn (string $url) => Str::after($url, '?convite='));

        foreach ($codes as $code) {
            $this->post(route('survey.public.submit', $c->time->token), ['answers' => $c->answers, 'invitation' => $code])->assertRedirect();
        }
        $this->assertSame(3, $c->survey->responses()->count());

        // Marca «usado» só com o MÊS (dia 1, sem hora): nenhum convite se distingue de outro pelo momento do uso.
        $used = SurveyInvitationToken::query()->whereNotNull('used_month')->get();
        $this->assertCount(3, $used);
        $this->assertSame(1, $used->pluck('used_month')->map(fn ($d) => $d->format('Y-m-d H:i:s'))->unique()->count());
        $this->assertSame(now()->startOfMonth()->format('Y-m-d'), $used->first()->used_month->format('Y-m-d'));
        foreach ($c->survey->responses()->get() as $response) {
            $this->assertSame(now()->startOfMonth()->format('Y-m-d'), $response->completed_at->format('Y-m-d'));
        }

        // Nenhum valor das tabelas de resposta contém o código, o hash ou algo derivado deles.
        $haystack = json_encode([
            DB::table('survey_responses')->get(),
            DB::table('survey_answers')->get(),
            DB::table('survey_results')->get(),
            DB::table('survey_group_results')->get(),
        ]);
        foreach ($codes as $code) {
            $this->assertStringNotContainsString($code, $haystack);
            $this->assertStringNotContainsString(hash('sha256', $code), $haystack);
            $this->assertStringNotContainsString(substr(hash('sha256', $code), 0, 16), $haystack);
        }

        // E o inverso: nada do convite aponta para uma resposta (só campanha, público, hash e mês).
        $haystackTokens = json_encode(DB::table('survey_invitation_tokens')->get());
        foreach ($c->survey->responses()->get() as $response) {
            $this->assertStringNotContainsString((string) $response->session_token, $haystackTokens);
        }
        $this->assertNotContains($response->session_token ?? '', $codes->all());

        // A única coluna em comum entre as duas tabelas é a campanha: um join só gera o produto cartesiano.
        $joined = DB::table('survey_invitation_tokens as t')->join('survey_responses as r', 'r.survey_id', '=', 't.survey_id')->count();
        $this->assertSame(3 * 3, $joined, 'Sem coluna de ligação: todo convite casa com toda resposta.');
        // Colunas de mesmo nome entre as duas tabelas: só campanha e público (grupo) e os genéricos id e created_at, que não
        // são chave entre elas. Não há coluna que ligue um convite a uma resposta.
        $this->assertEqualsCanonicalizing(['id', 'survey_id', 'audience', 'created_at'], array_values(array_intersect(Schema::getColumnListing('survey_invitation_tokens'), Schema::getColumnListing('survey_responses'))));
    }

    public function test_only_talents_manage_invitations_and_input_is_validated(): void
    {
        $c = $this->campaign();
        $plain = User::factory()->superAdmin()->create(['is_owner' => false]);

        foreach ([$c->admin, $plain] as $user) {
            $this->actingAs($user)->post($this->optionUrl($c), ['enabled' => true])->assertForbidden();
            $this->actingAs($user)->post($this->generateUrl($c), ['audience' => 'time', 'count' => 5])->assertForbidden();
        }
        $this->assertFalse($c->survey->fresh()->uses_invitations);
        $this->assertSame(0, SurveyInvitationToken::query()->count());

        $this->actingAs($c->talents)->post($this->generateUrl($c), ['audience' => 'time', 'count' => 0])->assertSessionHasErrors('count');
        $this->actingAs($c->talents)->post($this->generateUrl($c), ['audience' => 'time', 'count' => 5001])->assertSessionHasErrors('count');
        $this->actingAs($c->talents)->post($this->generateUrl($c), ['audience' => 'todos', 'count' => 5])->assertSessionHasErrors('audience');
        $this->actingAs($c->talents)->post($this->generateUrl($c), ['count' => 5])->assertSessionHas('error');
        $this->assertSame(0, SurveyInvitationToken::query()->count());
    }
}
