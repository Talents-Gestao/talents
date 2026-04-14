<?php

namespace Tests\Feature\Client;

use App\Models\RhidEspelhoImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSurveyFixtures;
use Tests\TestCase;

class RhidEspelhoApiTest extends TestCase
{
    use CreatesSurveyFixtures;
    use RefreshDatabase;

    public function test_guest_cannot_store_espelho_pdf(): void
    {
        $this->postJson(route('client.rhid.api.espelhos.store'), [
            'guid' => 'abc',
            'id_person' => 1,
            'ini' => '20260401',
            'fim' => '20260414',
        ])->assertUnauthorized();
    }

    public function test_store_espelho_validates_required_fields(): void
    {
        $fx = $this->createSurveyFixture();
        $user = User::factory()->companyAdmin($fx->company->id)->create();

        $this->actingAs($user)
            ->postJson(route('client.rhid.api.espelhos.store'), [])
            ->assertUnprocessable();
    }

    public function test_cannot_view_espelho_import_from_other_company(): void
    {
        $fxA = $this->createSurveyFixture();
        $fxB = $this->createSurveyFixture();

        $import = RhidEspelhoImport::query()->create([
            'company_id' => $fxB->company->id,
            'user_id' => null,
            'id_person' => 99,
            'period_ini' => '2026-04-01',
            'period_fim' => '2026-04-14',
            'guid' => 'test-guid',
            'storage_path' => 'rhid-espelhos/'.$fxB->company->id.'/x.pdf',
            'file_hash' => null,
            'source' => 'api',
            'parse_status' => 'pending',
        ]);

        $userA = User::factory()->companyAdmin($fxA->company->id)->create();

        $this->actingAs($userA)
            ->getJson(route('client.rhid.api.espelhos.imports.show', $import->id))
            ->assertNotFound();
    }
}
