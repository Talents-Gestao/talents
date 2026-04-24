<?php

namespace Tests\Feature\Client;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonSchedulePreferenceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_batch_schedule_preferences(): void
    {
        $this->postJson(route('client.rhid.api.people.schedule-preferences.batch'), [
            'id_people' => [1],
            'use_second_lunch_interval' => true,
        ])->assertUnauthorized();
    }

    public function test_batch_sets_second_lunch_for_multiple_people(): void
    {
        $company = Company::query()->create(['name' => 'C']);
        $this->subscribeCompanyToNr1($company);
        $admin = User::factory()->companyAdmin($company->id)->create();

        $this->actingAs($admin)
            ->postJson(route('client.rhid.api.people.schedule-preferences.batch'), [
                'id_people' => [10, 20, 10],
                'use_second_lunch_interval' => true,
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('updated', 2);

        $this->assertDatabaseHas('rhid_person_schedule_preferences', [
            'company_id' => $company->id,
            'id_person' => 10,
            'use_second_lunch_interval' => true,
        ]);
    }

    public function test_batch_clears_when_false(): void
    {
        $company = Company::query()->create(['name' => 'C2']);
        $this->subscribeCompanyToNr1($company);
        $admin = User::factory()->companyAdmin($company->id)->create();

        $this->actingAs($admin)
            ->postJson(route('client.rhid.api.people.schedule-preferences.batch'), [
                'id_people' => [5],
                'use_second_lunch_interval' => true,
            ])
            ->assertOk();

        $this->actingAs($admin)
            ->postJson(route('client.rhid.api.people.schedule-preferences.batch'), [
                'id_people' => [5],
                'use_second_lunch_interval' => false,
            ])
            ->assertOk();

        $this->assertDatabaseMissing('rhid_person_schedule_preferences', [
            'company_id' => $company->id,
            'id_person' => 5,
        ]);
    }
}
