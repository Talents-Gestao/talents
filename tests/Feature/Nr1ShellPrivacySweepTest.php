<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AdminPermissionModule;
use App\Enums\PermissionAction;
use App\Models\AdminUserPermission;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\CreatesSurveyFixtures;
use Tests\Support\CreatesV11Campaign;
use Tests\TestCase;

/**
 * Shell NR-1, fechamento: varredura de privacidade e acesso no payload Inertia de TODAS as seções, por perfil
 * (empresa, colaborador e Comercial limitado). Complementa os testes por etapa, que olham seção a seção.
 */
class Nr1ShellPrivacySweepTest extends TestCase
{
    use CreatesSurveyFixtures;
    use CreatesV11Campaign;
    use RefreshDatabase;

    /** Campos que nunca podem aparecer no payload de empresa/colaborador/Comercial. */
    private const FORBIDDEN = ['"answers"', '"answer_value"', '"responses"', '"respondent_', '"email"', '"cpf"', '"raw_export"', '"ip_address"', '"user_agent"'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function published(string $companyName): object
    {
        $c = $this->v11Campaign(true);
        $c->company->forceFill(['name' => $companyName])->save();
        $c->survey->forceFill(['requires_publication' => true, 'published_at' => now()])->save();

        return $c;
    }

    private function payloadJson(TestResponse $response): string
    {
        $props = $response->viewData('page')['props'];
        unset($props['auth']); // dados do próprio usuário (compartilhados em toda página)

        return (string) json_encode($props);
    }

    public function test_every_company_section_isolates_tenants_and_never_carries_row_level_data(): void
    {
        $mine = $this->published('Empresa Minha');
        $other = $this->published('Empresa Alheia Confidencial');
        $admin = User::factory()->companyAdmin($mine->company->id)->create();

        foreach (['nr1.visao-geral', 'nr1.campanha', 'nr1.resultados', 'nr1.plano-de-acao', 'nr1.documentos', 'nr1.solucoes'] as $name) {
            $json = $this->payloadJson($this->actingAs($admin)->get(route($name))->assertOk());
            $this->assertStringNotContainsString('Empresa Alheia Confidencial', $json, "{$name}: dado de outra empresa");
            $this->assertStringNotContainsString($other->survey->public_token, $json, "{$name}: token de outra campanha");
            // Só a tela Campanha mostra o link público da própria campanha (é o que a empresa distribui, como na tela antiga).
            if ($name !== 'nr1.campanha') {
                $this->assertStringNotContainsString($mine->survey->public_token, $json, "{$name}: a empresa não recebe o token público");
            }
            foreach (self::FORBIDDEN as $field) {
                $this->assertStringNotContainsString($field, $json, "{$name}: campo sensível {$field}");
            }
            // Outra campanha pedida por querystring não abre.
            $this->actingAs($admin)->get(route($name, ['campanha' => $other->survey->id]))->assertInertia(
                fn ($p) => $p->where('context.survey.id', $mine->survey->id),
            );
        }
        $this->actingAs($admin)->get(route('nr1.oportunidades'))->assertForbidden();
    }

    public function test_the_collaborator_journey_payload_has_no_company_results_or_row_level_data(): void
    {
        $c = $this->published('Empresa Minha');
        $json = $this->payloadJson($this->get(route('nr1.jornada', $c->survey->public_token))->assertOk());

        foreach (self::FORBIDDEN as $field) {
            $this->assertStringNotContainsString($field, $json, "campo sensível {$field}");
        }
        foreach (['sectors', 'measures', 'documents', 'opportunities', 'recommendations'] as $resultKey) {
            $this->assertStringNotContainsString('"'.$resultKey.'"', $json, "a jornada não carrega {$resultKey}");
        }
        foreach (['nr1.resultados', 'nr1.documentos', 'nr1.plano-de-acao', 'nr1.oportunidades'] as $name) {
            $this->get(route($name))->assertRedirect(); // sem login: nunca abre
        }
    }

    public function test_the_limited_commercial_profile_gets_the_queue_and_nothing_from_the_results(): void
    {
        $c = $this->published('Empresa Minha');
        $user = User::factory()->superAdmin()->create(['is_owner' => false]);
        AdminUserPermission::query()->create([
            'user_workspace_id' => $user->workspaces()->firstOrFail()->id,
            'module' => AdminPermissionModule::Nr1Oportunidades,
            'action' => PermissionAction::View,
        ]);

        $json = $this->payloadJson($this->actingAs($user)->get(route('nr1.oportunidades'))->assertOk());
        foreach (['"dimensions"', '"sectors"', '"measures"', '"participation"', '"documents"', '"recommendations"', $c->survey->public_token] as $leak) {
            $this->assertStringNotContainsString($leak, $json, "o Comercial não recebe {$leak}");
        }
        foreach (['nr1.resultados', 'nr1.plano-de-acao', 'nr1.documentos', 'nr1.campanha', 'nr1.visao-geral', 'nr1.solucoes'] as $name) {
            $this->actingAs($user)->get(route($name, ['empresa' => $c->company->id, 'campanha' => $c->survey->id]))->assertForbidden();
        }
    }

    public function test_the_company_cannot_open_the_documents_of_another_company_by_the_shell(): void
    {
        $mine = $this->published('Empresa Minha');
        $other = $this->published('Empresa Alheia Confidencial');
        $admin = User::factory()->companyAdmin($mine->company->id)->create();

        $json = $this->payloadJson($this->actingAs($admin)->get(route('nr1.documentos', ['empresa' => $other->company->id, 'campanha' => $other->survey->id]))->assertOk());
        $this->assertStringNotContainsString('Empresa Alheia Confidencial', $json);
        $this->assertInstanceOf(Company::class, $mine->company);
    }
}
