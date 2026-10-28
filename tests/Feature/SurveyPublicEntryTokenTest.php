<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SurveyAudienceLink;
use App\Services\SurveyPublicEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\CreatesSurveyFixtures;
use Tests\TestCase;

/**
 * Regressão: `surveys.public_token` é uuid. Em PostgreSQL, comparar a coluna com um token de texto (o do link por público)
 * lança erro e a página pública responde 500. A resolução só consulta a campanha pelo token quando ele é um uuid.
 */
class SurveyPublicEntryTokenTest extends TestCase
{
    use CreatesSurveyFixtures;
    use RefreshDatabase;

    public function test_a_text_audience_token_never_hits_the_uuid_column_of_surveys(): void
    {
        $fx = $this->createSurveyFixture();
        $link = SurveyAudienceLink::query()->create(['survey_id' => $fx->survey->id, 'audience' => 'time', 'token' => Str::random(40), 'enabled' => true]);

        $queried = [];
        DB::listen(function ($query) use (&$queried, $link): void {
            if (str_contains($query->sql, '"public_token"') && in_array($link->token, $query->bindings, true)) {
                $queried[] = $query->sql;
            }
        });

        $entry = SurveyPublicEntry::resolve($link->token);

        $this->assertSame($fx->survey->id, $entry['survey']->id);
        $this->assertSame($link->id, $entry['link']->id);
        $this->assertSame([], $queried);
        $this->assertNull(SurveyPublicEntry::resolve('token-que-nao-existe'));
    }

    public function test_the_general_uuid_link_still_resolves_when_there_are_no_audience_links(): void
    {
        $fx = $this->createSurveyFixture();

        $entry = SurveyPublicEntry::resolve((string) $fx->survey->public_token);

        $this->assertSame($fx->survey->id, $entry['survey']->id);
        $this->assertNull($entry['link']);
    }
}
