<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ResendUserInvitation;
use App\Actions\SyncAdminUserPermissions;
use App\Enums\AdminPermissionModule;
use App\Enums\PermissionAction;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Mail\UserInvitationMail;
use App\Models\User;
use App\Support\InvitationPassword;
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
    ) {}

    public function index(): Response
    {
        $users = User::query()
            ->where('role', UserRole::SuperAdmin)
            ->with('adminPermissions')
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'is_owner' => $u->isOwner(),
                'has_all_admin_permissions' => $u->hasAllAdminPermissions(),
                'is_active' => $u->isActive(),
                'is_commercial' => (bool) $u->is_commercial,
                'pending_registration' => ! $u->hasCompletedRegistration(),
            ]);

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

        $this->syncAdminUserPermissions->execute($user, $validated['permissions'] ?? []);

        $mailMessage = 'Utilizador criado.';
        try {
            $token = InvitationPassword::createToken($user);
            $resetUrl = InvitationPassword::setPasswordUrl($user, $token);
            Mail::to($user->email)->send(new UserInvitationMail($user, null, $resetUrl));
            $mailMessage = 'Utilizador criado. Foi enviado um e-mail com o link para definir a senha.';
        } catch (\Throwable $e) {
            report($e);
            $mailMessage = 'Utilizador criado, mas o e-mail de convite não pôde ser enviado. Erro: '.$e->getMessage();
        }

        return redirect()->route('admin.users.index')->with('success', $mailMessage);
    }

    public function edit(User $user): Response
    {
        $this->assertSuperAdminUser($user);

        $user->load('adminPermissions');
        $initialPermissions = $user->adminPermissions->map(fn ($p) => [
            'module' => $p->module->value,
            'action' => $p->action->value,
        ])->values()->all();

        return Inertia::render('Admin/Users/Form', [
            'mode' => 'edit',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_owner' => $user->isOwner(),
                'is_active' => $user->isActive(),
                'is_commercial' => (bool) $user->is_commercial,
                'permissions' => $initialPermissions,
            ],
            ...$this->sharedFormProps(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->assertSuperAdminUser($user);

        $validated = $this->validatedPayload($request, $user, requirePermissions: ! $user->isOwner());

        if ($user->isOwner()) {
            if (($validated['is_active'] ?? $user->is_active) === false) {
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
        } else {
            $this->assertKeepsAtLeastOneActiveSuperAdmin($user, $validated);

            $user->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'is_active' => $validated['is_active'] ?? $user->is_active,
                'is_commercial' => $validated['is_commercial'] ?? $user->is_commercial,
            ]);

            $this->syncAdminUserPermissions->execute($user, $validated['permissions'] ?? []);
        }

        return redirect()->route('admin.users.index')->with('success', 'Utilizador atualizado.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->assertSuperAdminUser($user);

        if ($user->isOwner()) {
            return redirect()->route('admin.users.index')->with('error', 'Não é possível remover o proprietário da plataforma.');
        }

        if ($user->id === auth()->id()) {
            return redirect()->route('admin.users.index')->with('error', 'Não pode remover a sua própria conta.');
        }

        if (! $this->otherActiveSuperAdminsExist($user)) {
            return redirect()->route('admin.users.index')->with('error', 'Tem de existir pelo menos um super administrador ativo.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'Utilizador removido.');
    }

    public function resendInvitation(User $user): RedirectResponse
    {
        $this->assertSuperAdminUser($user);

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

    private function assertSuperAdminUser(User $user): void
    {
        abort_unless($user->isSuperAdmin(), 404);
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
        $emailRule = Rule::unique('users', 'email');
        if ($existing) {
            $emailRule = $emailRule->ignore($existing->id);
        }

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
            'email' => ['required', 'string', 'email', 'max:255', $emailRule],
            'is_active' => ['boolean'],
            'is_commercial' => ['boolean'],
        ], $permissionRules));
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function assertKeepsAtLeastOneActiveSuperAdmin(User $user, array $validated): void
    {
        $newActive = (bool) ($validated['is_active'] ?? $user->is_active);

        if ($newActive) {
            return;
        }

        if ($this->otherActiveSuperAdminsExist($user)) {
            return;
        }

        throw ValidationException::withMessages([
            'is_active' => 'Tem de existir pelo menos um super administrador ativo.',
        ]);
    }

    private function otherActiveSuperAdminsExist(User $excluding): bool
    {
        return User::query()
            ->where('role', UserRole::SuperAdmin)
            ->where('id', '!=', $excluding->id)
            ->where('is_active', true)
            ->exists();
    }
}
