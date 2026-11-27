<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Hiring;

use App\Http\Controllers\Controller;
use App\Models\CompanyEmployee;
use App\Models\HiringApplication;
use App\Models\HiringVacancy;
use App\Services\Hiring\EmploymentCycleService;
use App\Support\Hiring\FollowupPayload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * «Registrar admissão» numa candidatura na etapa de contratado (contratacao_pos,edit).
 * O colaborador é escolhido ou criado na empresa da vaga (deduplicado por e-mail dentro da empresa);
 * o gestor é um contato da empresa com o tópico pós-contratação. Rotas com scopeBindings.
 */
class AdmissionController extends Controller
{
    public function __construct(
        private readonly EmploymentCycleService $cycles,
    ) {}

    /** Colaboradores da empresa (busca) e gestores possíveis. */
    public function options(Request $request, HiringVacancy $vacancy, HiringApplication $application): JsonResponse
    {
        $application->loadMissing('candidate:id,name,email,phone,current_role');
        $search = trim($request->string('q')->toString());
        $email = mb_strtolower(trim((string) $application->candidate?->email));

        $query = CompanyEmployee::query()
            ->where('company_id', $vacancy->company_id)
            ->whereNull('merged_into_employee_id')
            ->orderBy('name')
            ->limit(15);

        if ($search !== '') {
            $operator = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(fn (Builder $q) => $q->where('name', $operator, '%'.$search.'%')->orWhere('email', $operator, '%'.$search.'%'));
        }

        $sameEmail = $email !== ''
            ? CompanyEmployee::query()->where('company_id', $vacancy->company_id)->whereNull('merged_into_employee_id')->whereRaw('lower(email) = ?', [$email])->first()
            : null;

        return response()->json([
            'candidate' => [
                'name' => $application->candidate?->name,
                'email' => $application->candidate?->email,
                'phone' => $application->candidate?->phone,
            ],
            'suggested_role' => $vacancy->title,
            // Mesmo e-mail na empresa: o cadastro existente é reaproveitado (o nome não identifica a pessoa).
            'same_email_employee' => $sameEmail ? ['id' => $sameEmail->id, 'name' => $sameEmail->name, 'email' => $sameEmail->email] : null,
            'employees' => $query->get(['id', 'name', 'email'])->map(fn (CompanyEmployee $e) => ['id' => $e->id, 'name' => $e->name, 'email' => $e->email])->values(),
            'managers' => FollowupPayload::managerOptions((int) $vacancy->company_id),
        ]);
    }

    public function store(Request $request, HiringVacancy $vacancy, HiringApplication $application): RedirectResponse
    {
        $data = $request->validate([
            'company_employee_id' => ['nullable', 'integer'],
            'employee_name' => ['nullable', 'string', 'max:255'],
            'employee_email' => ['nullable', 'email', 'max:255'],
            'employee_phone' => ['nullable', 'string', 'max:40'],
            'role_title' => ['nullable', 'string', 'max:255'],
            'manager_contact_id' => ['nullable', 'integer'],
            'admission_date' => ['nullable', 'date_format:Y-m-d'],
            'collaborator_email' => ['nullable', 'email', 'max:255'],
            'collaborator_phone' => ['nullable', 'string', 'max:40'],
            'collaborator_email_opt_in' => ['boolean'],
        ], [], [
            'employee_name' => 'nome do colaborador',
            'employee_email' => 'e-mail do colaborador',
            'role_title' => 'cargo',
            'manager_contact_id' => 'gestor',
            'admission_date' => 'data de admissão',
        ]);

        $cycle = $this->cycles->registerAdmission($application, $data, $request->user()?->id);

        return redirect()
            ->route('admin.contratacao.pos.show', $cycle)
            ->with('success', $cycle->admission_date
                ? 'Admissão registrada. Os marcos foram criados; nada é enviado sozinho.'
                : 'Vínculo registrado com admissão pendente. Confirme a data de admissão para criar os marcos.');
    }
}
