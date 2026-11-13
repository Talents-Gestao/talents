<?php

// Cartão do quadro SUPORTE: «COLABORADORES» (como o colaborador acessa e o que enxerga).
// Definição pelo PRD: o colaborador não tem conta; responde pelo link da campanha (/nr1/jornada/{token}), sem login,
// e vê só orientação, questionário e treinamentos liberados ao seu público. Usuários da empresa (company_user)
// acessam o portal conforme a permissão de cada módulo.
// Já cobertos: a jornada não traz resultados, ofertas nem dados de outros colaboradores
// (Nr1V11JourneyTest::test_the_journey_never_carries_results_offers_or_other_collaborators_data), treinamentos por
// público (Nr1V11JourneyTest::test_t06_*), permissões por módulo (Permissions/UserPermissionsTest) e isolamento entre
// empresas (Client/ClientCrossCompanyAccessTest).

namespace Tests\Feature\Acceptance\Support;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Support\CreatesSurveyFixtures;
use Tests\Support\CreatesV11Campaign;
use Tests\TestCase;

class EmployeesTest extends TestCase
{
    use CreatesSurveyFixtures;
    use CreatesV11Campaign;
    use RefreshDatabase;

    public function test_employee_opens_the_campaign_link_without_login_and_only_gets_the_collaborator_journey(): void
    {
        $this->withoutVite();
        $campaign = $this->v11Campaign(withResponses: false);

        $this->assertGuest();
        $this->get(route('nr1.jornada', $campaign->survey->public_token))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Nr1/Section')
                ->where('shell.profile', 'colaborador'));

        $this->get(route('nr1.jornada', 'token-que-nao-existe'))->assertNotFound();
    }

    public function test_employee_without_an_account_cannot_reach_the_company_portal(): void
    {
        $this->get('/client/dashboard')->assertRedirect(route('login'));
        $this->get(route('nr1.index'))->assertRedirect(route('login'));
    }
}
