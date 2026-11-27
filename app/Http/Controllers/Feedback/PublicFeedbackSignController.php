<?php

declare(strict_types=1);

namespace App\Http\Controllers\Feedback;

use App\Actions\Feedback\ReopenFeedbackSession;
use App\Actions\Notices\PublishFeedbackNotice;
use App\Enums\FeedbackSessionStatus;
use App\Http\Controllers\Controller;
use App\Models\FeedbackSessionSignature;
use App\Support\Feedback\FeedbackSessionStructure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PublicFeedbackSignController extends Controller
{
    public function show(string $token): Response
    {
        $signature = $this->findSignature($token);

        // Convite cancelado («Reabrir para edição»): o link recusa, com aviso claro e sem mostrar o conteúdo.
        if ($signature->isCancelled()) {
            return Inertia::render('Feedback/Sign', [
                'cancelled' => true,
                'cancelledMessage' => ReopenFeedbackSession::CANCELLED_LINK_MESSAGE,
                'signature' => ['role_label' => $signature->role->label(), 'signer_name' => $signature->signer_name],
                'token' => $token,
                'session' => null,
            ]);
        }

        $session = $signature->session()->with([
            'employee.department',
            'employee.position',
            'leader',
            'templateVersion.sections.questions',
            'answers',
            'signatures',
        ])->firstOrFail();

        // Quem assina não é administrador: a «Reflexão do líder» fica fora, como na tela do líder.
        $template = FeedbackSessionStructure::templatePayload($session, false);
        $visibleQuestionIds = collect($template['sections'])
            ->flatMap(fn ($s) => collect($s['questions'])->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->flip();

        $answers = [];
        foreach ($session->answers as $answer) {
            if ($visibleQuestionIds->has((int) $answer->feedback_template_question_id)) {
                $answers[$answer->feedback_template_question_id] = $answer->value_json ?? $answer->value_text;
            }
        }

        return Inertia::render('Feedback/Sign', [
            'signature' => [
                'id' => $signature->id,
                'role' => $signature->role->value,
                'role_label' => $signature->role->label(),
                'signer_name' => $signature->signer_name,
                'signer_email' => $signature->signer_email,
                'signed_at' => $signature->signed_at?->toIso8601String(),
            ],
            'token' => $token,
            'cancelled' => false,
            'session' => [
                'id' => $session->id,
                'title' => $session->title,
                'scheduled_at' => $session->scheduled_at?->format('d/m/Y H:i'),
                'next_alignment_at' => $session->next_alignment_at?->format('d/m/Y'),
                'employee' => $session->employee,
                'leader' => $session->leader?->only(['name', 'email']),
                'template' => $template,
                'answers' => $answers,
                'out_of_version_answers' => FeedbackSessionStructure::outOfVersionAnswers($session, false),
                'signatures' => $session->signatures->map(fn ($s) => [
                    'role' => $s->role->value,
                    'signer_name' => $s->signer_name,
                    'signed_at' => $s->signed_at?->toIso8601String(),
                ]),
            ],
        ]);
    }

    public function store(Request $request, string $token, PublishFeedbackNotice $notices): RedirectResponse
    {
        $signature = $this->findSignature($token);
        // Convite cancelado: não assina; volta à página do link, que mostra o aviso.
        if ($signature->isCancelled()) {
            return redirect()
                ->route('feedback.sign.show', $token)
                ->with('error', ReopenFeedbackSession::CANCELLED_LINK_MESSAGE);
        }
        abort_if($signature->isSigned(), 403, 'Este documento já foi assinado.');

        $data = $request->validate([
            'signature_data' => ['required', 'string', 'starts_with:data:image/png;base64,'],
            'declaration_accepted' => ['accepted'],
        ]);

        $binary = base64_decode(substr($data['signature_data'], strlen('data:image/png;base64,')), true);
        abort_unless($binary !== false && strlen($binary) > 100, 422, 'Assinatura inválida.');

        $path = 'feedback-signatures/'.$signature->feedback_session_id.'/'.$signature->id.'.png';
        Storage::disk('local')->put($path, $binary);

        $signature->update([
            'signature_path' => $path,
            'signed_at' => now(),
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ]);

        $session = $signature->session()->with('signatures')->first();
        if ($session && $session->isFullySigned()) {
            $session->update([
                'status' => FeedbackSessionStatus::Completed,
                'completed_at' => now(),
            ]);

            $notices->completed($session);
        }

        return redirect()
            ->route('feedback.sign.show', $token)
            ->with('success', 'Assinatura registrada com sucesso. Obrigado!');
    }

    private function findSignature(string $token): FeedbackSessionSignature
    {
        // Feedback excluído: o link de assinatura deixa de existir (404), sem assinar nem mostrar respostas.
        return FeedbackSessionSignature::query()
            ->where('token', $token)
            ->whereHas('session')
            ->firstOrFail();
    }
}
