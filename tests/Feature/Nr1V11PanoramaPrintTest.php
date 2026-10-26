<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Models\ApproverOrganization;
use App\Models\Department;
use App\Models\PsychosocialAccessLog;
use App\Models\SurveyMethodVersion;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Models\UserPermission;
use App\Services\PsychosocialDashboardBuilder;
use App\Services\SurveyResultCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesSurveyFixtures;
use Tests\Support\CreatesV11Campaign;
use Tests\TestCase;

/**
 * Etapa 5, fatia 3: impressão do panorama e exportação agregada das perguntas, com as mesmas regras
 * (empresa, permissão de Relatórios, grupo mínimo, só agregado) das demais saídas do painel.
 */
class Nr1V11PanoramaPrintTest extends TestCase
{
    use CreatesSurveyFixtures;
    use CreatesV11Campaign;
    use RefreshDatabase;

    private const SESSION_MARKER = 'sessao-marcador-secreta';

    /**
     * @param  array<string, int>  $sectors
     */
    private function groupCampaign(array $sectors, bool $showComposition = false): object
    {
        $fx = $this->createSurveyFixture([
            'method_version_id' => SurveyMethodVersion::idForCode(SurveyMethodVersion::CODE_TALENTS39_V1),
            'show_others_composition' => $showComposition,
        ]);

        foreach ($sectors as $name => $size) {
            $department = Department::query()->create(['company_id' => $fx->company->id, 'name' => $name]);
            for ($i = 0; $i < $size; $i++) {
                SurveyResponse::query()->create([
                    'survey_id' => $fx->survey->id,
                    'session_token' => self::SESSION_MARKER.'-'.Str::uuid(),
                    'department_id' => $department->id,
                    'completed_at' => now()->startOfMonth(),
                ])->answers()->create(['survey_template_question_id' => $fx->question->id, 'value' => 4]);
            }
        }

        app(SurveyResultCalculator::class)->recalculate($fx->survey->fresh());

        return $fx;
    }

    private function companyUserWithReports(int $companyId, bool $granted): User
    {
        $user = User::factory()->companyUser($companyId)->create();
        if ($granted) {
            UserPermission::query()->create([
                'user_workspace_id' => $user->workspaces()->firstOrFail()->id,
                'module' => PermissionModule::Relatorios->value,
                'action' => PermissionAction::View->value,
            ]);
        }

        return $user;
    }

    public function test_print_shows_the_overview_the_groups_and_the_39_questions(): void
    {
        $campaign = $this->v11Campaign();
        $user = User::factory()->companyAdmin($campaign->company->id)->create();

        $response = $this->actingAs($user)
            ->get(route('client.surveys.psychosocial-dashboard.print', $campaign->survey))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8');

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $response->assertSee('Empresa V11')
            ->assertSee('Campanha V11')
            ->assertSee('Em coleta')
            ->assertSee('2,60', false)
            ->assertSee('Atenção')
            ->assertSee('Faixa do questionário. Não é classificação de risco ocupacional do PGR.')
            ->assertSee('Participação por grupo')
            ->assertSee('Imprimir panorama');

        $html = $response->getContent();
        $this->assertSame(39, preg_match_all('/<tr>\s*<td>\d+<\/td>\s*<td>/', $html), 'Uma linha por pergunta.');
        $this->assertStringContainsString('92,5%', $html, 'Q7: 37/40.');
        $this->assertStringContainsString('75,0%', $html, 'Q8: 30/40.');
        $this->assertStringContainsString('N = 40', $html);
    }

    public function test_print_lists_every_attention_dimension_in_descending_order(): void
    {
        $campaign = $this->v11Campaign();
        $user = User::factory()->companyAdmin($campaign->company->id)->create();
        $dashboard = app(PsychosocialDashboardBuilder::class)->build($campaign->survey->fresh());

        $titles = array_column($dashboard['attention'], 'title');
        $this->assertCount(6, $titles);

        $html = $this->actingAs($user)
            ->get(route('client.surveys.psychosocial-dashboard.print', $campaign->survey))
            ->getContent();
        $block = substr($html, (int) strpos($html, 'O que merece atenção'), 2500);

        $last = -1;
        foreach ($titles as $title) {
            $position = strpos($block, e($title));
            $this->assertNotFalse($position, "{$title} fora da lista de atenção impressa.");
            $this->assertGreaterThan($last, $position, 'Ordem decrescente.');
            $last = $position;
        }
    }

    public function test_print_never_carries_hidden_sectors_or_raw_data(): void
    {
        $campaign = $this->groupCampaign(['Grande' => 12, 'Médio' => 6, 'Minúsculo' => 1]);
        $user = User::factory()->companyAdmin($campaign->company->id)->create();

        $html = $this->actingAs($user)
            ->get(route('client.surveys.psychosocial-dashboard.print', $campaign->survey))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Grande', $html);
        $this->assertStringContainsString('Outros Setores', $html);
        foreach (['Minúsculo', 'Médio', self::SESSION_MARKER, 'session_token', 'age_range', 'tenure'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $html, "{$forbidden} apareceu na impressão.");
        }
    }

    public function test_print_shows_composition_only_when_the_campaign_allows_it(): void
    {
        $off = $this->groupCampaign(['Grande' => 12, 'Alfa' => 2, 'Beta' => 2, 'Gama' => 2]);
        $on = $this->groupCampaign(['Grande' => 12, 'Alfa' => 2, 'Beta' => 2, 'Gama' => 2], showComposition: true);

        $offUser = User::factory()->companyAdmin($off->company->id)->create();
        $offHtml = $this->actingAs($offUser)->get(route('client.surveys.psychosocial-dashboard.print', $off->survey))->getContent();
        foreach (['Alfa', 'Beta', 'Gama', 'Reúne:'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $offHtml);
        }

        $onUser = User::factory()->companyAdmin($on->company->id)->create();
        $onHtml = $this->actingAs($onUser)->get(route('client.surveys.psychosocial-dashboard.print', $on->survey))->getContent();
        $this->assertStringContainsString('Reúne: Alfa, Beta, Gama.', $onHtml);
    }

    public function test_print_of_an_empty_campaign_shows_the_empty_state_without_zeroed_numbers(): void
    {
        $campaign = $this->v11Campaign(withResponses: false);
        $user = User::factory()->companyAdmin($campaign->company->id)->create();

        $this->actingAs($user)
            ->get(route('client.surveys.psychosocial-dashboard.print', $campaign->survey))
            ->assertOk()
            ->assertSee('Os resultados desta campanha ainda não foram calculados.')
            ->assertSee('Os indicadores aparecem depois que as respostas forem calculadas.')
            ->assertSee('Ainda não há respostas para destacar pontos fortes.')
            ->assertDontSee('0,00', false);
    }

    public function test_items_csv_is_aggregated_per_question_and_campaign_wide(): void
    {
        $campaign = $this->v11Campaign();
        $user = User::factory()->companyAdmin($campaign->company->id)->create();

        $response = $this->actingAs($user)
            ->get(route('client.surveys.psychosocial-dashboard.export.items', $campaign->survey))
            ->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));

        $csv = $response->streamedContent();
        $lines = array_values(array_filter(explode("\n", str_replace("\r\n", "\n", ltrim($csv, "\xEF\xBB\xBF")))));

        $this->assertSame(
            'posicao;dimensao;pergunta;n;nunca_pct;raramente_pct;as_vezes_pct;frequentemente_pct;sempre_pct;favoravel_pct;desfavoravel_pct;observacao',
            $lines[0],
        );
        $this->assertCount(40, $lines, 'Cabeçalho + 39 perguntas.');

        $q7 = collect($lines)->first(fn (string $line) => str_starts_with($line, '7;'));
        $this->assertStringContainsString(';40;0,0;2,5;5,0;15,0;77,5;92,5;2,5;', $q7);

        $this->assertStringNotContainsStringIgnoringCase('setor', $lines[0], 'O CSV não tem coluna de setor.');
        foreach (['session_token', 'department', 'age_range', 'tenure'] as $forbidden) {
            $this->assertStringNotContainsStringIgnoringCase($forbidden, $csv);
        }
    }

    public function test_print_and_items_export_are_audited(): void
    {
        $campaign = $this->v11Campaign();
        $user = User::factory()->companyAdmin($campaign->company->id)->create();

        $this->actingAs($user)->get(route('client.surveys.psychosocial-dashboard.print', $campaign->survey))->assertOk();
        $this->actingAs($user)->get(route('client.surveys.psychosocial-dashboard.export.items', $campaign->survey))->assertOk()->streamedContent();

        foreach (['panorama_print', 'export_items_csv'] as $resource) {
            $log = PsychosocialAccessLog::query()->where('resource', $resource)->sole();
            $this->assertSame($user->id, (int) $log->user_id);
            $this->assertSame($campaign->survey->id, (int) $log->survey_id);
            $this->assertSame($campaign->company->id, (int) $log->company_id);
        }
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function routes(): array
    {
        return [
            'imprimir' => ['client.surveys.psychosocial-dashboard.print'],
            'exportar perguntas' => ['client.surveys.psychosocial-dashboard.export.items'],
        ];
    }

    #[DataProvider('routes')]
    public function test_profile_matrix_for_print_and_items_export(string $routeName): void
    {
        $campaign = $this->v11Campaign();
        $url = route($routeName, $campaign->survey);
        $other = $this->createSurveyFixture();

        // Quem pode: administrador da empresa e usuário com Relatórios/ver.
        $this->actingAs(User::factory()->companyAdmin($campaign->company->id)->create())->get($url)->assertOk();
        $this->actingAs($this->companyUserWithReports($campaign->company->id, granted: true))->get($url)->assertOk();

        // Usuário da empresa sem a permissão: 403.
        $this->actingAs($this->companyUserWithReports($campaign->company->id, granted: false))->get($url)->assertForbidden();

        // Outra empresa (mesmo administrador): 404, sem confirmar que a campanha existe.
        $this->actingAs(User::factory()->companyAdmin($other->company->id)->create())->get($url)->assertNotFound();

        // Visitante: login.
        auth()->logout();
        $this->get($url)->assertRedirect();
    }

    #[DataProvider('routes')]
    public function test_talents_commercial_and_external_reviewer_do_not_reach_the_company_panorama(string $routeName): void
    {
        $campaign = $this->v11Campaign();
        $url = route($routeName, $campaign->survey);

        $reviewer = User::factory()->externalReviewer(ApproverOrganization::query()->create(['name' => 'Org', 'is_active' => true])->id)->create();
        $talents = User::factory()->superAdmin()->create(['is_owner' => true]);
        $commercial = User::factory()->superAdmin()->create();

        foreach (['revisor externo' => $reviewer, 'Talents (owner)' => $talents, 'comercial' => $commercial] as $label => $user) {
            $status = $this->actingAs($user)->get($url)->getStatusCode();
            $this->assertContains($status, [302, 403, 404], "{$label} recebeu {$status} em {$routeName}.");
        }
        $this->assertSame(0, PsychosocialAccessLog::query()->whereIn('resource', ['panorama_print', 'export_items_csv'])->count());
    }
}
