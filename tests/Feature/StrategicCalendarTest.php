<?php

namespace Tests\Feature;

use App\Enums\StrategicCalendarItemKind;
use App\Enums\StrategicCalendarRecurrence;
use App\Enums\StrategicCalendarSource;
use App\Enums\StrategicCalendarViewPeriod;
use App\Support\StrategicCalendarOccurrenceExpander;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Models\Company;
use App\Models\Module;
use App\Models\Plan;
use App\Models\StrategicCalendarCompletion;
use App\Models\StrategicCalendarItem;
use App\Models\Subscription;
use App\Models\TaskBoard;
use App\Models\TaskCard;
use App\Models\TaskList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class StrategicCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function baseCompany(): Company
    {
        return Company::query()->create([
            'name' => 'Empresa teste',
            'cnpj' => '11.111.111/0001-11',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
        ]);
    }

    public function test_guest_cannot_access_client_strategic_calendar(): void
    {
        $this->get('/client/calendario-estrategico')->assertRedirect(route('login'));
    }

    public function test_client_forbidden_without_access(): void
    {
        $company = $this->baseCompany();
        $plan = Plan::query()->create([
            'name' => 'Plano X',
            'slug' => 'plano-x-'.Str::random(8),
            'price_monthly_cents' => 0,
            'is_active' => true,
        ]);
        $nr1 = Module::query()->firstOrCreate(
            ['key' => 'nr1'],
            ['name' => 'NR1', 'description' => 'x']
        );
        $plan->modules()->sync([$nr1->id]);

        Subscription::query()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'starts_at' => now(),
            'ends_at' => now()->addYear(),
            'status' => 'active',
        ]);

        $user = User::factory()->companyAdmin($company->id)->create();

        $this->actingAs($user)
            ->get('/client/calendario-estrategico')
            ->assertForbidden();
    }

    public function test_client_ok_when_company_forced_enabled(): void
    {
        $company = $this->baseCompany();
        $company->update(['strategic_calendar_access' => true]);

        $user = User::factory()->companyAdmin($company->id)->create();

        $this->actingAs($user)
            ->get('/client/calendario-estrategico')
            ->assertOk();
    }

    public function test_client_ok_when_plan_has_module(): void
    {
        $company = $this->baseCompany();
        $plan = Plan::query()->create([
            'name' => 'Plano Cal',
            'slug' => 'plano-cal-'.Str::random(8),
            'price_monthly_cents' => 0,
            'is_active' => true,
        ]);
        $cal = Module::query()->firstOrCreate(
            ['key' => Module::KEY_CALENDARIO_ESTRATEGICO],
            ['name' => 'Calendário', 'description' => 'x']
        );
        $plan->modules()->sync([$cal->id]);

        Subscription::query()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'starts_at' => now(),
            'ends_at' => now()->addYear(),
            'status' => 'active',
        ]);

        $user = User::factory()->companyAdmin($company->id)->create();

        $this->actingAs($user)
            ->get('/client/calendario-estrategico')
            ->assertOk();
    }

    public function test_super_admin_can_create_strategic_calendar_item(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->post('/admin/calendario-estrategico', [
                'title' => 'Revisão trimestral',
                'description' => 'Alinhar metas com liderança.',
                'kind' => StrategicCalendarItemKind::Event->value,
                'occurs_on' => '2026-06-15',
                'company_id' => null,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('strategic_calendar_items', [
            'title' => 'Revisão trimestral',
            'kind' => 'event',
            'source' => 'talents',
        ]);
    }

    public function test_company_admin_cannot_post_admin_strategic_calendar(): void
    {
        $company = $this->baseCompany();
        $user = User::factory()->companyAdmin($company->id)->create();

        $this->actingAs($user)
            ->post('/admin/calendario-estrategico', [
                'title' => 'X',
                'kind' => StrategicCalendarItemKind::Ritual->value,
                'occurs_on' => '2026-01-01',
            ])
            ->assertForbidden();
    }

    public function test_client_sees_only_applicable_items(): void
    {
        $company = $this->baseCompany();
        $company->update(['strategic_calendar_access' => true]);

        StrategicCalendarItem::query()->create([
            'title' => 'Global',
            'description' => null,
            'kind' => StrategicCalendarItemKind::Event,
            'occurs_on' => '2026-05-10',
            'company_id' => null,
        ]);

        $otherCompany = Company::query()->create([
            'name' => 'Outra empresa',
            'cnpj' => '22.222.222/0001-22',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
        ]);

        StrategicCalendarItem::query()->create([
            'title' => 'Só para outra',
            'description' => null,
            'kind' => StrategicCalendarItemKind::Event,
            'occurs_on' => '2026-05-11',
            'company_id' => $otherCompany->id,
        ]);

        $user = User::factory()->companyAdmin($company->id)->create();

        $response = $this->actingAs($user)
            ->get('/client/calendario-estrategico?year=2026&month=5');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Client/StrategicCalendar/Index')
            ->has('monthItems', 1)
            ->where('monthItems.0.title', 'Global'));
    }

    public function test_client_calendar_clamped_to_two_months_plan(): void
    {
        $company = $this->baseCompany();
        $plan = Plan::query()->create([
            'name' => 'Plano 2 meses',
            'slug' => 'plano-2m-'.Str::random(8),
            'price_monthly_cents' => 0,
            'is_active' => true,
            'strategic_calendar_view_period' => StrategicCalendarViewPeriod::TwoMonths,
        ]);
        $cal = Module::query()->firstOrCreate(
            ['key' => Module::KEY_CALENDARIO_ESTRATEGICO],
            ['name' => 'Calendário', 'description' => 'x']
        );
        $plan->modules()->sync([$cal->id]);

        Subscription::query()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'starts_at' => now(),
            'ends_at' => now()->addYear(),
            'status' => 'active',
        ]);

        $user = User::factory()->companyAdmin($company->id)->create();

        $now = now();
        $inRange = $now->copy()->addMonth()->endOfMonth();
        $outOfRange = $now->copy()->addMonths(3)->startOfMonth();

        StrategicCalendarItem::query()->create([
            'title' => 'Dentro do plano',
            'description' => null,
            'kind' => StrategicCalendarItemKind::Event,
            'occurs_on' => $inRange->toDateString(),
            'company_id' => null,
        ]);

        StrategicCalendarItem::query()->create([
            'title' => 'Fora do plano',
            'description' => null,
            'kind' => StrategicCalendarItemKind::Event,
            'occurs_on' => $outOfRange->toDateString(),
            'company_id' => null,
        ]);

        $this->actingAs($user)
            ->get('/client/calendario-estrategico?year=2020&month=1')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Client/StrategicCalendar/Index')
                ->where('calendarYear', $now->year)
                ->where('calendarMonth', $now->month)
                ->where('visiblePeriod.label', StrategicCalendarViewPeriod::TwoMonths->label())
                ->where('canNavigatePrev', false)
                ->missing('monthItems.1'));

        $this->actingAs($user)
            ->get('/client/calendario-estrategico?year='.$outOfRange->year.'&month='.$outOfRange->month)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('calendarYear', $now->year)
                ->where('calendarMonth', $now->month));
    }

    public function test_client_calendar_unlimited_when_plan_has_no_period(): void
    {
        $company = $this->baseCompany();
        $plan = Plan::query()->create([
            'name' => 'Plano sem limite',
            'slug' => 'plano-free-cal-'.Str::random(8),
            'price_monthly_cents' => 0,
            'is_active' => true,
            'strategic_calendar_view_period' => null,
        ]);
        $cal = Module::query()->firstOrCreate(
            ['key' => Module::KEY_CALENDARIO_ESTRATEGICO],
            ['name' => 'Calendário', 'description' => 'x']
        );
        $plan->modules()->sync([$cal->id]);

        Subscription::query()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'starts_at' => now(),
            'ends_at' => now()->addYear(),
            'status' => 'active',
        ]);

        $user = User::factory()->companyAdmin($company->id)->create();

        $this->actingAs($user)
            ->get('/client/calendario-estrategico?year=2030&month=6')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Client/StrategicCalendar/Index')
                ->where('calendarYear', 2030)
                ->where('calendarMonth', 6)
                ->where('visiblePeriod', null)
                ->where('canNavigatePrev', true)
                ->where('canNavigateNext', true));
    }

    public function test_weekly_recurrence_expands_occurrences_in_month(): void
    {
        Carbon::setTestNow('2026-05-15');

        $item = StrategicCalendarItem::query()->create([
                'title' => 'Ritual semanal',
            'description' => null,
            'kind' => StrategicCalendarItemKind::Ritual,
            'occurs_on' => '2026-05-01',
            'recurrence' => StrategicCalendarRecurrence::Weekly,
            'company_id' => null,
        ]);

        $rangeStart = Carbon::parse('2026-05-01')->startOfDay();
        $rangeEnd = Carbon::parse('2026-05-31')->endOfDay();

        $occurrences = StrategicCalendarOccurrenceExpander::occurrencesForItem($item, $rangeStart, $rangeEnd);

        $this->assertCount(5, $occurrences);
        $this->assertSame('2026-05-01', $occurrences[0]['occurs_on']);
        $this->assertSame('2026-05-29', $occurrences[4]['occurs_on']);
    }

    public function test_date_range_expands_each_day_in_visible_month(): void
    {
        $item = StrategicCalendarItem::query()->create([
            'title' => 'Recesso de fim de ano',
            'description' => null,
            'kind' => StrategicCalendarItemKind::Event,
            'occurs_on' => '2026-12-24',
            'ends_on' => '2026-12-31',
            'company_id' => null,
        ]);

        $rangeStart = Carbon::parse('2026-12-01')->startOfDay();
        $rangeEnd = Carbon::parse('2026-12-31')->endOfDay();

        $occurrences = StrategicCalendarOccurrenceExpander::occurrencesForItem($item, $rangeStart, $rangeEnd);

        $this->assertCount(8, $occurrences);
        $this->assertSame('2026-12-24', $occurrences[0]['occurs_on']);
        $this->assertSame('2026-12-31', $occurrences[7]['occurs_on']);
        $this->assertSame('2026-12-31', $occurrences[0]['ends_on']);
        $this->assertSame('2026-12-24', $occurrences[0]['range_starts_on']);
    }

    public function test_date_range_spanning_months_appears_in_both_month_queries(): void
    {
        $item = StrategicCalendarItem::query()->create([
            'title' => 'Feriado prolongado',
            'description' => null,
            'kind' => StrategicCalendarItemKind::Event,
            'occurs_on' => '2026-04-28',
            'ends_on' => '2026-05-03',
            'company_id' => null,
        ]);

        $aprilEnd = Carbon::parse('2026-04-30')->endOfDay();
        $aprilStart = Carbon::parse('2026-04-01')->startOfDay();
        $mayStart = Carbon::parse('2026-05-01')->startOfDay();
        $mayEnd = Carbon::parse('2026-05-31')->endOfDay();

        $april = StrategicCalendarOccurrenceExpander::occurrencesForItem($item, $aprilStart, $aprilEnd);
        $may = StrategicCalendarOccurrenceExpander::occurrencesForItem($item, $mayStart, $mayEnd);

        $this->assertCount(3, $april);
        $this->assertSame('2026-04-28', $april[0]['occurs_on']);
        $this->assertSame('2026-04-30', $april[2]['occurs_on']);
        $this->assertCount(3, $may);
        $this->assertSame('2026-05-01', $may[0]['occurs_on']);
        $this->assertSame('2026-05-03', $may[2]['occurs_on']);
    }

    public function test_super_admin_can_create_item_with_date_range(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->post('/admin/calendario-estrategico', [
                'title' => 'Carnaval',
                'description' => 'Recesso coletivo',
                'kind' => StrategicCalendarItemKind::Event->value,
                'occurs_on' => '2026-02-14',
                'ends_on' => '2026-02-18',
            ])
            ->assertRedirect();

        $item = StrategicCalendarItem::query()->where('title', 'Carnaval')->first();
        $this->assertNotNull($item);
        $this->assertSame('2026-02-14', $item->occurs_on?->toDateString());
        $this->assertSame('2026-02-18', $item->ends_on?->toDateString());
        $this->assertNull($item->recurrence);
    }

    public function test_date_range_and_recurrence_are_mutually_exclusive(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->post('/admin/calendario-estrategico', [
                'title' => 'Inválido',
                'kind' => StrategicCalendarItemKind::Event->value,
                'occurs_on' => '2026-03-01',
                'ends_on' => '2026-03-05',
                'recurrence' => StrategicCalendarRecurrence::Weekly->value,
            ])
            ->assertSessionHasErrors(['ends_on', 'recurrence']);
    }

    public function test_super_admin_can_create_item_for_multiple_companies(): void
    {
        $companyA = $this->baseCompany();
        $companyB = Company::query()->create([
            'name' => 'Empresa B',
            'cnpj' => '33.333.333/0001-33',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
        ]);
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->post('/admin/calendario-estrategico', [
                'title' => 'Treinamento conjunto',
                'kind' => StrategicCalendarItemKind::Event->value,
                'occurs_on' => '2026-08-01',
                'company_ids' => [$companyA->id, $companyB->id],
            ])
            ->assertRedirect();

        $item = StrategicCalendarItem::query()->where('title', 'Treinamento conjunto')->first();
        $this->assertNotNull($item);
        $this->assertNull($item->company_id);
        $this->assertEqualsCanonicalizing(
            [$companyA->id, $companyB->id],
            $item->companies()->pluck('companies.id')->all(),
        );
    }

    public function test_client_sees_item_when_assigned_via_company_pivot(): void
    {
        $companyA = $this->baseCompany();
        $companyA->update(['strategic_calendar_access' => true]);
        $companyB = Company::query()->create([
            'name' => 'Empresa B',
            'cnpj' => '44.444.444/0001-44',
            'is_active' => true,
            'strategic_calendar_access' => true,
            'complaints_public_token' => (string) Str::uuid(),
        ]);

        $item = StrategicCalendarItem::query()->create([
            'title' => 'Para A e B',
            'description' => null,
            'kind' => StrategicCalendarItemKind::Event,
            'occurs_on' => '2026-05-12',
            'company_id' => null,
        ]);
        $item->companies()->sync([$companyA->id, $companyB->id]);

        $userA = User::factory()->companyAdmin($companyA->id)->create();
        $userB = User::factory()->companyAdmin($companyB->id)->create();
        $outsider = Company::query()->create([
            'name' => 'Empresa C',
            'cnpj' => '55.555.555/0001-55',
            'is_active' => true,
            'strategic_calendar_access' => true,
            'complaints_public_token' => (string) Str::uuid(),
        ]);
        $userC = User::factory()->companyAdmin($outsider->id)->create();

        $this->actingAs($userA)
            ->get('/client/calendario-estrategico?year=2026&month=5')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('monthItems', 1)
                ->where('monthItems.0.title', 'Para A e B'));

        $this->actingAs($userB)
            ->get('/client/calendario-estrategico?year=2026&month=5')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('monthItems', 1));

        $this->actingAs($userC)
            ->get('/client/calendario-estrategico?year=2026&month=5')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('monthItems', 0));
    }

    public function test_client_can_toggle_completion_without_affecting_other_company(): void
    {
        $companyA = $this->baseCompany();
        $companyA->update(['strategic_calendar_access' => true]);
        $companyB = Company::query()->create([
            'name' => 'Empresa B',
            'cnpj' => '22.222.222/0001-22',
            'is_active' => true,
            'strategic_calendar_access' => true,
            'complaints_public_token' => (string) Str::uuid(),
        ]);

        $item = StrategicCalendarItem::query()->create([
                'title' => 'Ritual mensal',
            'description' => null,
            'kind' => StrategicCalendarItemKind::Ritual,
            'occurs_on' => '2026-09-10',
            'company_id' => null,
        ]);

        $userA = User::factory()->companyAdmin($companyA->id)->create();
        $userB = User::factory()->companyAdmin($companyB->id)->create();

        $this->actingAs($userA)
            ->patch("/client/calendario-estrategico/{$item->id}/conclusao", [
                'occurs_on' => '2026-09-10',
                'completed' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('strategic_calendar_completions', [
            'company_id' => $companyA->id,
            'strategic_calendar_item_id' => $item->id,
            'occurs_on' => '2026-09-10 00:00:00',
        ]);

        $this->actingAs($userA)
            ->get('/client/calendario-estrategico?year=2026&month=9')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('monthItems.0.completed', true)
                ->where('monthItems.0.completed_by_user_id', $userA->id));

        $this->actingAs($userB)
            ->get('/client/calendario-estrategico?year=2026&month=9')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('monthItems.0.completed', false)
                ->where('monthItems.0.completed_at', null));
    }

    public function test_recurring_completion_is_scoped_to_occurrence_date(): void
    {
        $company = $this->baseCompany();
        $company->update(['strategic_calendar_access' => true]);
        $user = User::factory()->companyAdmin($company->id)->create();

        $item = StrategicCalendarItem::query()->create([
                'title' => 'Ritual semanal',
            'description' => null,
            'kind' => StrategicCalendarItemKind::Ritual,
            'occurs_on' => '2026-09-04',
            'recurrence' => StrategicCalendarRecurrence::Weekly,
            'company_id' => null,
        ]);

        StrategicCalendarCompletion::query()->create([
            'company_id' => $company->id,
            'strategic_calendar_item_id' => $item->id,
            'occurs_on' => '2026-09-11',
            'completed_at' => now(),
            'completed_by_user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get('/client/calendario-estrategico?year=2026&month=9')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('monthItems', 4)
                ->where('monthItems.0.occurs_on', '2026-09-04')
                ->where('monthItems.0.completed', false)
                ->where('monthItems.1.occurs_on', '2026-09-11')
                ->where('monthItems.1.completed', true));
    }

    public function test_client_calendar_includes_due_tasks_and_can_complete_task(): void
    {
        $company = $this->baseCompany();
        $company->update([
            'strategic_calendar_access' => true,
            'tasks_access' => true,
        ]);
        $user = User::factory()->companyAdmin($company->id)->create();

        $board = TaskBoard::query()->create([
            'company_id' => $company->id,
            'name' => 'Quadro',
            'is_archived' => false,
        ]);
        $list = TaskList::query()->create([
            'board_id' => $board->id,
            'name' => 'A fazer',
            'visibility' => 'company',
            'position' => 1,
            'is_archived' => false,
        ]);
        $card = TaskCard::query()->create([
            'list_id' => $list->id,
            'company_id' => $company->id,
            'title' => 'Enviar evidências',
            'description' => null,
            'position' => 1,
            'visibility' => 'company',
            'due_date' => '2026-09-12',
            'is_archived' => false,
        ]);

        $this->actingAs($user)
            ->get('/client/calendario-estrategico?year=2026&month=9')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('kindLabels.task', 'Tarefa')
                ->where('monthItems.0.kind', 'task')
                ->where('monthItems.0.title', 'Enviar evidências')
                ->where('monthItems.0.completed', false));

        $this->actingAs($user)
            ->patch("/client/calendario-estrategico/tarefas/{$card->id}/conclusao", [
                'completed' => true,
            ])
            ->assertRedirect();

        $this->assertNotNull($card->fresh()->completed_at);
    }

    public function test_client_calendar_includes_task_with_only_start_date(): void
    {
        $company = $this->baseCompany();
        $company->update([
            'strategic_calendar_access' => true,
            'tasks_access' => true,
        ]);
        $user = User::factory()->companyAdmin($company->id)->create();

        $board = TaskBoard::query()->create([
            'company_id' => $company->id,
            'name' => 'Quadro',
            'is_archived' => false,
        ]);
        $list = TaskList::query()->create([
            'board_id' => $board->id,
            'name' => 'A fazer',
            'visibility' => 'company',
            'position' => 1,
            'is_archived' => false,
        ]);
        TaskCard::query()->create([
            'list_id' => $list->id,
            'company_id' => $company->id,
            'title' => 'CANAL DE DENUNCIAS',
            'description' => null,
            'position' => 1,
            'visibility' => 'company',
            'start_date' => '2026-07-04',
            'due_date' => null,
            'is_archived' => false,
        ]);

        $this->actingAs($user)
            ->get('/client/calendario-estrategico?year=2026&month=7')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('monthItems.0.kind', 'task')
                ->where('monthItems.0.title', 'CANAL DE DENUNCIAS')
                ->where('monthItems.0.occurs_on', '2026-07-04'));
    }

    public function test_admin_can_upload_attachments_for_item(): void
    {
        Storage::fake('public');

        $admin = User::factory()->superAdmin()->create();

        $item = StrategicCalendarItem::query()->create([
            'title' => 'Com material',
            'description' => 'Guia PDF',
            'kind' => StrategicCalendarItemKind::Event,
            'occurs_on' => '2026-07-01',
            'recurrence' => StrategicCalendarRecurrence::Monthly,
            'company_id' => null,
        ]);

        $this->actingAs($admin)
            ->post('/admin/calendario-estrategico/'.$item->id.'/anexos', [
                'files' => [
                    UploadedFile::fake()->create('guia.pdf', 100, 'application/pdf'),
                    UploadedFile::fake()->create('checklist.docx', 50, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                ],
            ])
            ->assertRedirect();

        $item->refresh();
        $this->assertCount(2, $item->attachments);
        Storage::disk('public')->assertExists($item->attachments->first()->path);
    }

    public function test_admin_can_upload_video_attachment(): void
    {
        Storage::fake('public');

        $admin = User::factory()->superAdmin()->create();

        $item = StrategicCalendarItem::query()->create([
            'title' => 'Com vídeo',
            'description' => 'Treinamento',
            'kind' => StrategicCalendarItemKind::Event,
            'occurs_on' => '2026-07-01',
            'company_id' => null,
        ]);

        $this->actingAs($admin)
            ->post('/admin/calendario-estrategico/'.$item->id.'/anexos', [
                'files' => [
                    UploadedFile::fake()->create('treinamento.mp4', 5000, 'video/mp4'),
                ],
            ])
            ->assertRedirect();

        $item->refresh();
        $this->assertCount(1, $item->attachments);
        $this->assertTrue($item->attachments->first()->isVideo());
        Storage::disk('public')->assertExists($item->attachments->first()->path);
    }

    public function test_admin_rejects_attachment_above_max_size(): void
    {
        Storage::fake('public');
        config(['strategic_calendar.max_attachment_kb' => 100]);

        $admin = User::factory()->superAdmin()->create();

        $item = StrategicCalendarItem::query()->create([
            'title' => 'Com anexo grande',
            'description' => null,
            'kind' => StrategicCalendarItemKind::Event,
            'occurs_on' => '2026-07-01',
            'company_id' => null,
        ]);

        $this->actingAs($admin)
            ->post('/admin/calendario-estrategico/'.$item->id.'/anexos', [
                'files' => [
                    UploadedFile::fake()->create('grande.mp4', 101, 'video/mp4'),
                ],
            ])
            ->assertSessionHasErrors('files.0');

        $this->assertCount(0, $item->fresh()->attachments);
    }

    public function test_client_can_download_attachment_for_global_item(): void
    {
        Storage::fake('public');

        $company = $this->baseCompany();
        $company->update(['strategic_calendar_access' => true]);

        $path = 'strategic-calendar/test.pdf';
        Storage::disk('public')->put($path, 'conteudo-teste');

        $item = StrategicCalendarItem::query()->create([
            'title' => 'Global com PDF',
            'description' => null,
            'kind' => StrategicCalendarItemKind::Event,
            'occurs_on' => '2026-05-20',
            'company_id' => null,
        ]);

        $attachment = $item->attachments()->create([
            'disk' => 'public',
            'path' => $path,
            'original_name' => 'guia.pdf',
            'mime' => 'application/pdf',
            'size' => 14,
        ]);

        $user = User::factory()->companyAdmin($company->id)->create();

        $response = $this->actingAs($user)
            ->get('/client/calendario-estrategico/anexos/'.$attachment->id.'/download');

        $response->assertOk();
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_client_video_attachment_served_inline(): void
    {
        Storage::fake('public');

        $company = $this->baseCompany();
        $company->update(['strategic_calendar_access' => true]);

        $path = 'strategic-calendar/video.mp4';
        Storage::disk('public')->put($path, 'fake-video-content');

        $item = StrategicCalendarItem::query()->create([
            'title' => 'Global com vídeo',
            'description' => null,
            'kind' => StrategicCalendarItemKind::Event,
            'occurs_on' => '2026-05-20',
            'company_id' => null,
        ]);

        $attachment = $item->attachments()->create([
            'disk' => 'public',
            'path' => $path,
            'original_name' => 'treinamento.mp4',
            'mime' => 'video/mp4',
            'size' => 18,
        ]);

        $user = User::factory()->companyAdmin($company->id)->create();

        $response = $this->actingAs($user)
            ->get('/client/calendario-estrategico/anexos/'.$attachment->id.'/download');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'video/mp4');
        $this->assertStringContainsString('inline', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('treinamento.mp4', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_super_admin_can_create_birthday_and_client_sees_it(): void
    {
        $company = $this->baseCompany();
        $company->update(['strategic_calendar_access' => true]);

        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->post('/admin/calendario-estrategico', [
                'title' => 'Aniversário — Maria Silva',
                'description' => 'Parabéns à Maria!',
                'kind' => StrategicCalendarItemKind::Birthday->value,
                'occurs_on' => '2026-08-12',
                'company_ids' => [$company->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('strategic_calendar_items', [
            'title' => 'Aniversário — Maria Silva',
            'kind' => 'birthday',
        ]);

        $user = User::factory()->companyAdmin($company->id)->create();

        $this->withoutVite();

        $this->actingAs($user)
            ->get('/client/calendario-estrategico?year=2026&month=8')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Client/StrategicCalendar/Index')
                ->where('kindLabels.birthday', 'Aniversário')
                ->where('monthItems.0.kind', 'birthday')
                ->where('monthItems.0.title', 'Aniversário — Maria Silva'));
    }

    public function test_client_can_create_company_agenda_item(): void
    {
        $company = $this->baseCompany();
        $company->update(['strategic_calendar_access' => true]);
        $user = User::factory()->companyAdmin($company->id)->create();

        $this->actingAs($user)
            ->post('/client/calendario-estrategico', [
                'title' => 'Reunião de líderes',
                'description' => 'Alinhamento interno.',
                'kind' => StrategicCalendarItemKind::Event->value,
                'occurs_on' => '2026-07-20',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('strategic_calendar_items', [
            'title' => 'Reunião de líderes',
            'company_id' => $company->id,
            'source' => 'company',
            'created_by' => $user->id,
        ]);
    }

    public function test_client_cannot_update_talents_agenda_item(): void
    {
        $company = $this->baseCompany();
        $company->update(['strategic_calendar_access' => true]);
        $user = User::factory()->companyAdmin($company->id)->create();

        $item = StrategicCalendarItem::query()->create([
            'title' => 'Ritual Talents',
            'kind' => StrategicCalendarItemKind::Ritual,
            'occurs_on' => '2026-07-10',
            'company_id' => $company->id,
            'source' => StrategicCalendarSource::Talents,
            'is_published' => true,
        ]);

        $this->actingAs($user)
            ->put('/client/calendario-estrategico/'.$item->id, [
                'title' => 'Hack',
                'kind' => StrategicCalendarItemKind::Event->value,
                'occurs_on' => '2026-07-11',
            ])
            ->assertForbidden();
    }

    public function test_admin_cannot_update_company_agenda_item(): void
    {
        $company = $this->baseCompany();
        $admin = User::factory()->superAdmin()->create();

        $item = StrategicCalendarItem::query()->create([
            'title' => 'Evento interno',
            'kind' => StrategicCalendarItemKind::Event,
            'occurs_on' => '2026-07-12',
            'company_id' => $company->id,
            'source' => StrategicCalendarSource::Company,
            'is_published' => true,
        ]);

        $this->actingAs($admin)
            ->put('/admin/calendario-estrategico/'.$item->id, [
                'title' => 'Hack admin',
                'kind' => StrategicCalendarItemKind::Event->value,
                'occurs_on' => '2026-07-12',
            ])
            ->assertForbidden();
    }

    public function test_client_can_filter_by_company_agenda(): void
    {
        $company = $this->baseCompany();
        $company->update(['strategic_calendar_access' => true]);

        StrategicCalendarItem::query()->create([
            'title' => 'Evento Talents',
            'kind' => StrategicCalendarItemKind::Event,
            'occurs_on' => '2026-05-05',
            'company_id' => $company->id,
            'source' => StrategicCalendarSource::Talents,
            'is_published' => true,
        ]);

        StrategicCalendarItem::query()->create([
            'title' => 'Evento Empresa',
            'kind' => StrategicCalendarItemKind::Event,
            'occurs_on' => '2026-05-06',
            'company_id' => $company->id,
            'source' => StrategicCalendarSource::Company,
            'is_published' => true,
        ]);

        $user = User::factory()->companyAdmin($company->id)->create();
        $this->withoutVite();

        $this->actingAs($user)
            ->get('/client/calendario-estrategico?year=2026&month=5&agenda=company')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Client/StrategicCalendar/Index')
                ->where('agendaFilter', 'company')
                ->has('monthItems', 1)
                ->where('monthItems.0.title', 'Evento Empresa')
                ->where('monthItems.0.agenda', 'company')
                ->where('monthItems.0.can_manage', true));
    }
}
