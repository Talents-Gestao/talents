<?php

namespace Tests\Feature\Landing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * A Home nova (Hero em vidro, cabeçalho de vidro, Frentes, Pilares, Recrutamento e Além) é a landing padrão,
 * sem flag: o payload não tem interruptores e parâmetros antigos de comparação/depuração não têm efeito.
 * O fallback da Hero (sem WebGL, movimento reduzido, aparelho fraco) é decidido no navegador.
 */
class LandingHomeTest extends TestCase
{
    use RefreshDatabase;

    private const LEGACY_PARAMS = [
        '?landing=classic',
        '?hero=a',
        '?hero=b',
        '?hero=c',
        '?perf=1',
        '?tier=high',
        '?tier=low',
        '?adapt=0',
        '?landing=classic&hero=c&perf=1&tier=high&adapt=0',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_home_nova_sem_interruptores_no_payload(): void
    {
        $this->get('/')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Welcome')
            ->has('canLogin')
            ->has('canRegister')
            ->missing('heroGlass')
            ->missing('redesign')
            ->missing('heroDirection')
            ->missing('heroPerf'));
    }

    public function test_flags_antigas_da_landing_nao_existem_mais(): void
    {
        $this->assertNull(config('landing.hero_glass'));
        $this->assertNull(config('landing.redesign'));
        $this->assertNotEmpty(config('landing.interest_recipients'));
    }

    public function test_parametros_antigos_nao_tem_efeito_em_nenhum_ambiente(): void
    {
        foreach (['local', 'production', 'testing'] as $env) {
            $this->app->detectEnvironment(fn () => $env);

            $expected = $this->get('/')->assertOk()->viewData('page')['props'];
            unset($expected['errors']);

            foreach (self::LEGACY_PARAMS as $query) {
                $props = $this->get('/'.$query)->assertOk()->viewData('page')['props'];
                unset($props['errors']);
                $this->assertEquals($expected, $props, "{$env}: {$query} alterou o payload da Home");
            }
        }
    }
}
