<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Feedback\MapFeedbackRecordingSpeakers;
use App\Actions\Feedback\StoreFeedbackRecording;
use App\Enums\FeedbackSessionStatus;
use App\Jobs\ProcessFeedbackRecordingJob;
use App\Models\AiSetting;
use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\FeedbackRecording;
use App\Models\FeedbackSession;
use App\Models\FeedbackTemplate;
use App\Models\User;
use App\Services\Feedback\PlaudTranscriptExtractor;
use App\Support\Feedback\Ai\FeedbackAiGate;
use Database\Seeders\FeedbackTemplateSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * SÓ AMBIENTE LOCAL. Monta uma demonstração de Feedbacks + Plaud com dados 100% FICTÍCIOS: empresa, líder,
 * administrador e colaboradora inventados, um feedback em andamento e a transcrição inventada (~45 min e ~7.600 palavras, com assuntos sensíveis para exercitar o filtro, com
 * falantes e marcação de tempo) já enviada, parada em «mapear falantes». Aprova o provedor de demonstração
 * (sem IA externa). Nenhum dado real é lido ou enviado. Fora de APP_ENV=local, recusa.
 */
class FeedbacksDemoAiCommand extends Command
{
    protected $signature = 'feedbacks:demo-ia';

    protected $description = 'Cria (só em local) um feedback fictício com transcrição do Plaud pronta para mapear falantes e processar com o provedor de demonstração';

    public const COMPANY_NAME = 'Empresa Demonstração Feedbacks (fictícia)';

    public const ADMIN_EMAIL = 'admin.feedbacks@demo.local';

    public const LEADER_EMAIL = 'lider.feedbacks@demo.local';

    public const PASSWORD = 'password';

    public function handle(PlaudTranscriptExtractor $extractor, StoreFeedbackRecording $store): int
    {
        if (! app()->environment('local')) {
            $this->error('Este comando só roda com APP_ENV=local. Nada foi alterado.');

            return self::FAILURE;
        }

        $company = Company::query()->firstOrCreate(
            ['name' => self::COMPANY_NAME],
            ['feedbacks_access' => true, 'is_active' => true],
        );
        $company->forceFill(['feedbacks_access' => true, 'is_active' => true])->save();

        $admin = $this->user(self::ADMIN_EMAIL, 'Helena Prado (fictícia)', $company, admin: true);
        $leader = $this->user(self::LEADER_EMAIL, 'Rafael Mendes (fictício)', $company, admin: false);

        $employee = CompanyEmployee::query()->firstOrCreate(
            ['company_id' => $company->id, 'email' => 'camila.duarte@demo.local'],
            ['name' => 'Camila Duarte (fictícia)', 'leader_user_id' => $leader->id, 'is_active' => true],
        );

        $template = FeedbackTemplate::query()->whereNull('company_id')->first();
        if ($template === null) {
            $this->call('db:seed', ['--class' => FeedbackTemplateSeeder::class, '--force' => true]);
            $template = FeedbackTemplate::query()->whereNull('company_id')->firstOrFail();
        }

        $session = FeedbackSession::query()->create([
            'company_id' => $company->id,
            'feedback_template_id' => $template->id,
            'company_employee_id' => $employee->id,
            'leader_user_id' => $leader->id,
            'created_by_user_id' => $leader->id,
            'title' => 'Alinhamento trimestral — demonstração fictícia',
            'status' => FeedbackSessionStatus::InProgress,
            'scheduled_at' => now(),
        ]);

        $path = database_path('seeders/data/feedback-demo-transcript.txt');
        $extracted = $extractor->extract($path, 'demonstracao-plaud.txt');

        $store->execute($session, $leader, [
            'format' => $extracted['format'],
            'filename' => 'demonstracao-plaud.txt',
            'sha256' => (string) hash_file('sha256', $path),
            'size' => (int) filesize($path),
            'text' => $extracted['text'],
            'has_speakers' => $extracted['has_speakers'],
            'has_timestamps' => $extracted['has_timestamps'],
        ]);

        $setting = AiSetting::current() ?? AiSetting::query()->create([
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'is_enabled' => false,
            'max_tokens' => 2000,
            'temperature' => 0.30,
        ]);
        $setting->forceFill([
            'feedback_approved_provider' => FeedbackAiGate::PROVIDER_DEMO,
            'feedback_approved_by' => null,
            'feedback_approved_at' => now(),
        ])->save();

        [$person, $duplicate] = $this->seedEvolutionDemo($company, $leader, $template, $extractor, $store);

        $this->info('Demonstração criada (dados fictícios; provedor de demonstração aprovado, sem IA externa).');
        $this->table(['O quê', 'Valor'], [
            ['Empresa', $company->name],
            ['Administrador', self::ADMIN_EMAIL.' / '.self::PASSWORD],
            ['Líder', self::LEADER_EMAIL.' / '.self::PASSWORD],
            ['Feedback', url('/client/feedbacks/sessions/'.$session->id)],
            ['Evolução (3 feedbacks prontos)', url('/client/feedbacks/employees/'.$person->id)],
            ['Cadastro duplicado (para unir)', $duplicate->name.' · '.url('/client/feedbacks/employees/duplicados')],
        ]);
        $this->line('A Talents (super admin) também vê em Admin → Feedbacks, escolhendo a empresa acima.');

        return self::SUCCESS;
    }

    /**
     * Pessoa fictícia com 3 feedbacks (trimestres diferentes) já processados pelo provedor de demonstração, e um
     * segundo cadastro dela (nome sem o sufixo e sem e-mail) com 1 feedback, para testar a união.
     *
     * @return array{0: CompanyEmployee, 1: CompanyEmployee}
     */
    private function seedEvolutionDemo(Company $company, User $leader, FeedbackTemplate $template, PlaudTranscriptExtractor $extractor, StoreFeedbackRecording $store): array
    {
        $person = CompanyEmployee::query()->firstOrCreate(
            ['company_id' => $company->id, 'email' => 'bruno.siqueira@demo.local'],
            ['name' => 'Bruno Siqueira (fictício)', 'leader_user_id' => $leader->id, 'is_active' => true],
        );

        foreach ([1 => 8, 2 => 5, 3 => 1] as $n => $monthsAgo) {
            $session = FeedbackSession::query()->create([
                'company_id' => $company->id,
                'feedback_template_id' => $template->id,
                'company_employee_id' => $person->id,
                'employee_name' => $person->name,
                'leader_user_id' => $leader->id,
                'created_by_user_id' => $leader->id,
                'title' => "Alinhamento {$n} — evolução fictícia",
                'status' => FeedbackSessionStatus::InProgress,
                'scheduled_at' => now()->subMonths($monthsAgo),
            ]);

            $path = database_path("seeders/data/feedback-demo-evolution-{$n}.txt");
            $extracted = $extractor->extract($path, "evolucao-{$n}.txt");
            $recording = $store->execute($session, $leader, [
                'format' => $extracted['format'],
                'filename' => "evolucao-{$n}.txt",
                'sha256' => (string) hash_file('sha256', $path),
                'size' => (int) filesize($path),
                'text' => $extracted['text'],
                'has_speakers' => $extracted['has_speakers'],
                'has_timestamps' => $extracted['has_timestamps'],
            ]);

            app(MapFeedbackRecordingSpeakers::class)->execute($recording, $leader, ['Speaker 1' => 'leader', 'Speaker 2' => 'employee']);
            $recording->forceFill(['processing_status' => FeedbackRecording::STATUS_PROCESSING, 'processing_mode' => FeedbackRecording::MODE_FULL])->save();
            app()->call([new ProcessFeedbackRecordingJob($recording->id, FeedbackRecording::MODE_FULL, $leader->id), 'handle']);
        }

        $duplicate = CompanyEmployee::query()
            ->where('company_id', $company->id)
            ->where('name', 'Bruno Siqueira')
            ->whereNull('merged_into_employee_id')
            ->first()
            ?? CompanyEmployee::query()->create(['company_id' => $company->id, 'name' => 'Bruno Siqueira', 'leader_user_id' => $leader->id, 'is_active' => true]);

        FeedbackSession::query()->create([
            'company_id' => $company->id,
            'feedback_template_id' => $template->id,
            'company_employee_id' => $duplicate->id,
            'employee_name' => 'Bruno Siqueira',
            'leader_user_id' => $leader->id,
            'created_by_user_id' => $leader->id,
            'title' => 'Alinhamento registrado no cadastro duplicado — fictício',
            'status' => FeedbackSessionStatus::InProgress,
            'scheduled_at' => now()->subMonths(5),
        ]);

        return [$person, $duplicate];
    }

    private function user(string $email, string $name, Company $company, bool $admin): User
    {
        $user = User::query()->where('email', $email)->first();
        if ($user !== null) {
            return $user;
        }

        $factory = $admin ? User::factory()->companyAdmin($company->id) : User::factory()->companyUser($company->id);

        return $factory->create(['name' => $name, 'email' => $email, 'password' => Hash::make(self::PASSWORD)]);
    }
}
