<?php

// Cartão do quadro SUPORTE: «DIRECIONAMENTO ESTRATÉGICO» (a tela inicial exibe os dados corretos).
// Tela: /admin/metodologia (PRD 7.13). Números: empresas com assinatura ATIVA cujo plano inclui o módulo `metodologia`,
// total de templates de satisfação e os 5 templates mais recentes.

namespace Tests\Feature\Acceptance\Support;

use App\Models\Company;
use App\Models\MethodologyFormTemplate;
use App\Models\Module;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class StrategicDirectionTest extends TestCase
{
    use RefreshDatabase;

    private function companyWithPlan(string $name, string $moduleKey, string $status): Company
    {
        $company = Company::query()->create(['name' => $name, 'is_active' => true, 'complaints_public_token' => (string) Str::uuid()]);
        $module = Module::query()->firstOrCreate(['key' => $moduleKey], ['name' => $moduleKey, 'description' => 'Teste']);
        $plan = Plan::query()->create(['name' => "Plano {$name}", 'slug' => 'p-'.Str::random(8), 'price_monthly_cents' => 0, 'is_active' => true]);
        $plan->modules()->sync([$module->id]);
        Subscription::query()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addYear(),
            'status' => $status,
        ]);

        return $company;
    }

    public function test_home_counts_only_active_subscriptions_with_the_methodology_module_and_lists_recent_templates(): void
    {
        $this->withoutVite();
        $this->companyWithPlan('Com metodologia 1', Module::KEY_METODOLOGIA, 'active');
        $this->companyWithPlan('Com metodologia 2', Module::KEY_METODOLOGIA, 'active');
        $this->companyWithPlan('Assinatura cancelada', Module::KEY_METODOLOGIA, 'cancelled');
        $this->companyWithPlan('Outro módulo', Module::KEY_NR1, 'active');

        foreach (range(1, 6) as $i) {
            MethodologyFormTemplate::query()->create(['title' => "Template {$i}", 'step_number' => 1, 'is_active' => true]);
        }

        $this->actingAs(User::factory()->talentsOwner()->create())
            ->get(route('admin.metodologia.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Methodology/Index')
                ->where('stats.companies_with_methodology', 2)
                ->where('stats.templates_count', 6)
                ->has('recentTemplates', 5)
                ->where('recentTemplates.0.title', 'Template 6')
                ->has('recentTemplates.0.sections_count')
                ->has('recentTemplates.0.companies_count'));
    }
}
