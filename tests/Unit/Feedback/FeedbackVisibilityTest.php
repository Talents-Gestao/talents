<?php

declare(strict_types=1);

namespace Tests\Unit\Feedback;

use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\FeedbackSession;
use App\Models\User;
use App\Support\Feedback\FeedbackCompanyContext;
use App\Support\Feedback\FeedbackVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesFeedbackFixtures;
use Tests\TestCase;

class FeedbackVisibilityTest extends TestCase
{
    use CreatesFeedbackFixtures;
    use RefreshDatabase;

    public function test_company_admin_sees_all_sessions_and_employees(): void
    {
        $company = $this->createFeedbackCompany();
        $admin = User::factory()->companyAdmin($company->id)->create();
        $leaderA = User::factory()->companyUser($company->id)->create();
        $leaderB = User::factory()->companyUser($company->id)->create();

        $employeeA = $this->createFeedbackEmployee($company, $leaderA, ['email' => 'a@test.local']);
        $employeeB = $this->createFeedbackEmployee($company, $leaderB, ['email' => 'b@test.local']);

        $employees = FeedbackVisibility::scopeEmployees(
            CompanyEmployee::query()->where('company_id', $company->id),
            $admin,
        )->pluck('id');

        $this->assertEqualsCanonicalizing([$employeeA->id, $employeeB->id], $employees->all());

        $this->createFeedbackSession($company, $leaderA, $employeeA, ['title' => 'S1']);
        $this->createFeedbackSession($company, $leaderB, $employeeB, ['title' => 'S2']);

        $sessions = FeedbackVisibility::scopeSessions(
            FeedbackSession::query()->where('company_id', $company->id),
            $admin,
        )->count();

        $this->assertSame(2, $sessions);
    }

    public function test_company_user_only_sees_own_team(): void
    {
        $company = $this->createFeedbackCompany();
        $leaderA = User::factory()->companyUser($company->id)->create();
        $leaderB = User::factory()->companyUser($company->id)->create();

        $employeeA = $this->createFeedbackEmployee($company, $leaderA, ['email' => 'a@test.local']);
        $employeeB = $this->createFeedbackEmployee($company, $leaderB, ['email' => 'b@test.local']);

        $visibleEmployees = FeedbackVisibility::scopeEmployees(
            CompanyEmployee::query()->where('company_id', $company->id),
            $leaderA,
        )->pluck('id');

        $this->assertSame([$employeeA->id], $visibleEmployees->all());

        $this->createFeedbackSession($company, $leaderA, $employeeA, ['title' => 'S1']);
        $this->createFeedbackSession($company, $leaderB, $employeeB, ['title' => 'S2']);

        $visibleSessions = FeedbackVisibility::scopeSessions(
            FeedbackSession::query()->where('company_id', $company->id),
            $leaderA,
        )->pluck('title');

        $this->assertSame(['S1'], $visibleSessions->all());
    }

    public function test_super_admin_authorizes_session_for_selected_company_only(): void
    {
        $companyA = $this->createFeedbackCompany(['name' => 'A']);
        $companyB = $this->createFeedbackCompany(['name' => 'B']);
        $admin = User::factory()->superAdmin()->create();
        $leader = User::factory()->companyAdmin($companyA->id)->create();
        $employee = $this->createFeedbackEmployee($companyA, $leader);
        $sessionA = $this->createFeedbackSession($companyA, $leader, $employee);

        $leaderB = User::factory()->companyAdmin($companyB->id)->create();
        $employeeB = $this->createFeedbackEmployee($companyB, $leaderB, ['email' => 'b@test.local']);
        $sessionB = $this->createFeedbackSession($companyB, $leaderB, $employeeB);

        $this->actingAs($admin);
        $this->withSession([FeedbackCompanyContext::SESSION_KEY => $companyA->id]);
        $this->get(route('admin.feedbacks.sessions.show', $sessionA))->assertOk();

        $this->withSession([FeedbackCompanyContext::SESSION_KEY => $companyA->id]);
        $this->get(route('admin.feedbacks.sessions.show', $sessionB))->assertForbidden();
    }

    public function test_acts_as_company_admin_includes_super_admin(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $rh = User::factory()->companyAdmin($this->createFeedbackCompany()->id)->create();
        $leader = User::factory()->companyUser($this->createFeedbackCompany()->id)->create();

        $this->assertTrue(FeedbackVisibility::actsAsCompanyAdmin($admin));
        $this->assertTrue(FeedbackVisibility::actsAsCompanyAdmin($rh));
        $this->assertFalse(FeedbackVisibility::actsAsCompanyAdmin($leader));
    }
}
