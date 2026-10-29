<?php

namespace Tests\Feature\Landing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * As direções experimentais da Hero (?hero=a|b|c, ?perf, ?tier, ?adapt) foram descartadas: a Home
 * entrega só a HeroGlass, com o mesmo payload com ou sem esses parâmetros, em qualquer ambiente.
 */
class HeroLegacyParamsTest extends TestCase
{
    use RefreshDatabase;

    private const PARAMS = ['?hero=a', '?hero=b', '?hero=c', '?perf=1', '?tier=high', '?tier=low', '?adapt=0', '?hero=c&perf=1&tier=high&adapt=0'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_parametros_das_direcoes_nao_tem_efeito_em_nenhum_ambiente(): void
    {
        foreach (['local', 'production', 'testing'] as $env) {
            foreach ([true, false] as $heroGlass) {
                config(['landing.hero_glass' => $heroGlass, 'landing.redesign' => true]);
                $this->app->detectEnvironment(fn () => $env);

                $expected = $this->get('/')->assertOk()->viewData('page')['props'];
                unset($expected['errors']);

                foreach (self::PARAMS as $query) {
                    $this->get('/'.$query)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
                        ->component('Welcome')
                        ->where('heroGlass', $heroGlass)
                        ->missing('heroDirection')
                        ->missing('heroPerf'));

                    $props = $this->get('/'.$query)->viewData('page')['props'];
                    unset($props['errors']);
                    $this->assertEquals($expected, $props, "{$env}: {$query} alterou o payload da Home");
                }
            }
        }
    }
}
