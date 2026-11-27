<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Hiring;

use App\Enums\HiringCommunicationChannel;
use App\Enums\HiringCommunicationKind;
use App\Enums\HiringCommunicationStatus;
use App\Http\Controllers\Controller;
use App\Models\HiringCommunication;
use App\Models\HiringVacancy;
use App\Models\HiringVacancyStage;
use App\Services\Hiring\HiringCommunicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * «Comunicar etapa» no quadro da vaga (contratacao_vagas,edit). Texto sem dado de candidato,
 * revisão antes do envio, idempotência e limite diário no HiringCommunicationService.
 * Rotas com scopeBindings: comunicação de outra vaga dá 404; contato de outra empresa dá 422.
 */
class VacancyCommunicationController extends Controller
{
    public function __construct(
        private readonly HiringCommunicationService $service,
    ) {}

    /** Texto-modelo da etapa (para o formulário). */
    public function template(Request $request, HiringVacancy $vacancy): JsonResponse
    {
        $stage = $this->stage($request, $vacancy);

        return response()->json($this->service->stageTemplate($vacancy, $stage));
    }

    /** Revisão antes do envio: destinatários, elegibilidade e se o mesmo texto já saiu. */
    public function review(Request $request, HiringVacancy $vacancy): JsonResponse
    {
        $data = $this->validated($request);
        $stage = $this->stage($request, $vacancy);

        return response()->json([
            'rows' => $this->service->review(
                $vacancy,
                HiringCommunicationKind::StageUpdate,
                $stage,
                null,
                $data['contact_ids'],
                HiringCommunicationChannel::from($data['channel']),
                (string) ($data['subject'] ?? ''),
                $data['body'],
            ),
            'emails_remaining_today' => $this->service->emailsRemainingToday(),
            'daily_limit' => HiringCommunicationService::DAILY_EMAIL_LIMIT,
        ]);
    }

    public function store(Request $request, HiringVacancy $vacancy): RedirectResponse
    {
        $data = $this->validated($request);
        $stage = $this->stage($request, $vacancy);
        $channel = HiringCommunicationChannel::from($data['channel']);

        $communications = $this->service->sendStageUpdate(
            $vacancy,
            $stage,
            $data['contact_ids'],
            $channel,
            (string) ($data['subject'] ?? ''),
            $data['body'],
            (bool) ($data['resend'] ?? false),
            $request->user()?->id,
        );

        if ($channel === HiringCommunicationChannel::Whatsapp) {
            return back()->with('success', 'Texto do WhatsApp gerado. Use «Copiar» em cada contato; o envio é feito por você no WhatsApp.');
        }

        $failed = $communications->where('status', HiringCommunicationStatus::Failed)->count();
        $sent = $communications->count() - $failed;

        return back()->with($failed > 0 ? 'error' : 'success', $failed > 0
            ? "Enviado ao servidor de e-mail: {$sent}. Falhou: {$failed} (veja o motivo no histórico)."
            : "Comunicação enviada ao servidor de e-mail para {$sent} ".($sent === 1 ? 'contato' : 'contatos').'.');
    }

    /** «Copiar» do WhatsApp: registra «Texto copiado». Nunca marca como enviado. */
    public function copied(HiringVacancy $vacancy, HiringCommunication $communication): JsonResponse
    {
        $communication = $this->service->markCopied($communication);

        return response()->json([
            'status' => $communication->status->value,
            'status_label' => $communication->status->label(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'stage_id' => ['required', 'integer'],
            'channel' => ['required', Rule::enum(HiringCommunicationChannel::class)],
            'contact_ids' => ['required', 'array', 'min:1', 'max:50'],
            'contact_ids.*' => ['integer'],
            'subject' => ['nullable', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
            'resend' => ['sometimes', 'boolean'],
        ], [
            'contact_ids.required' => 'Escolha ao menos um contato.',
        ], [
            'subject' => 'assunto',
            'body' => 'texto',
            'channel' => 'canal',
        ]);
    }

    private function stage(Request $request, HiringVacancy $vacancy): HiringVacancyStage
    {
        $stage = HiringVacancyStage::query()
            ->where('hiring_vacancy_id', $vacancy->id)
            ->find($request->integer('stage_id'));

        abort_if($stage === null, 422, 'Etapa inválida para esta vaga.');

        return $stage;
    }
}
