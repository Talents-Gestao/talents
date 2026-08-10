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

    public function test_admin_recent_includes_company_scoped_notices_with_company_name(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Teste',
            'cnpj' => '11.222.333/0001-44',
            'is_active' => true,
        ]);

        $this->publishTalents('Proposta fechada', 7);

        app(PublishCompanyNotice::class)->handle(
            companyId: (int) $company->id,
            title: 'Aviso da empresa',
            body: 'Somente para a empresa.',
            audience: CompanyNoticeAudience::Company,
        );

        $response = $this->actingAs($this->admin())->getJson(route('admin.notices.recent'));

        $response->assertOk()
            ->assertJsonPath('unread_count', 2)
            ->assertJsonCount(2, 'notices');

        $titles = collect($response->json('notices'))->pluck('title')->all();
        $this->assertContains('Proposta fechada', $titles);
        $this->assertContains('Aviso da empresa', $titles);

        $companyNotice = collect($response->json('notices'))
            ->firstWhere('title', 'Aviso da empresa');

        $this->assertSame('Empresa Teste', $companyNotice['company_name'] ?? null);
        $this->assertSame($company->id, $companyNotice['company_id'] ?? null);
    }

    public function test_admin_can_mark_company_notice_as_read(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Lida',
            'cnpj' => '22.333.444/0001-55',
            'is_active' => true,
        ]);

        $notice = app(PublishCompanyNotice::class)->handle(
            companyId: (int) $company->id,
            title: 'Feedback concluído',
            body: 'Corpo.',
            audience: CompanyNoticeAudience::Company,
        );

        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson(route('admin.notices.mark-read', $notice))
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->actingAs($admin)
            ->getJson(route('admin.notices.recent'))
            ->assertJsonPath('unread_count', 0)
            ->assertJsonPath('notices.0.read', true);
    }

    public function test_admin_can_mark_all_notices_as_read_including_company(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Mix',
            'cnpj' => '33.444.555/0001-66',
            'is_active' => true,
        ]);

        $this->publishTalents('Proposta A', 1);
        $this->publishTalents('Proposta B', 2);

        app(PublishCompanyNotice::class)->handle(
            companyId: (int) $company->id,
            title: 'Aviso company',
            body: 'Corpo.',
            audience: CompanyNoticeAudience::Company,
        );

        $admin = $this->admin();

        $this->actingAs($admin)->postJson(route('admin.notices.mark-all-read'))
            ->assertOk()
            ->assertJsonPath('marked', 3)
            ->assertJsonPath('unread_count', 0);

        $this->actingAs($admin)->getJson(route('admin.notices.recent'))
            ->assertJsonPath('unread_count', 0);
    }
}
