<?php

// Cartão do quadro SUPORTE: «CALENDÁRIO» (agenda da empresa criada pelo cliente; duas agendas ligadas, Talents e Empresa,
// com filtro de origem no admin e no portal; itens da Talents somente leitura para a empresa).
// Já cobertos (StrategicCalendarTest): test_client_can_create_company_agenda_item, test_client_cannot_update_talents_agenda_item,
// test_admin_cannot_update_company_agenda_item e test_client_can_filter_by_company_agenda.

namespace Tests\Feature\Acceptance\Support;

use App\Enums\StrategicCalendarItemKind;
use App\Enums\StrategicCalendarSource;
use App\Models\Company;
use App\Models\StrategicCalendarItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CalendarTest extends TestCase
{
    use RefreshDatabase;

    private function companyWithBothAgendas(): Company
    {
        $company = Company::query()->create([
            'name' => 'Empresa Agenda',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
            'strategic_calendar_access' => true,
        ]);
        foreach ([['Evento Talents', StrategicCalendarSource::Talents, '2026-05-05'], ['Evento Empresa', StrategicCalendarSource::Company, '2026-05-06']] as [$title, $source, $date]) {
            StrategicCalendarItem::query()->create([
                'title' => $title,
                'kind' => StrategicCalendarItemKind::Event,
                'occurs_on' => $date,
                'company_id' => $company->id,
                'source' => $source,
                'is_published' => true,
            ]);
        }

        return $company;
    }

    public function test_admin_and_portal_see_talents_and_company_agendas_together_and_talents_items_are_read_only_for_the_company(): void
    {
        $this->withoutVite();
        $company = $this->companyWithBothAgendas();

        $this->actingAs(User::factory()->talentsOwner()->create())
            ->get(route('admin.strategic-calendar.index', ['year' => 2026, 'month' => 5, 'company_id' => $company->id]))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) {
                $titles = collect($page->toArray()['props']['monthItems'])->pluck('title')->all();
                $this->assertContains('Evento Talents', $titles);
                $this->assertContains('Evento Empresa', $titles);
            });

        $this->actingAs(User::factory()->companyAdmin($company->id)->create())
            ->get('/client/calendario-estrategico?year=2026&month=5')
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) {
                $items = collect($page->toArray()['props']['monthItems'])->keyBy('title');
                $this->assertSame('talents', $items['Evento Talents']['agenda']);
                $this->assertFalse((bool) $items['Evento Talents']['can_manage']);
                $this->assertSame('company', $items['Evento Empresa']['agenda']);
                $this->assertTrue((bool) $items['Evento Empresa']['can_manage']);
            });
    }

    public function test_admin_filters_the_calendar_by_origin(): void
    {
        $this->withoutVite();
        $company = $this->companyWithBothAgendas();

        $this->actingAs(User::factory()->talentsOwner()->create())
            ->get(route('admin.strategic-calendar.index', ['year' => 2026, 'month' => 5, 'company_id' => $company->id, 'agenda' => 'company']))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) {
                $titles = collect($page->toArray()['props']['monthItems'])->pluck('title')->all();
                $this->assertSame(['Evento Empresa'], $titles);
            });
    }

    public function test_admin_origin_filter_combines_with_kind_and_company_and_reports_the_active_choice(): void
    {
        $this->withoutVite();
        $company = $this->companyWithBothAgendas();
        $admin = User::factory()->talentsOwner()->create();

        $titles = fn (array $query) => collect(
            $this->actingAs($admin)
                ->get(route('admin.strategic-calendar.index', ['year' => 2026, 'month' => 5, 'company_id' => $company->id, ...$query]))
                ->assertOk()
                ->inertiaProps('monthItems')
        )->pluck('title')->all();

        $this->assertSame(['Evento Talents'], $titles(['agenda' => 'talents']));
        $this->assertSame(['Evento Empresa'], $titles(['agenda' => 'company', 'kind' => StrategicCalendarItemKind::Event->value]));
        $this->assertSame([], $titles(['agenda' => 'company', 'kind' => StrategicCalendarItemKind::Ritual->value]));
        $this->assertEqualsCanonicalizing(['Evento Talents', 'Evento Empresa'], $titles(['agenda' => 'valor-invalido']));

        $this->actingAs($admin)
            ->get(route('admin.strategic-calendar.index', ['agenda' => 'company']))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('filters.agenda', 'company'));
        $this->actingAs($admin)
            ->get(route('admin.strategic-calendar.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('filters.agenda', 'all'));
    }
}
