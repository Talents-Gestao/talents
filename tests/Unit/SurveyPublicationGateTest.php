<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\ActionPlan;
use App\Models\Survey;
use App\Models\SurveyMethodVersion;
use App\Support\SurveyPublicationGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSurveyFixtures;
use Tests\Support\CreatesV11Campaign;
use Tests\TestCase;

class SurveyPublicationGateTest extends TestCase
{
    use CreatesSurveyFixtures;
    use CreatesV11Campaign;
    use RefreshDatabase;

    public function test_legacy_parecer_publication_opens_the_company_gate_without_published_at(): void
    {
        $fx = $this->createSurveyFixture();
        $fx->survey->forceFill(['requires_publication' => true, 'published_at' => null])->save();

        $this->assertFalse(SurveyPublicationGate::allows($fx->survey->fresh()));
        $this->assertFalse(SurveyPublicationGate::isPublished($fx->survey->fresh()));
        $this->assertSame(0, SurveyPublicationGate::scopeVisible(Survey::query()->whereKey($fx->survey->id))->count());

        ActionPlan::query()->create([
            'company_id' => $fx->company->id,
            'survey_id' => $fx->survey->id,
            'technical_opinion' => '<p>Parecer</p>',
            'admin_published_at' => now(),
        ]);

        $survey = $fx->survey->fresh();
        $this->assertTrue(SurveyPublicationGate::isPublished($survey));
        $this->assertTrue(SurveyPublicationGate::allows($survey));
        $this->assertSame(1, SurveyPublicationGate::scopeVisible(Survey::query()->whereKey($survey->id))->count());
    }

    public function test_v11_parecer_alone_does_not_open_the_company_gate(): void
    {
        $campaign = $this->v11Campaign();
        $campaign->survey->forceFill(['requires_publication' => true, 'published_at' => null])->save();

        ActionPlan::query()->create([
            'company_id' => $campaign->company->id,
            'survey_id' => $campaign->survey->id,
            'technical_opinion' => '<p>Parecer interno V11</p>',
            'admin_published_at' => now(),
        ]);

        $survey = $campaign->survey->fresh();
        $this->assertTrue($survey->usesReviewFlow());
        $this->assertSame(SurveyMethodVersion::CODE_TALENTS39_V1, $survey->methodVersion?->code);
        $this->assertFalse(SurveyPublicationGate::isPublished($survey));
        $this->assertFalse(SurveyPublicationGate::allows($survey));
        $this->assertSame(0, SurveyPublicationGate::scopeVisible(Survey::query()->whereKey($survey->id))->count());
    }

    public function test_published_at_opens_the_gate_for_any_method(): void
    {
        $campaign = $this->v11Campaign();
        $campaign->survey->forceFill(['requires_publication' => true, 'published_at' => now()])->save();

        $this->assertTrue(SurveyPublicationGate::allows($campaign->survey->fresh()));
        $this->assertSame(1, SurveyPublicationGate::scopeVisible(Survey::query()->whereKey($campaign->survey->id))->count());
    }
}
