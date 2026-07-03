/**
 * Espelha CommercialProductPricingService (PHP) para cálculo ao vivo na proposta.
 */

const pickTier = (n, maxes, values) => {
    for (let i = 0; i < maxes.length; i++) {
        if (n <= Number(maxes[i] ?? 0)) {
            return Number(values[i] ?? 0);
        }
    }
    return Number(values[maxes.length] ?? 0);
};

export const FLEXIBLE_RATE_DEFS = [
    { key: 'hour', label: 'Por hora', unitsLabel: 'Quantidade de horas', suffix: 'h' },
    { key: 'quantity', label: 'Por quantidade', unitsLabel: 'Quantidade', suffix: 'un.' },
    { key: 'unit', label: 'Por unidade', unitsLabel: 'Número de unidades', suffix: 'un.' },
];

export const FLEXIBLE_RATE_CUSTOM = {
    key: 'custom',
    label: 'Personalizado',
    unitsLabel: 'Valor personalizado (R$)',
};

const applyDiscount = (subtotal, selection) => {
    const discountType = String(selection.discount_type ?? 'percent');

    if (discountType === 'value') {
        const discountCents = Math.max(0, Number(selection.discount_value_cents ?? 0));
        return Math.max(0, subtotal - discountCents);
    }

    const pct = Math.min(100, Math.max(0, Number(selection.discount_percent ?? 0)));
    return Math.round(subtotal * (1 - pct / 100));
};

export const applyAdjustment = (subtotal, selection) => {
    const adjustment = String(selection.adjustment ?? 'none');

    if (adjustment === 'bonus') {
        return 0;
    }
    if (adjustment === 'discount') {
        return applyDiscount(subtotal, selection);
    }
    return subtotal;
};

const flexibleRatesSubtotal = (config, selection) => {
    const mode = String(selection.rate_mode ?? '');

    if (mode === FLEXIBLE_RATE_CUSTOM.key) {
        return Math.max(0, Number(selection.custom_cents ?? 0));
    }

    const rate = config.rates?.[mode];
    if (!rate?.enabled) {
        return 0;
    }

    const units = Math.max(0, Number(selection.units ?? 0));
    if (units <= 0) {
        return 0;
    }

    return Math.round(units * Number(rate.cents_per_unit ?? 0));
};

const discountSuffix = (selection) => {
    const discountType = String(selection.discount_type ?? 'percent');

    if (discountType === 'value') {
        const cents = Math.max(0, Number(selection.discount_value_cents ?? 0));
        const fmt = (cents / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
        return ` · Desconto ${fmt}`;
    }

    const pct = Number(selection.discount_percent ?? 0).toLocaleString('pt-BR', { maximumFractionDigits: 2 });
    return ` · Desconto ${pct}%`;
};

const appendAdjustmentSuffix = (base, selection) => {
    const adjustment = String(selection.adjustment ?? 'none');
    if (adjustment === 'bonus') {
        return `${base} · Bonificação`;
    }
    if (adjustment === 'discount') {
        return `${base}${discountSuffix(selection)}`;
    }
    return base;
};

const buildFlexibleBaseDetail = (selection, config) => {
    const mode = String(selection.rate_mode ?? '');

    if (mode === FLEXIBLE_RATE_CUSTOM.key) {
        const cents = Math.max(0, Number(selection.custom_cents ?? 0));
        if (cents <= 0) {
            return '—';
        }
        return (cents / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    }

    const def = FLEXIBLE_RATE_DEFS.find((d) => d.key === mode);
    const units = Number(selection.units ?? 0);
    const centsPerUnit = Number(config.rates?.[mode]?.cents_per_unit ?? 0);
    const suffix = def?.suffix ?? '';

    if (units <= 0) {
        return '—';
    }

    const unitsFmt = units.toLocaleString('pt-BR', { maximumFractionDigits: 2 });
    const priceFmt = (centsPerUnit / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    return `${unitsFmt} ${suffix} × ${priceFmt}`;
};

const buildBaseDetail = (product, employees, selection, subtotal) => {
    if (subtotal <= 0) {
        return '—';
    }

    const config = product.pricing_config || {};
    const n = Math.max(0, Number(employees ?? 0));

    switch (product.pricing_type) {
        case 'flexible_rates':
            return buildFlexibleBaseDetail(selection, config);
        case 'fixed':
            return 'Valor fixo';
        case 'per_employee':
        case 'tiered_per_employee':
            return `${n} funcionários`;
        case 'fixed_modality': {
            const mod = (config.modalities || []).find((m) => m.key === selection.modality);
            return mod?.label || selection.modality || '—';
        }
        case 'salary_times_employees':
            return `Salário × ${n} funcionários`;
        case 'threshold_multiplier':
            return n > Number(config.threshold_employees ?? 30) ? 'Pacote ampliado' : 'Pacote padrão';
        default:
            return '—';
    }
};

const shouldIncludeLine = (selection, result) => {
    if (!selection?.enabled) {
        return false;
    }
    if (result.total_cents > 0) {
        return true;
    }
    return selection.adjustment === 'bonus' && (result.subtotal_cents ?? 0) > 0;
};

/**
 * @param {object} product
 * @param {number} employees
 * @param {object} selection
 */
export function calculateCatalogLine(product, employees, selection) {
    if (!selection?.enabled) {
        return { total_cents: 0, subtotal_cents: 0, detail: '' };
    }

    const config = product.pricing_config || {};
    const n = Math.max(0, Number(employees ?? 0));
    let subtotal = 0;

    switch (product.pricing_type) {
        case 'fixed':
            subtotal = Number(config.amount_cents ?? 0);
            break;
        case 'per_employee':
            subtotal = n > 0 ? n * Number(config.cents_per_employee ?? 0) : 0;
            break;
        case 'tiered_per_employee':
            if (n > 0) {
                subtotal = n * pickTier(
                    n,
                    [config.tier1_max, config.tier2_max, config.tier3_max],
                    [config.tier1_cents, config.tier2_cents, config.tier3_cents, config.tier4_cents],
                );
            }
            break;
        case 'fixed_modality': {
            const mod = String(selection.modality ?? '');
            const found = (config.modalities || []).find((m) => m.key === mod);
            subtotal = found ? Number(found.cents ?? 0) : 0;
            break;
        }
        case 'salary_times_employees':
            subtotal = n > 0 ? n * Math.max(0, Number(selection.salary_cents ?? 0)) : 0;
            break;
        case 'threshold_multiplier': {
            const base = Number(config.base_cents ?? 0);
            const threshold = Number(config.threshold_employees ?? 30);
            const multiplier = Math.max(1, Number(config.multiplier ?? 2));
            subtotal = n > threshold ? base * multiplier : base;
            break;
        }
        case 'flexible_rates':
            subtotal = flexibleRatesSubtotal(config, selection);
            break;
        default:
            subtotal = 0;
    }

    subtotal = Math.max(0, subtotal);
    const total = Math.max(0, applyAdjustment(subtotal, selection));
    const detail = appendAdjustmentSuffix(buildBaseDetail(product, n, selection, subtotal), selection);

    return { total_cents: total, subtotal_cents: subtotal, detail };
}

/**
 * @param {Array<object>} products
 * @param {number} employees
 * @param {Array<object>} selections
 */
export function calculateCatalogProducts(products, employees, selections) {
    const byId = Object.fromEntries((products || []).map((p) => [p.id, p]));
    const lines = [];
    let total = 0;

    (selections || []).forEach((selection) => {
        const product = byId[selection.product_id];
        if (!product) return;
        const result = calculateCatalogLine(product, employees, selection);
        if (!shouldIncludeLine(selection, result)) return;
        lines.push({
            product_id: product.id,
            key: product.slug,
            label: product.name,
            detail: result.detail,
            value_cents: result.total_cents,
            subtotal_cents: result.subtotal_cents,
        });
        total += result.total_cents;
    });

    return { total_cents: total, lines };
}

export function enabledFlexibleRates(product) {
    const rates = product?.pricing_config?.rates || {};
    return FLEXIBLE_RATE_DEFS.filter((def) => rates[def.key]?.enabled);
}
