<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyResponse;
use App\Services\SurveyResultCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class PublicSurveyController extends Controller
{
    public function thanks(string $token): InertiaResponse
    {
        $survey = Survey::query()->where('public_token', $token)->firstOrFail();

        return Inertia::render('Survey/ThankYou', [
            'surveyTitle' => $survey->title,
        ]);
    }

    public function show(string $token): InertiaResponse|RedirectResponse
    {
        $survey = Survey::query()
            ->where('public_token', $token)
            ->with(['template.sections.questions', 'company.departments'])
            ->firstOrFail();

        $closedReason = $survey->publicParticipationClosureReason();
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

        return Inertia::render('Survey/Take', [
            'survey' => $survey,
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

    public function submit(Request $request, string $token, SurveyResultCalculator $calculator): RedirectResponse
    {
        $survey = Survey::query()
            ->where('public_token', $token)
            ->with(['template.sections.questions'])
            ->firstOrFail();

        if (! $survey->acceptsPublicResponses()) {
            abort(403);
        }

        $questionIds = $survey->template->sections->flatMap->questions->pluck('id')->all();

        $rules = [
            'answers' => ['required', 'array'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'age_range' => ['nullable', 'string', 'max:32'],
            'tenure_range' => ['nullable', 'string', 'max:32'],
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

        $response = SurveyResponse::create([
            'survey_id' => $survey->id,
            'session_token' => Str::random(40),
            'department_id' => $data['department_id'] ?? null,
            'age_range' => $data['age_range'] ?? null,
            'tenure_range' => $data['tenure_range'] ?? null,
            'completed_at' => now(),
        ]);

        foreach ($data['answers'] as $qid => $value) {
            SurveyAnswer::create([
                'survey_response_id' => $response->id,
                'survey_template_question_id' => $qid,
                'value' => (int) $value,
            ]);
        }

        $calculator->recalculate($survey->fresh());

        return redirect()->route('survey.public.thanks', ['token' => $token]);
    }
}
