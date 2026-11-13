<?php

// Cartão do quadro SUPORTE: «AVISOS» (área de avisos com sino e não lidos; data alterada no calendário vira aviso).
// Já cobertos: lista e marcar como lido (CompanyNoticeTest::test_client_sees_unread_notices_and_can_mark_read),
// aviso ao criar e ao excluir item (CompanyNoticeTest::test_calendar_item_create_publishes_notice_for_company e
// test_calendar_item_delete_publishes_notice).

namespace Tests\Feature\Acceptance\Support;

use App\Enums\CompanyNoticeEventKind;
use App\Enums\StrategicCalendarItemKind;
use App\Models\Company;
use App\Models\CompanyNotice;
use App\Models\StrategicCalendarItem;
use App\Models\User;
use App\Support\StrategicCalendarAudience;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class NoticesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function company(string $name, string $cnpj): Company
    {
        return Company::query()->create([
            'name' => $name,
            'cnpj' => $cnpj,
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
            'strategic_calendar_access' => true,
        ]);
    }

    /** @return array{item: StrategicCalendarItem, audience: list<Company>, outsider: Company} */
    private function itemForTwoCompanies(): array
    {
        $a = $this->company('Empresa A', '11.111.111/0001-11');
        $b = $this->company('Empresa B', '22.222.222/0001-22');
        $outsider = $this->company('Empresa fora', '33.333.333/0001-33');

        $item = StrategicCalendarItem::query()->create([
            'title' => 'Treinamento de líderes',
            'kind' => StrategicCalendarItemKind::Event->value,
            'occurs_on' => '2026-12-10',
            'company_id' => null,
        ]);
        StrategicCalendarAudience::syncCompanies($item, [$a->id, $b->id]);

        return ['item' => $item, 'audience' => [$a, $b], 'outsider' => $outsider];
    }

    public function test_moving_a_calendar_date_notifies_each_audience_company_and_the_bell_counts_it_until_read(): void
    {
        ['item' => $item, 'audience' => [$a, $b], 'outsider' => $outsider] = $this->itemForTwoCompanies();

        $this->actingAs(User::factory()->talentsOwner()->create())
            ->patch(route('admin.strategic-calendar.update-date', $item), ['occurs_on' => '2026-12-15'])
            ->assertRedirect();

        foreach ([$a, $b] as $company) {
            $this->assertDatabaseHas('company_notices', [
                'company_id' => $company->id,
                'title' => 'Data alterada no calendário',
                'event_kind' => CompanyNoticeEventKind::DateChanged->value,
                'source_id' => $item->id,
            ]);
        }
        $this->assertDatabaseMissing('company_notices', ['company_id' => $outsider->id]);

        $notice = CompanyNotice::query()->where('company_id', $a->id)->firstOrFail();
        $this->assertStringContainsString('de 10/12/2026 para', $notice->body);

        $client = User::factory()->companyAdmin($a->id)->create();

        $this->actingAs($client)->get('/client/dashboard')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('nav.unread_notices_count', 1));

        $this->actingAs($client)->postJson(route('client.notices.mark-read', $notice))
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->actingAs($client)->get('/client/dashboard')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('nav.unread_notices_count', 0));
    }

    public function test_changing_the_date_in_the_edit_form_also_publishes_a_date_changed_notice(): void
    {
        ['item' => $item, 'audience' => [$a, $b]] = $this->itemForTwoCompanies();

        $this->actingAs(User::factory()->talentsOwner()->create())
            ->put(route('admin.strategic-calendar.update', $item), [
                'title' => $item->title,
                'kind' => StrategicCalendarItemKind::Event->value,
                'occurs_on' => '2026-12-20',
                'company_ids' => [$a->id, $b->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('company_notices', [
            'company_id' => $a->id,
            'event_kind' => CompanyNoticeEventKind::DateChanged->value,
            'title' => 'Data alterada no calendário',
        ]);
        // Um aviso só por empresa: o genérico «Calendário atualizado» não sai junto.
        $this->assertSame(1, CompanyNotice::query()->where('company_id', $a->id)->count());
    }
}
