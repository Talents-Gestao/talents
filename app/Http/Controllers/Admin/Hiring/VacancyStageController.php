<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Hiring;

use App\Http\Controllers\Controller;
use App\Models\HiringVacancy;
use App\Models\HiringVacancyStage;
use App\Services\Hiring\HiringVacancyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Etapas próprias de uma vaga. As rotas usam scopeBindings: etapa de outra vaga dá 404. */
class VacancyStageController extends Controller
{
    public function __construct(
        private readonly HiringVacancyService $service,
    ) {}

    public function store(Request $request, HiringVacancy $vacancy): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
        ], [], ['name' => 'nome da etapa']);

        $this->service->addStage($vacancy, $data['name'], $request->user()?->id);

        return back()->with('success', 'Etapa adicionada.');
    }

    public function update(Request $request, HiringVacancy $vacancy, HiringVacancyStage $stage): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:80'],
            'is_hired' => ['sometimes', 'accepted'],
        ], [], ['name' => 'nome da etapa']);

        $userId = $request->user()?->id;

        if (array_key_exists('name', $data)) {
            $this->service->renameStage($stage, $data['name'], $userId);
        }
        if (array_key_exists('is_hired', $data)) {
            $this->service->markHiredStage($stage, $userId);
        }

        return back()->with('success', 'Etapa atualizada.');
    }

    public function destroy(Request $request, HiringVacancy $vacancy, HiringVacancyStage $stage): RedirectResponse
    {
        $this->service->deleteStage($stage, $request->user()?->id);

        return back()->with('success', 'Etapa excluída.');
    }

    public function reorder(Request $request, HiringVacancy $vacancy): RedirectResponse
    {
        $data = $request->validate([
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['integer'],
        ]);

        $this->service->reorderStages($vacancy, $data['ordered_ids'], $request->user()?->id);

        return back()->with('success', 'Ordem das etapas atualizada.');
    }
}
