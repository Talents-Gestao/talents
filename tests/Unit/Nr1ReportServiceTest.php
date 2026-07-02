<?php

namespace Tests\Unit;

use App\Models\SurveyNr1Report;
use App\Services\Nr1ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\Support\CreatesSurveyFixtures;
use Tests\Support\SeedsNr1SurveyResults;
use Tests\TestCase;

class Nr1ReportServiceTest extends TestCase
{
    use CreatesSurveyFixtures;
    use RefreshDatabase;
    use SeedsNr1SurveyResults;

    public function test_scenario_meta_returns_label_for_red_scenario(): void
    {
        $fx = $this->createSurveyFixture();
        $this->seedNr1OverallAndSectionResult($fx, 'red');

        $meta = app(Nr1ReportService::class)->scenarioMeta($fx->survey->fresh());

        $this->assertSame('red', $meta['scenario']);
        $this->assertSame('Cenário geral: Alto risco', $meta['scenario_label']);
        $this->assertSame('intervencao_imediata', $meta['config']['action_plan']['item_kind']);
    }

    public function test_stream_executive_downloads_published_upload_instead_of_generating_pdf(): void
    {
        Storage::fake('local');

        $fx = $this->createSurveyFixture();
        $this->seedNr1OverallAndSectionResult($fx, 'green', 2.0);

        $path = 'survey-nr1-reports/'.$fx->survey->id.'/executive/custom.pdf';
        Storage::disk('local')->put($path, 'conteudo-teste');

        SurveyNr1Report::query()->create([
            'survey_id' => $fx->survey->id,
            'company_id' => $fx->company->id,
            'type' => SurveyNr1Report::TYPE_EXECUTIVE,
            'file_path' => $path,
            'file_name' => 'custom-executivo.pdf',
            'published_at' => now(),
        ]);

        $response = app(Nr1ReportService::class)->streamExecutive($fx->survey->fresh());

        $this->assertInstanceOf(StreamedResponse::class, $response);
    }

    public function test_unpublished_upload_is_ignored(): void
    {
        Storage::fake('local');

        $fx = $this->createSurveyFixture();
        $this->seedNr1OverallAndSectionResult($fx, 'green', 2.0);

        $path = 'survey-nr1-reports/'.$fx->survey->id.'/executive/draft.pdf';
        Storage::disk('local')->put($path, 'rascunho');

        SurveyNr1Report::query()->create([
            'survey_id' => $fx->survey->id,
            'company_id' => $fx->company->id,
            'type' => SurveyNr1Report::TYPE_EXECUTIVE,
            'file_path' => $path,
            'file_name' => 'rascunho.pdf',
            'published_at' => null,
        ]);

        $response = app(Nr1ReportService::class)->streamExecutive($fx->survey->fresh());

        $this->assertNotInstanceOf(StreamedResponse::class, $response);
    }
}
