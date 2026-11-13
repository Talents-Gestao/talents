<?php

// Cartão do quadro SUPORTE: «TALENTS GESTÃO» (cada módulo com timeline/andamento visível ao cliente; checklist 6/12).
// Existe hoje: andamento da contratação (Acompanhamento) e status da campanha NR-1. Os demais módulos não têm
// cronograma próprio; sem o checklist do cartão não dá para dizer quais itens faltam (ver relatório).
// Já cobertos: etapas, candidatos e observações do Acompanhamento visíveis ao cliente
// (Client/AcompanhamentoModuleTest::test_candidates_and_comments_persist_and_update_across_stages_for_client,
// test_client_only_sees_own_company_processes).

namespace Tests\Feature\Acceptance\Support;

use App\Enums\HiringProcessStage;
use App\Models\Company;
use App\Models\HiringProcess;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class TalentsManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_home_points_to_the_hiring_progress_while_there_are_active_processes(): void
    {
        $this->withoutVite();
        $company = Company::query()->create([
            'name' => 'Empresa Contratando',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
            'acompanhamento_access' => true,
        ]);
        HiringProcess::query()->create(['company_id' => $company->id, 'title' => 'Analista de RH', 'current_stage' => HiringProcessStage::AnaliseCurriculo]);
        HiringProcess::query()->create(['company_id' => $company->id, 'title' => 'Vaga concluída', 'current_stage' => HiringProcessStage::Contratacao]);

        $this->actingAs(User::factory()->companyAdmin($company->id)->create())
            ->get('/client/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('activeHiringProcessesCount', 1));

        $this->get(route('client.acompanhamento.index'))->assertOk();
    }
}
