<?php

declare(strict_types=1);

namespace App\Actions\Company;

use App\Enums\UserRole;
use App\Mail\CompanyAdminInvitationMail;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\User;
use App\Support\InvitationPassword;
use App\Support\WorkspaceManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Cria a empresa cliente com o primeiro company_admin (e-mail de contato) e envia o convite de acesso.
 * Fluxo único do cadastro de empresa (Admin → Empresas) e da conversão de lead em cliente.
 */
final class CreateCompanyWithAdmin
{
    public function __construct(
        private WorkspaceManager $workspaceManager,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Campos já validados; `name` e `contact_email` obrigatórios.
     *                                      `admin_name` (opcional) é o nome do primeiro administrador.
     * @param  (callable(Company): void)|null  $afterCreate  Roda na mesma transação, depois de criar empresa e administrador.
     * @return array{company: Company, admin: User, invitation_error: string|null}
     */
    public function execute(array $data, ?callable $afterCreate = null): array
    {
        $state = $data['address_state'] ?? null;
        $state = is_string($state) && $state !== '' ? strtoupper($state) : null;

        [$company, $admin] = DB::transaction(function () use ($data, $state, $afterCreate) {
            $company = Company::create([
                'name' => $data['name'],
                'contact_email' => $data['contact_email'],
                'instagram' => $data['instagram'] ?? null,
                'legal_name' => $data['legal_name'] ?? null,
                'cnpj' => $data['cnpj'] ?? null,
                'segment' => $data['segment'] ?? null,
                'activity_branch' => $data['activity_branch'] ?? null,
                'collective_bargaining_month' => $data['collective_bargaining_month'] ?? null,
                'address_street' => $data['address_street'] ?? null,
                'address_neighborhood' => $data['address_neighborhood'] ?? null,
                'address_city' => $data['address_city'] ?? null,
                'address_state' => $state,
                'address_zip' => $data['address_zip'] ?? null,
                'tax_regime' => $data['tax_regime'] ?? null,
                'employee_count_estimate' => $data['employee_count_estimate'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'complaints_public_token' => (string) Str::uuid(),
            ]);

            if (! empty($data['plan_id'])) {
                Subscription::create([
                    'company_id' => $company->id,
                    'plan_id' => $data['plan_id'],
                    'starts_at' => now(),
                    'ends_at' => now()->addYear(),
                    'status' => 'active',
                ]);
            }

            $admin = User::create([
                'name' => $data['admin_name'] ?? $data['name'],
                'email' => $data['contact_email'],
                'password' => Hash::make(Str::password(32)),
                'role' => UserRole::CompanyAdmin,
                'company_id' => $company->id,
                'email_verified_at' => now(),
            ]);

            $this->workspaceManager->createCompanyWorkspace($admin, $company->id, UserRole::CompanyAdmin);

            if ($afterCreate !== null) {
                $afterCreate($company);
            }

            return [$company, $admin];
        });

        $invitationError = null;
        try {
            $token = InvitationPassword::createToken($admin);
            $resetUrl = InvitationPassword::setPasswordUrl($admin, $token);

            Mail::to($admin->email)->send(new CompanyAdminInvitationMail($admin, $company, $resetUrl));
        } catch (\Throwable $e) {
            report($e);
            $invitationError = $e->getMessage();
        }

        return ['company' => $company, 'admin' => $admin, 'invitation_error' => $invitationError];
    }
}
