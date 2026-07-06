<?php

declare(strict_types=1);

namespace Tests\Unit\Feedback;

use App\Models\Company;
use App\Models\User;
use App\Support\Feedback\FeedbackCompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Tests\Support\CreatesFeedbackFixtures;
use Tests\TestCase;

class FeedbackCompanyContextTest extends TestCase
{
    use CreatesFeedbackFixtures;
    use RefreshDatabase;

    private FeedbackCompanyContext $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->context = app(FeedbackCompanyContext::class);
    }

    public function test_needs_company_selection_for_super_admin_without_session_company(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $request = $this->adminFeedbacksRequest($admin);

        $this->assertTrue($this->context->needsCompanySelection($request));
    }

    public function test_resolves_company_from_session_for_super_admin(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $company = $this->createFeedbackCompany();
        $request = $this->adminFeedbacksRequest($admin, $company->id);

        $this->assertFalse($this->context->needsCompanySelection($request));
        $this->assertSame($company->id, $this->context->resolve($request)->id);
    }

    public function test_resolves_workspace_company_for_rh(): void
    {
        $company = $this->createFeedbackCompany();
        $rh = User::factory()->companyAdmin($company->id)->create();
        $request = $this->clientFeedbacksRequest($rh);

        $this->assertFalse($this->context->needsCompanySelection($request));
        $this->assertSame($company->id, $this->context->resolve($request)->id);
    }

    public function test_available_companies_lists_only_active_with_feedbacks_enabled(): void
    {
        $this->createFeedbackCompany(['name' => 'Ativa', 'feedbacks_access' => true, 'is_active' => true]);
        $this->createFeedbackCompany(['name' => 'Sem módulo', 'feedbacks_access' => false, 'is_active' => true]);
        $this->createFeedbackCompany(['name' => 'Inativa', 'feedbacks_access' => true, 'is_active' => false]);

        $names = $this->context->availableCompanies()->pluck('name');

        $this->assertSame(['Ativa'], $names->all());
    }

    private function adminFeedbacksRequest(User $user, ?int $companyId = null): Request
    {
        $request = Request::create('/admin/feedbacks', 'GET');
        $request->setLaravelSession($this->app['session.store']);
        $request->setUserResolver(fn () => $user);
        $request->setRouteResolver(fn () => (new Route('GET', '/admin/feedbacks', []))->name('admin.feedbacks.index'));

        if ($companyId !== null) {
            $request->session()->put(FeedbackCompanyContext::SESSION_KEY, $companyId);
        }

        return $request;
    }

    private function clientFeedbacksRequest(User $user): Request
    {
        $request = Request::create('/client/feedbacks', 'GET');
        $request->setLaravelSession($this->app['session.store']);
        $request->setUserResolver(fn () => $user);
        $request->setRouteResolver(fn () => (new Route('GET', '/client/feedbacks', []))->name('client.feedbacks.index'));

        return $request;
    }
}
