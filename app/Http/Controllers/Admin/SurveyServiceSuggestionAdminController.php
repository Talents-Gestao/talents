<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exceptions\Nr1CampaignException;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Survey;
use App\Models\SurveyServiceSuggestion;
use App\Services\SurveyServiceSuggestionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SurveyServiceSuggestionAdminController extends Controller
{
    public function __construct(private readonly SurveyServiceSuggestionService $suggestions) {}

    public function update(Request $request, Company $company, Survey $survey, SurveyServiceSuggestion $suggestion): RedirectResponse
    {
        $this->assertOwns($company, $survey, $suggestion);

        $data = $request->validate([
            'rationale' => ['required', 'string', 'max:2000'],
        ]);

        $this->suggestions->update($suggestion, trim($data['rationale']));

        return back()->with('success', 'Texto da sugestão atualizado.');
    }

    public function dismiss(Company $company, Survey $survey, SurveyServiceSuggestion $suggestion): RedirectResponse
    {
        $this->assertOwns($company, $survey, $suggestion);

        try {
            $this->suggestions->dismiss($suggestion);
        } catch (Nr1CampaignException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Sugestão dispensada. Ela não vai para a empresa.');
    }

    public function release(Request $request, Company $company, Survey $survey): RedirectResponse
    {
        abort_unless((int) $survey->company_id === (int) $company->id, 404);

        $count = $this->suggestions->release($survey, $request->user());
        if ($count === 0) {
            return back()->with('error', 'Não há sugestões aguardando liberação.');
        }

        return back()->with('success', 'Serviços liberados para a empresa.');
    }

    private function assertOwns(Company $company, Survey $survey, SurveyServiceSuggestion $suggestion): void
    {
        abort_unless((int) $survey->company_id === (int) $company->id, 404);
        abort_unless((int) $suggestion->survey_id === (int) $survey->id, 404);
    }
}
