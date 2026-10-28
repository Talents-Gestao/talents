<?php

namespace Tests\Feature\Landing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class HeroDirectionParamTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function assertDirection(TestResponse $response, ?string $expected): void
    {
        $response->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Welcome')
            ->where('heroDirection', $expected));
    }

    public function test_direcoes_disponiveis_em_ambiente_local(): void
    {
        config(['landing.hero_glass' => true]);
        $this->app->detectEnvironment(fn () => 'local');

        foreach (['a', 'b', 'c'] as $direction) {
            $this->assertDirection($this->get('/?hero='.$direction), $direction);
        }
    }

    public function test_parametro_ignorado_fora_do_local(): void
    {
        config(['landing.hero_glass' => true]);

        foreach (['production', 'staging', 'testing'] as $env) {
            $this->app->detectEnvironment(fn () => $env);
            $this->assertDirection($this->get('/?hero=a'), null);
        }
    }

    public function test_valor_desconhecido_cai_na_hero_atual(): void
    {
        config(['landing.hero_glass' => true]);
        $this->app->detectEnvironment(fn () => 'local');

        $this->assertDirection($this->get('/?hero=z'), null);
        $this->assertDirection($this->get('/'), null);
    }

    public function test_flag_da_hero_em_vidro_desligada_mantem_a_hero_atual(): void
    {
        config(['landing.hero_glass' => false]);
        $this->app->detectEnvironment(fn () => 'local');

        $this->get('/?hero=b')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('heroDirection', null)
            ->where('heroGlass', false));
    }

    public function test_indicador_de_desempenho_so_em_ambiente_local(): void
    {
        config(['landing.hero_glass' => true]);

        $this->app->detectEnvironment(fn () => 'local');
        $this->get('/?hero=a&perf=1')->assertInertia(fn (AssertableInertia $page) => $page->where('heroPerf', true));
        $this->get('/?hero=a')->assertInertia(fn (AssertableInertia $page) => $page->where('heroPerf', false));

        foreach (['production', 'staging'] as $env) {
            $this->app->detectEnvironment(fn () => $env);
            $this->get('/?hero=a&perf=1')->assertInertia(fn (AssertableInertia $page) => $page->where('heroPerf', false));
        }
    }
}
