<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Mail\CompanyAdminInvitationMail;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SurveyTemplate;
use App\Models\User;
use App\Services\ReceitaWsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use RuntimeException;

class CompanyController extends Controller
{
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

        return Inertia::render('Admin/Companies/Index', [
            'companies' => $companies,
            'filters' => $request->only(['search']),
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
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_email' => ['required', 'string', 'max:255', 'email', 'unique:users,email'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'cnpj' => ['nullable', 'string', 'max:18'],
            'segment' => ['nullable', 'string', 'max:120'],
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
        });

        $mailMessage = ' Empresa criada. O primeiro administrador da empresa receberá um e-mail com o link para definir a senha e acessar o portal.';

        try {
            $token = Password::broker()->createToken($adminUser);
            $resetUrl = route('password.reset', ['token' => $token]).'?email='.urlencode($adminUser->email);

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
            'subscriptions.plan',
            'surveyTemplates',
            'users',
            'surveys' => fn ($q) => $q->orderByDesc('id'),
        ]);

        return Inertia::render('Admin/Companies/Show', [
            'company' => $company,
            'complaintsPublicUrl' => $company->complaints_public_token
                ? url('/denuncia/'.$company->complaints_public_token)
                : null,
            'plans' => Plan::query()->where('is_active', true)->get(),
            'templates' => SurveyTemplate::query()->where('is_active', true)->get(['id', 'title']),
        ]);
    }

    public function edit(Company $company): Response
    {
        return Inertia::render('Admin/Companies/Edit', [
            'company' => $company,
            'plans' => Plan::query()->where('is_active', true)->get(['id', 'name', 'slug']),
        ]);
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_email' => ['nullable', 'string', 'max:255', 'email'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'cnpj' => ['nullable', 'string', 'max:18'],
            'segment' => ['nullable', 'string', 'max:120'],
            'address_street' => ['nullable', 'string', 'max:255'],
            'address_neighborhood' => ['nullable', 'string', 'max:120'],
            'address_city' => ['nullable', 'string', 'max:120'],
            'address_state' => ['nullable', 'string', 'max:2'],
            'address_zip' => ['nullable', 'string', 'max:12'],
            'tax_regime' => ['nullable', 'string', 'max:255'],
            'employee_count_estimate' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        if (isset($data['address_state'])) {
            $data['address_state'] = strtoupper($data['address_state']);
        }

        $company->update($data);

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

    public function attachTemplate(Company $company, SurveyTemplate $template): RedirectResponse
    {
        $company->surveyTemplates()->syncWithoutDetaching([$template->id]);

        return back()->with('success', 'Template vinculado à empresa.');
    }

    public function detachTemplate(Company $company, SurveyTemplate $template): RedirectResponse
    {
        $company->surveyTemplates()->detach($template->id);

        return back()->with('success', 'Template removido.');
    }
}
