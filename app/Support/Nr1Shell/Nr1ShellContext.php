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

    /** Empresas abertas no painel nesta sessão (mais recente primeiro), para «Abertas recentemente». */
    private const SESSION_RECENT = 'nr1_shell.recent_company_ids';

    private const RECENT_LIMIT = 6;

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

    /** Talents já tem empresa em foco (query `empresa` ou a última lembrada na sessão). */
    public static function hasTalentsFocus(Request $request): bool
    {
        return $request->query('empresa') !== null || $request->session()->get(self::SESSION_COMPANY) !== null;
    }

    /**
     * Limpa empresa/campanha lembradas (mantém «Abertas recentemente»).
     * Usado ao abrir o seletor: senão qualquer ida a `/nr1` sem query devolve à empresa anterior.
     */
    public static function clearTalentsFocus(Request $request): void
    {
        $request->session()->forget(self::SESSION_COMPANY);
        $request->session()->forget(self::SESSION_SURVEY);
    }

    /** @return list<int> */
    public static function recentCompanyIds(Request $request): array
    {
        $ids = $request->session()->get(self::SESSION_RECENT, []);

        return array_values(array_map('intval', is_array($ids) ? $ids : []));
    }

    /**
     * Escolha de empresa (Talents): só nome e quantidade de campanhas; nenhum resultado nem contagem de respostas.
     *
     * @return list<array{id: int, name: string, campaigns: int}>
     */
    public static function companiesForPicker(): array
    {
        return Company::query()
            ->whereHas('surveys')
            ->withCount('surveys')
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->map(fn (Company $company) => ['id' => (int) $company->id, 'name' => (string) $company->name, 'campaigns' => (int) $company->surveys_count])
            ->values()
            ->all();
    }

    private static function forTalents(Request $request): self
    {
        $companies = Company::query()
            ->whereHas('surveys')
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name']);

        $companyId = (int) ($request->query('empresa') ?? $request->session()->get(self::SESSION_COMPANY) ?? 0);
        $company = $companies->firstWhere('id', $companyId) ?? $companies->first();

        $surveys = $company ? self::surveysOf($company, false) : collect();
        $surveyId = (int) ($request->query('campanha') ?? $request->session()->get(self::SESSION_SURVEY) ?? 0);
        $survey = $surveys->firstWhere('id', $surveyId) ?? $surveys->first();

        $request->session()->put(self::SESSION_COMPANY, $company?->id);
        $request->session()->put(self::SESSION_SURVEY, $survey?->id);
        if ($company !== null) {
            $recent = array_values(array_filter(self::recentCompanyIds($request), fn (int $id) => $id !== (int) $company->id));
            $request->session()->put(self::SESSION_RECENT, array_slice([(int) $company->id, ...$recent], 0, self::RECENT_LIMIT));
        }

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
