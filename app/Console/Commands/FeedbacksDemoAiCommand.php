<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Feedback\StoreFeedbackRecording;
use App\Enums\FeedbackSessionStatus;
use App\Models\AiSetting;
use App\Models\Company;
use App\Models\CompanyEmployee;
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

        $this->info('Demonstração criada (dados fictícios; provedor de demonstração aprovado, sem IA externa).');
        $this->table(['O quê', 'Valor'], [
            ['Empresa', $company->name],
            ['Administrador', self::ADMIN_EMAIL.' / '.self::PASSWORD],
            ['Líder', self::LEADER_EMAIL.' / '.self::PASSWORD],
            ['Feedback', url('/client/feedbacks/sessions/'.$session->id)],
        ]);
        $this->line('A Talents (super admin) também vê em Admin → Feedbacks, escolhendo a empresa acima.');

        return self::SUCCESS;
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
