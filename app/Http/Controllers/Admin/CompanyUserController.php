<?php

namespace App\Http\Controllers\Admin;

use App\Actions\SyncUserPermissions;
use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Mail\UserInvitationMail;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CompanyUserController extends Controller
{
    public function __construct(
        private SyncUserPermissions $syncUserPermissions,
    ) {}

    public function index(Company $company): Response
    {
        $company->load(['users' => fn ($q) => $q->orderBy('name')]);

        return Inertia::render('Admin/Companies/Users/Index', [
            'company' => $company->only('id', 'name'),
            'users' => $company->users->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role->value,
                'is_active' => $u->isActive(),
            ]),
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
        $validated = $this->validatedUserPayload($request, null);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make(Str::password(32)),
            'role' => UserRole::from($validated['role']),
            'company_id' => $company->id,
            'email_verified_at' => now(),
            'is_active' => $validated['is_active'] ?? true,
        ]);

        if ($user->isCompanyUser()) {
            $this->syncUserPermissions->execute($user, $company, $validated['permissions'] ?? []);
        }

        $mailMessage = ' Utilizador criado.';
        try {
            $token = Password::broker()->createToken($user);
            $resetUrl = route('password.reset', ['token' => $token]).'?email='.urlencode($user->email);
            Mail::to($user->email)->send(new UserInvitationMail($user, $company, $resetUrl));
            $mailMessage = ' Utilizador criado. Foi enviado um e-mail com o link para definir a senha.';
        } catch (\Throwable $e) {
            report($e);
            $mailMessage = ' Utilizador criado, mas o e-mail de convite não pôde ser enviado. Erro: '.$e->getMessage();
        }

        return redirect()
            ->route('admin.companies.users.index', $company)
            ->with('success', $mailMessage);
    }

    public function edit(Company $company, User $user): Response
    {
        abort_unless($user->company_id === $company->id, 404);

        $user->load('permissions');
        $initialPermissions = $user->permissions->map(fn ($p) => [
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
                'role' => $user->role->value,
                'is_active' => $user->isActive(),
                'permissions' => $initialPermissions,
            ],
            ...$this->sharedFormProps($company),
        ]);
    }

    public function update(Request $request, Company $company, User $user): RedirectResponse
    {
        abort_unless($user->company_id === $company->id, 404);

        $validated = $this->validatedUserPayload($request, $user);

        $this->assertKeepsAtLeastOneActiveAdmin($company, $user, $validated);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => UserRole::from($validated['role']),
            'is_active' => $validated['is_active'] ?? $user->is_active,
        ]);

        if ($user->isCompanyUser()) {
            $this->syncUserPermissions->execute($user, $company, $validated['permissions'] ?? []);
        } else {
            $user->permissions()->delete();
        }

        return redirect()
            ->route('admin.companies.users.index', $company)
            ->with('success', 'Utilizador atualizado.');
    }

    public function destroy(Company $company, User $user): RedirectResponse
    {
        abort_unless($user->company_id === $company->id, 404);

        if ($user->isCompanyAdmin() && $this->companyAdminCount($company) <= 1) {
            return redirect()
                ->route('admin.companies.users.index', $company)
                ->with('error', 'Não é possível remover o único administrador da empresa.');
        }

        $user->delete();

        return redirect()
            ->route('admin.companies.users.index', $company)
            ->with('success', 'Utilizador removido.');
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
    private function validatedUserPayload(Request $request, ?User $existing): array
    {
        $emailRule = Rule::unique('users', 'email');
        if ($existing) {
            $emailRule = $emailRule->ignore($existing->id);
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', $emailRule],
            'role' => ['required', Rule::in([UserRole::CompanyAdmin->value, UserRole::CompanyUser->value])],
            'is_active' => ['boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.*.module' => ['required', Rule::enum(PermissionModule::class)],
            'permissions.*.action' => ['required', Rule::enum(PermissionAction::class)],
        ]);
    }

    private function companyAdminCount(Company $company): int
    {
        return User::query()
            ->where('company_id', $company->id)
            ->where('role', UserRole::CompanyAdmin)
            ->count();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function assertKeepsAtLeastOneActiveAdmin(Company $company, User $user, array $validated): void
    {
        if (! $user->isCompanyAdmin()) {
            return;
        }

        $newRole = UserRole::from($validated['role']);
        $newActive = (bool) ($validated['is_active'] ?? $user->is_active);

        if ($newRole === UserRole::CompanyAdmin && $newActive) {
            return;
        }

        $others = User::query()
            ->where('company_id', $company->id)
            ->where('id', '!=', $user->id)
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
