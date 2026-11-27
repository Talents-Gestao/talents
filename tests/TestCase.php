<?php

namespace Tests;

use App\Models\Company;
use App\Models\Module;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Mfa\MfaService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $base = dirname(__DIR__);
        $cachedConfig = $base.'/bootstrap/cache/config.php';
        if (is_file($cachedConfig)) {
            @unlink($cachedConfig);
        }
        foreach (glob($base.'/bootstrap/cache/routes*.php') ?: [] as $routeCache) {
            if (is_file($routeCache)) {
                @unlink($routeCache);
            }
        }

        $app = require $base.'/bootstrap/app.php';

        $this->traitsUsedByTest = array_flip(class_uses_recursive(static::class));

        $app->make(Kernel::class)->bootstrap();

        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('database.connections.sqlite.foreign_key_constraints', true);

        $app->make('db')->purge();

        return $app;
    }

    /**
     * Age como o usuário com a sessão de MFA já verificada agora (para as ações sensíveis do NR-1 e a área do revisor).
     * Use com `User::factory()->...->mfaVerified()`.
     */
    protected function actingAsMfa(User $user): static
    {
        return $this->actingAs($user)->withSession([MfaService::SESSION_VERIFIED_AT => now()->getTimestamp()]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
        Cache::flush();

        $this->isolateFakeDisksPerProcess();

        // Impede chamadas HTTP reais em todos os testes — qualquer Http::get/post não
        // interceptado com Http::fake() lança RequestException imediatamente.
        Http::preventStrayRequests();
    }

    /**
     * Storage::fake() usa storage/framework/testing/disks/{disco}, uma pasta só para todos os processos.
     * Com duas execuções de PHPUnit ao mesmo tempo, o cleanDirectory de uma apagava os arquivos da outra
     * (era a falha intermitente do AiInterviewTest na suíte completa). Com um token por processo, o Laravel
     * usa {disco}_test_{token}; a pasta do processo é apagada quando ele termina.
     */
    private function isolateFakeDisksPerProcess(): void
    {
        $token = 'pid'.getmypid();
        ParallelTesting::resolveTokenUsing(fn () => $token);

        static $cleanupRegistered = false;
        if ($cleanupRegistered) {
            return;
        }
        $cleanupRegistered = true;

        $pattern = storage_path('framework/testing/disks/*_test_'.$token);
        register_shutdown_function(static function () use ($pattern): void {
            $filesystem = new Filesystem;
            foreach (glob($pattern, GLOB_ONLYDIR) ?: [] as $dir) {
                $filesystem->deleteDirectory($dir);
            }
        });
    }

    protected function subscribeCompanyToNr1(Company $company, bool $withRhid = true, bool $withDenuncias = true): void
    {
        $nr1 = Module::query()->firstOrCreate(
            ['key' => Module::KEY_NR1],
            ['name' => 'NR1', 'description' => 'Teste']
        );
        $moduleIds = [$nr1->id];
        if ($withDenuncias) {
            $denuncias = Module::query()->firstOrCreate(
                ['key' => Module::KEY_DENUNCIAS],
                ['name' => 'Canal de denúncias', 'description' => 'Teste']
            );
            $moduleIds[] = $denuncias->id;
        }
        if ($withRhid) {
            $rhid = Module::query()->firstOrCreate(
                ['key' => Module::KEY_RHID],
                ['name' => 'RHID / Ponto', 'description' => 'Teste']
            );
            $moduleIds[] = $rhid->id;
        }
        $plan = Plan::query()->create([
            'name' => 'Plano NR1 Test',
            'slug' => 'nr1-test-'.Str::random(8),
            'price_monthly_cents' => 0,
            'is_active' => true,
        ]);
        $plan->modules()->sync($moduleIds);
        Subscription::query()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'starts_at' => now(),
            'ends_at' => now()->addYear(),
            'status' => 'active',
        ]);
    }
}
