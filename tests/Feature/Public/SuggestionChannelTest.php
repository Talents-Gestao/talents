<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Models\Company;
use App\Models\Module;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\TeamSuggestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SuggestionChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_form_stores_anonymous_suggestion_and_discards_contact_without_reply_request(): void
    {
        $token = (string) Str::uuid();
        $company = Company::query()->create([
            'name' => 'Empresa Sugestões',
            'cnpj' => '66.666.666/0001-66',
            'is_active' => true,
            'complaints_public_token' => $token,
        ]);

        $this->get(route('sugestao.create', $token))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Suggestion/Submit')
                ->where('companyName', 'Empresa Sugestões'));

        $this->post(route('sugestao.store', $token), [
            'topic' => 'ideia',
            'area' => 'rh',
            'message' => 'Podemos organizar um café de boas-vindas para quem entra no time.',
            'response_preference' => 'nao',
            'reporter_name' => 'Ana Souza',
            'contact' => 'pessoa@example.com',
        ])->assertRedirect(route('sugestao.thanks', $token));

        $suggestion = TeamSuggestion::query()->first();
        $this->assertNotNull($suggestion);
        $this->assertSame($company->id, $suggestion->company_id);
        $this->assertSame('ideia', $suggestion->topic);
        $this->assertSame('rh', $suggestion->area);
        $this->assertSame('nao', $suggestion->response_preference);
        $this->assertNull($suggestion->reporter_name);
        $this->assertNull($suggestion->contact);
        $this->assertSame(
            'Podemos organizar um café de boas-vindas para quem entra no time.',
            $suggestion->message,
        );

        $raw = DB::table('team_suggestions')->value('message');
        $this->assertNotSame($suggestion->message, $raw);

        $this->get(route('sugestao.thanks', $token))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Suggestion/Thanks'));
    }

    public function test_public_store_keeps_contact_when_reply_is_requested(): void
    {
        $token = (string) Str::uuid();
        Company::query()->create([
            'name' => 'Empresa Retorno',
            'cnpj' => '77.777.777/0001-77',
            'is_active' => true,
            'complaints_public_token' => $token,
        ]);

        $this->post(route('sugestao.store', $token), [
            'topic' => 'duvida',
            'message' => 'Como funciona o banco de horas neste mês?',
            'response_preference' => 'sim',
            'reporter_name' => 'Ana Souza',
            'contact' => '11 98888-0000',
        ])->assertRedirect();

        $suggestion = TeamSuggestion::query()->first();
        $this->assertSame('Ana Souza', $suggestion?->reporter_name);
        $this->assertSame('11 98888-0000', $suggestion?->contact);
    }

    public function test_public_store_requires_contact_when_reply_is_requested(): void
    {
        $token = (string) Str::uuid();
        Company::query()->create([
            'name' => 'Empresa Contato',
            'cnpj' => '88.888.888/0001-88',
            'is_active' => true,
            'complaints_public_token' => $token,
        ]);

        $this->from(route('sugestao.create', $token))
            ->post(route('sugestao.store', $token), [
                'topic' => 'duvida',
                'message' => 'Como funciona o banco de horas neste mês?',
                'response_preference' => 'sim',
            ])
            ->assertRedirect(route('sugestao.create', $token))
            ->assertSessionHasErrors(['reporter_name', 'contact']);

        $this->assertSame(0, TeamSuggestion::query()->count());
    }

    public function test_unknown_token_is_not_found(): void
    {
        $this->get(route('sugestao.create', (string) Str::uuid()))->assertNotFound();
    }

    public function test_company_admin_lists_suggestion_when_module_is_enabled(): void
    {
        $token = (string) Str::uuid();
        $company = $this->companyWithSuggestions();
        $company->update(['complaints_public_token' => $token]);

        TeamSuggestion::query()->create([
            'company_id' => $company->id,
            'topic' => 'feedback',
            'area' => 'atendimento',
            'message' => 'O atendimento interno poderia ter um canal mais claro.',
            'response_preference' => 'apenas_sugestao',
            'status' => 'new',
        ]);

        $admin = User::factory()->companyAdmin($company->id)->create();

        $this->assertTrue($admin->canAccess(PermissionModule::Sugestoes, PermissionAction::View));

        $this->actingAs($admin)
            ->get(route('client.suggestions.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Client/Suggestions/Index')
                ->where('suggestions.data.0.topic_label', 'Feedback')
                ->where('publicUrl', route('sugestao.create', $token)));
    }

    public function test_company_admin_cannot_open_channel_when_module_is_disabled(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa sem canal',
            'cnpj' => '99.999.999/0001-99',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
        ]);
        $admin = User::factory()->companyAdmin($company->id)->create();

        $this->actingAs($admin)
            ->get(route('client.suggestions.index'))
            ->assertForbidden();
    }

    private function companyWithSuggestions(): Company
    {
        $company = Company::query()->create([
            'name' => 'Empresa com canal',
            'cnpj' => '12.123.123/0001-12',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
        ]);

        $module = Module::query()->firstOrCreate(
            ['key' => Module::KEY_SUGESTOES],
            ['name' => 'Sugestões', 'description' => 'Teste'],
        );

        $plan = Plan::query()->create([
            'name' => 'Plano sugestões',
            'slug' => 'sugestoes-'.Str::random(8),
            'price_monthly_cents' => 0,
            'is_active' => true,
        ]);
        $plan->modules()->sync([$module->id]);

        Subscription::query()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->toDateString(),
        ]);

        return $company->fresh();
    }
}
