<?php

namespace App\Http\Controllers\Client;

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

class UserController extends Controller
{
    public function __construct(
        private SyncUserPermissions $syncUserPermissions,
        private WorkspaceManager $workspaceManager,
    ) {}

    public function index(Request $request): Response
    {
        $company = $request->user()->contextCompany();
        abort_unless($company, 404);

        $users = User::query()
            ->whereHas('workspaces', function ($q) use ($company) {
                $q->where('workspace_type', WorkspaceType::Company)
                    ->where('company_id', $company->id)
                    ->where('role', UserRole::CompanyUser);
            })
            ->with(['workspaces' => function ($q) use ($company) {
                $q->where('workspace_type', WorkspaceType::Company)
                    ->where('company_id', $company->id);
            }])
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return Inertia::render('Client/Users/Index', [
            'users' => $users->map(function (User $u) {
                $workspace = $u->workspaces->first();

                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'role' => $workspace?->role->value ?? UserRole::CompanyUser->value,
                    'is_active' => $workspace ? (bool) $workspace->is_active : $u->isActive(),
                ];
            }),
        ]);
    }

    public function create(Request $request): Response
    {
        $company = $request->user()->contextCompany();
        abort_unless($company, 404);

        return Inertia::render('Client/Users/Form', [
            'mode' => 'create',
            'user' => null,
            ...$this->sharedFormProps($company),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $request->user()->contextCompany();
        abort_unless($company, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail) use ($company): void {
                    $owner = User::query()->where('email', $value)->first();

                    if (! $owner) {
                        return;
                    }

                    if ($this->workspaceManager->companyWorkspaceFor($owner, $company->id)) {
                        $fail('Este e-mail já está vinculado a esta empresa.');
                    }
                },
            ],
            'is_active' => ['boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.*.module' => ['required', Rule::enum(PermissionModule::class)],
            'permissions.*.action' => ['required', Rule::enum(PermissionAction::class)],
        ]);

        $existingUser = User::query()->where('email', $validated['email'])->first();

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
                UserRole::CompanyUser,
                $validated['is_active'] ?? true,
            );

            $user = $existingUser;
            $mailMessage = ' Utilizador vinculado à empresa (conta existente reutilizada).';
        } else {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make(Str::password(32)),
                'role' => UserRole::CompanyUser,
                'company_id' => $company->id,
                'email_verified_at' => now(),
                'is_active' => $validated['is_active'] ?? true,
            ]);

            $workspace = $this->workspaceManager->createCompanyWorkspace(
                $user,
                $company->id,
                UserRole::CompanyUser,
                $validated['is_active'] ?? true,
            );

            $mailMessage = ' Utilizador criado.';
        }

        $this->syncUserPermissions->execute($workspace, $company, $validated['permissions'] ?? []);

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

        return redirect()->route('client.usuarios.index')->with('success', trim($mailMessage));
    }

    public function edit(Request $request, User $user): Response
    {
        $company = $request->user()->contextCompany();
        abort_unless($company, 404);

        $workspace = $this->assertCompanyUserWorkspace($company, $user);
        abort_if($workspace->isCompanyAdmin(), 403);

        $workspace->load('permissions');
        $initialPermissions = $workspace->permissions->map(fn ($p) => [
            'module' => $p->module->value,
            'action' => $p->action->value,
        ])->values()->all();

        return Inertia::render('Client/Users/Form', [
            'mode' => 'edit',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => (bool) $workspace->is_active,
                'permissions' => $initialPermissions,
            ],
            ...$this->sharedFormProps($company),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $company = $request->user()->contextCompany();
        abort_unless($company, 404);

        $workspace = $this->assertCompanyUserWorkspace($company, $user);
        abort_if($workspace->isCompanyAdmin(), 403);
        abort_if($user->id === $request->user()->id, 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'is_active' => ['boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.*.module' => ['required', Rule::enum(PermissionModule::class)],
            'permissions.*.action' => ['required', Rule::enum(PermissionAction::class)],
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        $workspace->update([
            'is_active' => $validated['is_active'] ?? $workspace->is_active,
        ]);

        $this->syncUserPermissions->execute($workspace, $company, $validated['permissions'] ?? []);
        $this->workspaceManager->syncLegacyUserColumns($user);

        return redirect()->route('client.usuarios.index')->with('success', 'Utilizador atualizado.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $company = $request->user()->contextCompany();
        abort_unless($company, 404);

        $workspace = $this->assertCompanyUserWorkspace($company, $user);
        abort_if($workspace->isCompanyAdmin(), 403);
        abort_if($user->id === $request->user()->id, 403);

        $workspace->permissions()->delete();
        $workspace->delete();

        if ($user->workspaces()->doesntExist()) {
            $user->delete();
        } else {
            $this->workspaceManager->syncLegacyUserColumns($user);
        }

        return redirect()->route('client.usuarios.index')->with('success', 'Utilizador removido.');
    }

    private function assertCompanyUserWorkspace(Company $company, User $user): UserWorkspace
    {
        $workspace = $this->workspaceManager->companyWorkspaceFor($user, $company->id);
        abort_unless($workspace && $workspace->isCompanyUser(), 404);

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
        ];
    }
}
