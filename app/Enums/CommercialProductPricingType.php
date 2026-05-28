<?php

namespace App\Enums;

enum CommercialProductPricingType: string
{
    case Fixed = 'fixed';
    case PerEmployee = 'per_employee';
    case TieredPerEmployee = 'tiered_per_employee';
    case FixedModality = 'fixed_modality';
    case SalaryTimesEmployees = 'salary_times_employees';
    case ThresholdMultiplier = 'threshold_multiplier';

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::Fixed->value => 'Valor fixo',
            self::PerEmployee->value => 'Por funcionário (valor único)',
            self::TieredPerEmployee->value => 'Por funcionário (faixas)',
            self::FixedModality->value => 'Modalidade (valor fixo por opção)',
            self::SalaryTimesEmployees->value => 'Salário × funcionários',
            self::ThresholdMultiplier->value => 'Base com multiplicador por faixa',
        ];
    }
}
