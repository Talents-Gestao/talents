<?php

namespace Tests;

use App\Models\Company;
use App\Models\Module;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    protected function subscribeCompanyToNr1(Company $company): void
    {
        $nr1 = Module::query()->firstOrCreate(
            ['key' => Module::KEY_NR1],
            ['name' => 'NR1', 'description' => 'Teste']
        );
        $plan = Plan::query()->create([
            'name' => 'Plano NR1 Test',
            'slug' => 'nr1-test-'.Str::random(8),
            'price_monthly_cents' => 0,
            'is_active' => true,
        ]);
        $plan->modules()->sync([$nr1->id]);
        Subscription::query()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'starts_at' => now(),
            'ends_at' => now()->addYear(),
            'status' => 'active',
        ]);
    }
}
