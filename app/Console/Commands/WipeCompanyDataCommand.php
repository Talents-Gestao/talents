<?php

namespace App\Console\Commands;

use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class WipeCompanyDataCommand extends Command
{
    protected $signature = 'app:wipe-company-data
        {--cnpj= : CNPJ da empresa (com ou sem máscara)}
        {--force : Pula a confirmação interativa}
        {--dry-run : Apenas mostra o que seria apagado, sem executar}';

    protected $description = 'Apaga TODOS os dados operacionais de uma empresa (pesquisas, denúncias, calendário estratégico, RHID, metodologia, etc.) mantendo apenas o cadastro em companies e os usuários (users).';

    public function handle(): int
    {
        $cnpjInput = (string) ($this->option('cnpj') ?? '');
        if ($cnpjInput === '') {
            $cnpjInput = (string) $this->ask('Informe o CNPJ da empresa (com ou sem máscara)');
        }

        $digits = preg_replace('/\D+/', '', $cnpjInput) ?? '';
        if (strlen($digits) !== 14) {
            $this->error('CNPJ inválido. Esperado 14 dígitos.');

            return self::INVALID;
        }

        $company = Company::query()
            ->where(function ($q) use ($digits) {
                $q->whereRaw("regexp_replace(coalesce(cnpj, ''), '\\D', '', 'g') = ?", [$digits]);
            })
            ->first();

        if (! $company) {
            $this->error("Nenhuma empresa encontrada com CNPJ {$cnpjInput}.");

            return self::FAILURE;
        }

        $companyId = (int) $company->id;

        $this->line('');
        $this->info('Empresa encontrada:');
        $this->table(
            ['ID', 'Nome', 'Razão social', 'CNPJ'],
            [[$company->id, (string) $company->name, (string) $company->legal_name, (string) $company->cnpj]]
        );

        $usersCount = DB::table('users')->where('company_id', $companyId)->count();

        $counts = $this->collectCounts($companyId);

        $this->line('');
        $this->info('Resumo do que será MANTIDO:');
        $this->table(
            ['Tabela', 'Registros mantidos'],
            [
                ['companies (esta empresa)', 1],
                ['users (vinculados à empresa)', $usersCount],
            ]
        );

        $this->line('');
        $this->warn('Resumo do que será APAGADO:');
        $rows = [];
        foreach ($counts as $label => $n) {
            $rows[] = [$label, $n];
        }
        $this->table(['Item', 'Registros'], $rows);

        if ($this->option('dry-run')) {
            $this->line('');
            $this->info('[dry-run] Nada foi apagado.');

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            $this->line('');
            $this->warn('Esta ação é DESTRUTIVA e IRREVERSÍVEL. Os dados acima serão apagados.');
            $confirm = (string) $this->ask("Para confirmar, digite o CNPJ exatamente: {$company->cnpj}");
            if (trim($confirm) !== (string) $company->cnpj) {
                $this->error('Confirmação não confere. Operação cancelada.');

                return self::FAILURE;
            }
        }

        try {
            DB::transaction(function () use ($companyId) {
                $this->wipe($companyId);
            });
        } catch (Throwable $e) {
            $this->error('Falha ao apagar dados: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->line('');
        $this->info('Dados apagados com sucesso. Cadastro da empresa e usuários preservados.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, int>
     */
    private function collectCounts(int $companyId): array
    {
        $counts = [];

        $surveyIds = DB::table('surveys')->where('company_id', $companyId)->pluck('id');
        $methSurveyIds = DB::table('methodology_surveys')->where('company_id', $companyId)->pluck('id');
        $complaintIds = DB::table('complaints')->where('company_id', $companyId)->pluck('id');
        $rhidImportIds = DB::table('rhid_espelho_imports')->where('company_id', $companyId)->pluck('id');

        $counts['Pesquisas (surveys)'] = $surveyIds->count();
        if ($surveyIds->isNotEmpty()) {
            $responseIds = DB::table('survey_responses')->whereIn('survey_id', $surveyIds)->pluck('id');
            $counts['  → respostas (survey_responses)'] = $responseIds->count();
            $counts['  → answers (survey_answers)'] = $responseIds->isNotEmpty()
                ? DB::table('survey_answers')->whereIn('survey_response_id', $responseIds)->count()
                : 0;
            $counts['  → resultados (survey_results)'] = DB::table('survey_results')->whereIn('survey_id', $surveyIds)->count();
            $counts['  → insights (survey_insights)'] = DB::table('survey_insights')->whereIn('survey_id', $surveyIds)->count();
            if (Schema::hasTable('ai_analyses')) {
                $counts['  → análises de IA (ai_analyses)'] = DB::table('ai_analyses')->whereIn('survey_id', $surveyIds)->count();
            }
            $apIds = DB::table('action_plans')->whereIn('survey_id', $surveyIds)->pluck('id');
            $counts['  → planos de ação (action_plans)'] = $apIds->count();
            $counts['  → itens de plano (action_plan_items)'] = $apIds->isNotEmpty()
                ? DB::table('action_plan_items')->whereIn('action_plan_id', $apIds)->count()
                : 0;
        }

        if (Schema::hasTable('team_suggestions')) {
            $counts['Sugestões e dúvidas (team_suggestions)'] = DB::table('team_suggestions')->where('company_id', $companyId)->count();
        }

        $counts['Denúncias (complaints)'] = $complaintIds->count();
        if ($complaintIds->isNotEmpty()) {
            $counts['  → mensagens (complaint_messages)'] = DB::table('complaint_messages')->whereIn('complaint_id', $complaintIds)->count();
            $counts['  → audit logs (complaint_audit_logs)'] = DB::table('complaint_audit_logs')->whereIn('complaint_id', $complaintIds)->count();
        }

        $counts['Pesquisas Direcionamento Estratégico (methodology_surveys)'] = $methSurveyIds->count();
        if ($methSurveyIds->isNotEmpty()) {
            $methRespIds = DB::table('methodology_survey_responses')->whereIn('methodology_survey_id', $methSurveyIds)->pluck('id');
            $counts['  → respostas (methodology_survey_responses)'] = $methRespIds->count();
            $counts['  → answers (methodology_survey_answers)'] = $methRespIds->isNotEmpty()
                ? DB::table('methodology_survey_answers')->whereIn('methodology_survey_response_id', $methRespIds)->count()
                : 0;
        }
        $counts['Vínculo direcionamento estratégico (company_methodology)'] = DB::table('company_methodology')->where('company_id', $companyId)->count();
        $counts['Pivot company_methodology_form_template'] = DB::table('company_methodology_form_template')->where('company_id', $companyId)->count();
        $counts['Pivot company_survey_template'] = DB::table('company_survey_template')->where('company_id', $companyId)->count();

        $counts['Calendário estratégico (strategic_calendar_items)'] = DB::table('strategic_calendar_items')->where('company_id', $companyId)->count();

        $counts['RHID — imports (rhid_espelho_imports)'] = $rhidImportIds->count();
        if ($rhidImportIds->isNotEmpty()) {
            $counts['  → dias (rhid_espelho_days)'] = DB::table('rhid_espelho_days')->whereIn('import_id', $rhidImportIds)->count();
        }
        $counts['RHID — batches (rhid_espelho_batches)'] = DB::table('rhid_espelho_batches')->where('company_id', $companyId)->count();
        $counts['RHID — audit logs (rhid_audit_logs)'] = DB::table('rhid_audit_logs')->where('company_id', $companyId)->count();
        $counts['RHID — schedule settings (company_rhid_schedule_settings)'] = DB::table('company_rhid_schedule_settings')->where('company_id', $companyId)->count();
        $counts['RHID — preferências por pessoa (rhid_person_schedule_preferences)'] = DB::table('rhid_person_schedule_preferences')->where('company_id', $companyId)->count();

        $counts['Departamentos (departments)'] = DB::table('departments')->where('company_id', $companyId)->count();
        $counts['Cargos (positions)'] = DB::table('positions')->where('company_id', $companyId)->count();
        $counts['Assinaturas (subscriptions)'] = DB::table('subscriptions')->where('company_id', $companyId)->count();

        return $counts;
    }

    private function wipe(int $companyId): void
    {
        $surveyIds = DB::table('surveys')->where('company_id', $companyId)->pluck('id')->all();
        if (! empty($surveyIds)) {
            DB::table('surveys')->whereIn('id', $surveyIds)->delete();
        }

        if (Schema::hasTable('team_suggestions')) {
            DB::table('team_suggestions')->where('company_id', $companyId)->delete();
        }

        $complaintIds = DB::table('complaints')->where('company_id', $companyId)->pluck('id')->all();
        if (! empty($complaintIds)) {
            DB::table('complaints')->whereIn('id', $complaintIds)->delete();
        }

        $methIds = DB::table('methodology_surveys')->where('company_id', $companyId)->pluck('id')->all();
        if (! empty($methIds)) {
            DB::table('methodology_surveys')->whereIn('id', $methIds)->delete();
        }

        DB::table('company_methodology')->where('company_id', $companyId)->delete();
        DB::table('company_methodology_form_template')->where('company_id', $companyId)->delete();
        DB::table('company_survey_template')->where('company_id', $companyId)->delete();

        DB::table('strategic_calendar_items')->where('company_id', $companyId)->delete();

        DB::table('rhid_espelho_imports')->where('company_id', $companyId)->delete();
        DB::table('rhid_espelho_batches')->where('company_id', $companyId)->delete();
        DB::table('rhid_audit_logs')->where('company_id', $companyId)->delete();
        DB::table('company_rhid_schedule_settings')->where('company_id', $companyId)->delete();
        DB::table('rhid_person_schedule_preferences')->where('company_id', $companyId)->delete();

        DB::table('departments')->where('company_id', $companyId)->delete();
        DB::table('positions')->where('company_id', $companyId)->delete();

        DB::table('subscriptions')->where('company_id', $companyId)->delete();
    }
}
