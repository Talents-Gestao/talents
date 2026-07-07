<?php

declare(strict_types=1);

namespace Tests\Unit\Feedback;

use App\Actions\Feedback\SendFeedbackSignatureInvites;
use App\Enums\FeedbackSessionStatus;
use App\Mail\FeedbackSignatureInvitationMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesFeedbackFixtures;
use Tests\TestCase;

class SendFeedbackSignatureInvitesTest extends TestCase
{
    use CreatesFeedbackFixtures;
    use RefreshDatabase;

    public function test_execute_creates_two_signatures_and_sends_mails(): void
    {
        Mail::fake();

        $company = $this->createFeedbackCompany();
        $leader = User::factory()->companyAdmin($company->id)->create();
        $employee = $this->createFeedbackEmployee($company, $leader);
        $session = $this->createFeedbackSession($company, $leader, $employee, [
            'status' => FeedbackSessionStatus::InProgress,
        ]);

        app(SendFeedbackSignatureInvites::class)->execute($session->fresh());

        $session->refresh();
        $this->assertSame(FeedbackSessionStatus::AwaitingSignatures, $session->status);
        $this->assertCount(2, $session->signatures);
        $this->assertTrue($session->signatures->every(fn ($s) => $s->token !== null));
        $this->assertTrue($session->signatures->every(fn ($s) => $s->sent_at !== null));

        Mail::assertSent(FeedbackSignatureInvitationMail::class, 2);
    }

    public function test_execute_replaces_previous_signatures(): void
    {
        Mail::fake();

        $company = $this->createFeedbackCompany();
        $leader = User::factory()->companyAdmin($company->id)->create();
        $employee = $this->createFeedbackEmployee($company, $leader);
        $session = $this->createFeedbackSession($company, $leader, $employee);

        $session->signatures()->create([
            'role' => 'employee',
            'signer_name' => 'Antigo',
            'signer_email' => 'old@test.local',
            'token' => 'eeee5555-5555-5555-5555-555555555555',
        ]);

        app(SendFeedbackSignatureInvites::class)->execute($session->fresh());

        $this->assertCount(2, $session->fresh()->signatures);
        $this->assertDatabaseMissing('feedback_session_signatures', [
            'signer_email' => 'old@test.local',
        ]);
    }
}
