<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AdminPermissionModule;
use App\Enums\CompanyNoticeAudience;
use App\Enums\CompanyNoticeEventKind;
use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Mail\Nr1NoticeMail;
use App\Models\AdminUserPermission;
use App\Models\CompanyNotice;
use App\Models\MailSetting;
use App\Models\Nr1Opportunity;
use App\Models\Nr1ServiceMatch;
use App\Models\Survey;
use App\Models\User;
use App\Models\UserPermission;
use App\Services\Nr1\Nr1OpportunityService;
use App\Services\Psychosocial\ReviewRoundService;
use App\Support\Notices\NoticeDestinationUrl;
use App\Support\Notices\UnreadNoticeCounter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\CreatesSurveyFixtures;
use Tests\Support\CreatesV11Campaign;
use Tests\TestCase;

/**
 * Avisos do NR-1 no sistema de Avisos existente: «nova oportunidade» (Comercial), «nova solicitação de proposta ou
 * conversa» (Talents) e «relatório publicado» (empresa), com e-mail só quando há SMTP configurado e sempre respeitando as
 * permissões de cada perfil.
 */
class Nr1V11NoticesTest extends TestCase
{
    use CreatesSurveyFixtures;
    use CreatesV11Campaign;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Cache::flush();
    }

    /**
     * @return object{company: mixed, survey: Survey, admin: User, talents: User}
     */
    private function campaign(bool $published = true): object
    {
        $campaign = $this->v11Campaign(true);
        $campaign->survey->forceFill(['requires_publication' => true, 'published_at' => $published ? now() : null])->save();

        return (object) [
            'company' => $campaign->company,
            'survey' => $campaign->survey->fresh(),
            'admin' => User::factory()->companyAdmin($campaign->company->id)->create(['name' => 'Ana Admin']),
            'talents' => User::factory()->talentsOwner()->create(['name' => 'Talents Owner']),
        ];
    }

    private function adminWith(AdminPermissionModule ...$modules): User
    {
        $user = User::factory()->superAdmin()->create(['is_owner' => false]);
        foreach ($modules as $module) {
            AdminUserPermission::query()->create([
                'user_workspace_id' => $user->workspaces()->firstOrFail()->id,
                'module' => $module,
                'action' => PermissionAction::View,
            ]);
        }

        return $user;
    }

    private function smtp(): void
    {
        MailSetting::query()->create([
            'host' => 'smtp.example.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'user',
            'password' => 'secret-password',
            'from_address' => 'avisos@example.com',
            'from_name' => 'Talents',
            'is_enabled' => true,
        ]);
    }

    /** @return list<string> */
    private function kinds(?CompanyNoticeAudience $audience = null): array
    {
        return CompanyNotice::query()
            ->when($audience, fn ($q) => $q->where('audience', $audience->value))
            ->orderBy('id')->get()->map(fn (CompanyNotice $n) => $n->event_kind?->value)->all();
    }

    private function request(object $c, string $modality = 'online', string $audience = 'time'): Nr1Opportunity
    {
        return app(Nr1OpportunityService::class)->requestTraining($c->survey, $c->admin, $modality, $audience);
    }

    public function test_a_proposal_request_notifies_commercial_and_talents_once(): void
    {
        $c = $this->campaign();
        $opportunity = $this->request($c);

        $notices = CompanyNotice::query()->orderBy('id')->get();
        $this->assertSame(['nr1_opportunity_created', 'nr1_proposal_requested'], $notices->map(fn ($n) => $n->event_kind->value)->all());
        foreach ($notices as $notice) {
            $this->assertSame(CompanyNoticeAudience::Talents, $notice->audience);
            $this->assertNull($notice->company_id);
            $this->assertSame('nr1_opportunity', $notice->source_type);
            $this->assertSame($opportunity->id, (int) $notice->source_id);
            $this->assertStringContainsString($c->company->name, $notice->body);
            $this->assertStringContainsString('treinamento online para Time', $notice->body);
        }
        $this->assertStringContainsString('Ana Admin', $notices[1]->body);

        // O pedido duplicado é recusado e não gera aviso novo.
        try {
            $this->request($c);
        } catch (\Throwable) {
        }
        $this->assertCount(2, CompanyNotice::query()->get());
    }

    public function test_an_automatic_suggestion_notifies_only_commercial_never_a_declared_request(): void
    {
        $c = $this->campaign();

        $opportunity = app(Nr1OpportunityService::class)->openSolution($c->survey, Nr1Opportunity::ORIGIN_SOLUTION_SUGGESTION, 'Clima e escuta', 'Dimensão em atenção.', null);

        $this->assertNotNull($opportunity);
        $this->assertSame(['nr1_opportunity_created'], $this->kinds());
    }

    public function test_a_solution_conversation_request_notifies_both_and_a_converted_suggestion_notifies_talents(): void
    {
        $c = $this->campaign();
        // As regras padrão já existem (migration); a Talents revisa uma para a empresa poder pedir conversa.
        $match = Nr1ServiceMatch::query()->where('factor_label', 'Demandas e Sobrecarga')->firstOrFail();
        $match->forceFill(['reviewed_at' => now(), 'reviewed_by' => $c->talents->id])->save();
        $service = $match->service_name;

        // Conversa pedida direto: oportunidade nova + solicitação.
        $request = app(Nr1OpportunityService::class)->openSolution($c->survey, Nr1Opportunity::ORIGIN_SOLUTION_REQUEST, 'Outra solução', 'Pedido.', $c->admin);
        $this->assertNotNull($request);
        $this->assertSame(['nr1_opportunity_created', 'nr1_proposal_requested'], $this->kinds());

        // Sugestão que a empresa converte em pedido: só «nova solicitação» (a oportunidade já existia).
        CompanyNotice::query()->delete();
        app(Nr1OpportunityService::class)->openSolution($c->survey, Nr1Opportunity::ORIGIN_SOLUTION_SUGGESTION, $service, 'Dimensão em atenção.', null);
        CompanyNotice::query()->delete();
        app(\App\Services\Nr1\Nr1SolutionService::class)->requestConversation($c->survey, $c->admin, $match->id, null);
        $this->assertSame(['nr1_proposal_requested'], $this->kinds());
    }

    public function test_publishing_the_reports_notifies_the_company_only(): void
    {
        $c = $this->campaign(published: false);
        $talents = $c->talents;
        $this->registration($c);
        $rounds = app(ReviewRoundService::class);
        $round = $rounds->publish($rounds->regenerate($rounds->start($c->survey, $talents), $talents), $talents);

        $notice = CompanyNotice::query()->where('event_kind', 'nr1_report_published')->sole();
        $this->assertSame(CompanyNoticeAudience::Company, $notice->audience);
        $this->assertSame($c->company->id, (int) $notice->company_id);
        $this->assertSame('psychosocial_review_round', $notice->source_type);
        $this->assertSame($round->id, (int) $notice->source_id);
        $this->assertStringContainsString($round->protocol, $notice->body);
    }

    private function registration(object $c): void
    {
        \App\Models\SurveyCampaignRegistration::query()->updateOrCreate(['survey_id' => $c->survey->id], [
            'company_id' => $c->survey->company_id,
            'legal_name_snapshot' => 'Empresa V11 Ltda',
            'cnpj_snapshot' => '11.222.333/0001-81',
            'unit_name' => 'Unidade Centro',
            'eligible_count' => 48,
            'eligible_source' => 'Cadastro de RH',
            'technical_responsible_name' => 'Carla Souza',
            'technical_responsible_role' => 'Psicóloga do trabalho',
            'technical_responsible_registration' => 'CRP 06/123456',
            'approving_organization_name' => 'Organização aprovadora',
        ]);
        app(\App\Services\PsychosocialMeasureService::class)->createManual($c->survey, [
            'factor_label' => 'Demandas e Sobrecarga',
            'evidence_basis' => \App\Models\PsychosocialMeasure::BASIS_QUESTIONNAIRE_INDICATION,
            'measure_description' => 'Revisar a distribuição das tarefas.',
            'responsible_text' => 'Gestão',
            'suggested_due_date' => now()->addDays(30)->toDateString(),
        ], $c->talents);
    }

    public function test_each_profile_sees_only_the_notices_it_may_open(): void
    {
        $c = $this->campaign(published: false);
        $this->request($c);
        $this->registration($c);
        $rounds = app(ReviewRoundService::class);
        $rounds->publish($rounds->regenerate($rounds->start($c->survey, $c->talents), $c->talents), $c->talents);

        $commercial = $this->adminWith(AdminPermissionModule::Nr1Oportunidades);
        $consultancy = $this->adminWith(AdminPermissionModule::Companies);
        $plain = $this->adminWith();

        // Publicar também gera as sugestões automáticas (uma «nova oportunidade» por regra de solução ativa).
        $adminSees = fn (User $user): array => collect($this->actingAs($user)->get(route('admin.notices.recent'))->assertOk()->json('notices'))->pluck('event_kind')->unique()->sort()->values()->all();
        $count = fn (string $kind): int => CompanyNotice::query()->where('event_kind', $kind)->count();
        $opportunities = $count('nr1_opportunity_created');
        $this->assertGreaterThanOrEqual(1, $opportunities);

        // «Relatório publicado» é aviso da empresa: não aparece no sino da Talents.
        $this->assertSame(['nr1_opportunity_created', 'nr1_proposal_requested'], $adminSees($c->talents), 'Proprietário: oportunidade e solicitação.');
        $this->assertSame(['nr1_opportunity_created'], $adminSees($commercial), 'Comercial: oportunidade, nunca a solicitação.');
        $this->assertSame(['nr1_proposal_requested'], $adminSees($consultancy), 'Consultoria (empresas): a solicitação, nunca a oportunidade.');
        $this->assertSame([], $adminSees($plain), 'Sem permissão: nenhum aviso do NR-1.');

        // Contador e página de avisos seguem a mesma regra.
        $counter = app(UnreadNoticeCounter::class);
        Cache::flush();
        $this->assertSame($opportunities + 1, $counter->forUser($c->talents), 'Proprietário: oportunidades e a solicitação.');
        Cache::flush();
        $this->assertSame($opportunities, $counter->forUser($commercial));
        Cache::flush();
        $this->assertSame(0, $counter->forUser($plain));
        // A página de avisos (módulo Calendário estratégico) aplica a mesma regra.
        $this->actingAs($plain)->get(route('admin.notices.index'))->assertForbidden();
        $this->actingAs($c->talents)->get(route('admin.notices.index'))->assertInertia(fn ($page) => $page->has('notices.data', $opportunities + 1));

        // Abrir um aviso restrito sem permissão: 404.
        $hidden = CompanyNotice::query()->where('event_kind', 'nr1_opportunity_created')->first();
        $this->actingAs($plain)->post(route('admin.notices.mark-read', $hidden->id))->assertNotFound();
        $this->actingAs($consultancy)->post(route('admin.notices.mark-read', $hidden->id))->assertNotFound();
        $this->actingAs($commercial)->post(route('admin.notices.mark-read', $hidden->id))->assertRedirect();
    }

    public function test_the_company_sees_the_report_notice_only_with_reports_access_and_never_talents_notices(): void
    {
        $c = $this->campaign(published: false);
        $this->request($c);
        $this->registration($c);
        $rounds = app(ReviewRoundService::class);
        $rounds->publish($rounds->regenerate($rounds->start($c->survey, $c->talents), $c->talents), $c->talents);

        $withReports = User::factory()->companyUser($c->company->id)->create();
        $without = User::factory()->companyUser($c->company->id)->create();
        UserPermission::query()->create(['user_workspace_id' => $withReports->workspaces()->firstOrFail()->id, 'module' => PermissionModule::Relatorios->value, 'action' => PermissionAction::View->value]);
        $other = $this->v11Campaign(false);
        $outsider = User::factory()->companyAdmin($other->company->id)->create();

        $seen = fn (User $user): array => collect($this->actingAs($user)->get(route('client.notices.recent'))->assertOk()->json('notices'))->pluck('event_kind')->all();

        $this->assertSame(['nr1_report_published'], $seen($c->admin), 'Administrador da empresa: só o relatório (nunca avisos da Talents).');
        $this->assertSame(['nr1_report_published'], $seen($withReports));
        $this->assertSame([], $seen($without), 'Sem acesso a Relatórios: não vê o aviso.');
        $this->assertSame([], $seen($outsider), 'Outra empresa: nada.');

        Cache::flush();
        $counter = app(UnreadNoticeCounter::class);
        $this->assertSame(1, $counter->forUser($c->admin));
        Cache::flush();
        $this->assertSame(0, $counter->forUser($without));
        $this->actingAs($without)->get(route('client.notices.index'))->assertInertia(fn ($page) => $page->has('notices.data', 0));
        $this->actingAs($c->admin)->get(route('client.notices.index'))->assertInertia(fn ($page) => $page->has('notices.data', 1));
    }

    public function test_emails_go_out_only_with_smtp_configured_and_only_to_who_may_see_the_notice(): void
    {
        Mail::fake();
        $c = $this->campaign(published: false);
        $commercial = $this->adminWith(AdminPermissionModule::Nr1Oportunidades);
        $plain = $this->adminWith();
        $withReports = User::factory()->companyUser($c->company->id)->create();
        $without = User::factory()->companyUser($c->company->id)->create();
        UserPermission::query()->create(['user_workspace_id' => $withReports->workspaces()->firstOrFail()->id, 'module' => PermissionModule::Relatorios->value, 'action' => PermissionAction::View->value]);

        // Sem SMTP configurado: o aviso existe no sino, mas nenhum e-mail sai.
        $this->request($c);
        $this->assertSame(2, CompanyNotice::query()->count());
        Mail::assertNothingSent();

        // Com SMTP configurado.
        $this->smtp();
        $this->request($c, 'presencial', 'lideres');
        Mail::assertSent(Nr1NoticeMail::class, fn ($mail) => $mail->hasTo($c->talents->email) && str_contains($mail->noticeTitle, 'Nova oportunidade'));
        Mail::assertSent(Nr1NoticeMail::class, fn ($mail) => $mail->hasTo($c->talents->email) && str_contains($mail->noticeTitle, 'Nova solicitação de proposta'));
        Mail::assertSent(Nr1NoticeMail::class, fn ($mail) => $mail->hasTo($commercial->email) && str_contains($mail->noticeTitle, 'Nova oportunidade'));
        Mail::assertNotSent(Nr1NoticeMail::class, fn ($mail) => $mail->hasTo($commercial->email) && str_contains($mail->noticeTitle, 'solicitação'));
        Mail::assertNotSent(Nr1NoticeMail::class, fn ($mail) => $mail->hasTo($plain->email));

        // Relatório publicado: e-mail à empresa, só a quem vê Relatórios.
        $this->registration($c);
        $rounds = app(ReviewRoundService::class);
        $rounds->publish($rounds->regenerate($rounds->start($c->survey, $c->talents), $c->talents), $c->talents);
        Mail::assertSent(Nr1NoticeMail::class, fn ($mail) => $mail->hasTo($c->admin->email) && $mail->noticeTitle === 'Relatório publicado');
        Mail::assertSent(Nr1NoticeMail::class, fn ($mail) => $mail->hasTo($withReports->email) && $mail->noticeTitle === 'Relatório publicado');
        Mail::assertNotSent(Nr1NoticeMail::class, fn ($mail) => $mail->hasTo($without->email));
        Mail::assertNotSent(Nr1NoticeMail::class, fn ($mail) => $mail->hasTo($c->talents->email) && $mail->noticeTitle === 'Relatório publicado');

        // O e-mail só leva o texto do aviso: nenhum dado de resposta.
        Mail::assertSent(Nr1NoticeMail::class, function ($mail) {
            $text = $mail->noticeBody;

            return ! str_contains(mb_strtolower($text), 'resposta de') && ! preg_match('/\d+,\d{2}\s*\/\s*5/', $text);
        });
    }

    public function test_a_failing_email_never_breaks_the_flow(): void
    {
        $c = $this->campaign();
        $this->smtp();
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp fora do ar'));

        $opportunity = $this->request($c);

        $this->assertSame('new', $opportunity->fresh()->status);
        $this->assertSame(2, CompanyNotice::query()->count(), 'O aviso do sino existe mesmo com o e-mail falhando.');
    }

    public function test_notices_open_the_right_destination(): void
    {
        $c = $this->campaign(published: false);
        $opportunity = $this->request($c);
        $this->registration($c);
        $rounds = app(ReviewRoundService::class);
        $round = $rounds->publish($rounds->regenerate($rounds->start($c->survey, $c->talents), $c->talents), $c->talents);

        $url = app(NoticeDestinationUrl::class);
        $created = CompanyNotice::query()->where('event_kind', 'nr1_opportunity_created')->where('source_id', $opportunity->id)->sole();
        $requested = CompanyNotice::query()->where('event_kind', 'nr1_proposal_requested')->where('source_id', $opportunity->id)->sole();
        $published = CompanyNotice::query()->where('event_kind', 'nr1_report_published')->sole();

        $this->assertSame(route('admin.nr1-opportunities.opportunity', $opportunity->id), $url->url($created, admin: true));
        $this->assertSame(route('admin.companies.surveys.campaign.show', [$c->company->id, $c->survey->id]), $url->url($requested, admin: true));
        $this->assertSame(route('client.surveys.documents.index', $c->survey->id), $url->url($published, admin: false));
        $this->assertSame($round->survey_id, $c->survey->id);
    }
}
