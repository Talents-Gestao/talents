<script setup>
import FormPageHeader from '@/Components/FormPageHeader.vue';
import CommercialAdjustmentFields from '@/Components/Commercial/CommercialAdjustmentFields.vue';
import CatalogProductObservationField from '@/Components/Commercial/CatalogProductObservationField.vue';
import CommercialModuleNav from '@/Components/Commercial/CommercialModuleNav.vue';
import Modal from '@/Components/Modal.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatBRL, useCommercialPricing } from '@/composables/useCommercialPricing';
import {
    catalogReferenceDisplay,
    enabledFlexibleRates,
    FLEXIBLE_RATE_CUSTOM,
    FLEXIBLE_RATE_DEFS,
} from '@/composables/useCatalogProductPricing';
import { formatCnpj, maskCnpj } from '@/utils/formatCnpj';
import { CheckIcon } from '@heroicons/vue/24/solid';
import axios from 'axios';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';

const props = defineProps({
    mode: { type: String, default: 'create' },
    proposal: { type: Object, default: null },
    sellers: { type: Array, default: () => [] },
    settings: { type: Object, required: true },
    catalogProducts: { type: Array, default: () => [] },
    pdfOptionalSectionOptions: { type: Array, default: () => [] },
    paymentMethodOptions: { type: Array, default: () => [] },
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
            custom_cents: ex.custom_cents ?? 0,
            adjustment: ex.adjustment ?? 'none',
            discount_type: ex.discount_type ?? 'percent',
            discount_percent: ex.discount_percent ?? '',
            discount_value_cents: ex.discount_value_cents ?? 0,
            observation: ex.observation ?? '',
        };
    });
};

const buildPdfOptionalSections = () => Object.fromEntries(
    props.pdfOptionalSectionOptions.map((opt) => [
        opt.key,
        !!props.proposal?.pdf_optional_sections?.[opt.key],
    ]),
);

const formInitial = props.proposal
    ? {
          client_name: props.proposal.client_name ?? '',
          client_cnpj: maskCnpj(props.proposal.client_cnpj ?? ''),
          client_email: props.proposal.client_email ?? '',
          client_phone: props.proposal.client_phone ?? '',
          client_address: props.proposal.client_address ?? '',
          client_representative: props.proposal.client_representative ?? '',
          client_representative_role: props.proposal.client_representative_role ?? '',
          indication: props.proposal.indication ?? '',
          employee_count: props.proposal.employee_count ?? 0,
          include_publico_atendido: props.proposal.include_publico_atendido ?? true,
          seller_id: props.proposal.seller_id ?? '',
          is_closed: !!props.proposal.is_closed,
          notes: props.proposal.notes ?? '',
          payment_method_id: props.proposal.payment_method_id ?? '',
          include_minimum_stay: props.proposal.include_minimum_stay ?? true,
          is_recurring: !!props.proposal.is_recurring,
          recurring_months: props.proposal.recurring_months ?? '',
          recurring_monthly_reais: props.proposal.recurring_monthly_reais ?? '',
          recurring_notes: props.proposal.recurring_notes ?? '',
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
          pdf_optional_sections: buildPdfOptionalSections(),
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
          include_publico_atendido: true,
          seller_id: '',
          is_closed: false,
          notes: '',
          payment_method_id: '',
          include_minimum_stay: true,
          is_recurring: false,
          recurring_months: '',
          recurring_monthly_reais: '',
          recurring_notes: '',
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
          pdf_optional_sections: buildPdfOptionalSections(),
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

const recurringMonthlyCents = computed(() => {
    const reais = Number(form.recurring_monthly_reais);
    if (!Number.isFinite(reais) || reais <= 0) {
        return 0;
    }
    return Math.round(reais * 100);
});

const recurringMonthsCount = computed(() => {
    const months = Number(form.recurring_months);
    if (!Number.isInteger(months) || months < 1) {
        return 0;
    }
    return months;
});

const honorarioFinalCents = computed(() => {
    if (form.is_recurring && recurringMonthsCount.value > 0 && recurringMonthlyCents.value > 0) {
        return recurringMonthsCount.value * recurringMonthlyCents.value;
    }
    return totalFinalCents.value;
});

const setServiceType = (type) => {
    const recurring = type === 'recorrente';
    form.is_recurring = recurring;
    if (!recurring) {
        form.recurring_months = '';
        form.recurring_monthly_reais = '';
        form.recurring_notes = '';
        form.clearErrors('recurring_months', 'recurring_monthly_reais', 'recurring_notes');
    }
};

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
            custom_cents: 0,
            adjustment: 'none',
            discount_type: 'percent',
            discount_percent: '',
            discount_value_cents: 0,
            observation: '',
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
        if (!sel.enabled) {
            return false;
        }
        if (sel.rate_mode === FLEXIBLE_RATE_CUSTOM.key) {
            return Number(sel.custom_cents) > 0 && catalogLineSubtotal(product.id) > 0;
        }
        return !!sel.rate_mode && Number(sel.units) > 0 && catalogLineSubtotal(product.id) > 0;
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

const catalogCustomReais = (productId) => {
    const cents = catalogSelection(productId).custom_cents ?? 0;
    return ((Number(cents) || 0) / 100).toFixed(2).replace('.', ',');
};

const updateCatalogCustomValue = (productId, reaisStr) => {
    const numeric = Number(String(reaisStr ?? '').replace(/\./g, '').replace(',', '.'));
    catalogSelection(productId).custom_cents = Number.isFinite(numeric)
        ? Math.max(0, Math.round(numeric * 100))
        : 0;
};

const isFlexibleRateModeCustom = (productId) =>
    catalogSelection(productId).rate_mode === FLEXIBLE_RATE_CUSTOM.key;

const isProductSelected = (product) => {
    const sel = catalogSelection(product.id);
    if (product.pricing_type === 'fixed_modality') {
        return !!sel.modality;
    }
    if (product.pricing_type === 'flexible_rates') {
        if (!sel.enabled) {
            return false;
        }
        if (sel.rate_mode === FLEXIBLE_RATE_CUSTOM.key) {
            return Number(sel.custom_cents) > 0;
        }
        return !!sel.rate_mode && Number(sel.units) > 0;
    }
    return !!sel.enabled;
};

/** Produto marcado no formulário (checkbox/modalidade), mesmo antes do preço estar calculado. */
const isProductMarked = (product) => {
    const sel = catalogSelection(product.id);
    if (product.pricing_type === 'fixed_modality') {
        return !!sel.modality;
    }
    return !!sel.enabled;
};

const flexibleRatesForProduct = (product) => enabledFlexibleRates(product);

const unitsLabelForMode = (mode) => {
    if (mode === FLEXIBLE_RATE_CUSTOM.key) {
        return FLEXIBLE_RATE_CUSTOM.unitsLabel;
    }
    return FLEXIBLE_RATE_DEFS.find((d) => d.key === mode)?.unitsLabel ?? 'Quantidade';
};

const formatRateUnitPrice = (product, mode) => {
    const cents = product.pricing_config?.rates?.[mode]?.cents_per_unit ?? 0;
    return formatBRL(cents);
};

/**
 * Exibição à direita da linha: referência no idle; total calculado quando selecionado e calculável.
 */
const catalogPricePresentation = (product) => {
    const ref = catalogReferenceDisplay(product);
    const marked = isProductMarked(product);
    const lineCents = catalogLineCents(product.id);
    const subtotal = catalogLineSubtotal(product.id);
    const employees = Number(form.employee_count ?? 0);
    const sel = catalogSelection(product.id);

    const needsEmployees = ['per_employee', 'tiered_per_employee', 'salary_times_employees'].includes(
        product.pricing_type,
    ) && employees <= 0;

    const needsSalary = product.pricing_type === 'salary_times_employees'
        && marked
        && Number(sel.salary_cents ?? 0) <= 0;

    const needsFlexibleInput = product.pricing_type === 'flexible_rates'
        && marked
        && !isProductSelected(product);

    const showCalculated = marked
        && !needsEmployees
        && !needsSalary
        && !needsFlexibleInput
        && (lineCents > 0 || subtotal > 0 || product.pricing_type === 'fixed');

    if (showCalculated) {
        return {
            primary: formatBRL(lineCents),
            hint: null,
            isReference: false,
            noPrice: false,
        };
    }

    let hint = null;
    if (marked && needsEmployees) {
        hint = 'Informe o Nº de funcionários';
    } else if (marked && needsSalary) {
        hint = 'Informe o salário base';
    } else if (marked && needsFlexibleInput) {
        hint = 'Selecione a taxa e a quantidade';
    }

    return {
        primary: ref.label,
        hint,
        isReference: true,
        noPrice: !ref.has_catalog_price && product.pricing_type !== 'salary_times_employees',
    };
};

const hasPricedCatalogProduct = computed(() =>
    (catalogLines.value ?? []).some((line) => {
        if (Number(line?.value_cents ?? 0) > 0) {
            return true;
        }
        return line?.options?.adjustment === 'bonus' && Number(line?.subtotal_cents ?? 0) > 0;
    }),
);

const hasContractableServices = computed(() => {
    if (form.is_recurring) {
        return true;
    }
    if (props.proposal?.has_legacy_services) {
        return true;
    }
    return hasPricedCatalogProduct.value;
});

const palestrasProductSelected = computed(() =>
    props.catalogProducts.some((p) => p.slug === 'palestras' && isProductSelected(p)),
);

const activePdfServices = computed(() => {
    const seen = new Set();
    const items = [];

    const add = (key, label) => {
        if (!key || seen.has(key)) {
            return;
        }
        seen.add(key);
        items.push({ key, label });
    };

    (legacySummary.value || []).forEach((line) => add(line.key, line.label));

    (catalogLines.value || []).forEach((line) => add(line.key, line.label));

    props.catalogProducts.forEach((product) => {
        if (isProductMarked(product)) {
            add(product.slug, product.name);
        }
    });

    return items;
});

// Consulta CNPJ (Receita Federal) — reaproveita o endpoint já existente.
const cnpjLookupLoading = ref(false);
const cnpjLookupError = ref('');
const cnpjLookupSuccess = ref('');
const cnpjDigitCount = computed(() => (String(form.client_cnpj || '').match(/\d/g) || []).length);
const canLookupCnpj = computed(() => cnpjDigitCount.value === 14);

const onClientCnpjInput = (event) => {
    form.client_cnpj = maskCnpj(event.target.value);
};

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
        form.client_cnpj = maskCnpj(data.cnpj ?? form.client_cnpj);
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

const scrollToFirstFormError = () => {
    nextTick(() => {
        const priority = ['client_name', 'employee_count', 'catalog_products', 'payment_method_id', 'client_email'];
        const keys = [
            ...priority.filter((key) => form.errors[key]),
            ...Object.keys(form.errors).filter((key) => !priority.includes(key)),
        ];
        const first = keys[0];
        if (!first) {
            return;
        }
        const el = document.getElementById(`proposal-field-${first}`)
            ?? document.querySelector(`[data-error-for="${first}"]`);
        el?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
};

const isEmployeeCountValid = (value) => {
    if (value === null || value === undefined || value === '') {
        return false;
    }
    const n = Number(value);
    return Number.isFinite(n) && n >= 0 && Number.isInteger(n);
};

/** Gate client-side alinhado aos obrigatórios do backend. */
const validateRequiredFields = () => {
    form.clearErrors(
        'client_name',
        'employee_count',
        'payment_method_id',
        'recurring_months',
        'recurring_monthly_reais',
        'catalog_products',
    );

    let valid = true;

    if (form.is_recurring) {
        const months = Number(form.recurring_months);
        if (!Number.isInteger(months) || months < 1 || months > 60) {
            form.setError('recurring_months', 'Informe a duração em meses (1 a 60).');
            valid = false;
        }
        const monthly = Number(form.recurring_monthly_reais);
        if (!Number.isFinite(monthly) || monthly <= 0) {
            form.setError('recurring_monthly_reais', 'Informe o valor mensal.');
            valid = false;
        }
    }

    if (!String(form.client_name ?? '').trim()) {
        form.setError('client_name', 'Informe o nome / razão social.');
        valid = false;
    }

    if (!isEmployeeCountValid(form.employee_count)) {
        form.setError('employee_count', 'Informe o número de funcionários.');
        valid = false;
    }

    if (!form.payment_method_id) {
        form.setError('payment_method_id', 'Selecione a forma de pagamento.');
        valid = false;
    }

    if (!hasContractableServices.value) {
        form.setError('catalog_products', 'Selecione pelo menos um produto com valor.');
        valid = false;
    }

    if (!valid) {
        goToStepForErrors();
        scrollToFirstFormError();
    }

    return valid;
};

const submit = () => {
    if (!validateRequiredFields()) {
        return;
    }

    const options = {
        onError: () => {
            goToStepForErrors();
            scrollToFirstFormError();
        },
    };

    if (props.mode === 'edit') {
        form.put(route('admin.comercial.propostas.update', props.proposal.id), {
            ...options,
            preserveScroll: true,
        });
    } else {
        form.post(route('admin.comercial.propostas.store'), options);
    }
};

const markAsClosedModalOpen = ref(false);

const openMarkAsClosedModal = () => {
    if (form.is_closed || form.processing) {
        return;
    }
    if (!validateRequiredFields()) {
        return;
    }
    markAsClosedModalOpen.value = true;
};

const closeMarkAsClosedModal = () => {
    markAsClosedModalOpen.value = false;
};

const confirmMarkAsClosed = () => {
    if (form.is_closed || form.processing) {
        return;
    }
    if (!validateRequiredFields()) {
        markAsClosedModalOpen.value = false;
        return;
    }
    form.is_closed = true;
    markAsClosedModalOpen.value = false;
    submit();
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

const wizardSteps = [
    { id: 'tipo', label: 'Tipo', title: 'Tipo de serviço' },
    { id: 'cliente', label: 'Cliente', title: 'Dados do cliente' },
    { id: 'produtos', label: 'Produtos', title: 'Produtos' },
    { id: 'pdf', label: 'PDF', title: 'Conteúdo do PDF' },
    { id: 'comercial', label: 'Comercial', title: 'Informações comerciais' },
];

const currentStepIndex = ref(0);
const currentStepId = computed(() => wizardSteps[currentStepIndex.value]?.id ?? 'tipo');
const isFirstStep = computed(() => currentStepIndex.value === 0);
const isLastStep = computed(() => currentStepIndex.value === wizardSteps.length - 1);
const currentStepMeta = computed(() => wizardSteps[currentStepIndex.value] ?? wizardSteps[0]);

const stepIndexById = (id) => wizardSteps.findIndex((step) => step.id === id);

const goToStep = (index) => {
    if (index < 0 || index >= wizardSteps.length) {
        return;
    }
    currentStepIndex.value = index;
    nextTick(() => {
        document.getElementById('proposal-wizard')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
};

const errorKeyToStepId = (key) => {
    if (key.startsWith('recurring_') || key === 'is_recurring') {
        return 'tipo';
    }
    if (
        key.startsWith('client_')
        || key === 'indication'
        || key === 'employee_count'
        || key === 'include_publico_atendido'
    ) {
        return 'cliente';
    }
    if (key.startsWith('catalog_products') || key.startsWith('svc_')) {
        return 'produtos';
    }
    if (
        key.startsWith('pdf_')
        || key.startsWith('service_descriptions')
        || key.startsWith('palestra_')
    ) {
        return 'pdf';
    }
    if (
        key === 'seller_id'
        || key === 'payment_method_id'
        || key === 'include_minimum_stay'
        || key === 'notes'
        || key === 'is_closed'
    ) {
        return 'comercial';
    }

    return null;
};

const goToStepForErrors = () => {
    const priority = [
        'recurring_months',
        'recurring_monthly_reais',
        'client_name',
        'employee_count',
        'payment_method_id',
        'client_email',
        'catalog_products',
    ];
    const keys = [
        ...priority.filter((key) => form.errors[key]),
        ...Object.keys(form.errors).filter((key) => !priority.includes(key)),
    ];
    for (const key of keys) {
        const stepId = errorKeyToStepId(key);
        const index = stepId ? stepIndexById(stepId) : -1;
        if (index >= 0) {
            goToStep(index);
            return;
        }
    }
};

const validateCurrentStep = () => {
    const stepId = currentStepId.value;

    if (stepId === 'tipo') {
        form.clearErrors('recurring_months', 'recurring_monthly_reais');
        if (!form.is_recurring) {
            return true;
        }
        let valid = true;
        const months = Number(form.recurring_months);
        if (!Number.isInteger(months) || months < 1 || months > 60) {
            form.setError('recurring_months', 'Informe a duração em meses (1 a 60).');
            valid = false;
        }
        const monthly = Number(form.recurring_monthly_reais);
        if (!Number.isFinite(monthly) || monthly <= 0) {
            form.setError('recurring_monthly_reais', 'Informe o valor mensal.');
            valid = false;
        }
        return valid;
    }

    if (stepId === 'cliente') {
        form.clearErrors('client_name', 'employee_count');
        let valid = true;
        if (!String(form.client_name ?? '').trim()) {
            form.setError('client_name', 'Informe o nome / razão social.');
            valid = false;
        }
        if (!isEmployeeCountValid(form.employee_count)) {
            form.setError('employee_count', 'Informe o número de funcionários.');
            valid = false;
        }
        return valid;
    }

    if (stepId === 'produtos') {
        form.clearErrors('catalog_products');
        if (form.is_recurring || props.proposal?.has_legacy_services) {
            return true;
        }
        if (!hasPricedCatalogProduct.value) {
            form.setError('catalog_products', 'Selecione pelo menos um produto com valor.');
            return false;
        }
        return true;
    }

    if (stepId === 'comercial') {
        form.clearErrors('payment_method_id');
        if (!form.payment_method_id) {
            form.setError('payment_method_id', 'Selecione a forma de pagamento.');
            return false;
        }
        return true;
    }

    return true;
};

const goNextStep = () => {
    if (!validateCurrentStep()) {
        scrollToFirstFormError();
        return;
    }
    if (!isLastStep.value) {
        goToStep(currentStepIndex.value + 1);
    }
};

const goPrevStep = () => {
    if (!isFirstStep.value) {
        goToStep(currentStepIndex.value - 1);
    }
};

const stepHasErrors = (index) => {
    const stepId = wizardSteps[index]?.id;
    if (!stepId) {
        return false;
    }
    return Object.keys(form.errors).some((key) => errorKeyToStepId(key) === stepId);
};

/** completed | active | invalid | default | disabled */
const stepStatus = (index) => {
    if (stepHasErrors(index)) {
        return 'invalid';
    }
    if (index < currentStepIndex.value) {
        return 'completed';
    }
    if (index === currentStepIndex.value) {
        return 'active';
    }
    if (index === currentStepIndex.value + 1) {
        return 'default';
    }
    return 'disabled';
};

const connectorClass = (index) => {
    const status = stepStatus(index);
    if (status === 'completed' || status === 'active') {
        return 'bg-talents-600';
    }
    if (status === 'invalid') {
        return 'bg-rose-500';
    }
    return 'bg-slate-200';
};

const canNavigateToStep = (index) => index <= currentStepIndex.value;

const onStepClick = (index) => {
    // Só volta para passos já alcançados; avanço só via Continuar (com validação).
    if (index > currentStepIndex.value) {
        return;
    }
    goToStep(index);
};
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

        <div
            v-if="Object.keys(form.errors).length"
            class="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"
            role="alert"
        >
            Corrija os campos destacados antes de salvar a proposta.
        </div>

        <div id="proposal-wizard" class="mb-8 rounded-xl border border-slate-200 bg-white px-4 py-6 shadow-sm sm:px-8">
            <nav aria-label="Progresso da proposta">
                <ol class="flex w-full items-start">
                    <li
                        v-for="(step, index) in wizardSteps"
                        :key="step.id"
                        class="relative flex flex-1 flex-col items-center"
                    >
                        <!-- Linha à esquerda (exceto o 1º) -->
                        <div
                            v-if="index > 0"
                            class="absolute left-0 right-1/2 top-[1.125rem] h-0.5 -translate-y-1/2"
                            :class="connectorClass(index - 1)"
                            aria-hidden="true"
                        />
                        <!-- Linha à direita (exceto o último) -->
                        <div
                            v-if="index < wizardSteps.length - 1"
                            class="absolute left-1/2 right-0 top-[1.125rem] h-0.5 -translate-y-1/2"
                            :class="connectorClass(index)"
                            aria-hidden="true"
                        />

                        <button
                            type="button"
                            class="relative z-10 flex flex-col items-center text-center disabled:cursor-not-allowed"
                            :disabled="!canNavigateToStep(index)"
                            :aria-current="stepStatus(index) === 'active' ? 'step' : undefined"
                            :aria-label="step.title"
                            @click="onStepClick(index)"
                        >
                            <span
                                class="relative inline-flex h-9 w-9 items-center justify-center rounded-full text-base font-semibold"
                                :class="{
                                    'bg-emerald-500 text-white': stepStatus(index) === 'completed',
                                    'bg-talents-600 text-white': stepStatus(index) === 'active',
                                    'bg-rose-500 text-white': stepStatus(index) === 'invalid',
                                    'border-2 border-slate-400 bg-white text-slate-600': stepStatus(index) === 'default',
                                    'border-2 border-slate-200 bg-white text-slate-300': stepStatus(index) === 'disabled',
                                }"
                            >
                                <CheckIcon v-if="stepStatus(index) === 'completed'" class="h-4 w-4" aria-hidden="true" />
                                <template v-else>{{ index + 1 }}</template>
                                <span
                                    v-if="stepStatus(index) === 'active'"
                                    class="absolute -bottom-2 left-1/2 h-0 w-0 -translate-x-1/2 border-x-[5px] border-b-[6px] border-x-transparent border-b-talents-400"
                                    aria-hidden="true"
                                />
                            </span>

                            <span
                                class="mt-3 max-w-[6rem] text-xs font-semibold leading-tight sm:max-w-none sm:text-sm"
                                :class="{
                                    'text-emerald-600': stepStatus(index) === 'completed',
                                    'text-talents-700': stepStatus(index) === 'active',
                                    'text-rose-600': stepStatus(index) === 'invalid',
                                    'text-slate-600': stepStatus(index) === 'default',
                                    'text-slate-300': stepStatus(index) === 'disabled',
                                }"
                            >
                                {{ step.label }}
                            </span>
                        </button>
                    </li>
                </ol>
            </nav>
            <p class="mt-5 text-center text-base font-semibold text-talents-800">{{ currentStepMeta.title }}</p>
        </div>

        <form class="grid gap-8 lg:grid-cols-3" novalidate @submit.prevent="submit">
            <div class="space-y-6 lg:col-span-2">
                <!-- Tipo de serviço (primeira impressão) -->
                <section v-show="currentStepId === 'tipo'" class="surface-card p-6">
                    <h3 class="text-lg font-semibold text-slate-900">Tipo de serviço</h3>
                    <p class="mt-1 text-sm text-slate-600">
                        Defina se a proposta é pontual ou um acompanhamento recorrente ao longo dos meses.
                    </p>

                    <div
                        class="mt-4 inline-flex w-full max-w-md rounded-xl border border-slate-200 bg-slate-50 p-1"
                        role="tablist"
                        aria-label="Tipo de serviço"
                    >
                        <button
                            type="button"
                            role="tab"
                            class="flex-1 rounded-lg px-4 py-2.5 text-sm font-semibold transition"
                            :class="
                                !form.is_recurring
                                    ? 'bg-white text-talents-800 shadow-sm ring-1 ring-slate-200'
                                    : 'text-slate-600 hover:text-slate-900'
                            "
                            :aria-selected="!form.is_recurring"
                            @click="setServiceType('esporadico')"
                        >
                            Esporádico
                        </button>
                        <button
                            type="button"
                            role="tab"
                            class="flex-1 rounded-lg px-4 py-2.5 text-sm font-semibold transition"
                            :class="
                                form.is_recurring
                                    ? 'bg-white text-talents-800 shadow-sm ring-1 ring-slate-200'
                                    : 'text-slate-600 hover:text-slate-900'
                            "
                            :aria-selected="form.is_recurring"
                            @click="setServiceType('recorrente')"
                        >
                            Recorrente
                        </button>
                    </div>

                    <p v-if="!form.is_recurring" class="mt-3 text-sm text-slate-600">
                        Entrega pontual: o honorário final segue o cálculo dos produtos selecionados.
                    </p>

                    <div v-else class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">
                                Duração (meses) *
                            </label>
                            <input
                                v-model.number="form.recurring_months"
                                type="number"
                                min="1"
                                max="60"
                                required
                                class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                :class="form.errors.recurring_months ? 'border-rose-400' : ''"
                            />
                            <p v-if="form.errors.recurring_months" class="mt-1 text-xs text-rose-600">
                                {{ form.errors.recurring_months }}
                            </p>
                        </div>
                        <div>
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">
                                Valor mensal (R$) *
                            </label>
                            <input
                                v-model="form.recurring_monthly_reais"
                                type="number"
                                step="0.01"
                                min="0.01"
                                required
                                class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                :class="form.errors.recurring_monthly_reais ? 'border-rose-400' : ''"
                            />
                            <p v-if="form.errors.recurring_monthly_reais" class="mt-1 text-xs text-rose-600">
                                {{ form.errors.recurring_monthly_reais }}
                            </p>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">
                                Cronograma / descrição da recorrência
                            </label>
                            <textarea
                                v-model="form.recurring_notes"
                                rows="3"
                                placeholder="Ex.: Acompanhamento mensal de Direcionamento Estratégico ao longo de 6 meses."
                                class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            />
                            <p class="mt-1 text-xs text-slate-500">
                                O total do período será valor mensal × duração. Na conversão em venda, gera uma
                                cobrança por mês em Contas a receber.
                            </p>
                            <p v-if="form.errors.recurring_notes" class="mt-1 text-xs text-rose-600">
                                {{ form.errors.recurring_notes }}
                            </p>
                        </div>
                    </div>
                </section>

                <!-- Cliente -->
                <section v-show="currentStepId === 'cliente'" class="surface-card p-6">
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
                                    inputmode="numeric"
                                    autocomplete="off"
                                    maxlength="18"
                                    class="block w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500 sm:max-w-md"
                                    @input="onClientCnpjInput"
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
                            <div id="proposal-field-client_name" class="sm:col-span-2" data-error-for="client_name">
                                <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Nome / Razão social *</label>
                                <input
                                    v-model="form.client_name"
                                    type="text"
                                    required
                                    class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                    :class="form.errors.client_name ? 'border-rose-400' : ''"
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
                            <div id="proposal-field-client_email" data-error-for="client_email">
                                <label class="text-xs font-medium uppercase tracking-wide text-slate-500">E-mail</label>
                                <input
                                    v-model="form.client_email"
                                    type="email"
                                    class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                    :class="form.errors.client_email ? 'border-rose-400' : ''"
                                />
                                <p v-if="form.errors.client_email" class="mt-1 text-xs text-rose-600">{{ form.errors.client_email }}</p>
                            </div>
                            <div>
                                <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Indicação</label>
                                <input
                                    v-model="form.indication"
                                    type="text"
                                    class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                />
                            </div>
                            <div id="proposal-field-employee_count" data-error-for="employee_count">
                                <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Nº de funcionários *</label>
                                <input
                                    v-model.number="form.employee_count"
                                    type="number"
                                    min="0"
                                    required
                                    class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                    :class="form.errors.employee_count ? 'border-rose-400' : ''"
                                />
                                <p v-if="form.errors.employee_count" class="mt-1 text-xs text-rose-600">
                                    {{ form.errors.employee_count }}
                                </p>
                                <p class="mt-1 text-[11px] text-slate-500">
                                    Necessário para calcular produtos por funcionário.
                                </p>
                            </div>
                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-3 sm:col-span-2">
                                <input
                                    v-model="form.include_publico_atendido"
                                    type="checkbox"
                                    class="mt-0.5 rounded border-slate-300 text-talents-700 focus:ring-talents-500"
                                />
                                <span>
                                    <span class="block text-sm font-medium text-slate-900">
                                        Incluir “Público Atendido” no PDF
                                    </span>
                                    <span class="mt-0.5 block text-xs text-slate-500">
                                        Quando marcado, o PDF mostra a secção com o número de colaboradores acima.
                                    </span>
                                </span>
                            </label>
                        </div>
                    </div>
                </section>

                <!-- Produtos -->
                <section
                    id="proposal-field-catalog_products"
                    v-show="currentStepId === 'produtos'"
                    class="surface-card p-6"
                    data-error-for="catalog_products"
                >
                    <h3 class="text-lg font-semibold text-slate-900">Produtos</h3>
                    <p class="mt-1 text-xs text-slate-500">
                        Selecione os produtos; o cálculo aparece no resumo ao lado.
                        Cadastre novos em Comercial → Valores e contratos → aba Produtos.
                    </p>
                    <p v-if="form.errors.catalog_products" class="mt-2 text-sm text-rose-600">
                        {{ form.errors.catalog_products }}
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
                                    </div>
                                    <div
                                        class="max-w-[11rem] text-right text-sm tabular-nums"
                                        :class="catalogPricePresentation(product).isReference ? 'text-slate-500' : 'text-slate-700'"
                                    >
                                        <div>{{ catalogPricePresentation(product).primary }}</div>
                                        <div
                                            v-if="catalogPricePresentation(product).noPrice"
                                            class="mt-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-700"
                                        >
                                            sem preço no catálogo
                                        </div>
                                        <div
                                            v-if="catalogPricePresentation(product).hint"
                                            class="mt-0.5 text-[11px] font-normal normal-case tracking-normal text-amber-700"
                                        >
                                            {{ catalogPricePresentation(product).hint }}
                                        </div>
                                    </div>
                                </label>
                                <CommercialAdjustmentFields
                                    v-if="showCommercialAdjustment(product)"
                                    :selection="catalogSelection(product.id)"
                                    :subtotal-cents="catalogLineSubtotal(product.id)"
                                    :total-cents="catalogLineCents(product.id)"
                                />
                                <CatalogProductObservationField
                                    v-if="isProductMarked(product)"
                                    v-model="catalogSelection(product.id).observation"
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
                                <div
                                    class="mt-2 text-right text-sm tabular-nums"
                                    :class="catalogPricePresentation(product).isReference ? 'text-slate-500' : 'text-slate-700'"
                                >
                                    <div>{{ catalogPricePresentation(product).primary }}</div>
                                    <div
                                        v-if="catalogPricePresentation(product).noPrice"
                                        class="mt-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-700"
                                    >
                                        sem preço no catálogo
                                    </div>
                                    <div
                                        v-if="catalogPricePresentation(product).hint"
                                        class="mt-0.5 text-[11px] font-normal text-amber-700"
                                    >
                                        {{ catalogPricePresentation(product).hint }}
                                    </div>
                                </div>
                                <CommercialAdjustmentFields
                                    v-if="showCommercialAdjustment(product)"
                                    :selection="catalogSelection(product.id)"
                                    :subtotal-cents="catalogLineSubtotal(product.id)"
                                    :total-cents="catalogLineCents(product.id)"
                                />
                                <CatalogProductObservationField
                                    v-if="isProductMarked(product)"
                                    v-model="catalogSelection(product.id).observation"
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
                                    </div>
                                    <div
                                        class="max-w-[11rem] text-right text-sm tabular-nums"
                                        :class="catalogPricePresentation(product).isReference ? 'text-slate-500' : 'text-slate-700'"
                                    >
                                        <div>{{ catalogPricePresentation(product).primary }}</div>
                                        <div
                                            v-if="catalogPricePresentation(product).noPrice"
                                            class="mt-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-700"
                                        >
                                            sem preço no catálogo
                                        </div>
                                        <div
                                            v-if="catalogPricePresentation(product).hint"
                                            class="mt-0.5 text-[11px] font-normal normal-case tracking-normal text-amber-700"
                                        >
                                            {{ catalogPricePresentation(product).hint }}
                                        </div>
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
                                            <option :value="FLEXIBLE_RATE_CUSTOM.key">
                                                {{ FLEXIBLE_RATE_CUSTOM.label }}
                                            </option>
                                        </select>
                                    </div>

                                    <div v-if="isFlexibleRateModeCustom(product.id)">
                                        <label class="text-xs font-medium uppercase tracking-wide text-slate-500">
                                            {{ FLEXIBLE_RATE_CUSTOM.unitsLabel }}
                                        </label>
                                        <input
                                            :value="catalogCustomReais(product.id)"
                                            type="text"
                                            placeholder="0,00"
                                            class="mt-1 w-full max-w-xs rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                            @input="updateCatalogCustomValue(product.id, $event.target.value)"
                                        />
                                    </div>

                                    <div v-else-if="catalogSelection(product.id).rate_mode">
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
                                <CatalogProductObservationField
                                    v-if="isProductMarked(product)"
                                    v-model="catalogSelection(product.id).observation"
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
                                    <div
                                        class="max-w-[11rem] text-right text-sm tabular-nums"
                                        :class="catalogPricePresentation(product).isReference ? 'text-slate-500' : 'text-slate-700'"
                                    >
                                        <div>{{ catalogPricePresentation(product).primary }}</div>
                                        <div
                                            v-if="catalogPricePresentation(product).noPrice"
                                            class="mt-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-700"
                                        >
                                            sem preço no catálogo
                                        </div>
                                        <div
                                            v-if="catalogPricePresentation(product).hint"
                                            class="mt-0.5 text-[11px] font-normal normal-case tracking-normal text-amber-700"
                                        >
                                            {{ catalogPricePresentation(product).hint }}
                                        </div>
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
                                <CatalogProductObservationField
                                    v-if="isProductMarked(product)"
                                    v-model="catalogSelection(product.id).observation"
                                />
                            </div>
                        </template>
                    </div>
                    <p v-else class="mt-4 text-sm text-slate-500">
                        Nenhum produto cadastrado. Acesse Comercial → Valores e contratos → aba Produtos.
                    </p>
                </section>

                <!-- Seções opcionais do PDF -->
                <section
                    v-show="currentStepId === 'pdf'"
                    v-if="pdfOptionalSectionOptions.length"
                    class="surface-card p-6"
                >
                    <h3 class="text-lg font-semibold text-slate-900">Seções opcionais no PDF</h3>
                    <p class="mt-1 text-xs text-slate-500">
                        Marque os blocos informativos que devem aparecer no PDF, além dos serviços orçados.
                        Itens sem preço — «conforme proposta específica».
                    </p>
                    <div class="mt-4 space-y-3">
                        <label
                            v-for="opt in pdfOptionalSectionOptions"
                            :key="opt.key"
                            class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-3 transition hover:border-talents-200 hover:bg-talents-50/30"
                        >
                            <input
                                v-model="form.pdf_optional_sections[opt.key]"
                                type="checkbox"
                                class="mt-0.5 rounded border-slate-300 text-talents-700 focus:ring-talents-500"
                            />
                            <span>
                                <span class="block text-sm font-medium text-slate-900">{{ opt.label }}</span>
                                <span class="mt-0.5 block text-xs text-slate-500">{{ opt.hint }}</span>
                            </span>
                        </label>
                    </div>
                </section>

                <!-- Descrições dos serviços no PDF -->
                <section v-show="currentStepId === 'pdf'" class="surface-card p-6">
                    <h3 class="text-lg font-semibold text-slate-900">Descrições no PDF</h3>
                    <p class="mt-1 text-xs text-slate-500">
                        Textos exibidos em cada serviço da proposta. Preenchidos automaticamente; clique para editar.
                    </p>
                    <div v-if="activePdfServices.length" class="mt-4 space-y-3">
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
                    <p v-else class="mt-4 text-sm text-slate-500">
                        Selecione um ou mais produtos acima para personalizar as descrições exibidas no PDF.
                    </p>
                </section>

                <!-- Palestra — evento (contrato) -->
                <section
                    v-show="currentStepId === 'pdf'"
                    v-if="palestrasProductSelected"
                    class="surface-card p-6"
                >
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
                <section v-show="currentStepId === 'comercial'" class="surface-card p-6">
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
                        <div id="proposal-field-payment_method_id" data-error-for="payment_method_id">
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">
                                Forma de pagamento (PDF) *
                            </label>
                            <select
                                v-model="form.payment_method_id"
                                required
                                class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                :class="form.errors.payment_method_id ? 'border-rose-400' : ''"
                            >
                                <option value="">— Selecionar —</option>
                                <option
                                    v-for="opt in paymentMethodOptions"
                                    :key="opt.value"
                                    :value="opt.value"
                                >
                                    {{ opt.label }}
                                </option>
                            </select>
                            <p class="mt-1 text-xs text-slate-500">
                                Aparece na secção «Condições de Pagamento» do PDF desta proposta.
                                Origem: Financeiro → Formas de pagamento.
                            </p>
                            <p v-if="!paymentMethodOptions.length" class="mt-1 text-xs text-amber-700">
                                Cadastre formas de pagamento em
                                <Link
                                    :href="route('admin.financeiro.formas-pagamento.index')"
                                    class="font-semibold underline"
                                >
                                    Financeiro → Formas de pagamento
                                </Link>.
                            </p>
                            <p v-if="form.errors.payment_method_id" class="mt-1 text-xs text-rose-600">
                                {{ form.errors.payment_method_id }}
                            </p>
                        </div>
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-3 sm:col-span-2">
                            <input
                                v-model="form.include_minimum_stay"
                                type="checkbox"
                                class="mt-0.5 rounded border-slate-300 text-talents-700 focus:ring-talents-500"
                            />
                            <span>
                                <span class="block text-sm font-medium text-slate-900">
                                    Incluir permanência mínima no PDF
                                </span>
                                <span class="mt-0.5 block text-xs text-slate-500">
                                    Quando marcado, inclui a condição de permanência mínima de 90 dias (3 meses) para
                                    cancelamento do plano.
                                </span>
                            </span>
                        </label>
                        <div class="sm:col-span-2">
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Observações</label>
                            <textarea
                                v-model="form.notes"
                                rows="3"
                                class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            />
                        </div>
                    </div>
                </section>

                <section
                    v-show="currentStepId === 'comercial'"
                    v-if="isEdit"
                    class="surface-card p-6"
                >
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

                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                    <button
                        type="button"
                        class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="isFirstStep"
                        @click="goPrevStep"
                    >
                        Voltar
                    </button>
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            v-if="!isLastStep"
                            type="button"
                            class="inline-flex items-center rounded-xl bg-talents-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-talents-700"
                            @click="goNextStep"
                        >
                            Continuar
                        </button>
                        <button
                            v-else
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex items-center rounded-xl bg-talents-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-talents-700 disabled:opacity-60"
                        >
                            {{ isEdit ? 'Salvar alterações' : 'Salvar proposta' }}
                        </button>
                    </div>
                </div>
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
                        <template v-if="form.is_recurring">
                            <div class="flex items-center justify-between text-sm text-slate-600">
                                <span>Valor mensal</span>
                                <span class="tabular-nums">{{ formatBRL(recurringMonthlyCents) }}</span>
                            </div>
                            <div class="mt-1 flex items-center justify-between text-sm text-slate-600">
                                <span>Duração</span>
                                <span class="tabular-nums">
                                    {{ recurringMonthsCount || '—' }}
                                    {{ recurringMonthsCount === 1 ? 'mês' : 'meses' }}
                                </span>
                            </div>
                        </template>
                        <div class="mt-2 flex items-center justify-between text-base font-semibold text-talents-700">
                            <span>{{ form.is_recurring ? 'Total do período' : 'Honorário Final' }}</span>
                            <span class="tabular-nums">{{ formatBRL(honorarioFinalCents) }}</span>
                        </div>
                    </div>

                    <div class="mt-6 space-y-2">
                        <button
                            type="button"
                            :disabled="form.is_closed || form.processing"
                            class="inline-flex w-full items-center justify-center rounded-xl border border-emerald-300 bg-white px-4 py-2.5 text-sm font-semibold text-emerald-800 shadow-sm transition hover:bg-emerald-50 disabled:cursor-not-allowed disabled:opacity-60"
                            @click="openMarkAsClosedModal"
                        >
                            {{ form.is_closed ? 'Proposta fechada' : 'Marcar como fechada' }}
                        </button>
                        <p v-if="!form.is_closed" class="text-center text-xs text-slate-500">
                            A data de fechamento será registrada ao salvar.
                        </p>
                    </div>
                </div>
            </aside>
        </form>

        <Modal :show="markAsClosedModalOpen" max-width="md" @close="closeMarkAsClosedModal">
            <div class="p-6">
                <h2 class="text-lg font-semibold text-slate-900">Marcar proposta como fechada?</h2>
                <p class="mt-2 text-sm text-slate-600">
                    A data de fechamento será registrada agora. Esta ação indica que a proposta foi ganha/fechada.
                </p>
                <div class="mt-6 flex justify-end gap-2">
                    <button
                        type="button"
                        class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                        @click="closeMarkAsClosedModal"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        :disabled="form.processing"
                        class="inline-flex items-center rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 disabled:opacity-60"
                        @click="confirmMarkAsClosed"
                    >
                        {{ form.processing ? 'Salvando…' : 'Confirmar' }}
                    </button>
                </div>
            </div>
        </Modal>
    </AdminLayout>
</template>
