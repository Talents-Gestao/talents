<?php

declare(strict_types=1);

namespace App\Http\Controllers\Feedback;

use App\Actions\Notices\PublishFeedbackNotice;
use App\Enums\FeedbackSessionStatus;
use App\Http\Controllers\Controller;
use App\Models\FeedbackSessionSignature;
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
        $session = $signature->session()->with([
            'employee.department',
            'employee.position',
            'leader',
            'template.sections.questions',
            'answers',
            'signatures',
        ])->firstOrFail();

        $answers = [];
        foreach ($session->answers as $answer) {
            $answers[$answer->feedback_template_question_id] = $answer->value_json ?? $answer->value_text;
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
            'session' => [
                'id' => $session->id,
                'title' => $session->title,
                'scheduled_at' => $session->scheduled_at?->format('d/m/Y H:i'),
                'next_alignment_at' => $session->next_alignment_at?->format('d/m/Y'),
                'employee' => $session->employee,
                'leader' => $session->leader?->only(['name', 'email']),
                'template' => $session->template,
                'answers' => $answers,
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
        return FeedbackSessionSignature::query()
            ->where('token', $token)
            ->firstOrFail();
    }
}
