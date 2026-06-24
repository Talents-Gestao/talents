<?php

namespace App\Actions;

use App\Mail\CompanyAdminInvitationMail;
use App\Models\Company;
use App\Support\InvitationPassword;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use RuntimeException;

class ResendCompanyInvitation
{
    public function execute(Company $company): void
    {
        $admin = $company->registrationAdmin();

        if ($admin === null) {
            throw new RuntimeException('Não foi encontrado um administrador para esta empresa.');
        }

        if ($admin->hasCompletedRegistration()) {
            throw new InvalidArgumentException('O cadastro desta empresa já foi concluído.');
        }

        $token = InvitationPassword::createToken($admin);
        $resetUrl = InvitationPassword::setPasswordUrl($admin, $token);

        Mail::to($admin->email)->send(new CompanyAdminInvitationMail($admin, $company, $resetUrl));
    }
}
