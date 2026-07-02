<?php

namespace Tests\Unit;

use App\Models\SurveyNr1Report;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSurveyFixtures;
use Tests\TestCase;

class SurveyNr1ReportTest extends TestCase
{
    use CreatesSurveyFixtures;
    use RefreshDatabase;

    public function test_is_published_requires_published_at_and_file_path(): void
    {
        $fx = $this->createSurveyFixture();

        $draft = SurveyNr1Report::query()->create([
            'survey_id' => $fx->survey->id,
            'company_id' => $fx->company->id,
            'type' => SurveyNr1Report::TYPE_EXECUTIVE,
            'file_path' => 'path/draft.pdf',
            'file_name' => 'draft.pdf',
            'published_at' => null,
        ]);

        $published = SurveyNr1Report::query()->create([
            'survey_id' => $fx->survey->id,
            'company_id' => $fx->company->id,
            'type' => SurveyNr1Report::TYPE_TECHNICAL_REFERRAL,
            'file_path' => 'path/final.pdf',
            'file_name' => 'final.pdf',
            'published_at' => now(),
        ]);

        $this->assertFalse($draft->isPublished());
        $this->assertTrue($published->isPublished());
    }
}
