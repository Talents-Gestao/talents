<?php

namespace Tests\Feature;

use App\Enums\CompanyNoticeEventKind;
use App\Enums\StrategicCalendarItemKind;
use App\Models\Company;
use App\Models\CompanyNotice;
use App\Models\StrategicCalendarItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CompanyNoticeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function companyWithCalendar(): Company
    {
        $company = Company::query()->create([
            'name' => 'Empresa avisos',
            'cnpj' => '22.222.222/0001-22',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
            'strategic_calendar_access' => true,
        ]);

        return $company;
    }

    public function test_client_sees_unread_notices_and_can_mark_read(): void
    {
        $company = $this->companyWithCalendar();
        $user = User::factory()->companyAdmin($company->id)->create();

        $notice = CompanyNotice::query()->create([
            'company_id' => $company->id,
            'title' => 'Calendário atualizado',
            'body' => 'Novo evento disponível.',
            'published_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/client/avisos')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Client/Notices/Index')
                ->has('notices.data', 1)
                ->where('notices.data.0.id', $notice->id)
                ->where('notices.data.0.read', false));

        $this->actingAs($user)
            ->get('/client/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('nav.unread_notices_count', 1));

        $this->actingAs($user)
            ->post(route('client.notices.mark-read', $notice), [], [
                'HTTP_REFERER' => route('client.notices.index'),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('company_notice_reads', [
            'company_notice_id' => $notice->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_calendar_item_create_publishes_notice_for_company(): void
    {
        $company = $this->companyWithCalendar();
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->post(route('admin.strategic-calendar.store'), [
                'title' => 'Rito mensal',
                'description' => 'Descrição',
                'kind' => StrategicCalendarItemKind::Rito->value,
                'occurs_on' => now()->addWeek()->toDateString(),
                'company_id' => $company->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('company_notices', [
            'company_id' => $company->id,
            'title' => 'Calendário atualizado',
            'source_type' => 'strategic_calendar_item',
            'event_kind' => CompanyNoticeEventKind::Created->value,
        ]);
    }

    public function test_calendar_item_delete_publishes_notice(): void
    {
        $company = $this->companyWithCalendar();
        $admin = User::factory()->superAdmin()->create();

        $item = StrategicCalendarItem::query()->create([
            'title' => 'Evento teste',
            'description' => null,
            'kind' => StrategicCalendarItemKind::Event->value,
            'occurs_on' => now()->addDays(3),
            'company_id' => $company->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.strategic-calendar.destroy', $item))
            ->assertRedirect();

        $this->assertDatabaseHas('company_notices', [
            'company_id' => $company->id,
            'source_id' => $item->id,
            'event_kind' => CompanyNoticeEventKind::Deleted->value,
        ]);
    }

    public function test_admin_can_publish_manual_notice(): void
    {
        $company = $this->companyWithCalendar();
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->post(route('admin.notices.store'), [
                'company_id' => $company->id,
                'title' => 'Novidade importante',
                'body' => 'Confira as atualizações do mês.',
            ])
            ->assertRedirect(route('admin.notices.index'));

        $this->assertDatabaseHas('company_notices', [
            'company_id' => $company->id,
            'title' => 'Novidade importante',
            'source_type' => null,
        ]);
    }
}
