<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Hiring;

use App\Enums\AdminPermissionModule;
use App\Enums\PermissionAction;
use App\Http\Controllers\Controller;
use App\Models\HiringApplication;
use App\Models\HiringVacancy;
use App\Models\HiringVacancyStage;
use App\Models\TalentCandidate;
use App\Services\Hiring\HiringVacancyService;
use App\Services\Hiring\TalentCandidateResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Candidatos numa vaga. As rotas usam scopeBindings: candidatura de outra vaga dá 404. */
class ApplicationController extends Controller
{
    public function __construct(
        private readonly HiringVacancyService $service,
        private readonly TalentCandidateResolver $resolver,
    ) {}

    /**
     * Inclui um candidato já cadastrado (talent_candidate_id) ou faz o cadastro rápido
     * (nome obrigatório, e-mail e telefone). O cadastro rápido exige contratacao_candidatos:create.
     */
    public function store(Request $request, HiringVacancy $vacancy): RedirectResponse
    {
        $data = $request->validate([
            'talent_candidate_id' => ['nullable', 'integer', 'exists:talent_candidates,id'],
            'name' => ['required_without:talent_candidate_id', 'nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! TalentCandidate::isValidInternationalPhone((string) $value)) {
                    $fail('Informe o telefone no formato internacional, começando com + e o código do país.');
                }
            }],
            'stage_id' => [
                'nullable',
                'integer',
                Rule::exists('hiring_vacancy_stages', 'id')->where('hiring_vacancy_id', $vacancy->id),
            ],
        ], [
            'name.required_without' => 'Informe o nome do candidato.',
            'stage_id.exists' => 'Etapa inválida para esta vaga.',
        ], [
            'name' => 'nome',
            'email' => 'e-mail',
            'phone' => 'telefone',
        ]);

        $user = $request->user();
        $userId = $user?->id;

        if (! empty($data['talent_candidate_id'])) {
            $candidate = TalentCandidate::query()->findOrFail((int) $data['talent_candidate_id']);
        } else {
            abort_unless(
                $user->canAccessAdmin(AdminPermissionModule::ContratacaoCandidatos, PermissionAction::Create),
                403,
                'Sem permissão para cadastrar candidatos.',
            );
            $candidate = $this->resolver->findOrCreate([
                'name' => $data['name'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'source' => 'Cadastro rápido na vaga',
            ], $userId, $user);
        }

        $stage = ! empty($data['stage_id'])
            ? HiringVacancyStage::query()->findOrFail((int) $data['stage_id'])
            : null;

        $this->service->addCandidate($vacancy, $candidate, $stage, $userId);

        return back()->with('success', 'Candidato incluído na vaga.');
    }

    public function move(Request $request, HiringVacancy $vacancy, HiringApplication $application): RedirectResponse
    {
        $data = $request->validate([
            'stage_id' => [
                'required',
                'integer',
                Rule::exists('hiring_vacancy_stages', 'id')->where('hiring_vacancy_id', $vacancy->id),
            ],
            'position' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ], [
            'stage_id.exists' => 'Etapa inválida para esta vaga.',
        ]);

        $stage = HiringVacancyStage::query()->findOrFail((int) $data['stage_id']);

        $this->service->moveApplication(
            $application,
            $stage,
            array_key_exists('position', $data) && $data['position'] !== null ? (int) $data['position'] : null,
            $request->user()?->id,
        );

        return back()->with('success', 'Candidato movido para '.$stage->name.'.');
    }

    public function storeNote(Request $request, HiringVacancy $vacancy, HiringApplication $application): RedirectResponse
    {
        $data = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
        ], [], ['note' => 'anotação']);

        $this->service->addNote($application, $data['note'], $request->user()?->id);

        return back()->with('success', 'Anotação registrada.');
    }

    public function destroy(Request $request, HiringVacancy $vacancy, HiringApplication $application): RedirectResponse
    {
        $this->service->removeApplication($application, $request->user()?->id);

        return back()->with('success', 'Candidato retirado da vaga. O cadastro continua disponível.');
    }
}
