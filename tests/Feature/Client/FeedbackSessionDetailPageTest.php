<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Actions\SyncAdminUserPermissions;
use App\Enums\AdminPermissionModule;
use App\Enums\FeedbackSessionStatus;
use App\Enums\PermissionAction;
use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\FeedbackSession;
use App\Models\FeedbackSessionAnswer;
use App\Models\FeedbackTemplate;
use App\Models\User;
use App\Support\Feedback\FeedbackCompanyContext;
use App\Support\Feedback\FeedbackVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Support\CreatesFeedbackFixtures;
use Tests\TestCase;

/**
 * Detalhe do feedback redesenhado (só apresentação): a aba vem da URL (?aba=) no portal e no admin, a página
 * recebe o que as abas usam (seções com tipo e chave, respostas, assinaturas, gravação) e a visibilidade de
 * «Reflexão do líder» continua a mesma.
 */
class FeedbackSessionDetailPageTest extends TestCase
{
    use CreatesFeedbackFixtures;
    use RefreshDatabase;

    private Company $company;

    private User $admin;

    private User $leader;

    private CompanyEmployee $employee;

    private FeedbackSession $session;

    private FeedbackTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->company = $this->createFeedbackCompany();
        $this->admin = User::factory()->companyAdmin($this->company->id)->create();
        $this->leader = User::factory()->companyUser($this->company->id)->create();
        $this->employee = $this->createFeedbackEmployee($this->company, $this->leader, ['name' => 'Larissa']);
        $this->template = $this->seedFeedbackTemplate();
        $this->session = $this->createFeedbackSession($this->company, $this->leader, $this->employee);
    }

    public function test_every_tab_in_the_url_opens_the_same_page_with_what_the_tabs_need(): void
    {
        foreach (['respostas', 'gravacao', 'assinaturas', 'desconhecida'] as $tab) {
            $this->actingAs($this->admin)
                ->get(route('client.feedbacks.sessions.show', $this->session).'?aba='.$tab)
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $page) => $page
                    ->component('Client/Feedbacks/Sessions/Show')
                    ->has('session.template.sections.0.section_type')
                    ->has('session.template.sections.0.key')
                    ->has('session.answers')
                    ->has('session.signatures')
                    ->where('session.employee_profile_id', $this->employee->id)
                    ->has('recordingAccess.rewindBlockedReason')
                    ->where('recording', null));
        }
    }

    public function test_admin_with_the_selected_company_opens_the_tab_from_the_url(): void
    {
        $owner = User::factory()->superAdmin()->create(['is_owner' => true]);

        $this->actingAs($owner)->withSession([FeedbackCompanyContext::SESSION_KEY => $this->company->id])
            ->get(route('admin.feedbacks.sessions.show', $this->session).'?aba=assinaturas')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Client/Feedbacks/Sessions/Show')->has('session.signatures'));

        $viewer = User::factory()->superAdmin()->create(['is_owner' => false]);
        app(SyncAdminUserPermissions::class)->execute($viewer->talentsWorkspace(), [
            ['module' => AdminPermissionModule::Feedbacks->value, 'action' => PermissionAction::View->value],
        ]);
        $this->actingAs($viewer)->withSession([FeedbackCompanyContext::SESSION_KEY => $this->createFeedbackCompany(['name' => 'Outra'])->id])
            ->get(route('admin.feedbacks.sessions.show', $this->session).'?aba=gravacao')
            ->assertForbidden();
    }

    public function test_leader_self_sections_stay_hidden_from_the_leader_in_every_tab(): void
    {
        $leaderSelf = $this->template->sections()->where('audience', FeedbackVisibility::AUDIENCE_LEADER_SELF)->with('questions')->first();
        $this->assertNotNull($leaderSelf, 'o modelo padrão tem «Reflexão do líder»');
        FeedbackSessionAnswer::query()->create([
            'feedback_session_id' => $this->session->id,
            'feedback_template_question_id' => $leaderSelf->questions->first()->id,
            'value_text' => 'Reflexão reservada.',
        ]);

        $sectionIds = fn (AssertableInertia $page) => collect($page->toArray()['props']['session']['template']['sections'])->pluck('id');

        $this->actingAs($this->leader)->get(route('client.feedbacks.sessions.show', $this->session).'?aba=respostas')
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) use ($sectionIds, $leaderSelf) {
                $this->assertNotContains($leaderSelf->id, $sectionIds($page));
                $this->assertArrayNotHasKey($leaderSelf->questions->first()->id, $page->toArray()['props']['session']['answers']);
            });

        $this->actingAs($this->admin)->get(route('client.feedbacks.sessions.show', $this->session))
            ->assertInertia(fn (AssertableInertia $page) => $this->assertContains($leaderSelf->id, $sectionIds($page)));
    }

    public function test_signature_state_reaches_the_page_for_the_journey_and_the_primary_action(): void
    {
        $this->session->update(['status' => FeedbackSessionStatus::AwaitingSignatures]);
        $this->session->signatures()->create([
            'role' => 'employee',
            'signer_name' => 'Larissa',
            'signer_email' => 'larissa@example.test',
            'token' => 'token-de-teste',
            'sent_at' => now(),
        ]);

        $this->actingAs($this->admin)->get(route('client.feedbacks.sessions.show', $this->session).'?aba=assinaturas')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('session.status', 'awaiting_signatures')
                ->has('session.signatures', 1)
                ->where('session.signatures.0.signed_at', null)
                ->has('session.signatures.0.sign_url'));
    }
}
