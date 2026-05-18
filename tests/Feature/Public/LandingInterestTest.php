<?php

namespace Tests\Feature\Public;

use App\Mail\LandingInterestMail;
use App\Models\LandingInterestSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class LandingInterestTest extends TestCase
{
    use RefreshDatabase;

    public function test_nr1_landing_page_renders(): void
    {
        $this->get('/nr-1')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Nr1')
                ->has('canLogin')
                ->has('canRegister'));
    }

    public function test_landing_interest_sends_mail_and_redirects_with_success(): void
    {
        Mail::fake();

        $response = $this->from('/')->post(route('landing.interest'), [
            'name' => ' João Silva ',
            'email' => 'joao@example.com',
            'phone' => ' (11) 98888-7777 ',
            'company' => ' ACME ',
            'message' => ' Gostaria de uma demo. ',
        ]);

        $response->assertRedirect('/');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('landing_interest_submissions', [
            'name' => 'João Silva',
            'email' => 'joao@example.com',
            'phone' => '(11) 98888-7777',
            'company' => 'ACME',
            'message' => 'Gostaria de uma demo.',
        ]);

        $row = LandingInterestSubmission::query()->where('email', 'joao@example.com')->first();
        $this->assertNotNull($row->mail_sent_at);
        $this->assertNull($row->mail_error);

        Mail::assertSent(LandingInterestMail::class, function (LandingInterestMail $mail) {
            return $mail->submitterName === 'João Silva'
                && $mail->submitterEmail === 'joao@example.com'
                && $mail->phone === '(11) 98888-7777'
                && $mail->company === 'ACME'
                && $mail->submitterMessage === 'Gostaria de uma demo.';
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
                && $mail->phone === null
                && $mail->company === null
                && $mail->submitterMessage === null;
        });
    }

    public function test_landing_interest_mail_renders_when_optional_fields_are_empty(): void
    {
        $mail = new LandingInterestMail(
            submitterName: 'Leticia',
            submitterEmail: 'leticia@example.com',
            phone: '11972599018',
            company: null,
            submitterMessage: null,
        );

        $html = $mail->render();

        $this->assertStringContainsString('Leticia', $html);
        $this->assertStringContainsString('leticia@example.com', $html);
        $this->assertStringContainsString('11972599018', $html);
        $this->assertStringContainsString('—', $html);
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
