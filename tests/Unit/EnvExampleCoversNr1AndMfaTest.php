<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

/** Toda variável NR1_* e MFA_* lida pelo config precisa estar no .env.example, com padrão seguro para produção. */
class EnvExampleCoversNr1AndMfaTest extends TestCase
{
    public function test_every_nr1_and_mfa_variable_is_documented_in_the_env_example(): void
    {
        $example = (string) file_get_contents(base_path('.env.example'));
        $found = [];
        foreach (['nr1', 'mfa'] as $file) {
            preg_match_all("/env\\('((?:NR1|MFA)_[A-Z0-9_]+)'/", (string) file_get_contents(config_path($file.'.php')), $m);
            $found = array_merge($found, $m[1]);
        }

        $this->assertNotEmpty($found);
        foreach (array_unique($found) as $variable) {
            $this->assertMatchesRegularExpression('/^'.$variable.'=/m', $example, "{$variable} falta no .env.example");
        }
    }

    public function test_the_example_keeps_the_risky_switches_off_and_the_protections_on(): void
    {
        $example = (string) file_get_contents(base_path('.env.example'));

        foreach (['NR1_SHELL_ENABLED', 'NR1_SHELL_LEGACY_EMBED', 'NR1_EXTERNAL_REVIEW_ENABLED', 'MFA_REQUIRED_AT_LOGIN'] as $off) {
            $this->assertMatchesRegularExpression('/^'.$off.'=false$/m', $example, "{$off} deve nascer desligada");
        }
        foreach (['NR1_EXTERNAL_REVIEW_MFA_REQUIRED', 'NR1_SOLUTIONS_REQUIRE_REVIEW', 'MFA_STEP_UP_REQUIRED'] as $on) {
            $this->assertMatchesRegularExpression('/^'.$on.'=true$/m', $example, "{$on} deve nascer ligada");
        }
    }
}
