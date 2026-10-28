<?php

declare(strict_types=1);

namespace App\Support\Nr1Shell;

use App\Models\Company;
use App\Models\Survey;
use App\Models\User;
use App\Support\SurveyPublicationGate;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Empresa e campanha em foco no shell NR-1. Talents escolhe (query `empresa`/`campanha`, lembrada na sessão); a empresa
 * entra direto na própria e só enxerga campanhas já «Publicadas» (T02). Comercial e revisor não têm empresa em foco.
 */
final class Nr1ShellContext
{
    private const SESSION_COMPANY = 'nr1_shell.company_id';

    private const SESSION_SURVEY = 'nr1_shell.survey_id';

    /**
     * @param  Collection<int, Company>  $companies
     * @param  Collection<int, Survey>  $surveys
     */
    private function __construct(
        public readonly ?Company $company,
        public readonly ?Survey $survey,
        public readonly Collection $companies,
        public readonly Collection $surveys,
        public readonly bool $canPickCompany,
        public readonly bool $awaitingPublication,
    ) {}

    public static function resolve(Request $request, User $user, string $profile): self
    {
        if ($profile === Nr1Shell::PROFILE_TALENTS) {
            return self::forTalents($request);
        }

        if ($profile === Nr1Shell::PROFILE_EMPRESA) {
            return self::forCompany($request, $user);
        }

        return new self(null, null, collect(), collect(), false, false);
    }

    private static function forTalents(Request $request): self
    {
        $companies = Company::query()
            ->whereHas('surveys')
            ->orderBy('name')
            ->get(['id', 'name']);

        $companyId = (int) ($request->query('empresa') ?? $request->session()->get(self::SESSION_COMPANY) ?? 0);
        $company = $companies->firstWhere('id', $companyId) ?? $companies->first();

        $surveys = $company ? self::surveysOf($company, false) : collect();
        $surveyId = (int) ($request->query('campanha') ?? $request->session()->get(self::SESSION_SURVEY) ?? 0);
        $survey = $surveys->firstWhere('id', $surveyId) ?? $surveys->first();

        $request->session()->put(self::SESSION_COMPANY, $company?->id);
        $request->session()->put(self::SESSION_SURVEY, $survey?->id);

        return new self($company, $survey, $companies, $surveys, true, false);
    }

    private static function forCompany(Request $request, User $user): self
    {
        $company = $user->contextCompany();
        if ($company === null) {
            return new self(null, null, collect(), collect(), false, false);
        }

        $all = self::surveysOf($company, false);
        $surveys = self::surveysOf($company, true);
        $surveyId = (int) ($request->query('campanha') ?? 0);
        $survey = $surveys->firstWhere('id', $surveyId) ?? $surveys->first();

        return new self($company, $survey, collect([$company]), $surveys, false, $survey === null && $all->isNotEmpty());
    }

    /**
     * Campanhas da empresa (V11 e modelo anterior), da mais recente (pelo início da coleta) para a mais antiga; com $onlyPublished, só as que a empresa pode ver.
     *
     * @return Collection<int, Survey>
     */
    private static function surveysOf(Company $company, bool $onlyPublished): Collection
    {
        $query = Survey::query()
            ->where('company_id', $company->id)
            ->orderByDesc('starts_at')
            ->orderByDesc('id');

        if ($onlyPublished) {
            $query = SurveyPublicationGate::scopeVisible($query);
        }

        return $query->get();
    }
}
