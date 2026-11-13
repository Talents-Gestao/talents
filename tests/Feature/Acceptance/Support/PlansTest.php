<?php

// Cartão do quadro SUPORTE: «PLANOS» (telas passam a dizer «Assinatura»; preço e colaboradores removidos; só produtos).
// Já coberto: criar e editar sem preço nem limite de colaboradores (Admin/PlanCrudTest).

namespace Tests\Feature\Acceptance\Support;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PlansTest extends TestCase
{
    use RefreshDatabase;

    private const PAGES = [
        'resources/js/Pages/Admin/Plans/Index.vue',
        'resources/js/Pages/Admin/Plans/Create.vue',
        'resources/js/Pages/Admin/Plans/Edit.vue',
    ];

    public function test_subscription_screens_use_the_new_name_and_have_no_price_or_employee_fields(): void
    {
        $this->withoutVite();
        $this->actingAs(User::factory()->talentsOwner()->create())
            ->get(route('admin.plans.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Admin/Plans/Index'));

        $titles = ['Index.vue' => 'Assinaturas', 'Create.vue' => 'Nova assinatura', 'Edit.vue' => 'Editar assinatura'];
        foreach (self::PAGES as $file) {
            $source = file_get_contents(base_path($file));
            $this->assertStringContainsString('<Head title="'.$titles[basename($file)].'"', $source, $file);
            foreach (['price_monthly', 'max_employees', 'Preço', 'Colaboradores', 'Funcionários'] as $removed) {
                $this->assertStringNotContainsStringIgnoringCase($removed, $source, "{$file}: ainda tem «{$removed}»");
            }
        }

        $menu = file_get_contents(base_path('resources/js/Layouts/AdminLayout.vue'));
        $this->assertMatchesRegularExpression("/route\\('admin\\.plans\\.index'\\)[\\s\\S]{0,200}label=\"Assinaturas\"/", $menu);
    }

    public function test_subscription_screens_no_longer_say_plan_in_visible_text(): void
    {
        foreach (self::PAGES as $file) {
            $template = substr(file_get_contents(base_path($file)), strpos(file_get_contents(base_path($file)), '<template>'));
            $visible = preg_replace(['/<[^>]+>/', '/\{\{[^}]*\}\}/'], ' ', $template);
            $this->assertDoesNotMatchRegularExpression('/\bplanos?\b/iu', $visible, "{$file}: texto visível ainda diz «plano»");
        }
    }
}
