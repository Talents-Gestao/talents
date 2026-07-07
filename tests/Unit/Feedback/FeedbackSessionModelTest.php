<?php

declare(strict_types=1);

namespace Tests\Unit\Feedback;

use App\Enums\FeedbackSessionStatus;
use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\FeedbackSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesFeedbackFixtures;
use Tests\TestCase;

class FeedbackSessionModelTest extends TestCase
{
    use CreatesFeedbackFixtures;
    use RefreshDatabase;

    public function test_is_fully_signed_requires_two_signed_records(): void
    {
        $company = $this->createFeedbackCompany();
        $leader = User::factory()->companyAdmin($company->id)->create();
        $employee = $this->createFeedbackEmployee($company, $leader);
        $session = $this->createFeedbackSession($company, $leader, $employee, [
            'status' => FeedbackSessionStatus::AwaitingSignatures,
        ]);

        $this->assertFalse($session->isFullySigned());

        $session->signatures()->create([
            'role' => 'employee',
            'signer_name' => $employee->name,
            'signer_email' => $employee->email,
            'token' => 'aaaa1111-1111-1111-1111-111111111111',
            'signed_at' => now(),
        ]);

        $this->assertFalse($session->fresh()->isFullySigned());

        $session->signatures()->create([
            'role' => 'leader',
            'signer_name' => $leader->name,
            'signer_email' => $leader->email,
            'token' => 'bbbb2222-2222-2222-2222-222222222222',
            'signed_at' => now(),
        ]);

        $this->assertTrue($session->fresh()->isFullySigned());
    }

    public function test_is_fully_signed_is_false_when_one_signature_pending(): void
    {
        $company = $this->createFeedbackCompany();
        $leader = User::factory()->companyAdmin($company->id)->create();
        $employee = $this->createFeedbackEmployee($company, $leader);
        $session = $this->createFeedbackSession($company, $leader, $employee);

        $session->signatures()->createMany([
            [
                'role' => 'employee',
                'signer_name' => $employee->name,
                'signer_email' => $employee->email,
                'token' => 'cccc3333-3333-3333-3333-333333333333',
                'signed_at' => now(),
            ],
            [
                'role' => 'leader',
                'signer_name' => $leader->name,
                'signer_email' => $leader->email,
                'token' => 'dddd4444-4444-4444-4444-444444444444',
                'signed_at' => null,
            ],
        ]);

        $this->assertFalse($session->fresh()->isFullySigned());
    }
}
