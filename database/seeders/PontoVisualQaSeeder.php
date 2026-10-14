<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\WorkspaceType;
use App\Models\Company;
use App\Models\CompanyRhidScheduleSetting;
use App\Models\Module;
use App\Models\Plan;
use App\Models\RhidEspelhoDay;
use App\Models\RhidEspelhoImport;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserWorkspace;
use App\Services\Rhid\PunchScheduleSettingsService;
use App\Support\WorkspaceManager;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

/**
 * Empresa local com espelhos do mês corrente para testar os 3 KPIs e 3 rankings.
 * Idempotente. Não chama a API Control iD.
 */
class PontoVisualQaSeeder extends Seeder
{
    public const COMPANY_CNPJ = '00.000.000/0002-70';

    public const CLIENT_EMAIL = 'rh-qa-ponto@talents.local';

    public function run(): void
    {
        $email = (string) config('rhid.visual_qa_email', 'qa-ponto@talents.local');

        $company = Company::query()->firstOrCreate(
            ['cnpj' => self::COMPANY_CNPJ],
            [
                'name' => 'QA Ponto Visual',
                'legal_name' => 'QA Ponto Visual LTDA',
                'segment' => 'servicos',
                'is_active' => true,
                'rhid_access' => true,
                'rhid_email' => $email,
                'rhid_password' => 'secret-qa',
            ],
        );

        $company->fill([
            'name' => 'QA Ponto Visual',
            'is_active' => true,
            'rhid_access' => true,
            'rhid_email' => $email,
            'rhid_password' => 'secret-qa',
        ]);
        $company->save();

        $this->attachRhidPlan($company);
        $this->ensureWeekdaySchedule($company);
        $this->ensureClientUser($company);
        $this->seedMonthEspelhos($company);

        Cache::flush();

        $this->command?->info('Empresa «QA Ponto Visual» pronta em /admin/ponto.');
        $this->command?->info('Cliente: '.self::CLIENT_EMAIL.' / password');
    }

    private function attachRhidPlan(Company $company): void
    {
        $plan = Plan::query()->where('slug', 'nr1-pro')->first();
        if ($plan === null) {
            return;
        }

        $rhid = Module::query()->where('key', Module::KEY_RHID)->first();
        if ($rhid !== null) {
            $plan->modules()->syncWithoutDetaching([$rhid->id]);
        }

        Subscription::query()->firstOrCreate(
            ['company_id' => $company->id, 'plan_id' => $plan->id],
            [
                'starts_at' => now(),
                'ends_at' => now()->addYear(),
                'status' => 'active',
            ],
        );
    }

    private function ensureWeekdaySchedule(Company $company): void
    {
        $dias = [];
        foreach (PunchScheduleSettingsService::DAY_KEYS as $key) {
            $weekday = in_array($key, ['seg', 'ter', 'qua', 'qui', 'sex'], true);
            $dias[$key] = [
                'ativo' => $weekday,
                'entrada' => $weekday ? '08:00' : null,
                'saida_almoco' => $weekday ? '12:00' : null,
                'volta_almoco' => $weekday ? '13:00' : null,
                'saida' => $weekday ? '17:00' : null,
                'almoco2_inicio' => null,
                'almoco2_fim' => null,
                'trabalho2_entrada' => null,
                'trabalho2_saida' => null,
            ];
        }

        CompanyRhidScheduleSetting::query()->updateOrCreate(
            ['company_id' => $company->id],
            [
                'settings' => [
                    'segundo_trabalho' => false,
                    'segundo_almoco' => false,
                    'tolerancia_minutos' => 0,
                    'dias' => $dias,
                ],
            ],
        );
    }

    private function ensureClientUser(Company $company): void
    {
        $user = User::query()->firstOrCreate(
            ['email' => self::CLIENT_EMAIL],
            [
                'name' => 'RH QA Ponto',
                'password' => Hash::make('password'),
                'role' => UserRole::CompanyAdmin,
                'company_id' => $company->id,
                'email_verified_at' => now(),
                'password_set_at' => now(),
                'is_active' => true,
            ],
        );

        if ((int) $user->company_id !== (int) $company->id) {
            $user->company_id = $company->id;
            $user->role = UserRole::CompanyAdmin;
            $user->save();
        }

        $workspace = $user->workspaces()
            ->where('workspace_type', WorkspaceType::Company)
            ->where('company_id', $company->id)
            ->first();

        if ($workspace === null) {
            UserWorkspace::query()->create([
                'user_id' => $user->id,
                'workspace_type' => WorkspaceType::Company,
                'company_id' => $company->id,
                'role' => UserRole::CompanyAdmin,
                'is_owner' => false,
                'is_active' => true,
            ]);
            app(WorkspaceManager::class)->syncLegacyUserColumns($user);
        }
    }

    private function seedMonthEspelhos(Company $company): void
    {
        $month = Carbon::now()->startOfMonth();
        $weekdays = $this->weekdayDates($month->copy(), $month->copy()->endOfMonth());
        if ($weekdays === []) {
            return;
        }

        $atestadoDates = array_slice($weekdays, -3);
        $brunoDates = array_slice($weekdays, 0, max(1, (int) floor(count($weekdays) * 0.35)));
        $carlaWorkDates = array_values(array_diff($weekdays, $atestadoDates));

        RhidEspelhoImport::query()
            ->where('company_id', $company->id)
            ->where('guid', 'like', 'qa-ponto-visual-%')
            ->each(function (RhidEspelhoImport $import): void {
                $import->days()->delete();
                $import->delete();
            });

        $this->seedPerson($company, 910001, 'Ana Pontual', $weekdays, []);
        $this->seedPerson($company, 910002, 'Bruno Faltas', $brunoDates, []);
        $this->seedPerson($company, 910003, 'Carla Atestado', $carlaWorkDates, $atestadoDates);
    }

    /**
     * @param  list<string>  $workedDates
     * @param  list<string>  $atestadoDates
     */
    private function seedPerson(
        Company $company,
        int $idPerson,
        string $nome,
        array $workedDates,
        array $atestadoDates,
    ): void {
        $allDates = array_values(array_unique([...$workedDates, ...$atestadoDates]));
        sort($allDates);
        if ($allDates === []) {
            return;
        }

        $import = RhidEspelhoImport::query()->create([
            'company_id' => $company->id,
            'user_id' => null,
            'id_person' => $idPerson,
            'period_ini' => $allDates[0],
            'period_fim' => $allDates[array_key_last($allDates)],
            'guid' => 'qa-ponto-visual-'.$idPerson.'-'.$allDates[0],
            'storage_path' => 'rhid/qa-visual-'.$idPerson.'.pdf',
            'source' => 'seed',
            'parse_status' => 'ok',
            'parsed_at' => now(),
        ]);

        foreach ($workedDates as $refDate) {
            RhidEspelhoDay::query()->create([
                'import_id' => $import->id,
                'ref_date' => $refDate,
                'row_json' => [
                    'colaboradores' => [[
                        'nome' => $nome,
                        'id_person' => $idPerson,
                        'ent_1' => '08:00',
                        'sai_1' => '12:00',
                        'ent_2' => '13:00',
                        'sai_2' => '17:00',
                    ]],
                ],
            ]);
        }

        foreach ($atestadoDates as $refDate) {
            RhidEspelhoDay::query()->create([
                'import_id' => $import->id,
                'ref_date' => $refDate,
                'row_json' => [
                    'atestado' => true,
                    'colaboradores' => [[
                        'nome' => $nome,
                        'id_person' => $idPerson,
                        'atestado' => true,
                    ]],
                ],
            ]);
        }
    }

    /**
     * @return list<string>
     */
    private function weekdayDates(Carbon $start, Carbon $end): array
    {
        $dates = [];
        $cursor = $start->copy()->startOfDay();
        $last = $end->copy()->startOfDay();
        while ($cursor->lte($last)) {
            if ($cursor->isWeekday()) {
                $dates[] = $cursor->toDateString();
            }
            $cursor->addDay();
        }

        return $dates;
    }
}
