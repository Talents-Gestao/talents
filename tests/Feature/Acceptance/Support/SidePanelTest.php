<?php

// Cartão do quadro SUPORTE: «PAINEL LATERAL» (22/57; só título e checklist). Derivado do PRD 4.2/4.3: cada item da
// barra lateral do admin abre a sua tela e a rota recusa quem não tem a permissão (esconder o item nunca é a única barreira).
// Já cobertos: matriz de permissões (Admin/AdminUserPermissionsMatrixTest), rotas críticas (Admin/AdminCriticalRoutesAuthorizationTest)
// e visibilidade de Denúncias no portal (Unit/ClientSidebarDenunciasVisibilityTest).

namespace Tests\Feature\Acceptance\Support;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SidePanelTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<string> */
    private function adminSidebarRoutes(): array
    {
        preg_match_all("/:href=\"route\\('(admin\\.[a-z0-9_.-]+)'\\)\"/", file_get_contents(base_path('resources/js/Layouts/AdminLayout.vue')), $matches);

        return array_values(array_unique($matches[1]));
    }

    public function test_every_admin_sidebar_link_opens_for_the_owner_without_server_error(): void
    {
        $this->withoutVite();
        Http::fake();
        $owner = User::factory()->talentsOwner()->create();
        $routes = $this->adminSidebarRoutes();
        $this->assertGreaterThan(20, count($routes));

        foreach ($routes as $name) {
            $this->assertTrue(Route::has($name), "Rota do menu inexistente: {$name}");
            $status = $this->actingAs($owner)->get(route($name))->status();
            $this->assertContains($status, [200, 302], "{$name} respondeu {$status}");
        }
    }

    public function test_admin_sidebar_routes_refuse_a_team_member_without_permissions(): void
    {
        $this->withoutVite();
        Http::fake();
        $member = User::factory()->superAdmin()->create();

        $open = [];
        foreach ($this->adminSidebarRoutes() as $name) {
            if ($name === 'admin.dashboard') {
                continue; // o Painel é leitura para qualquer super_admin ativo (PRD 3.x)
            }
            if ($this->actingAs($member)->get(route($name))->status() !== 403) {
                $open[] = $name;
            }
        }

        $this->assertSame([], $open, 'Itens do menu abertos sem permissão: '.implode(', ', $open));
    }
}
