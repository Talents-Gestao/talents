<?php

// Cartão do quadro SUPORTE: «NOTIFICAÇÕES» (super_admin vê todas; usuário comum só as suas).
// Na plataforma, as notificações do sino são avisos por empresa (CompanyNotice) ou da Talents (audience talents);
// as notificações por usuário (tarefas) vão só por e-mail. Já cobertos: AdminSeesAllPublisherNoticesTest (4 origens)
// e NotificationCenterTest (exclusão escopada por empresa).

namespace Tests\Feature\Acceptance\Support;

use App\Actions\Notices\PublishCompanyNotice;
use App\Enums\CompanyNoticeAudience;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function company(string $name, string $cnpj): Company
    {
        return Company::query()->create([
            'name' => $name,
            'cnpj' => $cnpj,
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
        ]);
    }

    public function test_super_admin_sees_every_notification_and_a_common_user_only_those_of_their_company(): void
    {
        $a = $this->company('Empresa A', '11.111.111/0001-11');
        $b = $this->company('Empresa B', '22.222.222/0001-22');
        $publish = app(PublishCompanyNotice::class);

        $publish->handle(companyId: $a->id, title: 'Aviso da A', body: 'Corpo A');
        $publish->handle(companyId: $b->id, title: 'Aviso da B', body: 'Corpo B');
        $publish->handle(companyId: null, title: 'Aviso interno Talents', body: 'Corpo T', audience: CompanyNoticeAudience::Talents);

        $adminTitles = collect(
            $this->actingAs(User::factory()->talentsOwner()->create())
                ->getJson(route('admin.notices.recent'))
                ->assertOk()
                ->json('notices')
        )->pluck('title')->all();

        $this->assertEqualsCanonicalizing(['Aviso da A', 'Aviso da B', 'Aviso interno Talents'], $adminTitles);

        foreach ([User::factory()->companyUser($a->id)->create(), User::factory()->companyAdmin($a->id)->create()] as $client) {
            $this->actingAs($client)
                ->getJson(route('client.notices.recent'))
                ->assertOk()
                ->assertJsonPath('unread_count', 1)
                ->assertJsonCount(1, 'notices')
                ->assertJsonPath('notices.0.title', 'Aviso da A');
        }
    }
}
