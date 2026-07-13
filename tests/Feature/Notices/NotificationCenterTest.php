<?php

declare(strict_types=1);

namespace Tests\Feature\Notices;

use App\Actions\Notices\PublishCompanyNotice;
use App\Enums\CompanyNoticeAudience;
use App\Enums\CompanyNoticeEventKind;
use App\Models\Company;
use App\Models\CompanyNotice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->superAdmin()->create([
            'email' => 'admin@talents.local',
            'is_owner' => true,
        ]);
    }

    private function publishTalents(string $title, int $sourceId): CompanyNotice
    {
        return app(PublishCompanyNotice::class)->handle(
            companyId: null,
            title: $title,
            body: 'Corpo do aviso.',
            audience: CompanyNoticeAudience::Talents,
            sourceType: 'commercial_proposal',
            sourceId: $sourceId,
            eventKind: CompanyNoticeEventKind::ProposalWon,
            dedupeWithinMinutes: 5,
        );
    }

    public function test_talents_notice_is_created_without_company(): void
    {
        $notice = $this->publishTalents('Proposta fechada', 1);

        $this->assertNull($notice->company_id);
        $this->assertSame(CompanyNoticeAudience::Talents, $notice->audience);
    }

    public function test_publish_dedupes_same_source_within_window(): void
    {
        $first = $this->publishTalents('Proposta fechada', 42);
        $second = $this->publishTalents('Proposta fechada', 42);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, CompanyNotice::query()->count());
    }

    public function test_admin_recent_returns_talents_notices_and_unread_count(): void
    {
        $this->publishTalents('Proposta fechada', 7);

        $response = $this->actingAs($this->admin())->getJson(route('admin.notices.recent'));

        $response->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('notices.0.title', 'Proposta fechada');
    }

    public function test_admin_recent_excludes_company_scoped_notices(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Teste',
            'cnpj' => '11.222.333/0001-44',
            'is_active' => true,
        ]);

        app(PublishCompanyNotice::class)->handle(
            companyId: (int) $company->id,
            title: 'Aviso da empresa',
            body: 'Somente para a empresa.',
            audience: CompanyNoticeAudience::Company,
        );

        $response = $this->actingAs($this->admin())->getJson(route('admin.notices.recent'));

        $response->assertOk()
            ->assertJsonPath('unread_count', 0)
            ->assertJsonCount(0, 'notices');
    }

    public function test_admin_can_mark_all_talents_notices_as_read(): void
    {
        $this->publishTalents('Proposta A', 1);
        $this->publishTalents('Proposta B', 2);

        $admin = $this->admin();

        $this->actingAs($admin)->postJson(route('admin.notices.mark-all-read'))
            ->assertOk()
            ->assertJsonPath('marked', 2)
            ->assertJsonPath('unread_count', 0);

        $this->actingAs($admin)->getJson(route('admin.notices.recent'))
            ->assertJsonPath('unread_count', 0);
    }
}
