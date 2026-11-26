<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Actions\SyncAdminUserPermissions;
use App\Enums\AdminPermissionModule;
use App\Enums\PermissionAction;
use App\Models\Company;
use App\Models\FeedbackTemplate;
use App\Models\User;
use App\Support\Feedback\FeedbackCompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Support\CreatesFeedbackFixtures;
use Tests\TestCase;

/**
 * Edição de modelos de feedback em /admin/feedbacks/templates (empresa selecionada) e no portal.
 * Modelo criado ou duplicado no admin é universal (company_id nulo), mesmo com empresa selecionada.
 * Modelo já usado em feedbacks: estrutura travada (as respostas apontam para as perguntas), com o motivo na tela.
 */
class FeedbackTemplateAdminTest extends TestCase
{
    use CreatesFeedbackFixtures;
    use RefreshDatabase;

    private Company $company;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->company = $this->createFeedbackCompany();
        $this->owner = User::factory()->superAdmin()->create(['is_owner' => true]);
    }

    private function asOwner(): static
    {
        return $this->actingAs($this->owner)
            ->withSession([FeedbackCompanyContext::SESSION_KEY => $this->company->id]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $title = 'Alinhamento trimestral', string $question = 'O que foi entregue?'): array
    {
        return [
            'title' => $title,
            'description' => 'Uso interno',
            'is_active' => true,
            'sections' => [[
                'title' => 'Resultados',
                'description' => '',
                'questions' => [
                    ['body' => $question, 'question_type' => 'textarea', 'is_required' => true, 'options' => []],
                    ['body' => 'Como avalia o período?', 'question_type' => 'single_choice', 'is_required' => false, 'options' => ['Bom', 'Regular']],
                ],
            ]],
        ];
    }

    private function createTemplate(): FeedbackTemplate
    {
        $this->asOwner()
            ->post(route('admin.feedbacks.templates.store'), $this->payload())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.feedbacks.templates.index'))
            ->assertSessionHas('success', 'Modelo de feedback criado.');

        return FeedbackTemplate::query()->whereNull('company_id')->where('title', 'Alinhamento trimestral')->firstOrFail();
    }

    private function useTemplate(FeedbackTemplate $template, int $times = 1): void
    {
        $employee = $this->createFeedbackEmployee($this->company, $this->owner);
        for ($i = 0; $i < $times; $i++) {
            $this->createFeedbackSession($this->company, $this->owner, $employee, ['feedback_template_id' => $template->id]);
        }
    }

    public function test_in_use_template_edit_screen_receives_the_lock_reason(): void
    {
        $template = $this->createTemplate();
        $this->useTemplate($template, 2);

        $this->asOwner()
            ->get(route('admin.feedbacks.templates.edit', $template))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Client/Feedbacks/Templates/Form')
                ->where('structureLocked', true)
                ->where('sessionsCount', 2)
                ->where('template.sections.0.questions.0.body', 'O que foi entregue?'));
    }

    public function test_unused_template_is_fully_editable_and_update_persists_the_structure(): void
    {
        $template = $this->createTemplate();

        $this->asOwner()
            ->get(route('admin.feedbacks.templates.edit', $template))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('structureLocked', false)
                ->where('sessionsCount', 0));

        $payload = $this->payload('Alinhamento semestral', 'Quais entregas marcaram o semestre?');
        $payload['sections'][] = [
            'title' => 'Próximos passos',
            'description' => 'Combine metas.',
            'questions' => [['body' => 'Metas', 'question_type' => 'bullet_list', 'is_required' => false, 'options' => []]],
        ];

        $this->asOwner()
            ->put(route('admin.feedbacks.templates.update', $template), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.feedbacks.templates.index'))
            ->assertSessionHas('success', 'Modelo de feedback atualizado.');

        $template->refresh()->load('sections.questions');
        $this->assertSame('Alinhamento semestral', $template->title);
        $this->assertCount(2, $template->sections);
        $this->assertSame('Quais entregas marcaram o semestre?', $template->sections[0]->questions[0]->body);
        $this->assertSame('Próximos passos', $template->sections[1]->title);
    }

    public function test_locked_template_saves_name_and_keeps_questions(): void
    {
        $template = $this->createTemplate();
        $this->useTemplate($template);

        $this->asOwner()
            ->put(route('admin.feedbacks.templates.update', $template), [
                'title' => 'Nome novo',
                'description' => null,
                'is_active' => false,
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Modelo atualizado. As perguntas não mudam porque o modelo já foi usado em feedbacks.');

        $template->refresh()->load('sections.questions');
        $this->assertSame('Nome novo', $template->title);
        $this->assertFalse((bool) $template->is_active);
        $this->assertSame('O que foi entregue?', $template->sections[0]->questions[0]->body);
    }

    public function test_blank_extra_option_is_dropped_instead_of_blocking_the_save(): void
    {
        $payload = $this->payload();
        $payload['sections'][0]['questions'][1]['options'] = ['Bom', 'Regular', ''];

        $this->asOwner()
            ->post(route('admin.feedbacks.templates.store'), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.feedbacks.templates.index'));

        $question = FeedbackTemplate::query()->whereNull('company_id')->where('title', 'Alinhamento trimestral')->firstOrFail()
            ->sections()->firstOrFail()->questions()->where('question_type', 'single_choice')->firstOrFail();
        $this->assertSame(['Bom', 'Regular'], collect($question->options)->pluck('label')->all());
    }

    public function test_validation_errors_come_per_field_in_portuguese(): void
    {
        $payload = $this->payload();
        $payload['title'] = '';
        $payload['sections'][0]['title'] = '';
        $payload['sections'][0]['questions'][0]['body'] = '';
        $payload['sections'][0]['questions'][1]['options'] = ['Só uma', ''];

        $this->asOwner()
            ->post(route('admin.feedbacks.templates.store'), $payload)
            ->assertSessionHasErrors([
                'title' => 'Informe o nome do modelo.',
                'sections.0.title' => 'Informe o título da seção.',
                'sections.0.questions.0.body' => 'Informe o texto da pergunta.',
            ]);

        $payload = $this->payload();
        $payload['sections'][0]['questions'][1]['options'] = ['Só uma', ''];
        $this->asOwner()
            ->post(route('admin.feedbacks.templates.store'), $payload)
            ->assertSessionHasErrors(['sections.0.questions.1.options' => 'Informe pelo menos duas opções.']);

        $this->assertDatabaseMissing('feedback_templates', ['title' => 'Alinhamento trimestral']);
    }

    public function test_admin_with_view_only_permission_cannot_change_templates(): void
    {
        $template = $this->createTemplate();
        $viewer = User::factory()->superAdmin()->create(['is_owner' => false]);
        app(SyncAdminUserPermissions::class)->execute($viewer->talentsWorkspace(), [
            ['module' => AdminPermissionModule::Feedbacks->value, 'action' => PermissionAction::View->value],
        ]);

        $as = fn () => $this->actingAs($viewer)->withSession([FeedbackCompanyContext::SESSION_KEY => $this->company->id]);

        $as()->get(route('admin.feedbacks.templates.index'))->assertOk();
        $as()->post(route('admin.feedbacks.templates.store'), $this->payload('Invasor'))->assertForbidden();
        $as()->put(route('admin.feedbacks.templates.update', $template), $this->payload('Invadido'))->assertForbidden();
        $as()->delete(route('admin.feedbacks.templates.destroy', $template))->assertForbidden();

        $this->assertSame('Alinhamento trimestral', $template->fresh()->title);
        $this->assertSame(0, FeedbackTemplate::query()->where('title', 'Invasor')->count());
    }

    public function test_duplicating_the_talents_template_creates_a_faithful_editable_universal_copy(): void
    {
        $talents = $this->seedFeedbackTemplate();
        $this->useTemplate($talents); // o original está em uso; a cópia nasce sem trava

        $response = $this->asOwner()->post(route('admin.feedbacks.templates.duplicate', $talents));

        $copy = FeedbackTemplate::query()->where('title', $talents->title.' (cópia)')->firstOrFail();
        $this->assertNull($copy->company_id, 'Duplicar no admin gera modelo universal.');
        $response->assertRedirect(route('admin.feedbacks.templates.edit', $copy))
            ->assertSessionHas('success', 'Cópia criada. Ajuste o que precisar e salve.');

        $this->assertSame($talents->title.' (cópia)', $copy->title);
        $this->assertTrue((bool) $copy->is_active);
        $this->assertFalse((bool) $copy->is_default);
        $this->assertSame($this->owner->id, (int) $copy->created_by);
        $this->assertSame($this->structureSignature($talents), $this->structureSignature($copy));
        $this->assertNull($talents->fresh()->company_id, 'O original da Talents não muda.');

        // A cópia abre editável e, salva sem mudanças pelo formulário, mantém público, tipo, chaves e valores.
        $page = $this->asOwner()->get(route('admin.feedbacks.templates.edit', $copy))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('structureLocked', false)
                ->where('readOnly', false))
            ->viewData('page');

        $this->asOwner()
            ->put(route('admin.feedbacks.templates.update', $copy), [
                'title' => $copy->title,
                'description' => $copy->description,
                'is_active' => true,
                'sections' => $page['props']['template']['sections'],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($this->structureSignature($talents), $this->structureSignature($copy->fresh()));
    }

    public function test_duplicating_a_locked_company_template_and_other_company_templates(): void
    {
        $template = $this->createTemplate();
        $this->useTemplate($template);

        $this->asOwner()->post(route('admin.feedbacks.templates.duplicate', $template))->assertRedirect();
        $copy = FeedbackTemplate::query()->where('title', 'Alinhamento trimestral (cópia)')->firstOrFail();
        $this->assertNull($copy->company_id, 'Duplicar no admin gera modelo universal.');
        $this->assertSame($this->structureSignature($template), $this->structureSignature($copy));

        // Nome longo e acentuado: corta por caractere, até 255 no total, sem quebrar acento.
        $long = FeedbackTemplate::query()->create([
            'company_id' => $this->company->id, 'title' => str_repeat('ç', 255), 'is_active' => true,
        ]);
        $long->sections()->create(['key' => 's1', 'title' => 'S', 'section_type' => 'questions', 'audience' => 'both', 'sort_order' => 1])
            ->questions()->create(['key' => 'q1', 'body' => 'P', 'question_type' => 'textarea', 'sort_order' => 1]);
        $this->asOwner()->post(route('admin.feedbacks.templates.duplicate', $long))->assertRedirect();
        $longCopy = FeedbackTemplate::query()->where('title', 'like', '%(cópia)')->latest('id')->firstOrFail();
        $this->assertSame(str_repeat('ç', 247).' (cópia)', $longCopy->title);
        $this->assertSame(255, mb_strlen($longCopy->title));

        // Modelo de outra empresa não existe para esta.
        $other = $this->createFeedbackCompany(['name' => 'Outra']);
        $foreign = FeedbackTemplate::query()->create(['company_id' => $other->id, 'title' => 'Alheio', 'is_active' => true]);
        $this->asOwner()->post(route('admin.feedbacks.templates.duplicate', $foreign))->assertNotFound();
    }

    public function test_view_only_admin_opens_the_template_read_only_and_cannot_duplicate(): void
    {
        $template = $this->createTemplate();
        $viewer = User::factory()->superAdmin()->create(['is_owner' => false]);
        app(SyncAdminUserPermissions::class)->execute($viewer->talentsWorkspace(), [
            ['module' => AdminPermissionModule::Feedbacks->value, 'action' => PermissionAction::View->value],
        ]);
        $as = fn () => $this->actingAs($viewer)->withSession([FeedbackCompanyContext::SESSION_KEY => $this->company->id]);

        $as()->get(route('admin.feedbacks.templates.edit', $template))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('readOnly', true)
                ->where('permissions', ['create' => false, 'edit' => false, 'delete' => false]));
        $as()->get(route('admin.feedbacks.templates.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('permissions.edit', false));
        $as()->post(route('admin.feedbacks.templates.duplicate', $template))->assertForbidden();

        // Owner tem tudo; no portal, o administrador da empresa também (nos modelos da própria empresa).
        $this->asOwner()->get(route('admin.feedbacks.templates.edit', $template))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('readOnly', false));
        $own = FeedbackTemplate::query()->create(['company_id' => $this->company->id, 'title' => 'Da empresa', 'is_active' => true]);
        $this->actingAs(User::factory()->companyAdmin($this->company->id)->create())
            ->get(route('client.feedbacks.templates.edit', $own))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('readOnly', false)
                ->where('permissions', ['create' => true, 'edit' => true, 'delete' => true]));
    }

    public function test_locked_template_refuses_structure_changes_explicitly(): void
    {
        $template = $this->createTemplate();
        $this->useTemplate($template);

        $this->asOwner()
            ->put(route('admin.feedbacks.templates.update', $template), $this->payload('Outro nome', 'Pergunta trocada'))
            ->assertSessionHasErrors('sections');

        $template->refresh()->load('sections.questions');
        $this->assertSame('Alinhamento trimestral', $template->title);
        $this->assertSame('O que foi entregue?', $template->sections[0]->questions[0]->body);
    }

    public function test_template_needs_at_least_one_section_with_questions(): void
    {
        $this->asOwner()
            ->post(route('admin.feedbacks.templates.store'), [
                'title' => 'Só orientação',
                'sections' => [['title' => 'Boas-vindas', 'section_type' => 'intro', 'description' => 'Leia antes.', 'questions' => []]],
            ])
            ->assertSessionHasErrors(['sections' => 'O modelo precisa de pelo menos uma seção com perguntas.']);

        $template = $this->createTemplate();
        $payload = $this->payload();
        $payload['sections'] = [];
        $this->asOwner()
            ->put(route('admin.feedbacks.templates.update', $template), $payload)
            ->assertSessionHasErrors(['sections' => 'O modelo precisa de pelo menos uma seção com perguntas.']);
        $this->assertSame(1, $template->sections()->count());
    }

    /**
     * Estrutura comparável: seções (chave, tipo, público, título) e perguntas (chave, tipo, valores das opções).
     *
     * @return list<array<string, mixed>>
     */
    private function structureSignature(FeedbackTemplate $template): array
    {
        $raw = fn ($v) => $v instanceof \BackedEnum ? $v->value : $v;

        return $template->sections()->with('questions')->get()->map(fn ($section) => [
            'key' => $section->key,
            'type' => $raw($section->section_type),
            'audience' => $raw($section->audience),
            'title' => $section->title,
            'questions' => $section->questions->map(fn ($question) => [
                'key' => $question->key,
                'type' => $raw($question->question_type),
                'body' => $question->body,
                'required' => (bool) $question->is_required,
                'options' => $question->options,
            ])->all(),
        ])->all();
    }

    public function test_company_user_cannot_open_or_change_templates_in_the_portal(): void
    {
        $template = $this->createTemplate();
        $user = User::factory()->companyUser($this->company->id)->create();

        $this->actingAs($user)->get(route('client.feedbacks.templates.edit', $template))->assertForbidden();
        $this->actingAs($user)->put(route('client.feedbacks.templates.update', $template), $this->payload('Invadido'))->assertForbidden();
        $this->assertSame('Alinhamento trimestral', $template->fresh()->title);
    }
}
