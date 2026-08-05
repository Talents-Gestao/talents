<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Module;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_admin_can_create_subscription_without_price_or_max_employees(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $module = Module::query()->create([
            'key' => 'surveys_test',
            'name' => 'Pesquisas',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.plans.store'), [
                'name' => 'Assinatura Essencial',
                'max_surveys_per_year' => 12,
                'module_ids' => [$module->id],
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.plans.index'));

        $plan = Plan::query()->where('name', 'Assinatura Essencial')->first();
        $this->assertNotNull($plan);
        $this->assertSame(0, $plan->price_monthly_cents);
        $this->assertNull($plan->max_employees);
        $this->assertSame(12, $plan->max_surveys_per_year);
        $this->assertTrue($plan->modules()->where('modules.id', $module->id)->exists());
    }

    public function test_admin_can_update_subscription_without_price_or_max_employees(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $plan = Plan::query()->create([
            'name' => 'Plano Antigo',
            'slug' => 'plano-antigo',
            'price_monthly_cents' => 9900,
            'max_employees' => 50,
            'max_surveys_per_year' => 5,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.plans.update', $plan), [
                'name' => 'Assinatura Atualizada',
                'max_surveys_per_year' => 20,
                'module_ids' => [],
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.plans.index'));

        $plan->refresh();
        $this->assertSame('Assinatura Atualizada', $plan->name);
        $this->assertSame(20, $plan->max_surveys_per_year);
        $this->assertSame(9900, $plan->price_monthly_cents);
        $this->assertSame(50, $plan->max_employees);
    }
}
