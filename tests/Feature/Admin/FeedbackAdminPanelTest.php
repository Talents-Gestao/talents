<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use App\Support\Feedback\FeedbackCompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedbackAdminPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_owner_can_open_feedbacks_admin_panel(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $this->withoutVite();

        $this->actingAs($admin)
            ->get(route('admin.feedbacks.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Client/Feedbacks/Index')
                ->has('companyPicker')
                ->where('isAdminContext', true));
    }

    public function test_super_admin_sees_same_dashboard_after_selecting_company(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $company = Company::query()->create([
            'name' => 'Empresa Admin Feedback',
            'feedbacks_access' => true,
            'is_active' => true,
        ]);

        $this->withoutVite();

        $this->actingAs($admin)
            ->withSession([FeedbackCompanyContext::SESSION_KEY => $company->id])
            ->get(route('admin.feedbacks.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Client/Feedbacks/Index')
                ->where('activeCompany.id', $company->id)
                ->where('isAdminContext', true));
    }

    public function test_super_admin_can_open_employees_list_in_admin_context(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $company = Company::query()->create([
            'name' => 'Empresa Admin Feedback',
            'feedbacks_access' => true,
            'is_active' => true,
        ]);

        $this->withoutVite();

        $this->actingAs($admin)
            ->withSession([FeedbackCompanyContext::SESSION_KEY => $company->id])
            ->get(route('admin.feedbacks.employees.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Client/Feedbacks/Employees/Index'));
    }
}
