<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Hiring;

use App\Models\CompanyContact;
use App\Models\EmploymentCycle;
use App\Models\HiringApplication;
use App\Models\HiringVacancy;
use App\Services\Hiring\EmploymentCycleService;
use App\Services\Hiring\HiringVacancyService;

abstract class FollowupTestCase extends CommunicationTestCase
{
    /**
     * Candidatura já na etapa de contratado.
     *
     * @return array{0: HiringApplication, 1: HiringVacancy}
     */
    protected function hiredApplication(string $name = 'Ana Souza', ?HiringVacancy $vacancy = null): array
    {
        [, $application, $vacancy] = $this->candidateWithApplication($name, $vacancy);
        $hired = $vacancy->stages()->where('is_hired', true)->firstOrFail();
        app(HiringVacancyService::class)->moveApplication($application, $hired, null, null);

        return [$application->fresh(), $vacancy];
    }

    /** Gestor elegível para pós-contratação por e-mail e WhatsApp. */
    protected function manager(int $companyId, array $overrides = []): CompanyContact
    {
        return $this->contact($companyId, [
            'name' => 'Marcos Gestor',
            'email' => 'marcos.gestor@empresa.test',
            'phone' => '+55 11 97777-0000',
            'topics' => ['pos_contratacao'],
            ...$overrides,
        ]);
    }

    /**
     * Ciclo ativo com admissão na data indicada (por padrão hoje − 20 dias: D15 devido).
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function activeCycle(?string $admissionDate = null, array $overrides = [], ?CompanyContact $manager = null): EmploymentCycle
    {
        [$application, $vacancy] = $this->hiredApplication();
        $manager ??= $this->manager($vacancy->company_id);

        return app(EmploymentCycleService::class)->registerAdmission($application, [
            'role_title' => 'Analista de RH',
            'manager_contact_id' => $manager->id,
            'admission_date' => $admissionDate ?? now('America/Sao_Paulo')->subDays(20)->toDateString(),
            'collaborator_email_opt_in' => true,
            ...$overrides,
        ], null);
    }
}
