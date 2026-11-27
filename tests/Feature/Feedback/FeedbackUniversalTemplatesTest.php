<?php

declare(strict_types=1);

namespace Tests\Feature\Feedback;

use App\Models\Company;
use App\Models\FeedbackTemplate;
use App\Models\User;
use App\Support\Feedback\FeedbackCompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Tests\Support\CreatesFeedbackFixtures;
use Tests\TestCase;

/**
 * Modelos criados pela Talents no admin são universais (company_id nulo) e valem para todas as empresas;
 * modelos criados pelo RH no portal continuam privados da empresa.
 */
class FeedbackUniversalTemplatesTest extends TestCase
{
    use CreatesFeedbackFixtures;
    use RefreshDatabase;

    private const IN_USE_MESSAGE = 'Este modelo já foi usado em feedbacks e não pode ser excluído. Arquive-o para que não apareça em novos feedbacks.';

    private Company $companyA;

    private Company $companyB;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->companyA = $this->createFeedbackCompany(['name' => 'Empresa A']);
        $this->companyB = $this->createFeedbackCompany(['name' => 'Empresa B']);
        $this->owner = User::factory()->superAdmin()->create(['is_owner' => true]);
    }

    public function test_template_created_in_admin_with_company_a_selected_is_usable_in_company_b(): void
    {
        $this->asOwnerOn($this->companyA)
            ->post(route('admin.feedbacks.templates.store'), $this->payload('Modelo Talents'))
            ->assertSessionHasNoErrors();

        $template = FeedbackTemplate::query()->where('title', 'Modelo Talents')->sole();
        $this->assertNull($template->company_id);

        // Admin com a empresa B selecionada: aparece como universal, editável.
        $this->asOwnerOn($this->companyB)
            ->get(route('admin.feedbacks.templates.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('isAdminContext', true)
                ->where('templates', fn ($templates) => collect($templates)->contains(
                    fn ($t) => $t['id'] === $template->id && $t['is_universal'] === true && $t['manageable'] === true
                )));

        // Portal da empresa B: aparece como modelo da Talents (só usar e duplicar) e na criação de feedback.
        $rhB = User::factory()->companyAdmin($this->companyB->id)->create();
        $this->actingAs($rhB)
            ->get(route('client.feedbacks.templates.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('isAdminContext', false)
                ->where('templates', fn ($templates) => collect($templates)->contains(
                    fn ($t) => $t['id'] === $template->id && $t['is_universal'] === true && $t['manageable'] === false
                )));
        $this->actingAs($rhB)
            ->get(route('client.feedbacks.sessions.create'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('templates', fn ($templates) => collect($templates)->pluck('id')->contains($template->id)));

        $leaderB = User::factory()->companyUser($this->companyB->id)->create();
        $this->actingAs($rhB)
            ->post(route('client.feedbacks.sessions.store'), [
                'employee_name' => 'Bruno Lima',
                'leader_user_id' => $leaderB->id,
                'feedback_template_id' => $template->id,
                'scheduled_at' => now()->format('Y-m-d H:i'),
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('feedback_sessions', [
            'company_id' => $this->companyB->id,
            'feedback_template_id' => $template->id,
        ]);
    }

    public function test_duplicate_in_admin_is_universal_and_in_portal_belongs_to_the_company(): void
    {
        $source = $this->universalTemplate('Origem');

        $this->asOwnerOn($this->companyA)->post(route('admin.feedbacks.templates.duplicate', $source))->assertRedirect();
        $this->assertNull(FeedbackTemplate::query()->where('title', 'Origem (cópia)')->sole()->company_id);

        $rhA = User::factory()->companyAdmin($this->companyA->id)->create();
        $this->actingAs($rhA)->post(route('client.feedbacks.templates.duplicate', $source))->assertRedirect();
        $this->assertSame(
            $this->companyA->id,
            (int) FeedbackTemplate::query()->where('title', 'Origem (cópia)')->whereNotNull('company_id')->sole()->company_id,
        );
    }

    public function test_portal_template_of_company_a_is_invisible_to_company_b(): void
    {
        $rhA = User::factory()->companyAdmin($this->companyA->id)->create();
        $this->actingAs($rhA)
            ->post(route('client.feedbacks.templates.store'), $this->payload('Privado da A'))
            ->assertSessionHasNoErrors();

        $private = FeedbackTemplate::query()->where('title', 'Privado da A')->sole();
        $this->assertSame($this->companyA->id, (int) $private->company_id);

        $hasPrivate = fn ($templates) => collect($templates)->pluck('id')->contains($private->id);

        // Admin com a empresa B selecionada.
        $this->asOwnerOn($this->companyB)
            ->get(route('admin.feedbacks.templates.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('templates', fn ($t) => ! $hasPrivate($t)));
        $this->asOwnerOn($this->companyB)->get(route('admin.feedbacks.templates.edit', $private))->assertNotFound();

        // Portal da empresa B.
        $rhB = User::factory()->companyAdmin($this->companyB->id)->create();
        $this->actingAs($rhB)
            ->get(route('client.feedbacks.templates.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('templates', fn ($t) => ! $hasPrivate($t)));
        $this->actingAs($rhB)
            ->get(route('client.feedbacks.sessions.create'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('templates', fn ($t) => ! $hasPrivate($t)));
        $this->actingAs($rhB)->get(route('client.feedbacks.templates.edit', $private))->assertNotFound();

        // Admin com a empresa A selecionada: aparece como «Desta empresa» e a Talents edita.
        $this->asOwnerOn($this->companyA)
            ->get(route('admin.feedbacks.templates.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('templates', fn ($templates) => collect($templates)->contains(
                fn ($t) => $t['id'] === $private->id && $t['is_universal'] === false && $t['manageable'] === true
            )));
        $this->asOwnerOn($this->companyA)->get(route('admin.feedbacks.templates.edit', $private))->assertOk();
    }

    public function test_portal_cannot_edit_deactivate_or_delete_a_universal_template(): void
    {
        $universal = $this->universalTemplate('Universal');
        $rhA = User::factory()->companyAdmin($this->companyA->id)->create();

        $this->actingAs($rhA)->get(route('client.feedbacks.templates.edit', $universal))->assertNotFound();
        $this->actingAs($rhA)
            ->put(route('client.feedbacks.templates.update', $universal), ['title' => 'Invadido', 'is_active' => false])
            ->assertNotFound();
        $this->actingAs($rhA)->getJson(route('client.feedbacks.templates.deletion', $universal))->assertNotFound();
        $this->actingAs($rhA)->delete(route('client.feedbacks.templates.destroy', $universal))->assertNotFound();

        $universal->refresh();
        $this->assertSame('Universal', $universal->title);
        $this->assertTrue($universal->is_active);
        $this->assertNotSoftDeleted($universal);
    }

    public function test_universal_in_use_in_another_company_gets_a_new_version_and_refuses_deletion(): void
    {
        $universal = $this->universalTemplate('Universal');
        $leaderB = User::factory()->companyUser($this->companyB->id)->create();
        $session = $this->createFeedbackSession(
            $this->companyB,
            $leaderB,
            $this->createFeedbackEmployee($this->companyB, $leaderB),
            ['feedback_template_id' => $universal->id],
        );

        // Uso em qualquer empresa conta: vista com a empresa A selecionada, a edição anuncia a versão 2.
        $this->asOwnerOn($this->companyA)
            ->get(route('admin.feedbacks.templates.edit', $universal))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('version.used', true)
                ->where('version.sessions_count', 1)
                ->where('version.next_number', 2)
                ->where('template.is_universal', true));
        $this->asOwnerOn($this->companyA)
            ->put(route('admin.feedbacks.templates.update', $universal), $this->payload('Universal', 'Pergunta trocada'))
            ->assertSessionHasNoErrors();
        $this->assertSame(2, $universal->fresh()->currentVersion->number);
        $this->assertSame(1, $session->fresh()->templateVersion->number, 'O feedback da B continua na versão 1.');

        // Contagem: total geral e o da empresa selecionada.
        $this->asOwnerOn($this->companyA)
            ->get(route('admin.feedbacks.templates.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('templates', fn ($templates) => collect($templates)->contains(
                fn ($t) => $t['id'] === $universal->id && $t['sessions_count_total'] === 1 && $t['sessions_count_company'] === 0
            )));

        // Portal da B: só o uso da própria empresa, sem total geral.
        $this->actingAs(User::factory()->companyAdmin($this->companyB->id)->create())
            ->get(route('client.feedbacks.templates.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('templates', fn ($templates) => collect($templates)->contains(
                fn ($t) => $t['id'] === $universal->id && $t['sessions_count'] === 1 && $t['sessions_count_total'] === null
            )));

        // Exclusão recusada, inclusive com confirmação; o feedback da B continua.
        $this->asOwnerOn($this->companyA)
            ->getJson(route('admin.feedbacks.templates.deletion', $universal))
            ->assertOk()
            ->assertJson(['blocked' => true, 'blocked_message' => self::IN_USE_MESSAGE, 'sessions_count' => 1]);
        $this->asOwnerOn($this->companyA)
            ->delete(route('admin.feedbacks.templates.destroy', $universal), ['confirmed' => true, 'sessions_count' => 1])
            ->assertSessionHasErrors(['template' => self::IN_USE_MESSAGE]);

        $this->assertNotSoftDeleted($universal);
        $this->assertNotSoftDeleted($session);
        $this->assertSame($this->companyB->id, (int) $session->fresh()->company_id);
    }

    public function test_unused_universal_template_is_deleted(): void
    {
        $universal = $this->universalTemplate('Sem uso');

        $this->asOwnerOn($this->companyA)
            ->getJson(route('admin.feedbacks.templates.deletion', $universal))
            ->assertJson(['blocked' => false, 'sessions_count' => 0]);
        $this->asOwnerOn($this->companyA)
            ->delete(route('admin.feedbacks.templates.destroy', $universal))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Modelo de feedback excluído.');

        $this->assertSoftDeleted($universal);
    }

    public function test_conversion_moves_only_super_admin_templates_and_down_restores(): void
    {
        $this->assertTrue(Schema::hasColumn('feedback_templates', 'converted_from_company_id'));

        $rhA = User::factory()->companyAdmin($this->companyA->id)->create();
        $leaderA = User::factory()->companyUser($this->companyA->id)->create();

        $byTalents = $this->companyTemplate($this->companyA, 'Da Talents na A', $this->owner->id);
        $byRh = $this->companyTemplate($this->companyA, 'Do RH da A', $rhA->id);
        $byLeader = $this->companyTemplate($this->companyB, 'Do líder da B', $leaderA->id);
        $withoutAuthor = $this->companyTemplate($this->companyB, 'Sem autor', null);

        $session = $this->createFeedbackSession(
            $this->companyA,
            $leaderA,
            $this->createFeedbackEmployee($this->companyA, $leaderA),
            ['feedback_template_id' => $byTalents->id],
        );

        $migration = require database_path('migrations/2026_11_26_200000_make_talents_feedback_templates_universal.php');

        $this->assertSame(1, $migration->convertTalentsTemplates());

        $row = fn (FeedbackTemplate $t) => DB::table('feedback_templates')->where('id', $t->id)->first();
        $this->assertNull($row($byTalents)->company_id);
        $this->assertSame($this->companyA->id, (int) $row($byTalents)->converted_from_company_id);
        foreach ([[$byRh, $this->companyA], [$byLeader, $this->companyB], [$withoutAuthor, $this->companyB]] as [$template, $company]) {
            $this->assertSame($company->id, (int) $row($template)->company_id);
            $this->assertNull($row($template)->converted_from_company_id);
        }

        // O feedback já feito continua na empresa original.
        $this->assertSame($this->companyA->id, (int) $session->fresh()->company_id);

        $this->assertSame(1, $migration->restoreConvertedTemplates());
        $this->assertSame($this->companyA->id, (int) $row($byTalents)->company_id);
        $this->assertNull($row($byTalents)->converted_from_company_id);
        $this->assertSame($this->companyA->id, (int) $session->fresh()->company_id);
    }

    private function asOwnerOn(Company $company): static
    {
        return $this->actingAs($this->owner)->withSession([FeedbackCompanyContext::SESSION_KEY => $company->id]);
    }

    private function universalTemplate(string $title): FeedbackTemplate
    {
        return $this->companyTemplate(null, $title, $this->owner->id);
    }

    private function companyTemplate(?Company $company, string $title, ?int $createdBy): FeedbackTemplate
    {
        $template = FeedbackTemplate::query()->create([
            'company_id' => $company?->id,
            'title' => $title,
            'is_active' => true,
            'created_by' => $createdBy,
        ]);
        $template->sections()->create(['key' => 's1', 'title' => 'Seção', 'section_type' => 'questions', 'audience' => 'both', 'sort_order' => 1])
            ->questions()->create(['key' => 'q1', 'body' => 'Pergunta', 'question_type' => 'textarea', 'sort_order' => 1]);

        return $template;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $title, string $question = 'O que foi entregue?'): array
    {
        return [
            'title' => $title,
            'description' => null,
            'is_active' => true,
            'sections' => [[
                'title' => 'Resultados',
                'description' => '',
                'questions' => [['body' => $question, 'question_type' => 'textarea', 'is_required' => true, 'options' => []]],
            ]],
        ];
    }
}
