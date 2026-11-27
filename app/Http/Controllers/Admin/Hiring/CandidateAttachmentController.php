<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Hiring;

use App\Enums\AdminPermissionModule;
use App\Enums\PermissionAction;
use App\Http\Controllers\Controller;
use App\Models\TalentCandidate;
use App\Models\TalentCandidateAttachment;
use App\Services\Hiring\TalentCandidateAttachmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Anexos internos do candidato (contratacao_anexos, restrito). Rotas com scopeBindings:
 * anexo de outro candidato dá 404. Download só por aqui, como attachment, e auditado.
 */
class CandidateAttachmentController extends Controller
{
    public function __construct(
        private readonly TalentCandidateAttachmentService $service,
    ) {}

    public function index(Request $request, TalentCandidate $candidate): Response
    {
        $user = $request->user();

        $attachments = $candidate->attachments()
            ->with('uploadedByUser:id,name')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (TalentCandidateAttachment $a) => [
                'id' => $a->id,
                'original_name' => $a->original_name,
                'mime' => $a->mime,
                'size' => $a->size,
                'sender_name' => $a->sender_name,
                'received_on' => $a->received_on?->toDateString(),
                'purpose' => $a->purpose,
                'uploaded_by_name' => $a->uploadedByUser?->name,
                'created_at' => $a->created_at?->toIso8601String(),
                'download_url' => route('admin.contratacao.candidatos.anexos.download', [$candidate, $a]),
            ]);

        return Inertia::render('Admin/Hiring/Candidates/Attachments', [
            'candidate' => [
                'id' => $candidate->id,
                'name' => $candidate->name,
                'archived' => $candidate->isArchived(),
            ],
            'attachments' => $attachments,
            'can' => [
                'create' => $user->canAccessAdmin(AdminPermissionModule::ContratacaoAnexos, PermissionAction::Create),
                'delete' => $user->canAccessAdmin(AdminPermissionModule::ContratacaoAnexos, PermissionAction::Delete),
            ],
        ]);
    }

    public function store(Request $request, TalentCandidate $candidate): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:5120', 'extensions:pdf,png,jpg,jpeg', 'mimetypes:application/pdf,image/png,image/jpeg'],
            'sender_name' => ['required', 'string', 'max:255'],
            'received_on' => ['required', 'date', 'before_or_equal:today'],
            'purpose' => ['required', 'string', 'max:1000'],
            'privacy_confirmed' => ['accepted'],
        ], [
            'file.max' => 'O arquivo pode ter até 5 MB.',
            'file.extensions' => 'Envie um arquivo PDF, PNG, JPG ou JPEG.',
            'file.mimetypes' => 'O conteúdo do arquivo não é PDF, PNG nem JPEG.',
            'privacy_confirmed.accepted' => 'Confirme que o arquivamento tem finalidade administrativa e foi conferido quanto à privacidade.',
            'received_on.before_or_equal' => 'A data de recebimento não pode estar no futuro.',
        ], [
            'file' => 'arquivo',
            'sender_name' => 'profissional ou pessoa que entregou',
            'received_on' => 'data de recebimento',
            'purpose' => 'finalidade administrativa',
        ]);

        $this->service->store($candidate, $request->file('file'), $data, $request->user()?->id);

        return back()->with('success', 'Anexo interno guardado.');
    }

    public function download(Request $request, TalentCandidate $candidate, TalentCandidateAttachment $attachment): StreamedResponse
    {
        return $this->service->download($attachment, $request->user()?->id);
    }

    public function destroy(Request $request, TalentCandidate $candidate, TalentCandidateAttachment $attachment): RedirectResponse
    {
        $this->service->delete($attachment, $request->user()?->id);

        return back()->with('success', 'Anexo interno excluído.');
    }
}
