<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Hiring;

use App\Actions\SyncAdminUserPermissions;
use App\Enums\HiringProcessStage;
use App\Models\Company;
use App\Models\HiringProcess;
use App\Models\HiringVacancy;
use App\Models\User;
use App\Services\Hiring\HiringVacancyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class HiringVacancyTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    protected function owner(): User
    {
        return User::factory()->talentsOwner()->create();
    }

    /**
     * @param  array<string, list<string>>  $grants  módulo => ações
     */
    protected function adminWith(array $grants): User
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => false]);
        $rows = [];
        foreach ($grants as $module => $actions) {
            foreach ($actions as $action) {
                $rows[] = ['module' => $module, 'action' => $action];
            }
        }
        app(SyncAdminUserPermissions::class)->execute($admin->talentsWorkspace(), $rows);

        return $admin->fresh();
    }

    protected function process(
        HiringProcessStage $stage = HiringProcessStage::AnaliseCurriculo,
        ?Company $company = null,
    ): HiringProcess {
        $company ??= Company::query()->create(['name' => 'Empresa Vagas', 'is_active' => true]);

        return HiringProcess::query()->create([
            'company_id' => $company->id,
            'title' => 'Processo Analista',
            'current_stage' => $stage,
        ]);
    }

    protected function vacancy(?HiringProcess $process = null, string $title = 'Analista de RH'): HiringVacancy
    {
        return app(HiringVacancyService::class)->create($process ?? $this->process(), ['title' => $title], null);
    }
}
