<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\FinancePayableStatus;
use App\Enums\HiringProcessStage;
use App\Models\CommercialProposal;
use App\Models\Company;
use App\Models\FinancePayable;
use App\Models\HiringProcess;
use App\Models\LandingInterestSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminHomeDashboardMetricsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_home_metrics_come_from_domain_records_not_placeholders(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $company = Company::query()->create(['name' => 'Cliente Ativo A', 'is_active' => true]);
        Company::query()->create(['name' => 'Cliente Ativo B', 'is_active' => true]);

        LandingInterestSubmission::query()->create([
            'name' => 'Lead Site',
            'email' => 'lead@example.com',
            'source' => 'site',
        ]);
        LandingInterestSubmission::query()->create([
            'name' => 'Lead Whats',
            'email' => 'whats@example.com',
            'phone' => '11999990000',
            'source' => 'whatsapp',
        ]);

        CommercialProposal::query()->create([
            'code' => 'PROP-TEST-1',
            'client_name' => 'Prospect',
            'is_closed' => false,
            'total_final_cents' => 150000,
        ]);

        FinancePayable::query()->create([
            'title' => 'Aluguel',
            'amount_cents' => 2100000,
            'due_date' => now()->addDays(5)->toDateString(),
            'status' => FinancePayableStatus::Pending,
            'created_by' => $admin->id,
        ]);

        HiringProcess::query()->create([
            'company_id' => $company->id,
            'title' => 'Analista RH',
            'current_stage' => HiringProcessStage::AnaliseCurriculo,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Dashboard')
                ->where('kpis.active_clients', 2)
                ->where('commercial.leads_new', 2)
                ->where('commercial.in_negotiation', 1)
                ->where('finance.payables_cents', 2100000)
                ->where('kpis.hiring_open', 1)
                ->has('leadsBySource')
                ->has('funnel')
                ->has('monthlyGoal')
                ->missing('recentLeads')
                ->missing('upcomingCalendar')
                ->missing('calendarKindLabels')
                ->missing('alerts'));
    }
}
