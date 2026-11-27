<?php

declare(strict_types=1);

namespace Tests\Feature\Feedback;

use App\Models\Company;
use App\Models\User;
use App\Support\Feedback\FeedbackCompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Mockery;
use Tests\TestCase;

/**
 * Admin → Feedbacks internos: «Escolher outra empresa» limpa a empresa da sessão
 * e volta à tela de seleção.
 */
class FeedbackChooseOtherCompanyTest extends TestCase
{
    use RefreshDatabase;

    public function test_destroy_forgets_selected_company_and_redirects_to_index(): void
    {
        $company = Company::query()->create(['name' => 'Empresa A', 'is_active' => true]);
        $owner = User::factory()->superAdmin()->create(['is_owner' => true]);

        $this->actingAs($owner)
            ->withSession([FeedbackCompanyContext::SESSION_KEY => $company->id])
            ->delete(route('admin.feedbacks.company.destroy'))
            ->assertRedirect(route('admin.feedbacks.index'))
            ->assertSessionMissing(FeedbackCompanyContext::SESSION_KEY);
    }

    public function test_index_shows_company_selection_after_destroy(): void
    {
        $company = Company::query()->create(['name' => 'Empresa A', 'is_active' => true]);
        $owner = User::factory()->superAdmin()->create(['is_owner' => true]);
        $this->withoutVite();

        $this->actingAs($owner)
            ->withSession([FeedbackCompanyContext::SESSION_KEY => $company->id])
            ->get(route('admin.feedbacks.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('activeCompany.id', $company->id)
                ->where('canChooseOtherCompany', true));

        $this->delete(route('admin.feedbacks.company.destroy'))->assertRedirect(route('admin.feedbacks.index'));

        $this->get(route('admin.feedbacks.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Client/Feedbacks/Index')
                ->where('activeCompany', null)
                ->where('canChooseOtherCompany', false)
                ->where('companyPicker.0.name', 'Empresa A'));
    }

    public function test_super_admin_without_feedbacks_permission_gets_403(): void
    {
        $company = Company::query()->create(['name' => 'Empresa A', 'is_active' => true]);
        $admin = User::factory()->superAdmin()->create(['is_owner' => false]);

        $this->actingAs($admin)
            ->withSession([FeedbackCompanyContext::SESSION_KEY => $company->id])
            ->delete(route('admin.feedbacks.company.destroy'))
            ->assertForbidden();
    }

    public function test_company_admin_cannot_use_the_route(): void
    {
        $company = Company::query()->create(['name' => 'Empresa A', 'is_active' => true]);
        $rh = User::factory()->companyAdmin($company->id)->create();

        $response = $this->actingAs($rh)->delete(route('admin.feedbacks.company.destroy'));

        $this->assertContains($response->status(), [302, 403]);
        if ($response->status() === 302) {
            $this->assertStringNotContainsString('/admin/feedbacks', (string) $response->headers->get('Location'));
        }
    }

    public function test_button_is_hidden_when_workspace_company_has_feedbacks(): void
    {
        $workspaceCompany = Company::query()->create(['name' => 'Empresa do workspace', 'is_active' => true]);
        $selected = Company::query()->create(['name' => 'Empresa selecionada', 'is_active' => true]);

        $user = Mockery::mock(User::factory()->superAdmin()->create(['is_owner' => true]))->makePartial();
        $user->shouldReceive('contextCompany')->andReturn($workspaceCompany);

        $request = Request::create('/admin/feedbacks', 'GET');
        $request->setLaravelSession($this->app['session.store']);
        $request->session()->put(FeedbackCompanyContext::SESSION_KEY, $selected->id);
        $request->setUserResolver(fn () => $user);
        $request->setRouteResolver(fn () => (new Route('GET', '/admin/feedbacks', []))->name('admin.feedbacks.index'));

        $context = app(FeedbackCompanyContext::class);

        $this->assertFalse($context->needsCompanySelection($request));
        $this->assertFalse($context->canChooseOtherCompany($request));

        // Sem workspace com Feedbacks, a mesma sessão mostra o botão.
        $workspaceCompany->forceFill(['feedbacks_access' => false])->save();
        $this->assertTrue($context->canChooseOtherCompany($request));
    }
}
