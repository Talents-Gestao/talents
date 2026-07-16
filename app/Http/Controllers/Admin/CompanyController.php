<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ResendCompanyInvitation;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Mail\CompanyAdminInvitationMail;
use App\Models\Company;
use App\Models\MethodologyFormTemplate;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SurveyTemplate;
use App\Models\User;
use App\Services\ReceitaWsService;
use App\Support\InvitationPassword;
use App\Support\WorkspaceManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use RuntimeException;

class CompanyController extends Controller
{
    public function __construct(
        private ResendCompanyInvitation $resendCompanyInvitation,
        private WorkspaceManager $workspaceManager,
    ) {}

    public function index(Request $request): Response
    {
        $q = Company::query()->orderBy('name');

        if ($request->filled('search')) {
            $s = $request->string('search');
            $q->where(function ($query) use ($s) {
                $query->where('name', 'like', '%'.$s.'%')
                    ->orWhere('cnpj', 'like', '%'.$s.'%');
            });
        }

        $companies = $q->paginate(15)->withQueryString();

        $rhidConfiguredIds = Company::query()
            ->whereNotNull('rhid_email')
            ->whereNotNull('rhid_password')
            ->pluck('id')
            ->all();

        $pendingRegistrationIds = Company::query()
            ->where(function ($query) {
                $query->whereHas('users', function ($q) {
                    $q->whereColumn('users.email', 'companies.contact_email')
                        ->whereNull('password_set_at');
                })->orWhereHas('users', function ($q) {
                    $q->where('role', UserRole::CompanyAdmin)
                        ->whereNull('password_set_at');
                });
            })
            ->pluck('id')
            ->all();

        return Inertia::render('Admin/Companies/Index', [
            'companies' => $companies,
            'filters' => $request->only(['search']),
            'rhidConfiguredIds' => $rhidConfiguredIds,
            'pendingRegistrationIds' => $pendingRegistrationIds,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Companies/Create', [
            'plans' => Plan::query()->where('is_active', true)->get(['id', 'name', 'slug']),
        ]);
    }

    public function lookupCnpj(Request $request, ReceitaWsService $receitaWs): JsonResponse
    {
        $request->validate([
            'cnpj' => ['required', 'string'],
        ]);

        try {
            $data = $receitaWs->lookupCnpj($request->string('cnpj')->toString());

            return response()->json($data);
        } catch (InvalidArgumentException|RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge($this->normalizeCompanyActivityFields($request->all()));

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_email' => ['required', 'string', 'max:255', 'email', 'unique:users,email'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'cnpj' => ['nullable', 'string', 'max:18'],
            'segment' => ['nullable', 'string', 'max:120'],
            'activity_branch' => ['nullable', 'string', 'max:120'],
            'collective_bargaining_month' => ['nullable', 'integer', 'between:1,12'],
            'address_street' => ['nullable', 'string', 'max:255'],
            'address_neighborhood' => ['nullable', 'string', 'max:120'],
            'address_city' => ['nullable', 'string', 'max:120'],
            'address_state' => ['nullable', 'string', 'max:2'],
            'address_zip' => ['nullable', 'string', 'max:12'],
            'tax_regime' => ['nullable', 'string', 'max:255'],
            'employee_count_estimate' => ['nullable', 'integer', 'min:0'],
            'plan_id' => ['nullable', 'exists:plans,id'],
            'is_active' => ['boolean'],
        ]);

        $state = $data['address_state'] ?? null;
        if (is_string($state) && $state !== '') {
            $state = strtoupper($state);
        } else {
            $state = null;
        }

        $company = null;
        $adminUser = null;

        DB::transaction(function () use ($data, $state, &$company, &$adminUser) {
            $company = Company::create([
                'name' => $data['name'],
                'contact_email' => $data['contact_email'],
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

            $adminUser = User::create([
                'name' => $data['name'],
                'email' => $data['contact_email'],
                'password' => Hash::make(Str::password(32)),
                'role' => UserRole::CompanyAdmin,
                'company_id' => $company->id,
                'email_verified_at' => now(),
            ]);

            $this->workspaceManager->createCompanyWorkspace(
                $adminUser,
                $company->id,
                UserRole::CompanyAdmin,
            );
        });

        $mailMessage = ' Empresa criada. O primeiro administrador da empresa receberá um e-mail com o link para definir a senha e acessar o portal.';

        try {
            $token = InvitationPassword::createToken($adminUser);
            $resetUrl = InvitationPassword::setPasswordUrl($adminUser, $token);

            Mail::to($adminUser->email)->send(new CompanyAdminInvitationMail($adminUser, $company, $resetUrl));
        } catch (\Throwable $e) {
            report($e);
            $mailMessage = ' Empresa e usuário administrador criados, mas o e-mail de convite não pôde ser enviado. Verifique o SMTP em Configurações ou defina a senha manualmente. Erro: '.$e->getMessage();
        }

        return redirect()->route('admin.companies.show', $company)->with('success', 'Empresa criada.'.$mailMessage);
    }

    public function show(Company $company): Response
    {
        $company->load([
            'subscriptions.plan.modules',
            'surveyTemplates',
            'methodologyFormTemplates',
            'users',
            'surveys' => fn ($q) => $q->orderByDesc('id'),
        ]);

        return Inertia::render('Admin/Companies/Show', [
            'company' => $company,
            'rhidConfigured' => $company->rhidConfigured(),
            'planIncludesMetodologia' => $company->hasMethodologyEnabled(),
            'pendingRegistration' => $company->hasPendingRegistration(),
            'registrationAdminEmail' => $company->registrationAdmin()?->email,
            'complaintsPublicUrl' => $company->complaints_public_token
                ? url('/denuncia/'.$company->complaints_public_token)
                : null,
            'plans' => Plan::query()->where('is_active', true)->get(),
            'templates' => SurveyTemplate::query()->where('is_active', true)->get(['id', 'title']),
            'methodologyTemplates' => MethodologyFormTemplate::query()->where('is_active', true)->orderBy('title')->get(['id', 'title']),
        ]);
    }

    public function edit(Company $company): Response
    {
        $activePlanId = $company->activeSubscription()?->plan_id;

        $plans = Plan::query()
            ->where(function ($q) use ($activePlanId) {
                $q->where('is_active', true);
                if ($activePlanId) {
                    $q->orWhere('id', $activePlanId);
                }
            })
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return Inertia::render('Admin/Companies/Edit', [
            'company' => $company,
            'activePlanId' => $activePlanId,
            'plans' => $plans,
        ]);
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        $request->merge($this->normalizeCompanyActivityFields($request->all()));

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_email' => ['nullable', 'string', 'max:255', 'email'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'cnpj' => ['nullable', 'string', 'max:18'],
            'segment' => ['nullable', 'string', 'max:120'],
            'activity_branch' => ['nullable', 'string', 'max:120'],
            'collective_bargaining_month' => ['nullable', 'integer', 'between:1,12'],
            'address_street' => ['nullable', 'string', 'max:255'],
            'address_neighborhood' => ['nullable', 'string', 'max:120'],
            'address_city' => ['nullable', 'string', 'max:120'],
            'address_state' => ['nullable', 'string', 'max:2'],
            'address_zip' => ['nullable', 'string', 'max:12'],
            'tax_regime' => ['nullable', 'string', 'max:255'],
            'employee_count_estimate' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'strategic_calendar_access_mode' => ['required', Rule::in(['inherit', 'enabled', 'disabled'])],
            'tasks_access_mode' => ['required', Rule::in(['inherit', 'enabled', 'disabled'])],
            'rhid_access_mode' => ['required', Rule::in(['inherit', 'enabled', 'disabled'])],
            'denuncias_access_mode' => ['required', Rule::in(['inherit', 'enabled', 'disabled'])],
            'ferias_access_mode' => ['required', Rule::in(['inherit', 'enabled', 'disabled'])],
            'desligamento_access_mode' => ['required', Rule::in(['inherit', 'enabled', 'disabled'])],
            'acompanhamento_access_mode' => ['required', Rule::in(['inherit', 'enabled', 'disabled'])],
            'plan_id' => ['nullable', 'exists:plans,id'],
        ]);

        $planId = $data['plan_id'] ?? null;
        unset($data['plan_id']);

        if (isset($data['address_state'])) {
            $data['address_state'] = strtoupper($data['address_state']);
        }

        $mode = $data['strategic_calendar_access_mode'];
        unset($data['strategic_calendar_access_mode']);
        $data['strategic_calendar_access'] = match ($mode) {
            'enabled' => true,
            'disabled' => false,
            default => null,
        };

        $tasksMode = $data['tasks_access_mode'];
        unset($data['tasks_access_mode']);
        $data['tasks_access'] = match ($tasksMode) {
            'enabled' => true,
            'disabled' => false,
            default => null,
        };

        $rhidMode = $data['rhid_access_mode'];
        unset($data['rhid_access_mode']);
        $data['rhid_access'] = match ($rhidMode) {
            'enabled' => true,
            'disabled' => false,
            default => null,
        };

        $denunciasMode = $data['denuncias_access_mode'];
        unset($data['denuncias_access_mode']);
        $data['denuncias_access'] = match ($denunciasMode) {
            'enabled' => true,
            'disabled' => false,
            default => null,
        };

        $feriasMode = $data['ferias_access_mode'];
        unset($data['ferias_access_mode']);
        $data['ferias_access'] = match ($feriasMode) {
            'enabled' => true,
            'disabled' => false,
            default => null,
        };

        $desligamentoMode = $data['desligamento_access_mode'];
        unset($data['desligamento_access_mode']);
        $data['desligamento_access'] = match ($desligamentoMode) {
            'enabled' => true,
            'disabled' => false,
            default => null,
        };

        $acompanhamentoMode = $data['acompanhamento_access_mode'];
        unset($data['acompanhamento_access_mode']);
        $data['acompanhamento_access'] = match ($acompanhamentoMode) {
            'enabled' => true,
            'disabled' => false,
            default => null,
        };

        $company->update($data);

        DB::transaction(function () use ($company, $planId) {
            if (empty($planId)) {
                $company->subscriptions()->where('status', 'active')->update(['status' => 'cancelled']);

                return;
            }

            $active = $company->subscriptions()->where('status', 'active')->orderByDesc('id')->first();

            if ($active && (int) $active->plan_id === (int) $planId) {
                return;
            }

            $company->subscriptions()->where('status', 'active')->update(['status' => 'cancelled']);

            Subscription::create([
                'company_id' => $company->id,
                'plan_id' => $planId,
                'starts_at' => now(),
                'ends_at' => now()->addYear(),
                'status' => 'active',
            ]);
        });

        return redirect()->route('admin.companies.show', $company)->with('success', 'Empresa atualizada.');
    }

    public function destroy(Company $company): RedirectResponse
    {
        DB::transaction(function () use ($company) {
            User::query()->where('company_id', $company->id)->delete();
            $company->delete();
        });

        return redirect()->route('admin.companies.index')->with('success', 'Empresa removida.');
    }

    public function resendInvitation(Company $company): RedirectResponse
    {
        $admin = $company->registrationAdmin();

        if ($admin === null) {
            return redirect()
                ->back()
                ->with('error', 'Não foi encontrado um administrador para esta empresa.');
        }

        try {
            $this->resendCompanyInvitation->execute($company);
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->back()
                ->with('error', 'Não foi possível reenviar o convite. Erro: '.$e->getMessage());
        }

        $message = $admin->hasCompletedRegistration()
            ? 'Link para redefinir a senha enviado para '.$admin->email.'.'
            : 'Convite de cadastro reenviado para '.$admin->email.'.';

        return redirect()
            ->back()
            ->with('success', $message);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeCompanyActivityFields(array $data): array
    {
        if (array_key_exists('collective_bargaining_month', $data)
            && ($data['collective_bargaining_month'] === '' || $data['collective_bargaining_month'] === null)) {
            $data['collective_bargaining_month'] = null;
        }

        if (array_key_exists('activity_branch', $data)) {
            $branch = is_string($data['activity_branch']) ? trim($data['activity_branch']) : $data['activity_branch'];
            $data['activity_branch'] = $branch === '' ? null : $branch;
        }

        return $data;
    }

    public function attachTemplate(Company $company, SurveyTemplate $template): RedirectResponse
    {
        $company->surveyTemplates()->syncWithoutDetaching([$template->id]);

        return back()->with('success', 'Mapeamento vinculado à empresa.');
    }

    public function detachTemplate(Company $company, SurveyTemplate $template): RedirectResponse
    {
        $company->surveyTemplates()->detach($template->id);

        return back()->with('success', 'Mapeamento desvinculado da empresa.');
    }
}
