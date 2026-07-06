<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\FeedbackSessionStatus;
use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\FeedbackSession;
use App\Models\FeedbackTemplate;
use App\Models\User;
use Database\Seeders\FeedbackTemplateSeeder;

trait CreatesFeedbackFixtures
{
    protected function createFeedbackCompany(array $attributes = []): Company
    {
        return Company::query()->create(array_merge([
            'name' => 'Empresa Feedback Test',
            'feedbacks_access' => true,
            'is_active' => true,
        ], $attributes));
    }

    protected function seedFeedbackTemplate(): FeedbackTemplate
    {
        $this->seed(FeedbackTemplateSeeder::class);

        return FeedbackTemplate::query()->whereNull('company_id')->firstOrFail();
    }

    protected function createFeedbackEmployee(Company $company, User $leader, array $attributes = []): CompanyEmployee
    {
        return CompanyEmployee::query()->create(array_merge([
            'company_id' => $company->id,
            'name' => 'Colaborador Teste',
            'email' => 'colab@teste.local',
            'leader_user_id' => $leader->id,
            'is_active' => true,
        ], $attributes));
    }

    protected function createFeedbackSession(
        Company $company,
        User $leader,
        CompanyEmployee $employee,
        array $attributes = [],
    ): FeedbackSession {
        $template = FeedbackTemplate::query()->whereNull('company_id')->first()
            ?? $this->seedFeedbackTemplate();

        return FeedbackSession::query()->create(array_merge([
            'company_id' => $company->id,
            'feedback_template_id' => $template->id,
            'company_employee_id' => $employee->id,
            'leader_user_id' => $leader->id,
            'title' => 'Feedback Teste',
            'status' => FeedbackSessionStatus::InProgress,
        ], $attributes));
    }
}
