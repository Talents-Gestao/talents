<?php

namespace App\Services\Commercial;

use App\Enums\CommercialProductPricingType;
use App\Models\CommercialProduct;
use Illuminate\Support\Collection;

class CommercialProductPricingService
{
    /**
     * @param  array<string, mixed>  $selection  enabled, modality?, salary_cents?
     * @return array{total_cents: int, subtotal_cents: int, detail: string}
     */
    public function calculateLine(CommercialProduct $product, int $employeeCount, array $selection): array
    {
        if (! ($selection['enabled'] ?? false)) {
            return ['total_cents' => 0, 'subtotal_cents' => 0, 'detail' => ''];
        }

        $config = $product->pricing_config ?? [];
        $employees = max(0, $employeeCount);

        $subtotal = match ($product->pricing_type) {
            CommercialProductPricingType::Fixed => (int) ($config['amount_cents'] ?? 0),
            CommercialProductPricingType::PerEmployee => $employees > 0
                ? $employees * (int) ($config['cents_per_employee'] ?? 0)
                : 0,
            CommercialProductPricingType::TieredPerEmployee => $employees > 0
                ? $employees * $this->pickTier(
                    $employees,
                    [
                        (int) ($config['tier1_max'] ?? 5),
                        (int) ($config['tier2_max'] ?? 10),
                        (int) ($config['tier3_max'] ?? 20),
                    ],
                    [
                        (int) ($config['tier1_cents'] ?? 0),
                        (int) ($config['tier2_cents'] ?? 0),
                        (int) ($config['tier3_cents'] ?? 0),
                        (int) ($config['tier4_cents'] ?? 0),
                    ],
                )
                : 0,
            CommercialProductPricingType::FixedModality => $this->modalityCents($config, (string) ($selection['modality'] ?? '')),
            CommercialProductPricingType::SalaryTimesEmployees => $employees > 0
                ? $employees * max(0, (int) ($selection['salary_cents'] ?? 0))
                : 0,
            CommercialProductPricingType::ThresholdMultiplier => $this->thresholdTotal($employees, $config),
            CommercialProductPricingType::FlexibleRates => $this->flexibleRatesSubtotal($config, $selection),
            default => 0,
        };

        $subtotal = max(0, $subtotal);
        $total = $this->applyAdjustment($subtotal, $selection);

        return [
            'total_cents' => max(0, $total),
            'subtotal_cents' => $subtotal,
            'detail' => $this->buildDetail($product, $employees, $selection, $subtotal),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $selections
     * @return array{total_cents: int, lines: array<int, array<string, mixed>>}
     */
    public function calculateMany(Collection $products, int $employeeCount, array $selections): array
    {
        $byId = $products->keyBy('id');
        $lines = [];
        $total = 0;

        foreach ($selections as $selection) {
            $productId = (int) ($selection['product_id'] ?? 0);
            /** @var CommercialProduct|null $product */
            $product = $byId->get($productId);
            if (! $product || ! $product->is_active) {
                continue;
            }

            $result = $this->calculateLine($product, $employeeCount, $selection);
            if (! $this->shouldIncludeLine($selection, $result)) {
                continue;
            }

            $lines[] = [
                'product_id' => $product->id,
                'key' => $product->slug,
                'label' => $product->name,
                'detail' => $result['detail'],
                'value_cents' => $result['total_cents'],
                'options' => $this->selectionOptions($selection, $result),
            ];
            $total += $result['total_cents'];
        }

        return ['total_cents' => $total, 'lines' => $lines];
    }

    /**
     * @param  array<string, mixed>  $selection
     */
    public function applyAdjustment(int $subtotal, array $selection): int
    {
        $adjustment = (string) ($selection['adjustment'] ?? 'none');

        return match ($adjustment) {
            'bonus' => 0,
            'discount' => $this->applyDiscount($subtotal, $selection),
            default => $subtotal,
        };
    }

    /**
     * @param  array<string, mixed>  $selection
     */
    private function applyDiscount(int $subtotal, array $selection): int
    {
        $discountType = (string) ($selection['discount_type'] ?? 'percent');

        if ($discountType === 'value') {
            $discountCents = max(0, (int) ($selection['discount_value_cents'] ?? 0));

            return max(0, $subtotal - $discountCents);
        }

        $pct = min(100, max(0, (float) ($selection['discount_percent'] ?? 0)));

        return (int) round($subtotal * (1 - $pct / 100));
    }

    /**
     * @param  array<string, mixed>  $selection
     * @param  array{total_cents: int, subtotal_cents: int, detail: string}  $result
     */
    private function shouldIncludeLine(array $selection, array $result): bool
    {
        if (! ($selection['enabled'] ?? false)) {
            return false;
        }

        if ($result['total_cents'] > 0) {
            return true;
        }

        return ($selection['adjustment'] ?? 'none') === 'bonus'
            && ($result['subtotal_cents'] ?? 0) > 0;
    }

    /**
     * @param  array<string, mixed>  $selection
     * @param  array{total_cents: int, subtotal_cents: int, detail: string}  $result
     * @return array<string, mixed>
     */
    private function selectionOptions(array $selection, array $result): array
    {
        return array_filter([
            'modality' => $selection['modality'] ?? null,
            'salary_cents' => isset($selection['salary_cents']) ? (int) $selection['salary_cents'] : null,
            'rate_mode' => $selection['rate_mode'] ?? null,
            'units' => isset($selection['units']) ? (float) $selection['units'] : null,
            'custom_cents' => isset($selection['custom_cents']) ? (int) $selection['custom_cents'] : null,
            'adjustment' => $selection['adjustment'] ?? null,
            'discount_type' => $selection['discount_type'] ?? null,
            'discount_percent' => isset($selection['discount_percent']) ? (float) $selection['discount_percent'] : null,
            'discount_value_cents' => isset($selection['discount_value_cents']) ? (int) $selection['discount_value_cents'] : null,
            'subtotal_cents' => ($result['subtotal_cents'] ?? 0) > 0 ? (int) $result['subtotal_cents'] : null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $selection
     */
    private function flexibleRatesSubtotal(array $config, array $selection): int
    {
        $mode = (string) ($selection['rate_mode'] ?? '');

        if ($mode === 'custom') {
            return max(0, (int) ($selection['custom_cents'] ?? 0));
        }

        $rate = $config['rates'][$mode] ?? null;

        if (! is_array($rate) || ! ($rate['enabled'] ?? false)) {
            return 0;
        }

        $units = max(0, (float) ($selection['units'] ?? 0));
        if ($units <= 0) {
            return 0;
        }

        return (int) round($units * (int) ($rate['cents_per_unit'] ?? 0));
    }

    public static function flexibleRateModeLabel(string $mode): string
    {
        return match ($mode) {
            'hour' => 'Por hora',
            'quantity' => 'Por quantidade',
            'unit' => 'Por unidade',
            'custom' => 'Personalizado',
            default => $mode,
        };
    }

    public static function flexibleUnitsSuffix(string $mode): string
    {
        return match ($mode) {
            'hour' => 'h',
            'quantity' => 'un.',
            'unit' => 'un.',
            default => '',
        };
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function modalityCents(array $config, string $modality): int
    {
        if ($modality === '') {
            return 0;
        }

        foreach ($config['modalities'] ?? [] as $item) {
            if (($item['key'] ?? '') === $modality) {
                return (int) ($item['cents'] ?? 0);
            }
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function thresholdTotal(int $employees, array $config): int
    {
        $base = (int) ($config['base_cents'] ?? 0);
        $threshold = (int) ($config['threshold_employees'] ?? 30);
        $multiplier = max(1, (int) ($config['multiplier'] ?? 2));

        return $employees > $threshold ? $base * $multiplier : $base;
    }

    /**
     * @param  array<string, mixed>  $selection
     */
    private function buildDetail(
        CommercialProduct $product,
        int $employees,
        array $selection,
        int $subtotalCents,
    ): string {
        if ($subtotalCents <= 0) {
            return '—';
        }

        $base = match ($product->pricing_type) {
            CommercialProductPricingType::FlexibleRates => $this->buildFlexibleBaseDetail(
                $selection,
                $product->pricing_config ?? [],
            ),
            CommercialProductPricingType::Fixed => 'Valor fixo',
            CommercialProductPricingType::PerEmployee,
            CommercialProductPricingType::TieredPerEmployee => "{$employees} funcionários",
            CommercialProductPricingType::FixedModality => $this->modalityLabel(
                $product->pricing_config ?? [],
                (string) ($selection['modality'] ?? ''),
            ),
            CommercialProductPricingType::SalaryTimesEmployees => sprintf(
                'Salário base R$ %s × %d funcionários',
                number_format(((int) ($selection['salary_cents'] ?? 0)) / 100, 2, ',', '.'),
                $employees,
            ),
            CommercialProductPricingType::ThresholdMultiplier => $employees > (int) ($product->pricing_config['threshold_employees'] ?? 30)
                ? 'Pacote ampliado'
                : 'Pacote padrão',
            default => '—',
        };

        return $this->appendAdjustmentSuffix($base, $selection);
    }

    /**
     * @param  array<string, mixed>  $selection
     * @param  array<string, mixed>  $config
     */
    private function buildFlexibleBaseDetail(array $selection, array $config): string
    {
        $mode = (string) ($selection['rate_mode'] ?? '');

        if ($mode === 'custom') {
            $cents = max(0, (int) ($selection['custom_cents'] ?? 0));

            return $cents > 0
                ? 'R$ '.number_format($cents / 100, 2, ',', '.')
                : '—';
        }

        $units = (float) ($selection['units'] ?? 0);
        $centsPerUnit = (int) ($config['rates'][$mode]['cents_per_unit'] ?? 0);
        $suffix = self::flexibleUnitsSuffix($mode);

        if ($units <= 0) {
            return '—';
        }

        return sprintf(
            '%s %s × R$ %s',
            rtrim(rtrim(number_format($units, 2, ',', '.'), '0'), ','),
            $suffix,
            number_format($centsPerUnit / 100, 2, ',', '.'),
        );
    }

    /**
     * @param  array<string, mixed>  $selection
     */
    private function appendAdjustmentSuffix(string $base, array $selection): string
    {
        $adjustment = (string) ($selection['adjustment'] ?? 'none');

        return match ($adjustment) {
            'bonus' => $base.' · Bonificação',
            'discount' => $base.$this->discountSuffix($selection),
            default => $base,
        };
    }

    /**
     * @param  array<string, mixed>  $selection
     */
    private function discountSuffix(array $selection): string
    {
        $discountType = (string) ($selection['discount_type'] ?? 'percent');

        if ($discountType === 'value') {
            $cents = max(0, (int) ($selection['discount_value_cents'] ?? 0));

            return sprintf(
                ' · Desconto R$ %s',
                number_format($cents / 100, 2, ',', '.'),
            );
        }

        return sprintf(
            ' · Desconto %s%%',
            rtrim(rtrim(number_format((float) ($selection['discount_percent'] ?? 0), 2, ',', '.'), '0'), ','),
        );
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function modalityLabel(array $config, string $modality): string
    {
        foreach ($config['modalities'] ?? [] as $item) {
            if (($item['key'] ?? '') === $modality) {
                return (string) ($item['label'] ?? $modality);
            }
        }

        return $modality !== '' ? $modality : '—';
    }

    /**
     * @param  array<int, int>  $maxes
     * @param  array<int, int>  $values
     */
    private function pickTier(int $employees, array $maxes, array $values): int
    {
        foreach ($maxes as $i => $max) {
            if ($employees <= $max) {
                return (int) $values[$i];
            }
        }

        return (int) $values[count($maxes)];
    }
}
