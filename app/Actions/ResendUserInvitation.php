<?php

namespace App\Actions;

use App\Mail\CompanyAdminInvitationMail;
use App\Mail\UserInvitationMail;
use App\Models\Company;
use App\Models\User;
use App\Support\InvitationPassword;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

class ResendUserInvitation
{
    public function execute(User $user, ?Company $company = null): void
    {
        if ($user->hasCompletedRegistration()) {
            throw new InvalidArgumentException('Este utilizador já concluiu o cadastro da senha.');
        }

        $token = InvitationPassword::createToken($user);
        $resetUrl = InvitationPassword::setPasswordUrl($user, $token);

        if ($user->isCompanyAdmin() && $company !== null) {
            Mail::to($user->email)->send(new CompanyAdminInvitationMail($user, $company, $resetUrl));

            return;
        }

        Mail::to($user->email)->send(new UserInvitationMail($user, $company, $resetUrl));
    }
}
