<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Hiring;

use App\Enums\AdminPermissionModule;
use App\Enums\HiringAssessmentDecision;
use App\Enums\HiringAssessmentRating;
use App\Enums\HiringCommunicationChannel;
use App\Enums\HiringCommunicationKind;
use App\Enums\HiringCommunicationStatus;
use App\Enums\PermissionAction;
use App\Http\Controllers\Controller;
use App\Models\HiringApplication;
use App\Models\HiringVacancy;
use App\Models\Interview;
use App\Models\MailSetting;
use App\Services\Hiring\HiringAssessmentService;
use App\Services\Hiring\HiringCommunicationService;
use App\Services\Hiring\HiringDossierRenderer;
use App\Support\Hiring\HiringCommunicationsPayload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Avaliação e dossiê por candidatura. Rotas com scopeBindings: candidatura de outra vaga dá 404.
 * Ver: contratacao_vagas,view · editar: contratacao_vagas,edit · aprovar: contratacao_vagas,validate.
 * Nada disto vai para o portal da empresa.
 */
class AssessmentController extends Controller
{
    public function __construct(
        private readonly HiringAssessmentService $service,
        private readonly HiringDossierRenderer $dossier,
        private readonly HiringCommunicationService $communications,
    ) {}

    public function show(Request $request, HiringVacancy $vacancy, HiringApplication $application): Response
    {
        $application->load(['candidate:id,name,current_role,current_company', 'stage:id,name', 'assessment.approvedBy:id,name', 'assessment.updatedBy:id,name']);
        $vacancy->load(['company:id,name', 'hiringProcess:id,title']);
        $assessment = $this->service->forApplication($application);
        $user = $request->user();
        $canInterviews = $user->canAccessAdmin(AdminPermissionModule::EntrevistasIa, PermissionAction::View);

        $interviews = Interview::query()
            ->where('hiring_application_id', $application->id)
            ->with('questionnaire:id,name,version')
            ->latest()
            ->get()
            ->map(fn (Interview $i) => [
                'id' => $i->id,
                'status_label' => $i->status->label(),
                'questionnaire' => $i->questionnaire ? $i->questionnaire->name.' (v'.$i->questionnaire->version.')' : null,
                'is_reviewed' => $i->isReviewed(),
                'created_at' => $i->created_at?->toIso8601String(),
                'url' => $canInterviews
                    ? route('admin.contratacao.vagas.applications.interviews.show', [$vacancy, $application, $i])
                    : null,
            ]);

        return Inertia::render('Admin/Hiring/Assessments/Edit', [
            'vacancy' => [
                'id' => $vacancy->id,
                'title' => $vacancy->title,
                'company_name' => $vacancy->company?->name,
                'process_title' => $vacancy->hiringProcess?->title,
            ],
            'application' => [
                'id' => $application->id,
                'stage_name' => $application->stage?->name,
                'candidate' => [
                    'name' => $application->candidate?->name,
                    'current_role' => $application->candidate?->current_role,
                    'current_company' => $application->candidate?->current_company,
                ],
            ],
            'assessment' => [
                'exists' => $assessment->exists,
                'success_profile' => $assessment->success_profile,
                'case_description' => $assessment->case_description,
                'case_result' => $assessment->case_result,
                'criteria' => $assessment->criteria ?? [],
                'interviewer_summary' => $assessment->interviewer_summary,
                'limitations' => $assessment->limitations,
                'decision' => ($assessment->decision ?? HiringAssessmentDecision::Pending)->value,
                'approved_at' => $assessment->approved_at?->toIso8601String(),
                'approved_by' => $assessment->approvedBy?->name,
                'updated_by' => $assessment->updatedBy?->name,
                'updated_at' => $assessment->updated_at?->toIso8601String(),
            ],
            'interviews' => $interviews,
            'decisions' => HiringAssessmentDecision::options(),
            'ratings' => HiringAssessmentRating::options(),
            'methodology_note' => HiringDossierRenderer::METHODOLOGY_NOTE,
            'can' => [
                'edit' => $user->canAccessAdmin(AdminPermissionModule::ContratacaoVagas, PermissionAction::Edit),
                'validate' => $user->canAccessAdmin(AdminPermissionModule::ContratacaoVagas, PermissionAction::Validate),
                'create_interview' => $user->canAccessAdmin(AdminPermissionModule::EntrevistasIa, PermissionAction::Create),
            ],
            // «Enviar dossiê ao cliente»: só com validate; contatos elegíveis (vagas + e-mail) e histórico desta candidatura.
            'dossier_send' => $user->canAccessAdmin(AdminPermissionModule::ContratacaoVagas, PermissionAction::Validate)
                ? [
                    'contacts' => array_values(array_filter(
                        HiringCommunicationsPayload::audience($vacancy, $user)['contacts'],
                        static fn (array $c) => in_array(HiringCommunicationChannel::Email->value, $c['channels'], true),
                    )),
                    'template' => $this->communications->dossierTemplate($application->loadMissing('vacancy')),
                    'smtp_configured' => MailSetting::smtpConfigured(),
                    'emails_remaining_today' => $this->communications->emailsRemainingToday(),
                    'contacts_url' => $user->canAccessAdmin(AdminPermissionModule::Companies, PermissionAction::View)
                        ? route('admin.companies.show', ['company' => $vacancy->company_id, 'tab' => 'contatos'])
                        : null,
                ]
                : null,
            'dossier_history' => array_values(array_filter(
                HiringCommunicationsPayload::history($vacancy, $application->id),
                static fn (array $row) => $row['kind'] === HiringCommunicationKind::Dossier->value,
            )),
        ]);
    }

    /** «Enviar dossiê ao cliente»: PDF do conteúdo aprovado, só por e-mail, só a contatos elegíveis. */
    public function sendDossier(Request $request, HiringVacancy $vacancy, HiringApplication $application): RedirectResponse
    {
        $data = $request->validate([
            'contact_ids' => ['required', 'array', 'min:1', 'max:50'],
            'contact_ids.*' => ['integer'],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
            'resend' => ['sometimes', 'boolean'],
        ], ['contact_ids.required' => 'Escolha ao menos um contato.'], ['subject' => 'assunto', 'body' => 'texto']);

        $communications = $this->communications->sendDossier(
            $application,
            $data['contact_ids'],
            $data['subject'],
            $data['body'],
            (bool) ($data['resend'] ?? false),
            $request->user()?->id,
        );

        $failed = $communications->where('status', HiringCommunicationStatus::Failed)->count();

        return back()->with($failed > 0 ? 'error' : 'success', $failed > 0
            ? 'O servidor de e-mail recusou '.$failed.' envio(s) do dossiê. Veja o motivo no histórico.'
            : 'Dossiê enviado ao servidor de e-mail.');
    }

    public function update(Request $request, HiringVacancy $vacancy, HiringApplication $application): RedirectResponse
    {
        $data = $request->validate([
            'success_profile' => ['nullable', 'string', 'max:10000'],
            'case_description' => ['nullable', 'string', 'max:10000'],
            'case_result' => ['nullable', 'string', 'max:10000'],
            'criteria' => ['nullable', 'array', 'max:40'],
            'criteria.*.criterio' => ['nullable', 'string', 'max:255'],
            'criteria.*.evidencia' => ['nullable', 'string', 'max:3000'],
            'criteria.*.origem' => ['nullable', 'string', 'max:255'],
            'criteria.*.avaliacao' => ['required', Rule::enum(HiringAssessmentRating::class)],
            'interviewer_summary' => ['nullable', 'string', 'max:10000'],
            'limitations' => ['nullable', 'string', 'max:5000'],
            'decision' => ['required', Rule::enum(HiringAssessmentDecision::class)],
        ], [], [
            'success_profile' => 'perfil de sucesso',
            'case_description' => 'descrição do case',
            'case_result' => 'resultado do case',
            'interviewer_summary' => 'síntese da entrevistadora',
            'limitations' => 'limites da avaliação',
            'decision' => 'decisão',
        ]);

        $assessment = $this->service->save($application, $data, $request->user()?->id);

        return back()->with('success', $assessment->isApproved()
            ? 'Avaliação salva.'
            : 'Avaliação salva. O dossiê precisa ser aprovado para gerar o PDF.');
    }

    public function approve(Request $request, HiringVacancy $vacancy, HiringApplication $application): RedirectResponse
    {
        $this->service->approve($application, $request->user()?->id);

        return back()->with('success', 'Dossiê aprovado.');
    }

    public function dossier(HiringVacancy $vacancy, HiringApplication $application): HttpResponse
    {
        $application->load('assessment');
        abort_unless($application->assessment?->isApproved() === true, 422, 'O dossiê só pode ser gerado depois de aprovado.');

        $slug = trim((string) preg_replace('/[^a-z0-9]+/i', '-', mb_strtolower((string) $application->candidate?->name)), '-') ?: 'candidato';

        return $this->dossier->pdf($application)->download('dossie-'.$slug.'-'.$application->id.'.pdf');
    }
}
