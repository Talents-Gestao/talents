<script setup>
import FormPageHeader from '@/Components/FormPageHeader.vue';
import CommercialAdjustmentFields from '@/Components/Comercial/CommercialAdjustmentFields.vue';
import CommercialModuleNav from '@/Components/Comercial/CommercialModuleNav.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatBRL, useCommercialPricing } from '@/composables/useCommercialPricing';
import { enabledFlexibleRates, FLEXIBLE_RATE_DEFS } from '@/composables/useCatalogProductPricing';
import { formatCnpj } from '@/utils/formatCnpj';
import axios from 'axios';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    mode: { type: String, default: 'create' },
    proposal: { type: Object, default: null },
    sellers: { type: Array, default: () => [] },
    settings: { type: Object, required: true },
    catalogProducts: { type: Array, default: () => [] },
});

const settingsRef = ref({ ...props.settings });
const catalogProductsRef = computed(() => props.catalogProducts);

const buildCatalogProductsInitial = () => {
    const existing = Object.fromEntries(
        (props.proposal?.catalog_products ?? []).map((s) => [s.product_id, s]),
    );
    return props.catalogProducts.map((p) => {
        const ex = existing[p.id] ?? {};
        return {
            product_id: p.id,
            enabled: !!ex.enabled,
            modality: ex.modality ?? '',
            salary_cents: ex.salary_cents ?? 0,
            rate_mode: ex.rate_mode ?? '',
            units: ex.units ?? '',
            adjustment: ex.adjustment ?? 'none',
            discount_type: ex.discount_type ?? 'percent',
            discount_percent: ex.discount_percent ?? '',
            discount_value_cents: ex.discount_value_cents ?? 0,
        };
    });
};

const formInitial = props.proposal
    ? {
          client_name: props.proposal.client_name ?? '',
          client_cnpj: formatCnpj(props.proposal.client_cnpj ?? ''),
          client_email: props.proposal.client_email ?? '',
          client_phone: props.proposal.client_phone ?? '',
          client_address: props.proposal.client_address ?? '',
          client_representative: props.proposal.client_representative ?? '',
          client_representative_role: props.proposal.client_representative_role ?? '',
          indication: props.proposal.indication ?? '',
          employee_count: props.proposal.employee_count ?? 0,
          seller_id: props.proposal.seller_id ?? '',
          is_closed: !!props.proposal.is_closed,
          notes: props.proposal.notes ?? '',
          palestra_topic: props.proposal.palestra_topic ?? '',
          palestra_event_date: props.proposal.palestra_event_date ?? '',
          palestra_start_time: props.proposal.palestra_start_time ?? '',
          palestra_duration_hours: props.proposal.palestra_duration_hours ?? '',
          palestra_venue_address: props.proposal.palestra_venue_address ?? '',
          palestra_audience_estimate: props.proposal.palestra_audience_estimate ?? '',
          palestra_format: props.proposal.palestra_format ?? '',
          catalog_products: buildCatalogProductsInitial(),
          pdf_subtitle: props.proposal.pdf_subtitle ?? '',
          pdf_objetivo: props.proposal.pdf_objetivo ?? '',
          service_descriptions: { ...(props.proposal.service_descriptions ?? {}) },
      }
    : {
          client_name: '',
          client_cnpj: '',
          client_email: '',
          client_phone: '',
          client_address: '',
          client_representative: '',
          client_representative_role: '',
          indication: '',
          employee_count: 0,
          seller_id: '',
          is_closed: false,
          notes: '',
          palestra_topic: '',
          palestra_event_date: '',
          palestra_start_time: '',
          palestra_duration_hours: '',
          palestra_venue_address: '',
          palestra_audience_estimate: '',
          palestra_format: '',
          catalog_products: buildCatalogProductsInitial(),
          pdf_subtitle: '',
          pdf_objetivo: '',
          service_descriptions: {},
      };

const form = useForm(formInitial);

const defaultDescriptionForKey = (key) => {
    if (props.settings.pdf_descricoes_servicos?.[key]) {
        return props.settings.pdf_descricoes_servicos[key];
    }
    const product = props.catalogProducts.find((p) => p.slug === key);
    return product?.description ?? '';
};

const descriptionDisplay = (key) => {
    if (Object.prototype.hasOwnProperty.call(form.service_descriptions, key)) {
        return form.service_descriptions[key] ?? '';
    }
    return defaultDescriptionForKey(key);
};

const updateServiceDescription = (key, value) => {
    const defaultText = defaultDescriptionForKey(key);
    if (value.trim() === defaultText.trim() || value.trim() === '') {
        const next = { ...form.service_descriptions };
        delete next[key];
        form.service_descriptions = next;
        return;
    }
    form.service_descriptions = { ...form.service_descriptions, [key]: value };
};

const expandedDescriptions = ref({});
const toggleDescription = (key) => {
    expandedDescriptions.value[key] = !expandedDescriptions.value[key];
};

const formRef = computed(() => form);
const proposalRef = computed(() => props.proposal);
const { totalFinalCents, catalogLines, legacySummary } = useCommercialPricing(
    formRef,
    settingsRef,
    catalogProductsRef,
    proposalRef,
);

const catalogSelection = (productId) => {
    let sel = form.catalog_products.find((s) => s.product_id === productId);
    if (!sel) {
        sel = {
            product_id: productId,
            enabled: false,
            modality: '',
            salary_cents: 0,
            rate_mode: '',
            units: '',
            adjustment: 'none',
            discount_type: 'percent',
            discount_percent: '',
            discount_value_cents: 0,
        };
        form.catalog_products.push(sel);
    }
    return sel;
};

const catalogLineCents = (productId) => {
    const line = catalogLines.value?.find((l) => l.product_id === productId);
    return line?.value_cents ?? 0;
};

const catalogLineSubtotal = (productId) => {
    const line = catalogLines.value?.find((l) => l.product_id === productId);
    return line?.subtotal_cents ?? 0;
};

const showCommercialAdjustment = (product) => {
    if (product.pricing_type === 'flexible_rates') {
        const sel = catalogSelection(product.id);
        return !!sel.enabled && !!sel.rate_mode && Number(sel.units) > 0 && catalogLineSubtotal(product.id) > 0;
    }
    if (product.pricing_type === 'fixed_modality') {
        return !!catalogSelection(product.id).modality && catalogLineSubtotal(product.id) > 0;
    }
    return !!catalogSelection(product.id).enabled && catalogLineSubtotal(product.id) > 0;
};

const catalogSalaryReais = (productId) => {
    const cents = catalogSelection(productId).salary_cents ?? 0;
    return ((Number(cents) || 0) / 100).toFixed(2).replace('.', ',');
};

const updateCatalogSalary = (productId, reaisStr) => {
    const numeric = Number(String(reaisStr ?? '').replace(/\./g, '').replace(',', '.'));
    catalogSelection(productId).salary_cents = Number.isFinite(numeric)
        ? Math.max(0, Math.round(numeric * 100))
        : 0;
};

const isProductSelected = (product) => {
    const sel = catalogSelection(product.id);
    if (product.pricing_type === 'fixed_modality') {
        return !!sel.modality;
    }
    if (product.pricing_type === 'flexible_rates') {
        return !!sel.enabled && !!sel.rate_mode && Number(sel.units) > 0;
    }
    return !!sel.enabled;
};

const flexibleRatesForProduct = (product) => enabledFlexibleRates(product);

const unitsLabelForMode = (mode) =>
    FLEXIBLE_RATE_DEFS.find((d) => d.key === mode)?.unitsLabel ?? 'Quantidade';

const formatRateUnitPrice = (product, mode) => {
    const cents = product.pricing_config?.rates?.[mode]?.cents_per_unit ?? 0;
    return formatBRL(cents);
};

const palestrasProductSelected = computed(() =>
    props.catalogProducts.some((p) => p.slug === 'palestras' && isProductSelected(p)),
);

const activePdfServices = computed(() =>
    props.catalogProducts
        .filter((product) => isProductSelected(product))
        .map((product) => ({ key: product.slug, label: product.name })),
);

// Consulta CNPJ (Receita Federal) — reaproveita o endpoint já existente.
const cnpjLookupLoading = ref(false);
const cnpjLookupError = ref('');
const cnpjLookupSuccess = ref('');
const cnpjDigitCount = computed(() => (String(form.client_cnpj || '').match(/\d/g) || []).length);
const canLookupCnpj = computed(() => cnpjDigitCount.value === 14);

const fetchCnpjFromReceita = async () => {
    cnpjLookupError.value = '';
    cnpjLookupSuccess.value = '';
    if (!canLookupCnpj.value) {
        cnpjLookupError.value = 'Informe um CNPJ com 14 dígitos.';
        return;
    }
    cnpjLookupLoading.value = true;
    try {
        const { data } = await axios.get(route('admin.companies.lookup-cnpj'), {
            params: { cnpj: form.client_cnpj },
        });
        form.client_cnpj = data.cnpj ?? form.client_cnpj;
        const fantasiaOuRazao = data.name || data.legal_name;
        if (fantasiaOuRazao) {
            form.client_name = fantasiaOuRazao;
        }
        if (data.contact_email) {
            form.client_email = data.contact_email;
        }
        cnpjLookupSuccess.value = 'Dados preenchidos a partir da Receita Federal.';
    } catch (e) {
        const d = e.response?.data;
        cnpjLookupError.value =
            typeof d?.message === 'string'
                ? d.message
                : d?.errors?.cnpj?.[0] ?? 'Não foi possível consultar o CNPJ.';
    } finally {
        cnpjLookupLoading.value = false;
    }
};

const submit = () => {
    if (props.mode === 'edit') {
        form.put(route('admin.comercial.propostas.update', props.proposal.id), { preserveScroll: true });
    } else {
        form.post(route('admin.comercial.propostas.store'), { preserveScroll: true });
    }
};

const downloadPdf = () => {
    if (!props.proposal?.id) return;
    window.open(route('admin.comercial.propostas.pdf', props.proposal.id), '_blank');
};

const openContractPdf = (contractId) => {
    window.open(route('admin.comercial.contratos.pdf', contractId), '_blank');
};

const formatContractDate = (iso) => (iso ? new Date(iso).toLocaleString('pt-BR') : '—');

const isEdit = computed(() => props.mode === 'edit');
const titleText = computed(() => (isEdit.value ? `Proposta ${props.proposal?.code}` : 'Nova proposta'));

const services = computed(() => {
    const legacy = (legacySummary.value || []).map((line) => ({
        label: line.label,
        cents: line.cents,
        on: true,
        readonly: true,
    }));
    const catalog = (catalogLines.value || []).map((line) => ({
        label: line.label,
        cents: line.value_cents,
        on: true,
        readonly: false,
    }));
    return [...legacy, ...catalog];
});
</script>

<template>
    <Head :title="`Comercial — ${titleText}`" />

    <AdminLayout>
        <template #header>
            <FormPageHeader
                :back-href="route('admin.comercial.propostas.index')"
                back-label="Propostas"
                :title="titleText"
            >
                <template v-if="isEdit" #trailing>
                    <button
                        type="button"
                        class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                        @click="downloadPdf"
                    >
                        Gerar PDF
                    </button>
                </template>
            </FormPageHeader>
        </template>

        <CommercialModuleNav />

        <form class="grid gap-8 lg:grid-cols-3" @submit.prevent="submit">
            <div class="space-y-6 lg:col-span-2">
                <!-- Cliente -->
                <section class="surface-card p-6">
                    <h3 class="text-lg font-semibold text-slate-900">Dados do cliente</h3>
                    <p class="mt-1 text-xs text-slate-500">Lead / prospect — não vinculado a empresas cadastradas.</p>

                    <div class="mt-4 space-y-4">
                        <div>
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">CNPJ</label>
                            <p class="mt-0.5 text-xs text-slate-500">
                                Informe o CNPJ e busque na Receita Federal para preencher nome e e-mail automaticamente.
                            </p>
                            <div class="mt-2 flex flex-col gap-2 sm:flex-row sm:items-stretch">
                                <input
                                    v-model="form.client_cnpj"
                                    type="text"
                                    placeholder="00.000.000/0001-00"
                                    class="block w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500 sm:max-w-md"
                                    @blur="form.client_cnpj = formatCnpj(form.client_cnpj)"
                                />
                                <button
                                    type="button"
                                    class="inline-flex shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:opacity-60"
                                    :disabled="form.processing || cnpjLookupLoading || !canLookupCnpj"
                                    @click="fetchCnpjFromReceita"
                                >
                                    {{ cnpjLookupLoading ? 'Buscando…' : 'Buscar na Receita Federal' }}
                                </button>
                            </div>
                            <p v-if="cnpjLookupError" class="mt-2 text-sm text-rose-600">{{ cnpjLookupError }}</p>
                            <p v-else-if="cnpjLookupSuccess" class="mt-2 text-sm text-emerald-700">{{ cnpjLookupSuccess }}</p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Nome / Razão social *</label>
                                <input
                                    v-model="form.client_name"
                                    type="text"
                                    required
                                    class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                />
                                <p v-if="form.errors.client_name" class="mt-1 text-xs text-rose-600">{{ form.errors.client_name }}</p>
                            </div>
                            <div>
                                <label class="text-xs font-medium uppercase tracking-wide text-slate-500">
                                    Celular / WhatsApp (contrato e ZapSign)
                                </label>
                                <input
                                    v-model="form.client_phone"
                                    type="text"
                                    placeholder="DDD + número — usado para envio do link de assinatura pela ZapSign"
                                    class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                />
                                <p class="mt-1 text-xs text-slate-500">
                                    Se não houver e-mail válido, o número com DDD é obrigatório para disparo por WhatsApp.
                                </p>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Endereço da sede (contrato)</label>
                                <input
                                    v-model="form.client_address"
                                    type="text"
                                    placeholder="Logradouro, número, bairro, cidade — UF, CEP"
                                    class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                />
                            </div>
                            <div class="sm:col-span-2">
                                <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Representante legal (contrato)</label>
                                <input
                                    v-model="form.client_representative"
                                    type="text"
                                    placeholder="Nome completo do signatário pela Contratante"
                                    class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                />
                            </div>
                            <div class="sm:col-span-2">
                                <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Cargo do representante (contrato)</label>
                                <input
                                    v-model="form.client_representative_role"
                                    type="text"
                                    placeholder="Ex.: Diretora Administrativa"
                                    class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                />
                            </div>
                            <div>
                                <label class="text-xs font-medium uppercase tracking-wide text-slate-500">E-mail</label>
                                <input
                                    v-model="form.client_email"
                                    type="email"
                                    class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                />
                            </div>
                            <div>
                                <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Indicação</label>
                                <input
                                    v-model="form.indication"
                                    type="text"
                                    class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                />
                            </div>
                            <div>
                                <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Nº de funcionários *</label>
                                <input
                                    v-model.number="form.employee_count"
                                    type="number"
                                    min="0"
                                    required
                                    class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                />
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Produtos -->
                <section class="surface-card p-6">
                    <h3 class="text-lg font-semibold text-slate-900">Produtos</h3>
                    <p class="mt-1 text-xs text-slate-500">
                        Selecione os produtos; o cálculo aparece no resumo ao lado.
                        Cadastre novos em Comercial → Valores e contratos → aba Produtos.
                    </p>

                    <div v-if="catalogProducts.length" class="mt-4 space-y-4">
                        <template v-for="product in catalogProducts" :key="product.id">
                            <div
                                v-if="product.pricing_type === 'fixed' || product.pricing_type === 'per_employee' || product.pricing_type === 'tiered_per_employee' || product.pricing_type === 'threshold_multiplier'"
                                class="rounded-xl border border-talents-100 bg-talents-50/30 p-3"
                            >
                                <label class="flex items-start gap-3 hover:bg-talents-50/50">
                                    <input
                                        v-model="catalogSelection(product.id).enabled"
                                        type="checkbox"
                                        class="mt-1 h-4 w-4 rounded border-slate-300 text-talents-600 focus:ring-talents-500"
                                    />
                                    <div class="flex-1">
                                        <div class="font-medium text-slate-900">{{ product.name }}</div>
                                        <p v-if="product.description" class="text-xs text-slate-500">{{ product.description }}</p>
                                    </div>
                                    <div class="text-right text-sm tabular-nums text-slate-700">
                                        {{ formatBRL(catalogLineCents(product.id)) }}
                                    </div>
                                </label>
                                <CommercialAdjustmentFields
                                    v-if="showCommercialAdjustment(product)"
                                    :selection="catalogSelection(product.id)"
                                    :subtotal-cents="catalogLineSubtotal(product.id)"
                                    :total-cents="catalogLineCents(product.id)"
                                />
                            </div>

                            <div
                                v-else-if="product.pricing_type === 'fixed_modality'"
                                class="rounded-xl border border-talents-100 bg-talents-50/30 p-3"
                            >
                                <div class="font-medium text-slate-900">{{ product.name }}</div>
                                <select
                                    v-model="catalogSelection(product.id).modality"
                                    class="mt-2 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                    @change="catalogSelection(product.id).enabled = !!catalogSelection(product.id).modality"
                                >
                                    <option value="">— Não contratado —</option>
                                    <option
                                        v-for="mod in product.pricing_config?.modalities || []"
                                        :key="mod.key"
                                        :value="mod.key"
                                    >
                                        {{ mod.label }} ({{ formatBRL(mod.cents) }})
                                    </option>
                                </select>
                                <div class="mt-2 text-right text-sm tabular-nums text-slate-700">
                                    {{ formatBRL(catalogLineCents(product.id)) }}
                                </div>
                                <CommercialAdjustmentFields
                                    v-if="showCommercialAdjustment(product)"
                                    :selection="catalogSelection(product.id)"
                                    :subtotal-cents="catalogLineSubtotal(product.id)"
                                    :total-cents="catalogLineCents(product.id)"
                                />
                            </div>

                            <div
                                v-else-if="product.pricing_type === 'flexible_rates'"
                                class="rounded-xl border border-talents-100 bg-talents-50/30 p-3"
                            >
                                <label class="flex items-start gap-3">
                                    <input
                                        v-model="catalogSelection(product.id).enabled"
                                        type="checkbox"
                                        class="mt-1 h-4 w-4 rounded border-slate-300 text-talents-600 focus:ring-talents-500"
                                    />
                                    <div class="flex-1">
                                        <div class="font-medium text-slate-900">{{ product.name }}</div>
                                        <p v-if="product.description" class="text-xs text-slate-500">{{ product.description }}</p>
                                    </div>
                                    <div class="text-right text-sm tabular-nums text-slate-700">
                                        {{ formatBRL(catalogLineCents(product.id)) }}
                                    </div>
                                </label>

                                <div v-if="catalogSelection(product.id).enabled" class="mt-3 space-y-3 border-t border-talents-100 pt-3">
                                    <div>
                                        <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Tipo de precificação</label>
                                        <select
                                            v-model="catalogSelection(product.id).rate_mode"
                                            class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                        >
                                            <option value="">— Selecionar —</option>
                                            <option
                                                v-for="rate in flexibleRatesForProduct(product)"
                                                :key="rate.key"
                                                :value="rate.key"
                                            >
                                                {{ rate.label }} ({{ formatRateUnitPrice(product, rate.key) }})
                                            </option>
                                        </select>
                                    </div>

                                    <div v-if="catalogSelection(product.id).rate_mode">
                                        <label class="text-xs font-medium uppercase tracking-wide text-slate-500">
                                            {{ unitsLabelForMode(catalogSelection(product.id).rate_mode) }}
                                        </label>
                                        <input
                                            v-model.number="catalogSelection(product.id).units"
                                            type="number"
                                            min="0"
                                            step="0.5"
                                            placeholder="0"
                                            class="mt-1 w-full max-w-xs rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                        />
                                    </div>
                                </div>

                                <CommercialAdjustmentFields
                                    v-if="showCommercialAdjustment(product)"
                                    :selection="catalogSelection(product.id)"
                                    :subtotal-cents="catalogLineSubtotal(product.id)"
                                    :total-cents="catalogLineCents(product.id)"
                                />
                            </div>

                            <div
                                v-else-if="product.pricing_type === 'salary_times_employees'"
                                class="rounded-xl border border-talents-100 bg-talents-50/30 p-3"
                            >
                                <label class="flex items-start gap-3">
                                    <input
                                        v-model="catalogSelection(product.id).enabled"
                                        type="checkbox"
                                        class="mt-1 h-4 w-4 rounded border-slate-300 text-talents-600 focus:ring-talents-500"
                                    />
                                    <div class="flex-1">
                                        <div class="font-medium text-slate-900">{{ product.name }}</div>
                                        <p class="text-xs text-slate-500">Salário base × nº de funcionários.</p>
                                    </div>
                                    <div class="text-right text-sm tabular-nums text-slate-700">
                                        {{ formatBRL(catalogLineCents(product.id)) }}
                                    </div>
                                </label>
                                <div v-if="catalogSelection(product.id).enabled" class="mt-3">
                                    <label class="text-xs font-medium uppercase tracking-wide text-slate-500">
                                        Salário base por funcionário (R$)
                                    </label>
                                    <input
                                        :value="catalogSalaryReais(product.id)"
                                        type="text"
                                        placeholder="0,00"
                                        class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                        @input="updateCatalogSalary(product.id, $event.target.value)"
                                    />
                                </div>
                                <CommercialAdjustmentFields
                                    v-if="showCommercialAdjustment(product)"
                                    :selection="catalogSelection(product.id)"
                                    :subtotal-cents="catalogLineSubtotal(product.id)"
                                    :total-cents="catalogLineCents(product.id)"
                                />
                            </div>
                        </template>
                    </div>
                    <p v-else class="mt-4 text-sm text-slate-500">
                        Nenhum produto cadastrado. Acesse Comercial → Valores e contratos → aba Produtos.
                    </p>
                </section>

                <!-- Texto da proposta (PDF) -->
                <section class="surface-card p-6">
                    <h3 class="text-lg font-semibold text-slate-900">Texto da proposta (PDF)</h3>
                    <p class="mt-1 text-xs text-slate-500">
                        Subtítulo e objetivo geral exibidos no início do documento comercial.
                    </p>
                    <div class="mt-4 space-y-4">
                        <div>
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">
                                Nome do programa / subtítulo
                            </label>
                            <input
                                v-model="form.pdf_subtitle"
                                type="text"
                                placeholder="Ex.: Programa de Diagnóstico Organizacional e Desenvolvimento de Lideranças"
                                class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            />
                        </div>
                        <div>
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Objetivo geral</label>
                            <textarea
                                v-model="form.pdf_objetivo"
                                rows="4"
                                placeholder="Descreva o objetivo geral da proposta para o cliente..."
                                class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            />
                        </div>
                    </div>
                </section>

                <!-- Descrições dos serviços no PDF -->
                <section v-if="activePdfServices.length" class="surface-card p-6">
                    <h3 class="text-lg font-semibold text-slate-900">Descrições no PDF</h3>
                    <p class="mt-1 text-xs text-slate-500">
                        Textos exibidos em cada serviço da proposta. Preenchidos automaticamente; clique para editar.
                    </p>
                    <div class="mt-4 space-y-3">
                        <div
                            v-for="svc in activePdfServices"
                            :key="svc.key"
                            class="rounded-xl border border-slate-200"
                        >
                            <button
                                type="button"
                                class="flex w-full items-center justify-between gap-2 px-4 py-3 text-left text-sm font-medium text-slate-900 hover:bg-slate-50"
                                @click="toggleDescription(svc.key)"
                            >
                                <span>{{ svc.label }}</span>
                                <span class="text-xs text-slate-400">
                                    {{ expandedDescriptions[svc.key] ? 'Recolher' : 'Editar descrição' }}
                                </span>
                            </button>
                            <div v-if="expandedDescriptions[svc.key]" class="border-t border-slate-100 px-4 pb-4 pt-3">
                                <label class="text-xs font-medium uppercase tracking-wide text-slate-500">
                                    Descrição no PDF
                                </label>
                                <textarea
                                    :value="descriptionDisplay(svc.key)"
                                    rows="8"
                                    class="mt-1 w-full rounded-xl border-slate-300 font-mono text-xs shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                    placeholder="O que contempla, bullets e objetivo do serviço..."
                                    @input="updateServiceDescription(svc.key, $event.target.value)"
                                />
                                <p class="mt-1 text-xs text-slate-500">
                                    Use linhas com • ou - para bullets. Deixe igual ao padrão para atualizar automaticamente.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Palestra — evento (contrato) -->
                <section v-if="palestrasProductSelected" class="surface-card p-6">
                    <h3 class="text-lg font-semibold text-slate-900">Palestra — dados do evento (contrato)</h3>
                    <p class="mt-1 text-xs text-slate-500">
                        Alimentam os placeholders do modelo &quot;Palestra — Padrão Talents&quot; (tema, data, local, formato, etc.).
                    </p>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Tema da palestra</label>
                            <input
                                v-model="form.palestra_topic"
                                type="text"
                                class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            />
                        </div>
                        <div>
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Data do evento</label>
                            <input
                                v-model="form.palestra_event_date"
                                type="date"
                                class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            />
                        </div>
                        <div>
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Horário de início</label>
                            <input
                                v-model="form.palestra_start_time"
                                type="text"
                                placeholder="14:00"
                                class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            />
                        </div>
                        <div>
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Duração estimada</label>
                            <input
                                v-model="form.palestra_duration_hours"
                                type="text"
                                placeholder="Ex.: 2 ou 2h"
                                class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            />
                        </div>
                        <div>
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Público estimado</label>
                            <input
                                v-model.number="form.palestra_audience_estimate"
                                type="number"
                                min="0"
                                placeholder="Participantes"
                                class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            />
                        </div>
                        <div>
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Formato</label>
                            <select
                                v-model="form.palestra_format"
                                class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            >
                                <option value="">— Selecionar —</option>
                                <option value="presencial">Presencial</option>
                                <option value="online">Online</option>
                                <option value="hibrido">Híbrido</option>
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Local do evento</label>
                            <input
                                v-model="form.palestra_venue_address"
                                type="text"
                                placeholder="Endereço completo ou link da sala virtual"
                                class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            />
                        </div>
                    </div>
                </section>

                <!-- Comercial -->
                <section class="surface-card p-6">
                    <h3 class="text-lg font-semibold text-slate-900">Informações comerciais</h3>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Vendedor</label>
                            <select
                                v-model="form.seller_id"
                                class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            >
                                <option value="">— Sem vendedor atribuído —</option>
                                <option v-for="s in sellers" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                            <p v-if="!sellers.length" class="mt-1 text-xs text-amber-700">
                                Nenhum vendedor marcado como Comercial. Marque usuários como "Comercial" no cadastro de usuários.
                            </p>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Observações</label>
                            <textarea
                                v-model="form.notes"
                                rows="3"
                                class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            />
                        </div>
                        <label class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm sm:col-span-2">
                            <input v-model="form.is_closed" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                            <span class="font-medium text-slate-900">Marcar como fechada</span>
                            <span class="text-xs text-slate-500">— a data de fechamento será registrada agora.</span>
                        </label>
                    </div>
                </section>

                <section v-if="isEdit" class="surface-card p-6">
                    <h3 class="text-lg font-semibold text-slate-900">Contratos gerados</h3>
                    <p class="mt-1 text-xs text-slate-500">Histórico de contratos PDF gerados a partir desta proposta.</p>
                    <ul
                        v-if="proposal?.contracts?.length"
                        class="mt-4 divide-y divide-slate-100 rounded-xl border border-slate-200"
                    >
                        <li
                            v-for="c in proposal.contracts"
                            :key="c.id"
                            class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm"
                        >
                            <div>
                                <div class="font-mono text-xs font-semibold text-slate-800">{{ c.code }}</div>
                                <div class="text-xs text-slate-500">{{ c.template_name_snapshot }} · {{ formatContractDate(c.generated_at) }}</div>
                            </div>
                            <button
                                type="button"
                                class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-talents-700 hover:bg-talents-50"
                                @click="openContractPdf(c.id)"
                            >
                                PDF
                            </button>
                        </li>
                    </ul>
                    <p v-else class="mt-4 text-sm text-slate-500">Nenhum contrato gerado ainda. Use a listagem de propostas para gerar.</p>
                </section>
            </div>

            <!-- Resumo lateral sticky -->
            <aside class="lg:sticky lg:top-24 lg:self-start">
                <div class="surface-card p-6">
                    <h3 class="text-lg font-semibold text-slate-900">Resumo</h3>
                    <p class="mt-1 text-xs text-slate-500">Cálculo em tempo real conforme você preenche.</p>

                    <ul class="mt-4 space-y-2 text-sm">
                        <li
                            v-for="svc in services"
                            :key="`${svc.label}-${svc.readonly ? 'legacy' : 'catalog'}`"
                            class="flex items-center justify-between gap-2"
                            :class="!svc.on ? 'text-slate-400' : svc.readonly ? 'text-slate-500' : 'text-slate-700'"
                        >
                            <span class="truncate">
                                {{ svc.label }}
                                <span v-if="svc.readonly" class="text-xs text-slate-400">(histórico)</span>
                            </span>
                            <span class="tabular-nums">{{ formatBRL(svc.cents) }}</span>
                        </li>
                        <li v-if="!services.length" class="text-sm text-slate-400">Nenhum produto selecionado.</li>
                    </ul>

                    <div class="mt-4 border-t border-slate-200 pt-4">
                        <div class="flex items-center justify-between text-base font-semibold text-talents-700">
                            <span>Honorário Final</span>
                            <span class="tabular-nums">{{ formatBRL(totalFinalCents) }}</span>
                        </div>
                    </div>

                    <div class="mt-6 space-y-2">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex w-full items-center justify-center rounded-xl bg-talents-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-talents-700 disabled:opacity-60"
                        >
                            {{ isEdit ? 'Salvar alterações' : 'Salvar proposta' }}
                        </button>
                        <button
                            v-if="isEdit"
                            type="button"
                            class="inline-flex w-full items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                            @click="downloadPdf"
                        >
                            Gerar PDF da proposta
                        </button>
                    </div>
                </div>
            </aside>
        </form>
    </AdminLayout>
</template>
