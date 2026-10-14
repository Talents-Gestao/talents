<?php

declare(strict_types=1);

namespace App\Http\Controllers\Suggestion;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\TeamSuggestion;
use App\Support\Suggestions\SuggestionCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PublicSuggestionController extends Controller
{
    private function findCompany(string $token): Company
    {
        return Company::query()->where('complaints_public_token', $token)->firstOrFail();
    }

    public function create(string $token): Response
    {
        $company = $this->findCompany($token);

        return Inertia::render('Suggestion/Submit', [
            'token' => $token,
            'companyName' => $company->name,
            'topics' => SuggestionCatalog::topics(),
            'areas' => SuggestionCatalog::areas(),
            'responsePreferences' => SuggestionCatalog::responsePreferences(),
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $company = $this->findCompany($token);

        $data = $request->validate([
            'topic' => ['required', 'string', Rule::in(array_keys(SuggestionCatalog::topics()))],
            'area' => ['nullable', 'string', Rule::in(array_keys(SuggestionCatalog::areas()))],
            'message' => ['required', 'string', 'min:10', 'max:20000'],
            'response_preference' => ['required', 'string', Rule::in(array_keys(SuggestionCatalog::responsePreferences()))],
            'reporter_name' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf(fn () => $request->input('response_preference') === 'sim'),
            ],
            'contact' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf(fn () => $request->input('response_preference') === 'sim'),
            ],
        ], [
            'topic.required' => 'Selecione sobre o que você gostaria de falar.',
            'message.required' => 'Escreva sua mensagem.',
            'message.min' => 'A mensagem precisa ter pelo menos 10 caracteres.',
            'response_preference.required' => 'Informe se deseja receber retorno.',
            'reporter_name.required' => 'Informe seu nome para receber o retorno.',
            'contact.required' => 'Deixe um e-mail, telefone ou outro contato para receber o retorno.',
        ]);

        $area = $data['area'] ?? null;
        if ($area === '') {
            $area = null;
        }

        $wantsReply = $data['response_preference'] === 'sim';
        $reporterName = $wantsReply ? trim((string) ($data['reporter_name'] ?? '')) : '';
        $contact = $wantsReply ? trim((string) ($data['contact'] ?? '')) : '';

        TeamSuggestion::query()->create([
            'company_id' => $company->id,
            'topic' => $data['topic'],
            'area' => $area,
            'message' => trim($data['message']),
            'response_preference' => $data['response_preference'],
            'reporter_name' => $reporterName !== '' ? $reporterName : null,
            'contact' => $contact !== '' ? $contact : null,
            'status' => 'new',
        ]);

        return redirect()->route('sugestao.thanks', ['token' => $token]);
    }

    public function thanks(string $token): Response
    {
        $company = $this->findCompany($token);

        return Inertia::render('Suggestion/Thanks', [
            'token' => $token,
            'companyName' => $company->name,
        ]);
    }
}
