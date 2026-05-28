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

/**
 * @param {object} product
 * @param {number} employees
 * @param {object} selection
 */
export function calculateCatalogLine(product, employees, selection) {
    if (!selection?.enabled) {
        return { total_cents: 0, detail: '' };
    }

    const config = product.pricing_config || {};
    const n = Math.max(0, Number(employees ?? 0));
    let total = 0;

    switch (product.pricing_type) {
        case 'fixed':
            total = Number(config.amount_cents ?? 0);
            break;
        case 'per_employee':
            total = n > 0 ? n * Number(config.cents_per_employee ?? 0) : 0;
            break;
        case 'tiered_per_employee':
            if (n > 0) {
                total = n * pickTier(
                    n,
                    [config.tier1_max, config.tier2_max, config.tier3_max],
                    [config.tier1_cents, config.tier2_cents, config.tier3_cents, config.tier4_cents],
                );
            }
            break;
        case 'fixed_modality': {
            const mod = String(selection.modality ?? '');
            const found = (config.modalities || []).find((m) => m.key === mod);
            total = found ? Number(found.cents ?? 0) : 0;
            break;
        }
        case 'salary_times_employees':
            total = n > 0 ? n * Math.max(0, Number(selection.salary_cents ?? 0)) : 0;
            break;
        case 'threshold_multiplier': {
            const base = Number(config.base_cents ?? 0);
            const threshold = Number(config.threshold_employees ?? 30);
            const multiplier = Math.max(1, Number(config.multiplier ?? 2));
            total = n > threshold ? base * multiplier : base;
            break;
        }
        default:
            total = 0;
    }

    total = Math.max(0, total);

    let detail = '—';
    if (total > 0) {
        switch (product.pricing_type) {
            case 'fixed':
                detail = 'Valor fixo';
                break;
            case 'per_employee':
            case 'tiered_per_employee':
                detail = `${n} funcionários`;
                break;
            case 'fixed_modality': {
                const mod = (config.modalities || []).find((m) => m.key === selection.modality);
                detail = mod?.label || selection.modality || '—';
                break;
            }
            case 'salary_times_employees':
                detail = `Salário × ${n} funcionários`;
                break;
            case 'threshold_multiplier':
                detail = n > Number(config.threshold_employees ?? 30) ? 'Pacote ampliado' : 'Pacote padrão';
                break;
            default:
                break;
        }
    }

    return { total_cents: total, detail };
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
        if (!selection.enabled || result.total_cents <= 0) return;
        lines.push({
            product_id: product.id,
            key: product.slug,
            label: product.name,
            detail: result.detail,
            value_cents: result.total_cents,
        });
        total += result.total_cents;
    });

    return { total_cents: total, lines };
}
