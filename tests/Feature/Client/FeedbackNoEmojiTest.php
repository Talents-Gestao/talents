<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Models\FeedbackSession;
use App\Models\FeedbackTemplate;
use App\Models\User;
use App\Support\Feedback\FeedbackSessionStructure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;
use Tests\Support\CreatesFeedbackFixtures;
use Tests\TestCase;

/**
 * A interface de Feedbacks não usa emojis (os ícones são de linha, Heroicons). Falha se aparecer emoji nos
 * seeders e factories, nas fontes do front-end do módulo, nas views do PDF e e-mails, ou nas páginas renderizadas.
 */
class FeedbackNoEmojiTest extends TestCase
{
    use CreatesFeedbackFixtures;
    use RefreshDatabase;

    /** Pictogramas, símbolos e dingbats com apresentação de emoji, e o seletor de variação FE0F. */
    private const EMOJI = '/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE0F}]/u';

    private function assertNoEmoji(string $content, string $where): void
    {
        $this->assertSame(0, preg_match(self::EMOJI, $content, $m), 'Emoji «'.($m[0] ?? '')."» encontrado em {$where}.");
    }

    /** @param list<string> $paths */
    private function assertFilesHaveNoEmoji(array $paths): void
    {
        $checked = 0;
        foreach ($paths as $path) {
            $files = is_dir(base_path($path)) ? File::allFiles(base_path($path)) : [new SplFileInfo(base_path($path), '', $path)];
            foreach ($files as $file) {
                // Só arquivos de texto (há .docx/.pdf de exemplo nos seeders).
                if (! in_array(strtolower($file->getExtension()), ['php', 'vue', 'js', 'mjs', 'txt', 'json', 'csv', 'md', 'html'], true)) {
                    continue;
                }
                $this->assertNoEmoji((string) file_get_contents($file->getPathname()), $file->getRelativePathname() ?: $path);
                $checked++;
            }
        }
        $this->assertGreaterThan(0, $checked);
    }

    public function test_seeders_and_factories_have_no_emoji(): void
    {
        $this->assertFilesHaveNoEmoji(['database/seeders', 'database/factories']);
    }

    public function test_feedback_front_end_and_views_have_no_emoji(): void
    {
        $this->assertFilesHaveNoEmoji([
            'resources/js/Pages/Client/Feedbacks',
            'resources/js/Components/Feedback',
            'resources/js/utils/feedbackSectionIcons.js',
            'resources/js/utils/feedbackRecordingSteps.js',
            'resources/views/reports/feedback-session.blade.php',
        ]);
        foreach (File::glob(resource_path('views/emails/*feedback*')) ?: [] as $mail) {
            $this->assertNoEmoji((string) file_get_contents($mail), basename($mail));
        }
    }

    public function test_rendered_feedback_pages_and_pdf_have_no_emoji(): void
    {
        $this->withoutVite();
        $company = $this->createFeedbackCompany();
        $admin = User::factory()->companyAdmin($company->id)->create();
        $leader = User::factory()->companyUser($company->id)->create();
        $employee = $this->createFeedbackEmployee($company, $leader);
        $this->seedFeedbackTemplate();
        $session = $this->createFeedbackSession($company, $leader, $employee);

        $this->actingAs($admin)->post(route('client.feedbacks.templates.duplicate', FeedbackTemplate::query()->whereNull('company_id')->firstOrFail()));
        $companyTemplate = FeedbackTemplate::query()->where('company_id', $company->id)->firstOrFail();

        $pages = [
            route('client.feedbacks.index'),
            route('client.feedbacks.sessions.index'),
            route('client.feedbacks.sessions.create'),
            route('client.feedbacks.sessions.show', $session),
            route('client.feedbacks.sessions.edit', $session),
            route('client.feedbacks.templates.index'),
            route('client.feedbacks.templates.create'),
            route('client.feedbacks.templates.edit', $companyTemplate),
        ];
        foreach ($pages as $url) {
            $response = $this->actingAs($admin)->get($url)->assertOk();
            $this->assertNoEmoji((string) $response->getContent(), $url);
        }

        $session = FeedbackSession::query()->with(['employee.department', 'employee.position', 'leader', 'templateVersion.sections.questions', 'answers.question', 'signatures'])->findOrFail($session->id);
        $html = view('reports.feedback-session', [
            'session' => $session,
            'version' => FeedbackSessionStructure::version($session),
            'sections' => FeedbackSessionStructure::sections($session, true),
            'outOfVersionAnswers' => FeedbackSessionStructure::outOfVersionAnswers($session, true),
            'outOfVersionExtras' => FeedbackSessionStructure::outOfVersionExtras($session, true),
            'answersByQuestion' => $session->answers->keyBy('feedback_template_question_id'),
            'sectionExtras' => $session->section_extras ?? [],
        ])->render();
        $this->assertNoEmoji($html, 'PDF do feedback');
    }
}
