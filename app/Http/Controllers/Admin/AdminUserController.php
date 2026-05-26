<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ResendUserInvitation;
use App\Actions\SyncAdminUserPermissions;
use App\Enums\AdminPermissionModule;
use App\Enums\PermissionAction;
use App\Enums\UserRole;
use App\Enums\WorkspaceType;
use App\Http\Controllers\Controller;
use App\Mail\UserInvitationMail;
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

class AdminUserController extends Controller
{
    public function __construct(
        private SyncAdminUserPermissions $syncAdminUserPermissions,
        private ResendUserInvitation $resendUserInvitation,
        private WorkspaceManager $workspaceManager,
    ) {}

    public function index(): Response
    {
        $users = User::query()
            ->whereHas('workspaces', fn ($q) => $q->where('workspace_type', WorkspaceType::Talents))
            ->with(['workspaces' => fn ($q) => $q->where('workspace_type', WorkspaceType::Talents)])
            ->orderBy('name')
            ->get()
            ->map(function (User $u) {
                $workspace = $u->workspaces->first();
                $u->setActiveWorkspace($workspace);

                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'is_owner' => $workspace?->isOwner() ?? false,
                    'has_all_admin_permissions' => $u->hasAllAdminPermissions(),
                    'is_active' => $workspace ? (bool) $workspace->is_active : $u->isActive(),
                    'is_commercial' => (bool) $u->is_commercial,
                    'pending_registration' => ! $u->hasCompletedRegistration(),
                ];
            });

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Users/Form', [
            'mode' => 'create',
            'user' => null,
            ...$this->sharedFormProps(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedPayload($request, null, requirePermissions: true);

        $existingUser = User::query()->where('email', $validated['email'])->first();

        if ($existingUser) {
            if ($this->workspaceManager->talentsWorkspaceFor($existingUser)) {
                throw ValidationException::withMessages([
                    'email' => 'Este e-mail já pertence à equipe Talents.',
                ]);
            }

            $existingUser->update([
                'name' => $validated['name'],
                'is_commercial' => $validated['is_commercial'] ?? $existingUser->is_commercial,
            ]);

            $workspace = $this->workspaceManager->createTalentsWorkspace(
                $existingUser,
                isOwner: false,
                isActive: $validated['is_active'] ?? true,
            );

            $user = $existingUser;
            $mailMessage = 'Utilizador vinculado à equipe Talents (conta existente reutilizada).';
        } else {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make(Str::password(32)),
                'role' => UserRole::SuperAdmin,
                'company_id' => null,
                'email_verified_at' => now(),
                'is_active' => $validated['is_active'] ?? true,
                'is_commercial' => $validated['is_commercial'] ?? false,
                'is_owner' => false,
            ]);

            $workspace = $this->workspaceManager->createTalentsWorkspace(
                $user,
                isOwner: false,
                isActive: $validated['is_active'] ?? true,
            );

            $mailMessage = 'Utilizador criado.';
        }

        $this->syncAdminUserPermissions->execute($workspace, $validated['permissions'] ?? []);

        if (! $user->hasCompletedRegistration()) {
            try {
                $token = InvitationPassword::createToken($user);
                $resetUrl = InvitationPassword::setPasswordUrl($user, $token);
                Mail::to($user->email)->send(new UserInvitationMail($user, null, $resetUrl));
                $mailMessage .= ' Foi enviado um e-mail com o link para definir a senha.';
            } catch (\Throwable $e) {
                report($e);
                $mailMessage .= ' O e-mail de convite não pôde ser enviado. Erro: '.$e->getMessage();
            }
        }

        $this->workspaceManager->syncLegacyUserColumns($user);

        return redirect()->route('admin.users.index')->with('success', $mailMessage);
    }

    public function edit(User $user): Response
    {
        $workspace = $this->assertTalentsWorkspace($user);
        $user->setActiveWorkspace($workspace);
        $workspace->load('adminPermissions');

        $initialPermissions = $workspace->adminPermissions->map(fn ($p) => [
            'module' => $p->module->value,
            'action' => $p->action->value,
        ])->values()->all();

        return Inertia::render('Admin/Users/Form', [
            'mode' => 'edit',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_owner' => $workspace->isOwner(),
                'is_active' => (bool) $workspace->is_active,
                'is_commercial' => (bool) $user->is_commercial,
                'permissions' => $initialPermissions,
            ],
            ...$this->sharedFormProps(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $workspace = $this->assertTalentsWorkspace($user);
        $user->setActiveWorkspace($workspace);

        $validated = $this->validatedPayload($request, $user, requirePermissions: ! $workspace->isOwner());

        if ($workspace->isOwner()) {
            if (($validated['is_active'] ?? $workspace->is_active) === false) {
                throw ValidationException::withMessages([
                    'is_active' => 'A conta do proprietário não pode ser desativada.',
                ]);
            }

            $user->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'is_active' => true,
                'is_commercial' => $validated['is_commercial'] ?? $user->is_commercial,
            ]);

            $workspace->update(['is_active' => true]);
        } else {
            $this->assertKeepsAtLeastOneActiveSuperAdmin($workspace, $validated);

            $user->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'is_active' => $validated['is_active'] ?? $user->is_active,
                'is_commercial' => $validated['is_commercial'] ?? $user->is_commercial,
            ]);

            $workspace->update([
                'is_active' => $validated['is_active'] ?? $workspace->is_active,
            ]);

            $this->syncAdminUserPermissions->execute($workspace, $validated['permissions'] ?? []);
        }

        $this->workspaceManager->syncLegacyUserColumns($user);

        return redirect()->route('admin.users.index')->with('success', 'Utilizador atualizado.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $workspace = $this->assertTalentsWorkspace($user);

        if ($workspace->isOwner()) {
            return redirect()->route('admin.users.index')->with('error', 'Não é possível remover o proprietário da plataforma.');
        }

        if ($user->id === auth()->id()) {
            return redirect()->route('admin.users.index')->with('error', 'Não pode remover a sua própria conta.');
        }

        if (! $this->otherActiveSuperAdminsExist($workspace)) {
            return redirect()->route('admin.users.index')->with('error', 'Tem de existir pelo menos um super administrador ativo.');
        }

        $workspace->adminPermissions()->delete();
        $workspace->delete();

        if ($user->workspaces()->doesntExist()) {
            $user->delete();
        } else {
            $this->workspaceManager->syncLegacyUserColumns($user);
        }

        return redirect()->route('admin.users.index')->with('success', 'Utilizador removido.');
    }

    public function resendInvitation(User $user): RedirectResponse
    {
        $this->assertTalentsWorkspace($user);

        if ($user->hasCompletedRegistration()) {
            return redirect()->route('admin.users.index')->with('error', 'Este utilizador já concluiu o cadastro.');
        }

        try {
            $this->resendUserInvitation->execute($user);
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('admin.users.index')->with(
                'error',
                'Não foi possível reenviar o convite. Erro: '.$e->getMessage()
            );
        }

        return redirect()->route('admin.users.index')->with(
            'success',
            'Convite reenviado para '.$user->email.'.'
        );
    }

    private function assertTalentsWorkspace(User $user): UserWorkspace
    {
        $workspace = $this->workspaceManager->talentsWorkspaceFor($user);
        abort_unless($workspace, 404);

        return $workspace;
    }

    /**
     * @return array<string, mixed>
     */
    private function sharedFormProps(): array
    {
        $modules = [];
        foreach (AdminPermissionModule::all() as $m) {
            $modules[] = ['value' => $m->value, 'label' => $m->label()];
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

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request, ?User $existing, bool $requirePermissions): array
    {
        $permissionRules = $requirePermissions
            ? [
                'permissions' => ['required', 'array', 'min:1'],
                'permissions.*.module' => ['required', Rule::enum(AdminPermissionModule::class)],
                'permissions.*.action' => ['required', Rule::enum(PermissionAction::class)],
            ]
            : [
                'permissions' => ['nullable', 'array'],
                'permissions.*.module' => ['required', Rule::enum(AdminPermissionModule::class)],
                'permissions.*.action' => ['required', Rule::enum(PermissionAction::class)],
            ];

        return $request->validate(array_merge([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail) use ($existing): void {
                    $owner = User::query()->where('email', $value)->first();

                    if (! $owner) {
                        return;
                    }

                    if ($existing && $owner->id === $existing->id) {
                        return;
                    }

                    if ($this->workspaceManager->talentsWorkspaceFor($owner)) {
                        $fail('Este e-mail já pertence à equipe Talents.');
                    }
                },
            ],
            'is_active' => ['boolean'],
            'is_commercial' => ['boolean'],
        ], $permissionRules));
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function assertKeepsAtLeastOneActiveSuperAdmin(UserWorkspace $workspace, array $validated): void
    {
        $newActive = (bool) ($validated['is_active'] ?? $workspace->is_active);

        if ($newActive) {
            return;
        }

        if ($this->otherActiveSuperAdminsExist($workspace)) {
            return;
        }

        throw ValidationException::withMessages([
            'is_active' => 'Tem de existir pelo menos um super administrador ativo.',
        ]);
    }

    private function otherActiveSuperAdminsExist(UserWorkspace $excluding): bool
    {
        return UserWorkspace::query()
            ->where('workspace_type', WorkspaceType::Talents)
            ->where('id', '!=', $excluding->id)
            ->where('is_active', true)
            ->exists();
    }
}
