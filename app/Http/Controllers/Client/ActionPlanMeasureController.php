<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Exceptions\PsychosocialMeasureException;
use App\Http\Controllers\Controller;
use App\Models\PsychosocialMeasure;
use App\Models\PsychosocialMeasureEvidence;
use App\Models\Survey;
use App\Models\User;
use App\Services\PsychosocialMeasureService;
use App\Support\PsychosocialMeasurePresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Plano de ação da empresa (Caderno §11): confirmar responsável e prazo, executar, medir. As rotas passam por
 * `survey.published` (nada antes de "Publicado") e por `planos_acao` (escrita exige a ação `edit`).
 */
class ActionPlanMeasureController extends Controller
{
    public function __construct(private readonly PsychosocialMeasureService $measures) {}

    public function show(Request $request, Survey $survey): Response
    {
        $this->authorizeSurvey($request, $survey);

        $rows = PsychosocialMeasure::query()
            ->where('survey_id', $survey->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $downloads = [];
        foreach ($rows as $measure) {
            foreach ($measure->evidences as $evidence) {
                if ($evidence->kind === PsychosocialMeasureEvidence::KIND_FILE) {
                    $downloads[$evidence->id] = route('client.surveys.measures.evidences.download', [$survey, $measure, $evidence]);
                }
            }
        }

        $companyId = (int) $survey->company_id;

        return Inertia::render('Client/Surveys/ActionPlanMeasures', [
            'survey' => ['id' => $survey->id, 'title' => $survey->title],
            'measures' => $rows->map(fn (PsychosocialMeasure $measure) => PsychosocialMeasurePresenter::present($measure, $downloads))->all(),
            'canEdit' => $this->canEdit($request->user()),
            'users' => User::query()
                ->whereHas('workspaces', fn ($q) => $q->where('company_id', $companyId)->where('is_active', true))
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])
                ->all(),
            'transitions' => PsychosocialMeasure::PLAN_TRANSITIONS,
            'effectivenessResults' => PsychosocialMeasure::EFFECTIVENESS_RESULTS,
            'cardLabel' => 'Medidas com responsável confirmado',
        ]);
    }

    public function confirm(Request $request, Survey $survey, PsychosocialMeasure $measure): RedirectResponse
    {
        // Autoriza ANTES de validar: quem não pode ver a medida recebe 403/404, nunca um erro de validação.
        $this->authorizeMeasure($request, $survey, $measure);

        $data = $request->validate([
            'responsible_user_id' => ['nullable', 'integer'],
            'responsible_text' => ['nullable', 'string', 'max:255'],
            'due_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->act($request, $survey, $measure, 'Responsável e prazo confirmados.', fn () => $this->measures->confirm(
            $measure,
            $request->user(),
            $data['responsible_user_id'] ?? null,
            $data['responsible_text'] ?? null,
            $data['due_date'],
            $data['reason'] ?? null,
        ));
    }

    public function adjust(Request $request, Survey $survey, PsychosocialMeasure $measure): RedirectResponse
    {
        // Autoriza ANTES de validar: quem não pode ver a medida recebe 403/404, nunca um erro de validação.
        $this->authorizeMeasure($request, $survey, $measure);

        $data = $request->validate([
            'responsible_user_id' => ['nullable', 'integer'],
            'responsible_text' => ['nullable', 'string', 'max:255'],
            'due_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        return $this->act($request, $survey, $measure, 'Ajuste registrado no histórico.', fn () => $this->measures->adjustConfirmation(
            $measure,
            $request->user(),
            $data['responsible_user_id'] ?? null,
            $data['responsible_text'] ?? null,
            $data['due_date'],
            $data['reason'],
        ));
    }

    public function start(Request $request, Survey $survey, PsychosocialMeasure $measure): RedirectResponse
    {
        return $this->act($request, $survey, $measure, 'Medida em andamento.', fn () => $this->measures->start($measure, $request->user()));
    }

    public function complete(Request $request, Survey $survey, PsychosocialMeasure $measure): RedirectResponse
    {
        return $this->act($request, $survey, $measure, 'Medida concluída.', fn () => $this->measures->complete($measure, $request->user()));
    }

    public function replan(Request $request, Survey $survey, PsychosocialMeasure $measure): RedirectResponse
    {
        // Autoriza ANTES de validar: quem não pode ver a medida recebe 403/404, nunca um erro de validação.
        $this->authorizeMeasure($request, $survey, $measure);

        $data = $request->validate([
            'due_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        return $this->act($request, $survey, $measure, 'Medida replanejada.', fn () => $this->measures->replan(
            $measure,
            $request->user(),
            $data['due_date'],
            $data['reason'],
        ));
    }

    public function storeEvidence(Request $request, Survey $survey, PsychosocialMeasure $measure): RedirectResponse
    {
        // Autoriza ANTES de validar: quem não pode ver a medida recebe 403/404, nunca um erro de validação.
        $this->authorizeMeasure($request, $survey, $measure);

        $data = $request->validate([
            'description' => ['required', 'string', 'max:2000'],
            'file' => ['nullable', 'file', 'max:20480'],
        ], [
            'file.max' => 'O arquivo pode ter no máximo 20 MB.',
        ]);

        return $this->act($request, $survey, $measure, 'Evidência registrada.', fn () => $this->measures->addEvidence(
            $measure,
            $request->user(),
            $data['description'],
            $request->file('file'),
        ));
    }

    public function recordEffectiveness(Request $request, Survey $survey, PsychosocialMeasure $measure): RedirectResponse
    {
        // Autoriza ANTES de validar: quem não pode ver a medida recebe 403/404, nunca um erro de validação.
        $this->authorizeMeasure($request, $survey, $measure);

        $data = $request->validate([
            'note' => ['required', 'string', 'max:5000'],
            'result' => ['required', 'string', 'in:'.implode(',', array_keys(PsychosocialMeasure::EFFECTIVENESS_RESULTS))],
            'checked_at' => ['nullable', 'date'],
        ]);

        return $this->act($request, $survey, $measure, 'Verificação de eficácia registrada.', fn () => $this->measures->recordEffectiveness(
            $measure,
            $request->user(),
            $data['note'],
            $data['result'],
            $data['checked_at'] ?? null,
        ));
    }

    public function downloadEvidence(Request $request, Survey $survey, PsychosocialMeasure $measure, PsychosocialMeasureEvidence $evidence): StreamedResponse
    {
        $this->authorizeMeasure($request, $survey, $measure);
        abort_unless((int) $evidence->measure_id === (int) $measure->id && $evidence->kind === PsychosocialMeasureEvidence::KIND_FILE, 404);

        $disk = Storage::disk('local');
        abort_unless($evidence->file_path && $disk->exists($evidence->file_path), 404);

        $bytes = $disk->get($evidence->file_path);
        abort_unless(hash_equals((string) $evidence->file_sha256, hash('sha256', (string) $bytes)), 409, 'O arquivo não confere com o hash registrado.');

        return response()->streamDownload(function () use ($bytes): void {
            echo $bytes;
        }, $evidence->file_name ?: 'evidencia');
    }

    private function act(Request $request, Survey $survey, PsychosocialMeasure $measure, string $success, callable $action): RedirectResponse
    {
        $this->authorizeMeasure($request, $survey, $measure);

        try {
            $action();
        } catch (PsychosocialMeasureException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', $success);
    }

    private function authorizeSurvey(Request $request, Survey $survey): void
    {
        abort_unless((int) $survey->company_id === (int) $request->user()->contextCompanyId(), 404);
    }

    private function authorizeMeasure(Request $request, Survey $survey, PsychosocialMeasure $measure): void
    {
        $this->authorizeSurvey($request, $survey);
        abort_unless((int) $measure->survey_id === (int) $survey->id, 404);
    }

    private function canEdit(User $user): bool
    {
        return ! $user->isSuperAdmin() && ! $user->isOwner() && $user->canAccess(PermissionModule::PlanosAcao, PermissionAction::Edit);
    }
}
