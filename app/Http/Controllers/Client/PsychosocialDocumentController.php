<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Exceptions\PsychosocialDocumentException;
use App\Http\Controllers\Controller;
use App\Models\Survey;
use App\Services\Psychosocial\PsychosocialDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PsychosocialDocumentController extends Controller
{
    public function __construct(private readonly PsychosocialDocumentService $documents)
    {
    }

    public function download(Request $request, Survey $survey, string $type): SymfonyResponse
    {
        $this->authorizeSurvey($request, $survey);

        $version = $this->documents->currentVersion($survey, $type);
        abort_if($version === null, 404);

        try {
            return $this->documents->download($version, $request->user(), $request->ip());
        } catch (PsychosocialDocumentException $exception) {
            return response($exception->getMessage(), SymfonyResponse::HTTP_CONFLICT, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }
    }

    /**
     * Validação no sistema. A rota exige relatorios + validate; o Admin Talents não valida por aqui.
     */
    public function validateInSystem(Request $request, Survey $survey, string $type): RedirectResponse
    {
        $this->authorizeSurvey($request, $survey);
        $user = $request->user();
        abort_if($user->isSuperAdmin() || $user->isOwner(), SymfonyResponse::HTTP_FORBIDDEN);

        $request->validate(
            ['declaration_accepted' => ['accepted']],
            ['declaration_accepted.accepted' => 'Para validar, aceite a declaração.'],
        );

        $version = $this->documents->currentVersion($survey, $type);
        abort_if($version === null, 404);

        try {
            $validated = $this->documents->validateInSystem($version, $user, $request->boolean('declaration_accepted'));
        } catch (PsychosocialDocumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', PsychosocialDocumentService::label($type)." validado (versão {$validated->version_number}).");
    }

    private function authorizeSurvey(Request $request, Survey $survey): void
    {
        abort_unless((int) $survey->company_id === (int) $request->user()->company_id, 404);
    }
}
