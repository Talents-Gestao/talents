import { calculateCatalogProducts } from '@/composables/useCatalogProductPricing';
import { computed } from 'vue';

/**
 * Replica em JS as fórmulas Q–X do CommercialPricingService (PHP) para
 * calcular o orçamento em tempo real conforme o usuário preenche o
 * formulário. Toda matemática é em CENTAVOS para evitar arredondamento.
 *
 * @param {import('vue').Ref<object>} formRef    Form reativo (useForm ou ref).
 * @param {import('vue').Ref<object>} settingsRef Settings vindos do backend.
 */
export function useCommercialPricing(formRef, settingsRef, catalogProductsRef = null) {
    const employees = computed(() => Math.max(0, Number(formRef.value?.employee_count ?? 0)));
    const s = () => settingsRef.value || {};

    const pickTier = (n, maxes, values) => {
        for (let i = 0; i < maxes.length; i++) {
            if (n <= Number(maxes[i] ?? 0)) {
                return Number(values[i] ?? 0);
            }
        }
        return Number(values[maxes.length] ?? 0);
    };

    const pesquisas = computed(() => {
        if (!formRef.value?.svc_pesquisas || employees.value <= 0) return 0;
        const cfg = s();
        return employees.value * pickTier(
            employees.value,
            [cfg.pesquisas_tier1_max, cfg.pesquisas_tier2_max, cfg.pesquisas_tier3_max],
            [cfg.pesquisas_tier1_cents, cfg.pesquisas_tier2_cents, cfg.pesquisas_tier3_cents, cfg.pesquisas_tier4_cents],
        );
    });

    const profiler = computed(() => {
        if (!formRef.value?.svc_profiler || employees.value <= 0) return 0;
        const cfg = s();
        return employees.value * pickTier(
            employees.value,
            [cfg.profiler_tier1_max, cfg.profiler_tier2_max, cfg.profiler_tier3_max],
            [cfg.profiler_tier1_cents, cfg.profiler_tier2_cents, cfg.profiler_tier3_cents, cfg.profiler_tier4_cents],
        );
    });

    const devolutiva = computed(() => {
        const modo = formRef.value?.svc_devolutiva;
        const cfg = s();
        if (modo === 'individual') return Number(cfg.devolutiva_individual_cents ?? 0);
        if (modo === 'grupo') return Number(cfg.devolutiva_grupo_cents ?? 0);
        return 0;
    });

    const nr1 = computed(() => {
        if (!formRef.value?.svc_nr1 || employees.value <= 0) return 0;
        const cfg = s();
        return employees.value * pickTier(
            employees.value,
            [cfg.nr1_tier1_max, cfg.nr1_tier2_max, cfg.nr1_tier3_max],
            [cfg.nr1_tier1_cents, cfg.nr1_tier2_cents, cfg.nr1_tier3_cents, cfg.nr1_tier4_cents],
        );
    });

    const nr1Implantacao = computed(() => {
        if (!formRef.value?.svc_nr1) return 0;
        const modo = formRef.value?.svc_nr1_implantacao_modo;
        if (!modo) return 0;
        const cfg = s();
        const base = modo === 'presencial'
            ? Number(cfg.nr1_implantacao_presencial_cents ?? 0)
            : employees.value * Number(cfg.nr1_implantacao_online_cents ?? 0);
        return base + nr1.value;
    });

    const contratacao = computed(() => {
        if (!formRef.value?.svc_contratacao || employees.value <= 0) return 0;
        const salario = Number(formRef.value?.svc_contratacao_salario_cents ?? 0);
        return salario * employees.value;
    });

    const direcionamento = computed(() => {
        if (!formRef.value?.svc_direcionamento || employees.value <= 0) return 0;
        const cfg = s();
        return employees.value * pickTier(
            employees.value,
            [cfg.direcionamento_tier1_max, cfg.direcionamento_tier2_max, cfg.direcionamento_tier3_max],
            [cfg.direcionamento_tier1_cents, cfg.direcionamento_tier2_cents, cfg.direcionamento_tier3_cents, cfg.direcionamento_tier4_cents],
        );
    });

    const palestras = computed(() => {
        if (!formRef.value?.svc_palestras) return 0;
        const cfg = s();
        const base = Number(cfg.palestras_base_cents ?? 0);
        const threshold = Number(cfg.palestras_threshold_funcionarios ?? 30);
        const multiplier = Math.max(1, Number(cfg.palestras_multiplier ?? 2));
        return employees.value > threshold ? base * multiplier : base;
    });

    const catalog = computed(() => {
        const products = catalogProductsRef?.value ?? [];
        const selections = formRef.value?.catalog_products ?? [];
        return calculateCatalogProducts(products, employees.value, selections);
    });

    const legacyTotal = computed(() =>
        pesquisas.value + profiler.value + devolutiva.value + nr1.value
        + nr1Implantacao.value + contratacao.value + direcionamento.value + palestras.value,
    );

    const totalFinal = computed(() => legacyTotal.value + catalog.value.total_cents);

    const commissionPercent = computed(() => Number(s().default_commission_percent ?? 0));
    const commissionCents = computed(() => Math.round(totalFinal.value * commissionPercent.value / 100));

    return {
        breakdownCents: computed(() => ({
            total_pesquisas_cents: pesquisas.value,
            total_profiler_cents: profiler.value,
            total_devolutiva_cents: devolutiva.value,
            total_nr1_cents: nr1.value,
            total_nr1_implantacao_cents: nr1Implantacao.value,
            total_contratacao_cents: contratacao.value,
            total_direcionamento_cents: direcionamento.value,
            total_palestras_cents: palestras.value,
            total_catalog_products_cents: catalog.value.total_cents,
            catalog_lines: catalog.value.lines,
            total_final_cents: totalFinal.value,
            commission_cents: commissionCents.value,
        })),
        catalogLines: computed(() => catalog.value.lines),
        totalFinalCents: totalFinal,
        commissionCents,
    };
}

/**
 * Formata centavos para uma string em reais (ex: 31172 → "R$ 311,72").
 * @param {number|null|undefined} cents
 */
export function formatBRL(cents) {
    const value = Number(cents ?? 0) / 100;
    return value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}
