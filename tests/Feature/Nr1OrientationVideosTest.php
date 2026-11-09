<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Nr1OrientationVideo;
use App\Models\SurveyAudienceLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CreatesSurveyFixtures;
use Tests\Support\CreatesV11Campaign;
use Tests\TestCase;

/**
 * Biblioteca global de vídeos NR-1 e etapas obrigatórias de abertura/encerramento por campanha.
 */
class Nr1OrientationVideosTest extends TestCase
{
    use CreatesSurveyFixtures;
    use CreatesV11Campaign;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function talents(): User
    {
        return User::factory()->talentsOwner()->create(['name' => 'Talents Owner']);
    }

    private function storeVideo(User $actor, string $role = 'intro', string $title = 'Orientação padrão'): Nr1OrientationVideo
    {
        $this->actingAs($actor)->post(route('admin.nr1-videos.store'), [
            'title' => $title,
            'role' => $role,
            'file' => UploadedFile::fake()->create('orientacao.mp4', 1200, 'video/mp4'),
        ])->assertRedirect();

        return Nr1OrientationVideo::query()->where('title', $title)->firstOrFail();
    }

    public function test_talents_can_upload_and_list_library_videos(): void
    {
        $talents = $this->talents();
        $video = $this->storeVideo($talents);

        $this->assertTrue(Storage::disk('local')->exists($video->path));
        $this->assertSame('intro', $video->role);
        $this->assertTrue($video->is_active);

        $this->actingAs($talents)->get(route('admin.nr1-videos.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Nr1Videos/Index')
                ->has('videos', 1)
                ->where('videos.0.title', 'Orientação padrão')
                ->where('videos.0.role', 'intro'));
    }

    public function test_campaign_can_require_intro_and_closing_videos(): void
    {
        $campaign = $this->v11Campaign();
        $survey = $campaign->survey;
        $talents = $this->talents();
        $intro = $this->storeVideo($talents, 'intro', 'Abertura');
        $closing = $this->storeVideo($talents, 'closing', 'Encerramento');

        $this->actingAs($talents)->post(route('admin.companies.surveys.campaign.videos', [$campaign->company->id, $survey->id]), [
            'intro_video_required' => true,
            'closing_video_required' => true,
            'intro_video_id' => $intro->id,
            'closing_video_id' => $closing->id,
        ])->assertRedirect();

        $survey->refresh();
        $this->assertTrue($survey->intro_video_required);
        $this->assertTrue($survey->closing_video_required);
        $this->assertSame($intro->id, (int) $survey->intro_video_id);
        $this->assertSame($closing->id, (int) $survey->closing_video_id);
    }

    public function test_required_video_without_selection_is_rejected(): void
    {
        $campaign = $this->v11Campaign();
        $talents = $this->talents();

        $this->actingAs($talents)->from(route('admin.companies.surveys.campaign.show', [$campaign->company->id, $campaign->survey->id]))
            ->post(route('admin.companies.surveys.campaign.videos', [$campaign->company->id, $campaign->survey->id]), [
                'intro_video_required' => true,
                'closing_video_required' => false,
                'intro_video_id' => null,
                'closing_video_id' => null,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_public_take_receives_video_urls_when_required(): void
    {
        $campaign = $this->v11Campaign();
        $survey = $campaign->survey;
        $talents = $this->talents();
        $intro = $this->storeVideo($talents, 'intro', 'Abertura');
        $closing = $this->storeVideo($talents, 'closing', 'Encerramento');

        $survey->forceFill([
            'intro_video_required' => true,
            'closing_video_required' => true,
            'intro_video_id' => $intro->id,
            'closing_video_id' => $closing->id,
        ])->save();

        $link = SurveyAudienceLink::query()->create([
            'survey_id' => $survey->id,
            'audience' => 'time',
            'token' => Str::random(40),
            'enabled' => true,
        ]);

        $this->get(route('survey.public', $link->token))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Survey/Take')
                ->where('videos.intro.required', true)
                ->where('videos.intro.id', $intro->id)
                ->where('videos.closing.required', true)
                ->where('videos.closing.id', $closing->id)
                ->where('videos.intro.url', route('survey.public.orientation-video', [$link->token, $intro->id])));
    }

    public function test_public_video_stream_is_gated_by_campaign_requirement(): void
    {
        $campaign = $this->v11Campaign();
        $survey = $campaign->survey;
        $talents = $this->talents();
        $intro = $this->storeVideo($talents, 'intro', 'Abertura');
        $other = $this->storeVideo($talents, 'closing', 'Outro');

        $survey->forceFill([
            'intro_video_required' => true,
            'intro_video_id' => $intro->id,
        ])->save();

        $link = SurveyAudienceLink::query()->create([
            'survey_id' => $survey->id,
            'audience' => 'time',
            'token' => Str::random(40),
            'enabled' => true,
        ]);

        $this->get(route('survey.public.orientation-video', [$link->token, $intro->id]))->assertOk();
        $this->get(route('survey.public.orientation-video', [$link->token, $other->id]))->assertNotFound();
        $this->get(route('survey.public.orientation-video', ['token-inexistente', $intro->id]))->assertNotFound();
    }

    public function test_company_user_cannot_manage_library(): void
    {
        $campaign = $this->v11Campaign();
        $admin = User::factory()->companyAdmin($campaign->company->id)->create();

        $this->actingAs($admin)->get(route('admin.nr1-videos.index'))->assertForbidden();
    }
}
