<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Actions\Company\ResolveOrCreateCompanyEmployee;
use App\Actions\SyncAdminUserPermissions;
use App\Enums\AdminPermissionModule;
use App\Enums\FeedbackSessionStatus;
use App\Enums\PermissionAction;
use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\FeedbackAuditLog;
use App\Models\User;
use App\Support\Feedback\FeedbackCompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\Support\CreatesFeedbackFixtures;
use Tests\TestCase;

/**
 * Feedbacks, Fase 2 — unir cadastros de colaborador: sugestões só na empresa e só para quem administra,
 * pré-visualização com contagens e conflitos, execução em transação e idempotente, só o vínculo muda (sessão
 * assinada intacta), empresas diferentes recusadas, trilha de auditoria e resolvedor seguindo a união.
 */
class FeedbackEmployeeMergeTest extends TestCase
{
    use CreatesFeedbackFixtures;
    use RefreshDatabase;

    private Company $company;

    private User $admin;

    private User $leader;

    private CompanyEmployee $primary;

    private CompanyEmployee $duplicate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->company = $this->createFeedbackCompany();
        $this->admin = User::factory()->companyAdmin($this->company->id)->create();
        $this->leader = User::factory()->companyUser($this->company->id)->create();
        $this->seedFeedbackTemplate();
        $this->primary = $this->createFeedbackEmployee($this->company, $this->leader, ['name' => 'Camila Duarte', 'email' => 'camila@teste.local']);
        $this->duplicate = $this->createFeedbackEmployee($this->company, $this->leader, ['name' => 'camila DUARTE', 'email' => null]);
    }

    private function preview(?User $user = null, ?CompanyEmployee $primary = null, ?CompanyEmployee $absorbed = null)
    {
        return $this->actingAs($user ?? $this->admin)->get(route('client.feedbacks.employees.merge.preview', [
            'principal' => ($primary ?? $this->primary)->id,
            'absorvido' => ($absorbed ?? $this->duplicate)->id,
        ]));
    }

    private function merge(array $counts, ?User $user = null, bool $confirmed = true)
    {
        return $this->actingAs($user ?? $this->admin)
            ->from(route('client.feedbacks.employees.duplicates'))
            ->post(route('client.feedbacks.employees.merge'), [
                'principal' => $this->primary->id,
                'absorvido' => $this->duplicate->id,
                'counts' => $counts,
                'confirmed' => $confirmed,
            ]);
    }

    /** @return array<string, int> */
    private function currentCounts(): array
    {
        $counts = [];
        $this->preview()->assertInertia(function (AssertableInertia $page) use (&$counts) {
            $counts = $page->toArray()['props']['preview']['counts'];
        });

        return $counts;
    }

    public function test_duplicates_are_suggested_only_inside_the_company_and_only_to_managers(): void
    {
        $other = $this->createFeedbackCompany(['name' => 'Outra']);
        $this->createFeedbackEmployee($other, User::factory()->companyUser($other->id)->create(), ['name' => 'Camila Duarte', 'email' => 'camila@teste.local']);
        $this->createFeedbackEmployee($this->company, $this->leader, ['name' => 'Fulano', 'email' => 'CAMILA@teste.local']);

        $this->actingAs($this->admin)->get(route('client.feedbacks.employees.duplicates'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Client/Feedbacks/Employees/Duplicates')
                ->has('pairs', 2)
                ->where('pairs.0.reason', 'email')
                ->where('pairs.1.reason', 'same_name')
                ->where('pairs.1.a.id', $this->primary->id)
                ->where('pairs.1.b.id', $this->duplicate->id));

        $this->actingAs($this->leader)->get(route('client.feedbacks.employees.duplicates'))->assertForbidden();
        $this->assertSame(0, CompanyEmployee::query()->whereNotNull('merged_into_employee_id')->count(), 'sugestão nunca une sozinha');
    }

    public function test_preview_lists_what_moves_and_the_conflicts_without_changing_anything(): void
    {
        $this->createFeedbackSession($this->company, $this->leader, $this->primary, ['scheduled_at' => '2026-05-10 10:00:00']);
        $dupSession = $this->createFeedbackSession($this->company, $this->leader, $this->duplicate, ['scheduled_at' => '2026-05-20 10:00:00']);
        DB::table('feedback_session_signatures')->insert([
            'feedback_session_id' => $dupSession->id, 'role' => 'employee', 'signer_name' => 'Camila', 'signer_email' => 'c@t.local',
            'token' => 'tok-1', 'signed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('employee_leaves')->insert(['company_id' => $this->company->id, 'company_employee_id' => $this->duplicate->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-10', 'created_at' => now(), 'updated_at' => now()]);

        $this->preview()->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Client/Feedbacks/Employees/Merge')
            ->where('preview.counts.feedback_sessions', 1)
            ->where('preview.counts.employee_leaves', 1)
            ->where('preview.signatures', 1)
            ->where('preview.signed_signatures', 1)
            ->where('preview.conflicts.0.type', 'same_period'));

        $this->assertSame($this->duplicate->id, (int) $dupSession->fresh()->company_employee_id);
        $this->assertNull($this->duplicate->fresh()->merged_into_employee_id);
    }

    public function test_merge_moves_only_the_links_keeps_signed_session_intact_and_logs(): void
    {
        $signed = $this->createFeedbackSession($this->company, $this->leader, $this->duplicate, [
            'employee_name' => 'camila DUARTE',
            'status' => FeedbackSessionStatus::Completed,
            'completed_at' => now()->subDay(),
            'scheduled_at' => now()->subMonths(2),
        ]);
        DB::table('feedback_session_signatures')->insert([
            'feedback_session_id' => $signed->id, 'role' => 'employee', 'signer_name' => 'camila DUARTE', 'signer_email' => 'c@t.local',
            'token' => 'tok-2', 'signed_at' => now()->subDay(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('feedback_sessions')->where('id', $signed->id)->update(['updated_at' => '2026-01-01 00:00:00']);
        DB::table('exit_interviews')->insert(['company_id' => $this->company->id, 'company_employee_id' => $this->duplicate->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('company_monthly_highlights')->insert(['company_id' => $this->company->id, 'company_employee_id' => $this->duplicate->id, 'person_name' => 'Camila', 'category' => 'productivity', 'year' => 2026, 'month' => 5, 'created_at' => now(), 'updated_at' => now()]);
        $before = (array) DB::table('feedback_sessions')->where('id', $signed->id)->first();
        $signatureBefore = (array) DB::table('feedback_session_signatures')->where('feedback_session_id', $signed->id)->first();

        $this->merge($this->currentCounts(), confirmed: false)->assertSessionHasErrors('confirmed');
        $this->merge($this->currentCounts())->assertRedirect(route('client.feedbacks.employees.show', $this->primary));

        $after = (array) DB::table('feedback_sessions')->where('id', $signed->id)->first();
        $this->assertSame($this->primary->id, (int) $after['company_employee_id']);
        unset($before['company_employee_id'], $after['company_employee_id']);
        $this->assertEquals($before, $after, 'sessão assinada: só o vínculo muda (nome, status, datas e updated_at iguais)');
        $this->assertEquals($signatureBefore, (array) DB::table('feedback_session_signatures')->where('feedback_session_id', $signed->id)->first());

        $this->assertSame(0, DB::table('exit_interviews')->where('company_employee_id', $this->duplicate->id)->count());
        $this->assertSame(0, DB::table('company_monthly_highlights')->where('company_employee_id', $this->duplicate->id)->count());
        $this->assertSame(0, DB::table('feedback_sessions')->where('company_employee_id', $this->duplicate->id)->count(), 'nada órfão no absorvido');

        $absorbed = $this->duplicate->fresh();
        $this->assertFalse($absorbed->is_active);
        $this->assertSame($this->primary->id, (int) $absorbed->merged_into_employee_id);
        $this->assertSame($this->admin->id, (int) $absorbed->merged_by_user_id);
        $this->assertSame('Camila Duarte', $this->primary->fresh()->name, 'identidade do principal mantida');

        $log = FeedbackAuditLog::query()->where('action', FeedbackAuditLog::ACTION_EMPLOYEES_MERGED)->sole();
        $this->assertSame($this->primary->id, (int) $log->details['primary_employee_id']);
        $this->assertSame($this->duplicate->id, (int) $log->details['absorbed_employee_id']);
        $this->assertSame([$signed->id], $log->details['feedback_session_ids']);
    }

    public function test_merge_is_idempotent_and_rejects_stale_preview(): void
    {
        $this->createFeedbackSession($this->company, $this->leader, $this->duplicate);
        $counts = $this->currentCounts();

        // Outro feedback entrou depois da pré-visualização: recusa e pede nova conferência.
        $this->createFeedbackSession($this->company, $this->leader, $this->duplicate, ['title' => 'Novo']);
        $this->merge($counts)->assertSessionHasErrors('confirmed');
        $this->assertNull($this->duplicate->fresh()->merged_into_employee_id);

        $this->merge($this->currentCountsForMerge())->assertSessionHasNoErrors();
        $this->merge(['feedback_sessions' => 99])->assertSessionHasNoErrors();
        $this->assertSame(1, FeedbackAuditLog::query()->where('action', FeedbackAuditLog::ACTION_EMPLOYEES_MERGED)->count(), 'repetir não muda nada');
        $this->assertSame(2, DB::table('feedback_sessions')->where('company_employee_id', $this->primary->id)->count());
    }

    /** @return array<string, int> */
    private function currentCountsForMerge(): array
    {
        return $this->currentCounts();
    }

    public function test_never_merges_across_companies_and_requires_manager(): void
    {
        $other = $this->createFeedbackCompany(['name' => 'Outra']);
        $foreign = $this->createFeedbackEmployee($other, User::factory()->companyUser($other->id)->create(), ['name' => 'Camila Duarte']);

        $this->preview(absorbed: $foreign)->assertForbidden();
        $this->actingAs($this->admin)->post(route('client.feedbacks.employees.merge'), [
            'principal' => $this->primary->id, 'absorvido' => $foreign->id, 'counts' => ['feedback_sessions' => 0], 'confirmed' => true,
        ])->assertForbidden();

        $this->preview($this->leader)->assertForbidden();
        $this->merge($this->currentCounts(), $this->leader)->assertForbidden();

        $otherAdmin = User::factory()->companyAdmin($other->id)->create();
        $this->preview($otherAdmin)->assertForbidden();
        $this->assertSame(0, CompanyEmployee::query()->whereNotNull('merged_into_employee_id')->count());
    }

    public function test_talents_admin_needs_edit_and_the_selected_company(): void
    {
        $as = function (array $actions, ?Company $company = null) {
            $user = User::factory()->superAdmin()->create(['is_owner' => false]);
            app(SyncAdminUserPermissions::class)->execute($user->talentsWorkspace(), array_map(
                fn (PermissionAction $a) => ['module' => AdminPermissionModule::Feedbacks->value, 'action' => $a->value],
                $actions,
            ));

            return $this->actingAs($user)->withSession([FeedbackCompanyContext::SESSION_KEY => ($company ?? $this->company)->id]);
        };

        $as([PermissionAction::View])->get(route('admin.feedbacks.employees.duplicates'))->assertForbidden();
        $as([PermissionAction::View, PermissionAction::Edit])->get(route('admin.feedbacks.employees.duplicates'))->assertOk();
        $as([PermissionAction::View, PermissionAction::Edit], $this->createFeedbackCompany(['name' => 'Outra']))
            ->get(route('admin.feedbacks.employees.merge.preview', ['principal' => $this->primary->id, 'absorvido' => $this->duplicate->id]))
            ->assertForbidden();
    }

    public function test_resolver_follows_the_merge_and_absorbed_page_redirects(): void
    {
        $this->merge($this->currentCounts())->assertSessionHasNoErrors();

        $resolved = app(ResolveOrCreateCompanyEmployee::class)->execute($this->company, 'camila duarte');
        $this->assertSame($this->primary->id, $resolved->id, 'digitar o nome do absorvido leva ao principal');
        $this->assertFalse($this->duplicate->fresh()->is_active, 'o absorvido não é reativado');

        $this->actingAs($this->admin)->get(route('client.feedbacks.employees.show', $this->duplicate))
            ->assertRedirect(route('client.feedbacks.employees.show', $this->primary));
    }
}
