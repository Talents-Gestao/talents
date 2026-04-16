<?php

namespace Tests\Feature\Public;

use App\Mail\LandingInterestMail;
use App\Models\LandingInterestSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LandingInterestTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_interest_sends_mail_and_redirects_with_success(): void
    {
        Mail::fake();

        $response = $this->from('/')->post(route('landing.interest'), [
            'name' => ' João Silva ',
            'email' => 'joao@example.com',
            'company' => ' ACME ',
            'message' => ' Gostaria de uma demo. ',
        ]);

        $response->assertRedirect('/');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('landing_interest_submissions', [
            'name' => 'João Silva',
            'email' => 'joao@example.com',
            'company' => 'ACME',
            'message' => 'Gostaria de uma demo.',
        ]);

        $row = LandingInterestSubmission::query()->where('email', 'joao@example.com')->first();
        $this->assertNotNull($row->mail_sent_at);
        $this->assertNull($row->mail_error);

        Mail::assertSent(LandingInterestMail::class, function (LandingInterestMail $mail) {
            return $mail->submitterName === 'João Silva'
                && $mail->submitterEmail === 'joao@example.com'
                && $mail->company === 'ACME'
                && $mail->message === 'Gostaria de uma demo.';
        });
    }

    public function test_landing_interest_accepts_only_required_fields(): void
    {
        Mail::fake();

        $response = $this->from('/')->post(route('landing.interest'), [
            'name' => 'Maria',
            'email' => 'maria@example.com',
        ]);

        $response->assertRedirect('/');
        $response->assertSessionHas('success');

        Mail::assertSent(LandingInterestMail::class, function (LandingInterestMail $mail) {
            return $mail->submitterName === 'Maria'
                && $mail->submitterEmail === 'maria@example.com'
                && $mail->company === null
                && $mail->message === null;
        });
    }

    public function test_landing_interest_throttle_returns_429_after_limit(): void
    {
        Mail::fake();
        Config::set('public_rate_limits.landing_interest_per_minute', 3);

        $payload = [
            'name' => 'Teste',
            'email' => 'teste@example.com',
        ];

        for ($i = 0; $i < 3; $i++) {
            $this->from('/')->post(route('landing.interest'), $payload)->assertRedirect('/');
        }

        $this->from('/')->post(route('landing.interest'), $payload)->assertStatus(429);
    }
}
