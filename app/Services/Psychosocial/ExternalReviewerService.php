<?php

declare(strict_types=1);

namespace App\Services\Psychosocial;

use App\Enums\UserRole;
use App\Enums\WorkspaceType;
use App\Exceptions\PsychosocialDocumentException;
use App\Mail\ExternalReviewerInvitationMail;
use App\Models\ApproverOrganization;
use App\Models\User;
use App\Models\UserWorkspace;
use App\Support\InvitationPassword;
use App\Support\WorkspaceManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Cadastro do revisor externo (PASSEG) pela Talents: nome, e-mail e organização aprovadora, com convite para definir a
 * senha. O cadastro NÃO libera acesso: a revisão externa continua desligada até o MFA existir (config nr1.external_review).
 */
class ExternalReviewerService
{
    public function __construct(private readonly WorkspaceManager $workspaces) {}

    /**
     * @return array{user: User, mailed: bool, mail_error: ?string}
     */
    public function register(User $actor, string $name, string $email, ?int $organizationId, ?string $newOrganizationName): array
    {
        $this->assertTalents($actor);

        $email = mb_strtolower(trim($email));
        if (User::query()->whereRaw('lower(email) = ?', [$email])->exists()) {
            throw new PsychosocialDocumentException('Este e-mail já tem cadastro. O revisor externo usa uma conta própria, separada da equipe e das empresas.');
        }

        $user = DB::transaction(function () use ($name, $email, $organizationId, $newOrganizationName): User {
            $organization = $this->organization($organizationId, $newOrganizationName);

            $user = User::query()->create([
                'name' => trim($name),
                'email' => $email,
                'password' => Hash::make(Str::password(32)),
                'role' => UserRole::ExternalReviewer,
                'company_id' => null,
                'email_verified_at' => now(),
                'is_active' => true,
                'is_owner' => false,
            ]);

            UserWorkspace::query()->create([
                'user_id' => $user->id,
                'workspace_type' => WorkspaceType::ExternalReview,
                'company_id' => null,
                'approver_organization_id' => $organization->id,
                'role' => UserRole::ExternalReviewer,
                'is_owner' => false,
                'is_active' => true,
            ]);

            $this->workspaces->syncLegacyUserColumns($user);

            return $user;
        });

        return ['user' => $user] + $this->invite($user);
    }

    /**
     * @return array{mailed: bool, mail_error: ?string}
     */
    public function invite(User $user): array
    {
        try {
            $token = InvitationPassword::createToken($user);
            Mail::to($user->email)->send(new ExternalReviewerInvitationMail($user, InvitationPassword::setPasswordUrl($user, $token)));

            return ['mailed' => true, 'mail_error' => null];
        } catch (\Throwable $exception) {
            report($exception);

            return ['mailed' => false, 'mail_error' => $exception->getMessage()];
        }
    }

    public function resend(User $actor, User $reviewer): array
    {
        $this->assertTalents($actor);
        $this->assertReviewer($reviewer);

        if ($reviewer->hasCompletedRegistration()) {
            throw new PsychosocialDocumentException('Este revisor já definiu a senha.');
        }

        return $this->invite($reviewer);
    }

    public function setActive(User $actor, User $reviewer, bool $active): void
    {
        $this->assertTalents($actor);
        $this->assertReviewer($reviewer);

        DB::transaction(function () use ($reviewer, $active): void {
            $reviewer->forceFill(['is_active' => $active])->save();
            $reviewer->workspaces()->where('workspace_type', WorkspaceType::ExternalReview)->update(['is_active' => $active]);
        });
    }

    private function organization(?int $organizationId, ?string $newName): ApproverOrganization
    {
        if (filled($newName)) {
            return ApproverOrganization::query()->firstOrCreate(['name' => trim((string) $newName)], ['is_active' => true]);
        }

        $organization = $organizationId ? ApproverOrganization::query()->where('is_active', true)->find($organizationId) : null;
        if ($organization === null) {
            throw new PsychosocialDocumentException('Escolha a organização aprovadora ou informe o nome de uma nova.');
        }

        return $organization;
    }

    private function assertTalents(User $actor): void
    {
        if (! $actor->isSuperAdmin() && ! $actor->isOwner()) {
            throw new PsychosocialDocumentException('Só a Talents cadastra revisores externos.');
        }
    }

    private function assertReviewer(User $user): void
    {
        if (! $user->workspaces()->where('workspace_type', WorkspaceType::ExternalReview)->exists()) {
            throw new PsychosocialDocumentException('Este usuário não é revisor externo.');
        }
    }
}
