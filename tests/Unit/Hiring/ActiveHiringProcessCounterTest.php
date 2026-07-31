<?php

declare(strict_types=1);

namespace Tests\Unit\Hiring;

use App\Enums\HiringProcessStage;
use App\Models\Company;
use App\Models\HiringProcess;
use App\Models\User;
use App\Support\Hiring\ActiveHiringProcessCounter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActiveHiringProcessCounterTest extends TestCase
{
    use RefreshDatabase;

    public function test_counts_active_processes_for_talents_owner(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $company = Company::query()->create(['name' => 'Demo', 'is_active' => true, 'acompanhamento_access' => true]);

        HiringProcess::query()->create([
            'company_id' => $company->id,
            'title' => 'Ativa',
            'current_stage' => HiringProcessStage::AnaliseCurriculo,
        ]);
        HiringProcess::query()->create([
            'company_id' => $company->id,
            'title' => 'Finalizada',
            'current_stage' => HiringProcessStage::Contratacao,
        ]);

        $this->assertSame(1, app(ActiveHiringProcessCounter::class)->forUser($admin));
    }

    public function test_company_admin_only_sees_own_company_active_processes(): void
    {
        $company = Company::query()->create(['name' => 'Minha', 'is_active' => true, 'acompanhamento_access' => true]);
        $other = Company::query()->create(['name' => 'Outra', 'is_active' => true, 'acompanhamento_access' => true]);
        $this->subscribeCompanyToNr1($company);

        $admin = User::factory()->companyAdmin($company->id)->create();

        HiringProcess::query()->create([
            'company_id' => $company->id,
            'title' => 'Própria',
            'current_stage' => HiringProcessStage::AnuncioVagas,
        ]);
        HiringProcess::query()->create([
            'company_id' => $other->id,
            'title' => 'Alheia',
            'current_stage' => HiringProcessStage::AnuncioVagas,
        ]);

        $this->assertSame(1, app(ActiveHiringProcessCounter::class)->forUser($admin));
    }

    public function test_company_user_does_not_receive_admin_alert_count(): void
    {
        $company = Company::query()->create(['name' => 'Empresa', 'is_active' => true, 'acompanhamento_access' => true]);
        $this->subscribeCompanyToNr1($company);
        $user = User::factory()->companyUser($company->id)->create();

        HiringProcess::query()->create([
            'company_id' => $company->id,
            'title' => 'Ativa',
            'current_stage' => HiringProcessStage::EngenhariaCargo,
        ]);

        $this->assertSame(0, app(ActiveHiringProcessCounter::class)->forUser($user));
    }
}
