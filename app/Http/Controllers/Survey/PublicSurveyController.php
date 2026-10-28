<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Nr1Training;
use App\Models\SurveyAnswer;
use App\Models\SurveyAudienceLink;
use App\Models\SurveyResponse;
use App\Services\Nr1\Nr1JourneyBuilder;
use App\Services\Nr1\Nr1TrainingService;
use App\Services\SurveyDuplicateGuard;
use App\Services\SurveyPublicEntry;
use App\Services\SurveyResultCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class PublicSurveyController extends Controller
{
    public function thanks(string $token): InertiaResponse
    {
        $entry = SurveyPublicEntry::resolve($token);
        abort_if($entry === null, 404);

        return Inertia::render('Survey/ThankYou', [
            'surveyTitle' => $entry['survey']->title,
        ]);
    }

    public function show(Request $request, string $token, Nr1JourneyBuilder $journey): InertiaResponse|RedirectResponse
    {
        $entry = SurveyPublicEntry::resolve($token, ['template.sections.questions', 'company.departments']);
        abort_if($entry === null, 404);

        $survey = $entry['survey'];
        $link = $entry['link'];

        $closedReason = SurveyPublicEntry::closureReason($survey, $link);
        if ($closedReason !== null) {
            return Inertia::render('Survey/Closed', [
                'message' => match ($closedReason) {
                    'inactive' => 'Esta pesquisa não está ativa no momento.',
                    'not_started' => 'Esta pesquisa ainda não iniciou.',
                    'ended' => 'Esta pesquisa já foi encerrada.',
                    default => 'Esta pesquisa não está ativa no momento.',
                },
            ]);
        }

        $invitation = $request->query('convite');
        $blocked = SurveyDuplicateGuard::blockReasonForView($request, $survey, $link, is_string($invitation) ? $invitation : null);
        if ($blocked !== null) {
            return Inertia::render('Survey/Closed', ['message' => SurveyDuplicateGuard::message($blocked)]);
        }

        return Inertia::render('Survey/Take', [
            'survey' => $survey,
            'entryToken' => $token,
            // Campanha V11: «Minha jornada» (orientação + só o treinamento contratado e liberado a este público).
            'journey' => $journey->build($survey, $link, fn (Nr1Training $training) => route('survey.public.training-material', [$token, $training->id])),
            'audience' => $link?->audience,
            'audienceLabel' => $link?->label(),
            'invitation' => is_string($invitation) ? $invitation : null,
            'collectDemographics' => $survey->collectsDemographics(),
            'clientDraft' => (bool) config('nr1.collection.client_draft', false),
            'likertLabels' => [
                1 => 'Nunca',
                2 => 'Raramente',
                3 => 'Às vezes',
                4 => 'Frequentemente',
                5 => 'Sempre',
            ],
            'agreementLabels' => [
                1 => 'Discordo totalmente',
                2 => 'Discordo',
                3 => 'Neutro',
                4 => 'Concordo',
                5 => 'Concordo totalmente',
            ],
            'ageRanges' => [
                '18-24' => '18 a 24',
                '25-34' => '25 a 34',
                '35-44' => '35 a 44',
                '45-54' => '45 a 54',
                '55+' => '55+',
            ],
            'tenureRanges' => [
                '0-1' => 'Menos de 1 ano',
                '1-3' => '1 a 3 anos',
                '3-5' => '3 a 5 anos',
                '5+' => 'Mais de 5 anos',
            ],
        ]);
    }

    /**
     * Arquivo de um treinamento, só para o público a que ele foi contratado e liberado, com a campanha aberta.
     */
    public function trainingMaterial(string $token, int $training)
    {
        $entry = SurveyPublicEntry::resolve($token);
        abort_if($entry === null || $entry['link'] === null, 404);
        abort_if(SurveyPublicEntry::closureReason($entry['survey'], $entry['link']) !== null, 404);
        abort_unless($entry['survey']->usesReviewFlow(), 404);

        $row = Nr1Training::query()
            ->whereKey($training)
            ->where('survey_id', $entry['survey']->id)
            ->where('audience', $entry['link']->audience)
            ->whereNotNull('contracted_at')
            ->whereNotNull('released_at')
            ->whereNotNull('material_path')
            ->first();
        abort_if($row === null || ! Storage::disk(Nr1TrainingService::DISK)->exists($row->material_path), 404);

        return Storage::disk(Nr1TrainingService::DISK)->download($row->material_path, $row->material_name ?? 'material', ['Cache-Control' => 'private, no-store']);
    }

    public function submit(Request $request, string $token, SurveyResultCalculator $calculator): RedirectResponse
    {
        $entry = SurveyPublicEntry::resolve($token, ['template.sections.questions']);
        abort_if($entry === null, 404);

        $survey = $entry['survey'];
        /** @var SurveyAudienceLink|null $link */
        $link = $entry['link'];

        if (SurveyPublicEntry::closureReason($survey, $link) !== null) {
            abort(403);
        }

        $questionIds = $survey->template->sections->flatMap->questions->pluck('id')->all();
        $hasDepartments = Department::query()->where('company_id', $survey->company_id)->exists();

        $rules = [
            'answers' => ['required', 'array'],
            'department_id' => [
                Rule::requiredIf($hasDepartments),
                'nullable',
                'exists:departments,id',
            ],
            'age_range' => ['nullable', 'string', 'max:32'],
            'tenure_range' => ['nullable', 'string', 'max:32'],
            'invitation' => ['nullable', 'string', 'max:128'],
        ];

        foreach ($questionIds as $qid) {
            $rules['answers.'.$qid] = ['required', 'integer', 'min:1', 'max:5'];
        }

        $data = $request->validate($rules);

        if (! empty($data['department_id'])) {
            abort_unless(
                Department::query()->where('id', $data['department_id'])->where('company_id', $survey->company_id)->exists(),
                422
            );
        }

        $collectsDemographics = $survey->collectsDemographics();
        $invitation = $data['invitation'] ?? null;

        // Convite consumido e resposta gravada na mesma transação: se algo falhar, o convite não se perde.
        $blocked = DB::transaction(function () use ($request, $survey, $link, $invitation, $data, $collectsDemographics): ?string {
            $blocked = SurveyDuplicateGuard::consume($request, $survey, $link, $invitation);
            if ($blocked !== null) {
                return $blocked;
            }

            $response = SurveyResponse::create([
                'survey_id' => $survey->id,
                'session_token' => Str::random(40),
                'department_id' => isset($data['department_id']) ? (int) $data['department_id'] : null,
                // Campanhas V11 não coletam faixa etária nem tempo de casa; a conclusão é gravada só com o mês.
                'age_range' => $collectsDemographics ? ($data['age_range'] ?? null) : null,
                'tenure_range' => $collectsDemographics ? ($data['tenure_range'] ?? null) : null,
                'audience' => $link?->audience,
                'completed_at' => now()->startOfMonth(),
            ]);

            foreach ($data['answers'] as $qid => $value) {
                SurveyAnswer::create([
                    'survey_response_id' => $response->id,
                    'survey_template_question_id' => $qid,
                    'value' => (int) $value,
                ]);
            }

            return null;
        });

        if ($blocked !== null) {
            // A página pública mostra o aviso adequado (convite inválido ou dispositivo que já respondeu).
            return redirect()->route('survey.public', array_filter(['token' => $token, 'convite' => $invitation]));
        }

        $calculator->recalculate($survey->fresh());
        SurveyDuplicateGuard::markSent($survey);

        return redirect()->route('survey.public.thanks', ['token' => $token]);
    }
}
