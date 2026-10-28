<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exceptions\PsychosocialDocumentException;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\DocumentShare;
use App\Models\Survey;
use App\Models\User;
use App\Services\Psychosocial\DocumentShareService;
use App\Services\Psychosocial\PsychosocialDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Admin Talents: compartilha a versão atual de um documento com um revisor externo e revoga o acesso.
 * Com a revisão externa desligada (padrão) a área não existe: 404.
 */
class DocumentShareAdminController extends Controller
{
    public function __construct(
        private readonly DocumentShareService $shares,
        private readonly PsychosocialDocumentService $documents,
    ) {}

    public function store(Request $request, Company $company, Survey $survey, string $type): RedirectResponse
    {
        abort_unless(DocumentShareService::enabled(), SymfonyResponse::HTTP_NOT_FOUND);
        abort_unless((int) $survey->company_id === (int) $company->id, SymfonyResponse::HTTP_NOT_FOUND);

        // Campanha V11: o envio ao revisor é feito pela rodada de revisão (Documentos), com os dois documentos juntos.
        if ($survey->usesReviewFlow()) {
            return back()->with('error', 'Nesta campanha o envio à PASSEG é feito em Documentos.');
        }

        $data = $request->validate([
            'reviewer_id' => ['required', 'integer', 'exists:users,id'],
            'expires_at' => ['required', 'date', 'after:now'],
        ]);

        $version = $this->documents->currentVersion($survey, $type);
        abort_if($version === null, SymfonyResponse::HTTP_NOT_FOUND);

        try {
            $this->shares->share(
                $version,
                User::query()->findOrFail($data['reviewer_id']),
                $request->user(),
                Carbon::parse($data['expires_at']),
            );
        } catch (PsychosocialDocumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Versão compartilhada com o revisor externo.');
    }

    public function revoke(Company $company, DocumentShare $share): RedirectResponse
    {
        abort_unless(DocumentShareService::enabled(), SymfonyResponse::HTTP_NOT_FOUND);
        abort_unless((int) $share->company_id === (int) $company->id, SymfonyResponse::HTTP_NOT_FOUND);

        $this->shares->revoke($share);

        return back()->with('success', 'Acesso do revisor encerrado.');
    }
}
