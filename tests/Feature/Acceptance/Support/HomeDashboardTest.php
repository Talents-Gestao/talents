<?php

// Cartões do quadro SUPORTE: «DASHBOARD - HOME» (0/10) e «FUNIL DE VENDAS» (só título e checklist). Derivado do PRD 7.1:
// números vêm do banco e, sem dados, a Home mostra estado vazio (HomeEmptyState), nunca zeros falsos.
// Já cobertos: métricas reais (Admin/AdminHomeDashboardMetricsTest), funil e período (Admin/AdminHomeProposalFunnelTest),
// meta mensal (Admin/MonthlyGoalTest), Tarefas · hoje (Admin/AdminHomeTasksTodayTest), frase do dia e leads.

namespace Tests\Feature\Acceptance\Support;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class HomeDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_without_data_sends_real_empties_and_the_screen_shows_empty_states(): void
    {
        $this->withoutVite();

        $this->actingAs(User::factory()->talentsOwner()->create())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Dashboard')
                ->where('tasksToday', [])
                ->where('operationToday', [])
                ->where('leadsThisMonth', 0)
                ->where('adminTasksOpen', 0)
                ->where('funnel', fn ($stages) => collect($stages)->every(fn ($stage) => $stage['count'] === 0))
                ->where('funnelLost.count', 0));

        $page = file_get_contents(base_path('resources/js/Pages/Admin/Dashboard.vue'));
        $this->assertGreaterThanOrEqual(3, substr_count($page, '<HomeEmptyState'), 'Tarefas, Calendário e Leads precisam de estado vazio');
        $this->assertMatchesRegularExpression('/v-if="tasksToday\.length"[\s\S]*?<HomeEmptyState\s+v-else/', $page);

        $funnel = file_get_contents(base_path('resources/js/Components/Dashboard/DashboardSalesFunnel.vue'));
        $this->assertStringContainsString('const isEmpty = computed(() => baseRows.value.every((row) => row.count === 0));', $funnel);
        $this->assertStringContainsString('<div v-if="isEmpty" class="funnel-empty">', $funnel);
    }
}
