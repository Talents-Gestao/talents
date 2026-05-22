<?php

namespace Tests\Feature;

use App\Enums\StrategicCalendarItemKind;
use App\Enums\StrategicCalendarViewPeriod;
use App\Models\Company;
use App\Models\Module;
use App\Models\Plan;
use App\Models\StrategicCalendarItem;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class StrategicCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function baseCompany(): Company
    {
        return Company::query()->create([
            'name' => 'Empresa teste',
            'cnpj' => '11.111.111/0001-11',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
        ]);
    }

    public function test_guest_cannot_access_client_strategic_calendar(): void
    {
        $this->get('/client/calendario-estrategico')->assertRedirect(route('login'));
    }

    public function test_client_forbidden_without_access(): void
    {
        $company = $this->baseCompany();
        $plan = Plan::query()->create([
            'name' => 'Plano X',
            'slug' => 'plano-x-'.Str::random(8),
            'price_monthly_cents' => 0,
            'is_active' => true,
        ]);
        $nr1 = Module::query()->firstOrCreate(
            ['key' => 'nr1'],
            ['name' => 'NR1', 'description' => 'x']
        );
        $plan->modules()->sync([$nr1->id]);

        Subscription::query()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'starts_at' => now(),
            'ends_at' => now()->addYear(),
            'status' => 'active',
        ]);

        $user = User::factory()->companyAdmin($company->id)->create();

        $this->actingAs($user)
            ->get('/client/calendario-estrategico')
            ->assertForbidden();
    }

    public function test_client_ok_when_company_forced_enabled(): void
    {
        $company = $this->baseCompany();
        $company->update(['strategic_calendar_access' => true]);

        $user = User::factory()->companyAdmin($company->id)->create();

        $this->actingAs($user)
            ->get('/client/calendario-estrategico')
            ->assertOk();
    }

    public function test_client_ok_when_plan_has_module(): void
    {
        $company = $this->baseCompany();
        $plan = Plan::query()->create([
            'name' => 'Plano Cal',
            'slug' => 'plano-cal-'.Str::random(8),
            'price_monthly_cents' => 0,
            'is_active' => true,
        ]);
        $cal = Module::query()->firstOrCreate(
            ['key' => Module::KEY_CALENDARIO_ESTRATEGICO],
            ['name' => 'Calendário', 'description' => 'x']
        );
        $plan->modules()->sync([$cal->id]);

        Subscription::query()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'starts_at' => now(),
            'ends_at' => now()->addYear(),
            'status' => 'active',
        ]);

        $user = User::factory()->companyAdmin($company->id)->create();

        $this->actingAs($user)
            ->get('/client/calendario-estrategico')
            ->assertOk();
    }

    public function test_super_admin_can_create_strategic_calendar_item(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->post('/admin/calendario-estrategico', [
                'title' => 'Revisão trimestral',
                'description' => 'Alinhar metas com liderança.',
                'kind' => StrategicCalendarItemKind::Event->value,
                'occurs_on' => '2026-06-15',
                'company_id' => null,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('strategic_calendar_items', [
            'title' => 'Revisão trimestral',
            'kind' => 'event',
        ]);
    }

    public function test_company_admin_cannot_post_admin_strategic_calendar(): void
    {
        $company = $this->baseCompany();
        $user = User::factory()->companyAdmin($company->id)->create();

        $this->actingAs($user)
            ->post('/admin/calendario-estrategico', [
                'title' => 'X',
                'kind' => StrategicCalendarItemKind::Rito->value,
                'occurs_on' => '2026-01-01',
            ])
            ->assertForbidden();
    }

    public function test_client_sees_only_applicable_items(): void
    {
        $company = $this->baseCompany();
        $company->update(['strategic_calendar_access' => true]);

        StrategicCalendarItem::query()->create([
            'title' => 'Global',
            'description' => null,
            'kind' => StrategicCalendarItemKind::Event,
            'occurs_on' => '2026-05-10',
            'company_id' => null,
        ]);

        $otherCompany = Company::query()->create([
            'name' => 'Outra empresa',
            'cnpj' => '22.222.222/0001-22',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
        ]);

        StrategicCalendarItem::query()->create([
            'title' => 'Só para outra',
            'description' => null,
            'kind' => StrategicCalendarItemKind::Event,
            'occurs_on' => '2026-05-11',
            'company_id' => $otherCompany->id,
        ]);

        $user = User::factory()->companyAdmin($company->id)->create();

        $response = $this->actingAs($user)
            ->get('/client/calendario-estrategico?year=2026&month=5');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Client/StrategicCalendar/Index')
            ->has('monthItems', 1)
            ->where('monthItems.0.title', 'Global'));
    }

    public function test_client_calendar_clamped_to_two_months_plan(): void
    {
        $company = $this->baseCompany();
        $plan = Plan::query()->create([
            'name' => 'Plano 2 meses',
            'slug' => 'plano-2m-'.Str::random(8),
            'price_monthly_cents' => 0,
            'is_active' => true,
            'strategic_calendar_view_period' => StrategicCalendarViewPeriod::TwoMonths,
        ]);
        $cal = Module::query()->firstOrCreate(
            ['key' => Module::KEY_CALENDARIO_ESTRATEGICO],
            ['name' => 'Calendário', 'description' => 'x']
        );
        $plan->modules()->sync([$cal->id]);

        Subscription::query()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'starts_at' => now(),
            'ends_at' => now()->addYear(),
            'status' => 'active',
        ]);

        $user = User::factory()->companyAdmin($company->id)->create();

        $now = now();
        $inRange = $now->copy()->addMonth()->endOfMonth();
        $outOfRange = $now->copy()->addMonths(3)->startOfMonth();

        StrategicCalendarItem::query()->create([
            'title' => 'Dentro do plano',
            'description' => null,
            'kind' => StrategicCalendarItemKind::Event,
            'occurs_on' => $inRange->toDateString(),
            'company_id' => null,
        ]);

        StrategicCalendarItem::query()->create([
            'title' => 'Fora do plano',
            'description' => null,
            'kind' => StrategicCalendarItemKind::Event,
            'occurs_on' => $outOfRange->toDateString(),
            'company_id' => null,
        ]);

        $this->actingAs($user)
            ->get('/client/calendario-estrategico?year=2020&month=1')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Client/StrategicCalendar/Index')
                ->where('calendarYear', $now->year)
                ->where('calendarMonth', $now->month)
                ->where('visiblePeriod.label', StrategicCalendarViewPeriod::TwoMonths->label())
                ->where('canNavigatePrev', false)
                ->missing('monthItems.1'));

        $this->actingAs($user)
            ->get('/client/calendario-estrategico?year='.$outOfRange->year.'&month='.$outOfRange->month)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('calendarYear', $now->year)
                ->where('calendarMonth', $now->month));
    }

    public function test_client_calendar_unlimited_when_plan_has_no_period(): void
    {
        $company = $this->baseCompany();
        $plan = Plan::query()->create([
            'name' => 'Plano sem limite',
            'slug' => 'plano-free-cal-'.Str::random(8),
            'price_monthly_cents' => 0,
            'is_active' => true,
            'strategic_calendar_view_period' => null,
        ]);
        $cal = Module::query()->firstOrCreate(
            ['key' => Module::KEY_CALENDARIO_ESTRATEGICO],
            ['name' => 'Calendário', 'description' => 'x']
        );
        $plan->modules()->sync([$cal->id]);

        Subscription::query()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'starts_at' => now(),
            'ends_at' => now()->addYear(),
            'status' => 'active',
        ]);

        $user = User::factory()->companyAdmin($company->id)->create();

        $this->actingAs($user)
            ->get('/client/calendario-estrategico?year=2030&month=6')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Client/StrategicCalendar/Index')
                ->where('calendarYear', 2030)
                ->where('calendarMonth', 6)
                ->where('visiblePeriod', null)
                ->where('canNavigatePrev', true)
                ->where('canNavigateNext', true));
    }
}
