<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Hiring;

use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\MailSetting;

abstract class CommunicationTestCase extends InterviewTestCase
{
    protected function enableSmtp(): void
    {
        MailSetting::query()->create([
            'host' => 'smtp.example.test',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'talents',
            'password' => 'senha-de-teste',
            'from_address' => 'contato@talents.test',
            'from_name' => 'Talents',
            'is_enabled' => true,
        ]);
    }

    /**
     * Contato elegível para vagas por e-mail e WhatsApp (habilitado e conferido).
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function contact(Company|int $company, array $overrides = []): CompanyContact
    {
        $companyId = $company instanceof Company ? $company->id : $company;

        return CompanyContact::query()->create([
            'company_id' => $companyId,
            'name' => 'Carla RH',
            'role' => 'RH',
            'email' => 'carla.rh@empresa.test',
            'phone' => '+55 11 95555-0000',
            'channel_email' => true,
            'channel_whatsapp' => true,
            'topics' => ['vagas'],
            'is_enabled' => true,
            'reviewed_at' => now(),
            ...$overrides,
        ]);
    }
}
