<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ResendUserInvitation;
use App\Actions\SyncUserPermissions;
use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Enums\UserRole;
use App\Enums\WorkspaceType;
use App\Http\Controllers\Controller;
use App\Mail\UserInvitationMail;
use App\Models\Company;
use App\Models\User;
use App\Models\UserWorkspace;
use App\Support\InvitationPassword;
use App\Support\WorkspaceManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CompanyUserController extends Controller
{
    public function __construct(
        private SyncUserPermissions $syncUserPermissions,
        private ResendUserInvitation $resendUserInvitation,
        private WorkspaceManager $workspaceManager,
    ) {}

    public function index(Company $company): Response
    {
        $users = $this->companyUsersQuery($company)->get();

        return Inertia::render('Admin/Companies/Users/Index', [
            'company' => $company->only('id', 'name'),
            'users' => $users->map(function (User $u) use ($company) {
                $workspace = $u->workspaces->first();

                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'role' => $workspace?->role->value ?? $u->role->value,
                    'is_active' => $workspace ? (bool) $workspace->is_active : $u->isActive(),
                    'pending_registration' => ! $u->hasCompletedRegistration(),
                ];
            }),
        ]);
    }

    public function create(Company $company): Response
    {
        return Inertia::render('Admin/Companies/Users/Form', [
            'mode' => 'create',
            'company' => $company->only('id', 'name'),
            'user' => null,
            ...$this->sharedFormProps($company),
        ]);
    }

    public function store(Request $request, Company $company): RedirectResponse
    {
        $validated = $this->validatedUserPayload($request, null, $company);

        $existingUser = User::query()->where('email', $validated['email'])->first();
        $role = UserRole::from($validated['role']);

        if ($existingUser) {
            if ($this->workspaceManager->companyWorkspaceFor($existingUser, $company->id)) {
                throw ValidationException::withMessages([
                    'email' => 'Este e-mail já está vinculado a esta empresa.',
                ]);
            }

            $existingUser->update(['name' => $validated['name']]);

            $workspace = $this->workspaceManager->createCompanyWorkspace(
                $existingUser,
                $company->id,
                $role,
                $validated['is_active'] ?? true,
            );

            $user = $existingUser;
            $mailMessage = ' Utilizador vinculado à empresa (conta existente reutilizada).';
        } else {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make(Str::password(32)),
                'role' => $role,
                'company_id' => $company->id,
                'email_verified_at' => now(),
                'is_active' => $validated['is_active'] ?? true,
            ]);

            $workspace = $this->workspaceManager->createCompanyWorkspace(
                $user,
                $company->id,
                $role,
                $validated['is_active'] ?? true,
            );

            $mailMessage = ' Utilizador criado.';
        }

        if ($workspace->isCompanyUser()) {
            $this->syncUserPermissions->execute($workspace, $company, $validated['permissions'] ?? []);
        }

        if (! $user->hasCompletedRegistration()) {
            try {
                $token = InvitationPassword::createToken($user);
                $resetUrl = InvitationPassword::setPasswordUrl($user, $token);
                Mail::to($user->email)->send(new UserInvitationMail($user, $company, $resetUrl));
                $mailMessage .= ' Foi enviado um e-mail com o link para definir a senha.';
            } catch (\Throwable $e) {
                report($e);
                $mailMessage .= ' O e-mail de convite não pôde ser enviado. Erro: '.$e->getMessage();
            }
        }

        $this->workspaceManager->syncLegacyUserColumns($user);

        return redirect()
            ->route('admin.companies.users.index', $company)
            ->with('success', trim($mailMessage));
    }

    public function edit(Company $company, User $user): Response
    {
        $workspace = $this->assertCompanyWorkspace($company, $user);
        $workspace->load('permissions');

        $initialPermissions = $workspace->permissions->map(fn ($p) => [
            'module' => $p->module->value,
            'action' => $p->action->value,
        ])->values()->all();

        return Inertia::render('Admin/Companies/Users/Form', [
            'mode' => 'edit',
            'company' => $company->only('id', 'name'),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $workspace->role->value,
                'is_active' => (bool) $workspace->is_active,
                'permissions' => $initialPermissions,
            ],
            ...$this->sharedFormProps($company),
        ]);
    }

    public function update(Request $request, Company $company, User $user): RedirectResponse
    {
        $workspace = $this->assertCompanyWorkspace($company, $user);

        $validated = $this->validatedUserPayload($request, $user, $company);

        $this->assertKeepsAtLeastOneActiveAdmin($company, $workspace, $validated);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        $workspace->update([
            'role' => UserRole::from($validated['role']),
            'is_active' => $validated['is_active'] ?? $workspace->is_active,
        ]);

        if ($workspace->isCompanyUser()) {
            $this->syncUserPermissions->execute($workspace, $company, $validated['permissions'] ?? []);
        } else {
            $workspace->permissions()->delete();
        }

        $this->workspaceManager->syncLegacyUserColumns($user);

        return redirect()
            ->route('admin.companies.users.index', $company)
            ->with('success', 'Utilizador atualizado.');
    }

    public function destroy(Company $company, User $user): RedirectResponse
    {
        $workspace = $this->assertCompanyWorkspace($company, $user);

        if ($workspace->isCompanyAdmin() && $this->companyAdminCount($company) <= 1) {
            return redirect()
                ->route('admin.companies.users.index', $company)
                ->with('error', 'Não é possível remover o único administrador da empresa.');
        }

        $workspace->permissions()->delete();
        $workspace->delete();

        if ($user->workspaces()->doesntExist()) {
            $user->delete();
        } else {
            $this->workspaceManager->syncLegacyUserColumns($user);
        }

        return redirect()
            ->route('admin.companies.users.index', $company)
            ->with('success', 'Utilizador removido.');
    }

    public function resendInvitation(Company $company, User $user): RedirectResponse
    {
        $this->assertCompanyWorkspace($company, $user);

        if ($user->hasCompletedRegistration()) {
            return redirect()
                ->route('admin.companies.users.index', $company)
                ->with('error', 'Este utilizador já concluiu o cadastro.');
        }

        try {
            $this->resendUserInvitation->execute($user, $company);
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('admin.companies.users.index', $company)
                ->with('error', 'Não foi possível reenviar o convite. Erro: '.$e->getMessage());
        }

        return redirect()
            ->route('admin.companies.users.index', $company)
            ->with('success', 'Convite reenviado para '.$user->email.'.');
    }

    private function companyUsersQuery(Company $company)
    {
        return User::query()
            ->whereHas('workspaces', function ($q) use ($company) {
                $q->where('workspace_type', WorkspaceType::Company)
                    ->where('company_id', $company->id);
            })
            ->with(['workspaces' => function ($q) use ($company) {
                $q->where('workspace_type', WorkspaceType::Company)
                    ->where('company_id', $company->id);
            }])
            ->orderBy('name');
    }

    private function assertCompanyWorkspace(Company $company, User $user): UserWorkspace
    {
        $workspace = $this->workspaceManager->companyWorkspaceFor($user, $company->id);
        abort_unless($workspace, 404);

        return $workspace;
    }

    /**
     * @return array<string, mixed>
     */
    private function sharedFormProps(Company $company): array
    {
        $modules = [];
        foreach (PermissionModule::all() as $m) {
            if ($company->hasModuleEnabled($m)) {
                $modules[] = ['value' => $m->value, 'label' => $m->label()];
            }
        }

        $actions = [];
        foreach (PermissionAction::all() as $a) {
            $actions[] = ['value' => $a->value, 'label' => $a->label()];
        }

        return [
            'permissionModules' => $modules,
            'permissionActions' => $actions,
            'roleOptions' => [
                ['value' => UserRole::CompanyAdmin->value, 'label' => UserRole::CompanyAdmin->label()],
                ['value' => UserRole::CompanyUser->value, 'label' => UserRole::CompanyUser->label()],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedUserPayload(Request $request, ?User $existing, Company $company): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail) use ($existing, $company): void {
                    $owner = User::query()->where('email', $value)->first();

                    if (! $owner) {
                        return;
                    }

                    if ($existing && $owner->id === $existing->id) {
                        return;
                    }

                    if ($this->workspaceManager->companyWorkspaceFor($owner, $company->id)) {
                        $fail('Este e-mail já está vinculado a esta empresa.');
                    }
                },
            ],
            'role' => ['required', Rule::in([UserRole::CompanyAdmin->value, UserRole::CompanyUser->value])],
            'is_active' => ['boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.*.module' => ['required', Rule::enum(PermissionModule::class)],
            'permissions.*.action' => ['required', Rule::enum(PermissionAction::class)],
        ]);
    }

    private function companyAdminCount(Company $company): int
    {
        return UserWorkspace::query()
            ->where('workspace_type', WorkspaceType::Company)
            ->where('company_id', $company->id)
            ->where('role', UserRole::CompanyAdmin)
            ->count();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function assertKeepsAtLeastOneActiveAdmin(Company $company, UserWorkspace $workspace, array $validated): void
    {
        if (! $workspace->isCompanyAdmin()) {
            return;
        }

        $newRole = UserRole::from($validated['role']);
        $newActive = (bool) ($validated['is_active'] ?? $workspace->is_active);

        if ($newRole === UserRole::CompanyAdmin && $newActive) {
            return;
        }

        $others = UserWorkspace::query()
            ->where('workspace_type', WorkspaceType::Company)
            ->where('company_id', $company->id)
            ->where('id', '!=', $workspace->id)
            ->where('role', UserRole::CompanyAdmin)
            ->where('is_active', true)
            ->count();

        if ($others < 1) {
            throw ValidationException::withMessages([
                'role' => 'Deve existir pelo menos um administrador da empresa ativo.',
            ]);
        }
    }
}
