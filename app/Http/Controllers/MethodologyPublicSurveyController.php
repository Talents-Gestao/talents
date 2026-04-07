<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\MethodologySurvey;
use App\Models\MethodologySurveyAnswer;
use App\Models\MethodologySurveyResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class MethodologyPublicSurveyController extends Controller
{
    public function thanks(string $token): InertiaResponse
    {
        $survey = MethodologySurvey::query()->where('public_token', $token)->firstOrFail();

        return Inertia::render('Methodology/SurveyThankYou', [
            'surveyTitle' => $survey->title,
        ]);
    }

    public function show(string $token): InertiaResponse|RedirectResponse
    {
        $survey = MethodologySurvey::query()
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
                    default => 'Esta pesquisa não está disponível.',
                },
            ]);
        }

        return Inertia::render('Methodology/SurveyTake', [
            'survey' => $survey,
        ]);
    }

    public function submit(Request $request, string $token): RedirectResponse
    {
        $survey = MethodologySurvey::query()
            ->where('public_token', $token)
            ->with(['template.sections.questions'])
            ->firstOrFail();

        if (! $survey->acceptsPublicResponses()) {
            abort(403);
        }

        $questions = $survey->template->sections->flatMap->questions;
        $rules = [
            'email' => [$survey->collect_email ? 'required' : 'nullable', 'string', 'max:255', 'email'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'answers' => ['required', 'array'],
        ];

        foreach ($questions as $q) {
            if ($q->type === 'scale') {
                $rules['answers.'.$q->id] = [
                    $q->is_required ? 'required' : 'nullable',
                    'integer',
                    'min:'.$q->scale_min,
                    'max:'.$q->scale_max,
                ];
            } else {
                $rules['answers.'.$q->id] = [
                    $q->is_required ? 'required' : 'nullable',
                    'string',
                    'max:10000',
                ];
            }
        }

        $data = $request->validate($rules);

        if (! empty($data['department_id'])) {
            abort_unless(
                Department::query()->where('id', $data['department_id'])->where('company_id', $survey->company_id)->exists(),
                422
            );
        }

        $response = MethodologySurveyResponse::create([
            'methodology_survey_id' => $survey->id,
            'email' => $data['email'] ?? null,
            'session_token' => Str::random(40),
            'department_id' => $data['department_id'] ?? null,
            'completed_at' => now(),
        ]);

        foreach ($questions as $q) {
            $raw = $data['answers'][$q->id] ?? null;
            if ($raw === null || $raw === '') {
                if (! $q->is_required) {
                    continue;
                }
            }

            MethodologySurveyAnswer::create([
                'methodology_survey_response_id' => $response->id,
                'methodology_form_question_id' => $q->id,
                'value_numeric' => $q->type === 'scale' && $raw !== null && $raw !== '' ? (int) $raw : null,
                'value_text' => $q->type === 'text' && $raw !== null && $raw !== '' ? $raw : null,
            ]);
        }

        return redirect()->route('methodology.public.thanks', ['token' => $token]);
    }
}
