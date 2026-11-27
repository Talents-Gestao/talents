<?php

declare(strict_types=1);

namespace Tests\Feature\Feedback;

use App\Models\Company;
use App\Models\Module;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Feedback\FeedbackCompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Feedbacks internos é liberado para toda empresa ativa, sem depender de assinatura ou plano;
 * só `feedbacks_access = false` bloqueia.
 */
class FeedbacksAvailableForAllCompaniesTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_company_without_subscription_is_listed(): void
    {
        $company = Company::query()->create(['name' => 'Sem assinatura', 'is_active' => true]);

        $this->assertTrue($company->hasFeedbacksEnabled());
        $this->assertSame(['Sem assinatura'], $this->availableNames());
        $this->assertSame(['Sem assinatura'], $this->adminPickerNames());
    }

    public function test_active_company_with_plan_without_feedbacks_is_listed(): void
    {
        $company = Company::query()->create(['name' => 'Plano sem Feedbacks', 'is_active' => true]);
        $this->subscribeCompanyToNr1($company);

        $this->assertFalse(in_array(Module::KEY_FEEDBACKS, $company->activePlanModuleKeys(), true));
        $this->assertTrue($company->hasFeedbacksEnabled());
        $this->assertSame(['Plano sem Feedbacks'], $this->availableNames());
        $this->assertSame(['Plano sem Feedbacks'], $this->adminPickerNames());
    }

    public function test_inactive_company_is_not_listed(): void
    {
        Company::query()->create(['name' => 'Ativa', 'is_active' => true]);
        Company::query()->create(['name' => 'Inativa', 'is_active' => false]);

        $this->assertSame(['Ativa'], $this->availableNames());
        $this->assertSame(['Ativa'], $this->adminPickerNames());
    }

    public function test_forced_disabled_company_is_hidden_and_portal_returns_403(): void
    {
        Company::query()->create(['name' => 'Liberada', 'is_active' => true]);
        $blocked = Company::query()->create([
            'name' => 'Bloqueada',
            'is_active' => true,
            'feedbacks_access' => false,
        ]);
        $this->subscribeCompanyToFeedbacksPlan($blocked);

        $this->assertFalse($blocked->fresh()->hasFeedbacksEnabled());
        $this->assertSame(['Liberada'], $this->availableNames());
        $this->assertSame(['Liberada'], $this->adminPickerNames());

        $rh = User::factory()->companyAdmin($blocked->id)->create();
        $leader = User::factory()->companyUser($blocked->id)->create();

        foreach ([$rh, $leader] as $user) {
            $this->actingAs($user)->get(route('client.feedbacks.index'))->assertForbidden();
            $this->actingAs($user)->get(route('client.feedbacks.sessions.create'))->assertForbidden();
        }

        $super = User::factory()->superAdmin()->create(['is_owner' => true]);
        $this->actingAs($super)
            ->post(route('admin.feedbacks.company.store'), ['company_id' => $blocked->id])
            ->assertForbidden();
    }

    public function test_company_admin_of_company_without_plan_opens_client_feedbacks(): void
    {
        $company = Company::query()->create(['name' => 'Sem plano', 'is_active' => true]);
        $rh = User::factory()->companyAdmin($company->id)->create();

        $this->withoutVite();

        $this->actingAs($rh)
            ->get(route('client.feedbacks.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Client/Feedbacks/Index'));
    }

    public function test_company_edit_selector_saves_null_and_false(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Seletor',
            'is_active' => true,
            'feedbacks_access' => true,
            'complaints_public_token' => (string) Str::uuid(),
        ]);
        $super = User::factory()->talentsOwner()->create();

        $this->actingAs($super)
            ->put(route('admin.companies.update', $company), $this->updatePayload($company, 'disabled'))
            ->assertSessionHasNoErrors();
        $this->assertFalse($company->fresh()->feedbacks_access);

        $this->actingAs($super)
            ->put(route('admin.companies.update', $company), $this->updatePayload($company, 'enabled'))
            ->assertSessionHasNoErrors();
        $this->assertNull($company->fresh()->feedbacks_access);

        $this->actingAs($super)
            ->put(route('admin.companies.update', $company), $this->updatePayload($company, 'inherit'))
            ->assertSessionHasErrors('feedbacks_access_mode');
    }

    /**
     * @return list<string>
     */
    private function availableNames(): array
    {
        return app(FeedbackCompanyContext::class)->availableCompanies()->pluck('name')->all();
    }

    /**
     * @return list<string>
     */
    private function adminPickerNames(): array
    {
        $super = User::factory()->superAdmin()->create(['is_owner' => true]);
        $this->withoutVite();

        $picker = $this->actingAs($super)
            ->get(route('admin.feedbacks.index'))
            ->assertOk()
            ->viewData('page')['props']['companyPicker'];

        return array_column($picker, 'name');
    }

    /**
     * @return array<string, mixed>
     */
    private function updatePayload(Company $company, string $feedbacksMode): array
    {
        return [
            'name' => $company->name,
            'is_active' => true,
            'strategic_calendar_access_mode' => 'inherit',
            'tasks_access_mode' => 'inherit',
            'rhid_access_mode' => 'inherit',
            'denuncias_access_mode' => 'inherit',
            'ferias_access_mode' => 'inherit',
            'feedbacks_access_mode' => $feedbacksMode,
            'desligamento_access_mode' => 'inherit',
            'acompanhamento_access_mode' => 'inherit',
        ];
    }

    private function subscribeCompanyToFeedbacksPlan(Company $company): void
    {
        $module = Module::query()->firstOrCreate(
            ['key' => Module::KEY_FEEDBACKS],
            ['name' => 'Feedbacks', 'description' => 'Teste']
        );
        $plan = Plan::query()->create([
            'name' => 'Plano Feedbacks',
            'slug' => 'feedbacks-'.Str::random(8),
            'price_monthly_cents' => 0,
            'is_active' => true,
        ]);
        $plan->modules()->sync([$module->id]);
        Subscription::query()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'starts_at' => now(),
            'ends_at' => now()->addYear(),
            'status' => 'active',
        ]);
    }
}
