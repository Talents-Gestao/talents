<?php

namespace App\Http\Controllers\Client;

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
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(
        private SyncUserPermissions $syncUserPermissions,
    ) {}

    public function index(Request $request): Response
    {
        $company = $request->user()->company;
        abort_unless($company, 404);

        $users = User::query()
            ->where('company_id', $company->id)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'is_active']);

        return Inertia::render('Client/Users/Index', [
            'users' => $users->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role->value,
                'is_active' => $u->isActive(),
            ]),
        ]);
    }

    public function create(Request $request): Response
    {
        $company = $request->user()->company;
        abort_unless($company, 404);

        return Inertia::render('Client/Users/Form', [
            'mode' => 'create',
            'user' => null,
            ...$this->sharedFormProps($company),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $request->user()->company;
        abort_unless($company, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'is_active' => ['boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.*.module' => ['required', Rule::enum(PermissionModule::class)],
            'permissions.*.action' => ['required', Rule::enum(PermissionAction::class)],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make(Str::password(32)),
            'role' => UserRole::CompanyUser,
            'company_id' => $company->id,
            'email_verified_at' => now(),
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $this->syncUserPermissions->execute($user, $company, $validated['permissions'] ?? []);

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

        return redirect()->route('client.usuarios.index')->with('success', $mailMessage);
    }

    public function edit(Request $request, User $user): Response
    {
        $company = $request->user()->company;
        abort_unless($company && $user->company_id === $company->id, 404);
        abort_if($user->isCompanyAdmin(), 403);

        $user->load('permissions');
        $initialPermissions = $user->permissions->map(fn ($p) => [
            'module' => $p->module->value,
            'action' => $p->action->value,
        ])->values()->all();

        return Inertia::render('Client/Users/Form', [
            'mode' => 'edit',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->isActive(),
                'permissions' => $initialPermissions,
            ],
            ...$this->sharedFormProps($company),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $company = $request->user()->company;
        abort_unless($company && $user->company_id === $company->id, 404);
        abort_if($user->isCompanyAdmin(), 403);
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
            'is_active' => $validated['is_active'] ?? $user->is_active,
        ]);

        $this->syncUserPermissions->execute($user, $company, $validated['permissions'] ?? []);

        return redirect()->route('client.usuarios.index')->with('success', 'Utilizador atualizado.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $company = $request->user()->company;
        abort_unless($company && $user->company_id === $company->id, 404);
        abort_if($user->isCompanyAdmin(), 403);
        abort_if($user->id === $request->user()->id, 403);

        $user->delete();

        return redirect()->route('client.usuarios.index')->with('success', 'Utilizador removido.');
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
