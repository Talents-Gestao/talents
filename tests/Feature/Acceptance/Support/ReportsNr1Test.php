<?php

// Cartão do quadro SUPORTE: «NR1» (relatórios: Executivo, Plano de Ação e Encaminhamento Técnico por cenário).
// Já cobertos em outros testes: download das 3 rotas (Nr1ReportsTest::test_client_can_download_nr1_report_pdfs),
// upload do executivo com precedência (Nr1ReportsTest::test_uploaded_executive_report_takes_precedence_for_client).

namespace Tests\Feature\Acceptance\Support;

use App\Models\ActionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesSurveyFixtures;
use Tests\Support\SeedsNr1SurveyResults;
use Tests\TestCase;

class ReportsNr1Test extends TestCase
{
    use CreatesSurveyFixtures;
    use RefreshDatabase;
    use SeedsNr1SurveyResults;

    /** Cenário geral → frases que o cartão exige em cada relatório. */
    public static function scenarios(): array
    {
        return [
            'baixo risco' => ['green', 2.0, 'Cenário geral: Baixo risco', [
                'executive' => ['oportunidades', 'Recomendações'],
                'referral' => ['Saúde e Segurança do Trabalho (SST)'],
                'action_plan' => ['medidas preventivas'],
            ]],
            'atenção' => ['yellow', 3.0, 'Cenário geral: Atenção', [
                'executive' => ['pontos prioritários', 'Recomendações prioritárias'],
                'referral' => ['atualização do Programa de Gerenciamento de Riscos (PGR)'],
                'action_plan' => ['acompanhamento obrigatório'],
            ]],
            'alto risco' => ['red', 4.2, 'Cenário geral: Alto risco', [
                'executive' => ['pontos críticos', 'intervenção imediata'],
                'referral' => ['análise prioritária'],
                'action_plan' => ['intervenções imediatas'],
            ]],
        ];
    }

    /**
     * Baixa o relatório pelo portal (como o cliente) e devolve o HTML que virou o PDF.
     */
    private function downloadedReportHtml(string $risk, float $score, string $route, string $view): string
    {
        $fx = $this->createSurveyFixture();
        $this->seedNr1OverallAndSectionResult($fx, $risk, $score);
        ActionPlan::query()->create([
            'company_id' => $fx->company->id,
            'survey_id' => $fx->survey->id,
            'status' => 'published',
            'admin_published_at' => now(),
        ]);

        $captured = null;
        View::composer($view, function ($composed) use (&$captured) {
            $captured = $composed->getData();
        });

        $this->actingAs(User::factory()->companyAdmin($fx->company->id)->create())
            ->get(route($route, $fx->survey))
            ->assertOk();

        $this->assertNotNull($captured, "A view {$view} não foi usada pela rota {$route}.");

        return view($view, $captured)->render();
    }

    #[DataProvider('scenarios')]
    public function test_executive_report_shows_the_overall_scenario_copy(string $risk, float $score, string $label, array $phrases): void
    {
        $html = $this->downloadedReportHtml($risk, $score, 'client.surveys.reports.executive', 'reports.executive');

        $this->assertStringContainsString($label, $html);
        foreach ($phrases['executive'] as $phrase) {
            $this->assertStringContainsString($phrase, $html);
        }
    }

    #[DataProvider('scenarios')]
    public function test_technical_referral_report_shows_the_overall_scenario_copy(string $risk, float $score, string $label, array $phrases): void
    {
        $html = $this->downloadedReportHtml($risk, $score, 'client.surveys.reports.referral', 'reports.referral');

        $this->assertStringContainsString($label, $html);
        foreach ($phrases['referral'] as $phrase) {
            $this->assertStringContainsString($phrase, $html);
        }
    }

    #[DataProvider('scenarios')]
    public function test_action_plan_report_shows_the_overall_scenario_label(string $risk, float $score, string $label): void
    {
        $html = $this->downloadedReportHtml($risk, $score, 'client.surveys.reports.action-plan', 'reports.action_plan_document');

        $this->assertStringContainsString($label, $html);
    }

    /**
     * GAP: o texto do plano por cenário (config nr1_reports.scenarios.*.action_plan) não é usado por nenhuma view;
     * o «Plano de ação» baixado é o parecer técnico e só traz o rótulo do cenário.
     */
    #[DataProvider('scenarios')]
    #[Group('gap')]
    public function test_action_plan_report_shows_the_actions_expected_for_the_scenario(string $risk, float $score, string $label, array $phrases): void
    {
        $html = $this->downloadedReportHtml($risk, $score, 'client.surveys.reports.action-plan', 'reports.action_plan_document');

        foreach ($phrases['action_plan'] as $phrase) {
            $this->assertStringContainsString($phrase, $html);
        }
    }
}
