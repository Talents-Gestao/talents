<?php

namespace Tests\Feature\Landing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class LandingRedesignFlagTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function home(string $query = ''): \Illuminate\Testing\TestResponse
    {
        return $this->get('/'.$query);
    }

    private function assertRedesign(\Illuminate\Testing\TestResponse $response, bool $expected): void
    {
        $response->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Welcome')
            ->where('redesign', $expected));
    }

    public function test_flag_desligada_entrega_a_home_atual(): void
    {
        config(['landing.redesign' => false]);

        $this->assertRedesign($this->home(), false);
    }

    public function test_flag_ligada_entrega_o_redesenho(): void
    {
        config(['landing.redesign' => true]);

        $this->assertRedesign($this->home(), true);
    }

    public function test_landing_classic_so_vale_em_ambiente_local(): void
    {
        config(['landing.redesign' => true]);
        $this->app->detectEnvironment(fn () => 'local');

        $this->assertRedesign($this->home('?landing=classic'), false);
        $this->assertRedesign($this->home(), true);
    }

    public function test_landing_classic_e_ignorado_fora_do_local(): void
    {
        config(['landing.redesign' => true]);
        $this->app->detectEnvironment(fn () => 'production');

        $this->assertRedesign($this->home('?landing=classic'), true);
    }

    public function test_hero_glass_segue_independente_da_flag_do_redesenho(): void
    {
        config(['landing.redesign' => false, 'landing.hero_glass' => true]);

        $this->home()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('heroGlass', true)
            ->where('redesign', false));
    }
}
