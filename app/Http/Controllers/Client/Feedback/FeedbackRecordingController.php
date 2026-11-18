<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client\Feedback;

use App\Actions\Feedback\DeleteFeedbackRecording;
use App\Actions\Feedback\StoreFeedbackRecording;
use App\Http\Controllers\Concerns\ResolvesFeedbackRoutes;
use App\Models\Company;
use App\Models\FeedbackAuditLog;
use App\Models\FeedbackSession;
use App\Services\Feedback\PlaudTranscriptExtractor;
use App\Services\Feedback\TranscriptExtractionException;
use App\Support\Feedback\FeedbackRecordingAccess;
use App\Support\Feedback\FeedbackVisibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Gravação do feedback (transcrição exportada do Plaud): enviar, ver o original e excluir.
 * Portal e admin (empresa selecionada) usam este controller; a sessão passa sempre por authorizeSession.
 */
class FeedbackRecordingController extends FeedbackCompanyController
{
    use ResolvesFeedbackRoutes;

    public const MAX_FILE_KB = 10240;

    public const NO_SPEAKERS_WARNING = 'Arquivo sem identificação de quem fala; o relatório ficará menos preciso. Exporte novamente com essa opção ligada.';

    public function store(
        Request $request,
        FeedbackSession $session,
        PlaudTranscriptExtractor $extractor,
        StoreFeedbackRecording $store,
    ): RedirectResponse {
        $this->authorizeSession($this->company($request), $session);
        abort_if(FeedbackRecordingAccess::isLocked($session), 403, 'Feedback concluído ou cancelado: a gravação não pode ser alterada.');
        abort_unless(FeedbackRecordingAccess::canUpload($request, $session), 403);

        $request->validate([
            'file' => [
                'required',
                'file',
                'extensions:txt,srt,docx,pdf',
                'max:'.self::MAX_FILE_KB,
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value instanceof UploadedFile && (int) $value->getSize() === 0) {
                        $fail('O arquivo está vazio.');
                    }
                },
            ],
            'consent' => ['accepted'],
        ], [
            'file.required' => 'Escolha o arquivo da transcrição exportada do Plaud.',
            'file.file' => 'Não foi possível receber o arquivo. Tente de novo.',
            'file.uploaded' => 'Não foi possível receber o arquivo (talvez passe de 10 MB). Tente de novo.',
            'file.extensions' => 'Formato não aceito. Envie TXT, SRT, DOCX ou PDF.',
            'file.max' => 'O arquivo deve ter no máximo 10 MB.',
            'consent.accepted' => 'Confirme que o colaborador foi informado e concordou com a gravação e o processamento por IA.',
        ]);

        if ($session->recordings()->exists()) {
            throw ValidationException::withMessages(['file' => StoreFeedbackRecording::ALREADY_EXISTS_MESSAGE]);
        }

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $path = (string) $file->getRealPath();
        $sha256 = (string) hash_file('sha256', $path);
        $size = (int) $file->getSize();
        $filename = mb_substr($file->getClientOriginalName(), 0, 255);

        try {
            $extracted = $extractor->extract($path, $filename);
        } catch (TranscriptExtractionException $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        } finally {
            // O arquivo enviado não é guardado: só o texto extraído e o hash.
            if (is_file($path)) {
                unlink($path);
            }
        }

        $store->execute($session, $request->user(), [
            'format' => $extracted['format'],
            'filename' => $filename,
            'sha256' => $sha256,
            'size' => $size,
            'text' => $extracted['text'],
            'has_speakers' => $extracted['has_speakers'],
            'has_timestamps' => $extracted['has_timestamps'],
        ]);

        $message = 'Transcrição recebida. O arquivo enviado foi descartado; ficou só o texto.';
        if (! $extracted['has_speakers']) {
            $message .= ' '.self::NO_SPEAKERS_WARNING;
        }

        return back()->with('success', $message);
    }

    /**
     * Texto original (JSON, sob demanda): só administrador da empresa e Talents; cada abertura vai para a trilha.
     */
    public function original(Request $request, FeedbackSession $session): JsonResponse
    {
        $this->authorizeSession($this->company($request), $session);
        abort_unless(FeedbackRecordingAccess::canViewOriginal($request), 403);

        $recording = $session->currentRecording();
        abort_if($recording === null, 404);

        FeedbackAuditLog::query()->create([
            'company_id' => $session->company_id,
            'user_id' => $request->user()->id,
            'action' => FeedbackAuditLog::ACTION_RECORDING_ORIGINAL_VIEWED,
            'subject_type' => FeedbackAuditLog::SUBJECT_RECORDING,
            'subject_id' => $recording->id,
            'subject_label' => $session->title,
            'details' => ['feedback_session_id' => $session->id],
        ]);

        return response()
            ->json([
                'text' => $recording->safeOriginalText(),
                'filename' => $recording->safeOriginalFilename(),
                'sha256' => $recording->file_sha256,
            ])
            ->header('Cache-Control', 'no-store, private');
    }

    public function destroy(Request $request, FeedbackSession $session, DeleteFeedbackRecording $delete): RedirectResponse
    {
        $this->authorizeSession($this->company($request), $session);
        abort_if(FeedbackRecordingAccess::isLocked($session), 403, 'Feedback concluído ou cancelado: a gravação não pode ser alterada.');
        abort_unless(FeedbackRecordingAccess::canDelete($request, $session), 403);

        $request->validate(
            ['confirmed' => ['accepted']],
            ['confirmed.accepted' => 'Confirme que a gravação será excluída.'],
        );

        $recording = $session->currentRecording();
        abort_if($recording === null, 404);

        $delete->execute($recording, $request->user());

        return back()->with('success', 'Gravação excluída.');
    }

    private function authorizeSession(Company $company, FeedbackSession $session): void
    {
        abort_unless($session->company_id === $company->id, 403);
        FeedbackVisibility::authorizeSession(request()->user(), $session);
    }
}
