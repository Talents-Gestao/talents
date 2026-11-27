<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Hiring;

use App\Enums\CompanyContactEventType;
use App\Enums\CompanyContactTopic;
use App\Enums\HiringCommunicationChannel;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\CompanyContactEvent;
use App\Support\Hiring\CompanyContactEligibility;
use Inertia\Testing\AssertableInertia;

class CompanyContactTest extends CommunicationTestCase
{
    private function company(string $name = 'Empresa Contatos'): Company
    {
        return Company::query()->create(['name' => $name, 'is_active' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Bruno Diretor',
            'role' => 'Diretoria',
            'email' => 'Bruno@Empresa.test',
            'phone' => '+55 11 94444-0000',
            'channel_email' => true,
            'channel_whatsapp' => false,
            'topics' => ['vagas', 'pesquisas'],
            'is_enabled' => true,
            'is_primary' => false,
            'notes' => null,
            ...$overrides,
        ];
    }

    public function test_eligibility_requires_enabled_reviewed_active_topic_and_channel(): void
    {
        $company = $this->company();
        $ok = $this->contact($company);
        $vagas = CompanyContactTopic::Vagas;
        $email = HiringCommunicationChannel::Email;

        $this->assertTrue(CompanyContactEligibility::isEligible($ok, $vagas, $email));

        $cases = [
            'desabilitado' => [['is_enabled' => false], 'Contato desabilitado.'],
            'não conferido' => [['reviewed_at' => null], 'Contato ainda não conferido.'],
            'arquivado' => [['archived_at' => now()], 'Contato arquivado.'],
            'sem tópico' => [['topics' => ['pesquisas']], 'Contato não aceita o tópico «Vagas».'],
            'sem canal' => [['channel_email' => false], 'Canal e-mail não autorizado.'],
            'sem e-mail' => [['email' => null], 'Contato sem e-mail.'],
        ];

        foreach ($cases as $label => [$overrides, $reason]) {
            $contact = $this->contact($company, ['name' => 'Caso '.$label, ...$overrides]);
            $this->assertFalse(CompanyContactEligibility::isEligible($contact, $vagas, $email), $label);
            $this->assertContains($reason, CompanyContactEligibility::reasons($contact, $vagas, $email), $label);
        }

        $whatsOnly = $this->contact($company, ['name' => 'Só e-mail', 'channel_whatsapp' => false]);
        $this->assertFalse(CompanyContactEligibility::isEligible($whatsOnly, $vagas, HiringCommunicationChannel::Whatsapp));
        $this->assertSame(['email'], CompanyContactEligibility::channelsFor($whatsOnly, $vagas));

        $this->assertSame([$ok->id, $whatsOnly->id], CompanyContactEligibility::eligibleFor($company->id, $vagas, $email)->pluck('id')->sort()->values()->all());
    }

    public function test_changing_email_or_phone_resets_review(): void
    {
        $company = $this->company();
        $owner = $this->owner();
        $contact = $this->contact($company);
        $this->assertNotNull($contact->reviewed_at);

        $this->actingAs($owner)
            ->patch(route('admin.companies.contacts.update', [$company, $contact]), $this->payload([
                'name' => $contact->name,
                'email' => 'nova@empresa.test',
                'phone' => $contact->phone,
            ]))
            ->assertSessionHasNoErrors();
        $this->assertNull($contact->fresh()->reviewed_at);

        $this->actingAs($owner)->post(route('admin.companies.contacts.review', [$company, $contact]))->assertSessionHasNoErrors();
        $this->assertNotNull($contact->fresh()->reviewed_at);

        $contact->fresh()->update(['phone' => '+55 11 93333-0000']);
        $this->assertNull($contact->fresh()->reviewed_at);

        // Mudar só o nome mantém a conferência.
        $this->actingAs($owner)->post(route('admin.companies.contacts.review', [$company, $contact]));
        $contact->fresh()->update(['name' => 'Carla Souza']);
        $this->assertNotNull($contact->fresh()->reviewed_at);
    }

    public function test_only_one_primary_contact_per_company(): void
    {
        $company = $this->company();
        $other = $this->company('Outra empresa');
        $owner = $this->owner();
        $first = $this->contact($company, ['is_primary' => true]);
        $otherPrimary = $this->contact($other, ['is_primary' => true]);

        $this->actingAs($owner)
            ->post(route('admin.companies.contacts.store', $company), $this->payload(['is_primary' => true]))
            ->assertSessionHasNoErrors();

        $second = CompanyContact::query()->where('name', 'Bruno Diretor')->firstOrFail();
        $this->assertTrue($second->is_primary);
        $this->assertFalse($first->fresh()->is_primary);
        $this->assertTrue($otherPrimary->fresh()->is_primary);
        $this->assertSame(1, CompanyContact::query()->where('company_id', $company->id)->where('is_primary', true)->count());
        $this->assertSame('bruno@empresa.test', $second->email);
        $this->assertSame('5511944440000', $second->phone_digits);
    }

    public function test_events_are_insert_only_with_changed_field_names(): void
    {
        $company = $this->company();
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('admin.companies.contacts.store', $company), $this->payload());
        $contact = CompanyContact::query()->firstOrFail();
        $this->actingAs($owner)->patch(route('admin.companies.contacts.update', [$company, $contact]), $this->payload(['role' => 'RH', 'notes' => 'Prefere e-mail']));
        $this->actingAs($owner)->post(route('admin.companies.contacts.archive', [$company, $contact]));
        $this->actingAs($owner)->post(route('admin.companies.contacts.restore', [$company, $contact]));

        $events = CompanyContactEvent::query()->where('company_contact_id', $contact->id)->orderBy('id')->get();
        $this->assertSame(
            [CompanyContactEventType::Created, CompanyContactEventType::Updated, CompanyContactEventType::Archived, CompanyContactEventType::Restored],
            $events->pluck('type')->all(),
        );
        $this->assertEqualsCanonicalizing(['role', 'notes'], $events[1]->meta['fields']);
        $this->assertStringNotContainsString('Prefere', json_encode($events[1]->meta));

        $this->expectException(\LogicException::class);
        $events[0]->delete();
    }

    public function test_contacts_tab_permissions(): void
    {
        $company = $this->company();
        $contact = $this->contact($company);

        $viewer = $this->adminWith(['companies' => ['view']]);
        $this->actingAs($viewer)
            ->get(route('admin.companies.show', ['company' => $company, 'tab' => 'contatos']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('tab', 'contatos')
                ->has('contacts', 1)
                ->where('contacts.0.name', 'Carla RH')
                ->where('contacts.0.eligibility.0.channels', ['email', 'whatsapp']));

        $this->actingAs($viewer)->post(route('admin.companies.contacts.store', $company), $this->payload())->assertForbidden();
        $this->actingAs($viewer)->patch(route('admin.companies.contacts.update', [$company, $contact]), $this->payload())->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.companies.contacts.review', [$company, $contact]))->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.companies.contacts.archive', [$company, $contact]))->assertForbidden();
        $this->assertNull($contact->fresh()->archived_at);

        // companies:edit sem companies:create basta para criar contato.
        $editor = $this->adminWith(['companies' => ['view', 'edit']]);
        $this->actingAs($editor)->post(route('admin.companies.contacts.store', $company), $this->payload())->assertSessionHasNoErrors();
        $this->actingAs($editor)->post(route('admin.companies.contacts.archive', [$company, $contact]))->assertSessionHasNoErrors();
        $this->assertNotNull($contact->fresh()->archived_at);

        $nobody = $this->adminWith(['tarefas' => ['view']]);
        $this->actingAs($nobody)->get(route('admin.companies.show', ['company' => $company, 'tab' => 'contatos']))->assertForbidden();
    }

    public function test_contact_of_another_company_returns_404_and_phone_must_be_international(): void
    {
        $owner = $this->owner();
        $company = $this->company();
        $other = $this->company('Outra');
        $foreign = $this->contact($other);

        $this->actingAs($owner)->patch(route('admin.companies.contacts.update', [$company, $foreign]), $this->payload())->assertNotFound();
        $this->actingAs($owner)->post(route('admin.companies.contacts.archive', [$company, $foreign]))->assertNotFound();
        $this->assertNull($foreign->fresh()->archived_at);

        $this->actingAs($owner)
            ->post(route('admin.companies.contacts.store', $company), $this->payload(['phone' => '11 94444-0000']))
            ->assertSessionHasErrors('phone');
        $this->actingAs($owner)
            ->post(route('admin.companies.contacts.store', $company), $this->payload(['email' => null, 'channel_email' => true]))
            ->assertSessionHasErrors('email');
    }
}
