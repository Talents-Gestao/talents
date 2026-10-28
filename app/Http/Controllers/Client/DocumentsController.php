<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Exceptions\PsychosocialDocumentException;
use App\Http\Controllers\Controller;
use App\Models\PsychosocialDocumentVersion;
use App\Models\Survey;
use App\Models\User;
use App\Services\Psychosocial\ReviewRoundService;
use App\Support\ReviewRoundPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Documentos da campanha V11 para a empresa: só a rodada PUBLICADA (nada de rascunho nem de parecer interno),
 * a ciência da publicação e a confirmação da integração ao PGR, sempre em campo separado e com evidência.
 * As rotas passam por `survey.published` (T02) e por Relatórios (escrita exige a ação `validate`).
 */
class DocumentsController extends Controller
{
    /** Eventos que a empresa vê no histórico; revisão interna e parecer da PASSEG ficam com a Talents. */
    private const COMPANY_VISIBLE_EVENTS = ['published', 'acknowledged', 'pgr_integration_confirmed'];

    public function __construct(private readonly ReviewRoundService $rounds) {}

    public function index(Request $request, Survey $survey): Response
    {
        $this->authorizeSurvey($request, $survey);

        $round = $this->rounds->publishedRound($survey);
        $round?->loadMissing(['executiveVersion.document', 'technicalVersion.document']);

        return Inertia::render('Client/Surveys/Documents', [
            'survey' => ['id' => $survey->id, 'title' => $survey->title],
            'round' => $round ? array_merge(ReviewRoundPresenter::base($round), [
                'versions' => ReviewRoundPresenter::versions(
                    $round,
                    fn (PsychosocialDocumentVersion $version) => route('client.surveys.psychosocial-documents.download', [$survey, $version->document->type]),
                ),
                'history' => array_values(array_filter(
                    $this->rounds->history($round),
                    fn (array $event) => in_array($event['action'], self::COMPANY_VISIBLE_EVENTS, true),
                )),
            ]) : null,
            'canRecord' => $this->canRecord($request->user()),
            'approvalMeaning' => ReviewRoundService::APPROVAL_MEANING,
        ]);
    }

    public function acknowledge(Request $request, Survey $survey): RedirectResponse
    {
        $this->authorizeSurvey($request, $survey);
        $round = $this->publishedRoundOr404($survey);

        try {
            $this->rounds->acknowledge($round, $request->user());
        } catch (PsychosocialDocumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Ciência registrada: os dois documentos desta publicação foram recebidos.');
    }

    public function confirmPgrIntegration(Request $request, Survey $survey): RedirectResponse
    {
        $this->authorizeSurvey($request, $survey);
        $round = $this->publishedRoundOr404($survey);

        $data = $request->validate([
            'evidence' => ['required', 'file', 'max:20480'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [
            'evidence.required' => 'A integração ao PGR só é confirmada com a evidência (documento de SST).',
            'evidence.max' => 'O arquivo pode ter no máximo 20 MB.',
        ]);

        try {
            $this->rounds->confirmPgrIntegration($round, $request->user(), $data['note'] ?? null, $request->file('evidence'));
        } catch (PsychosocialDocumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Integração ao PGR confirmada, com a evidência registrada.');
    }

    private function publishedRoundOr404(Survey $survey)
    {
        $round = $this->rounds->publishedRound($survey);
        abort_if($round === null, 404);

        return $round;
    }

    private function authorizeSurvey(Request $request, Survey $survey): void
    {
        abort_unless((int) $survey->company_id === (int) $request->user()->contextCompanyId(), 404);
        abort_unless($survey->usesReviewFlow(), 404);
    }

    private function canRecord(User $user): bool
    {
        return ! $user->isSuperAdmin() && ! $user->isOwner()
            && $user->canAccess(PermissionModule::Relatorios, PermissionAction::Validate);
    }
}
