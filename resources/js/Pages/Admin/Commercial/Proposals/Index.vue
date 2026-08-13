<script setup>
import CommercialModuleNav from '@/Components/Commercial/CommercialModuleNav.vue';
import FullScreenOverlay from '@/Components/FullScreenOverlay.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatBRL } from '@/composables/useCommercialPricing';
import { formatCnpj } from '@/utils/formatCnpj';
import {
    ArrowPathIcon,
    BanknotesIcon,
    CheckCircleIcon,
    DocumentArrowDownIcon,
    DocumentTextIcon,
    PencilSquareIcon,
    PlusIcon,
    TrashIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue';

const inertiaPage = usePage();

const props = defineProps({
    proposals: { type: Object, required: true },
    sellers: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    statusCounts: {
        type: Object,
        default: () => ({
            all: 0,
            abertas: 0,
            em_negociacao: 0,
            aprovadas: 0,
            encerradas: 0,
        }),
    },
    templates: { type: Array, default: () => [] },
    zapsign_configured: { type: Boolean, default: false },
    zapsignParties: {
        type: Object,
        default: () => ({
            contratada_signatario: '',
            contratada_telefone: '',
            contratada_email: '',
        }),
    },
});

const filterState = reactive({
    search: props.filters.search ?? '',
    seller_id: props.filters.seller_id ?? '',
    status: props.filters.status ?? '',
    sale_situation: props.filters.sale_situation ?? '',
    created_from: props.filters.created_from ?? '',
    created_to: props.filters.created_to ?? '',
});

watch(
    () => props.filters,
    (filters) => {
        filterState.search = filters.search ?? '';
        filterState.seller_id = filters.seller_id ?? '';
        filterState.status = filters.status ?? '';
        filterState.sale_situation = filters.sale_situation ?? '';
        filterState.created_from = filters.created_from ?? '';
        filterState.created_to = filters.created_to ?? '';
    },
    { deep: true },
);

const statusChipOptions = computed(() => [
    { value: '', label: 'Todas', count: props.statusCounts.all ?? 0 },
    { value: 'abertas', label: 'Em aberto', count: props.statusCounts.abertas ?? 0 },
    { value: 'em_negociacao', label: 'Em negociação', count: props.statusCounts.em_negociacao ?? 0 },
    { value: 'aprovadas', label: 'Aprovadas', count: props.statusCounts.aprovadas ?? 0 },
    { value: 'encerradas', label: 'Encerradas', count: props.statusCounts.encerradas ?? 0 },
]);

const statusFilterLabel = (value) => {
    if (value === 'abertas') return 'Em aberto';
    if (value === 'em_negociacao' || value === 'em_andamento') return 'Em negociação';
    if (value === 'aprovadas' || value === 'fechadas') return 'Aprovada';
    if (value === 'encerradas') return 'Encerrada';
    return 'Todos';
};

const saleSituationLabel = (value) => {
    if (value === 'without_sale') return 'Sem venda';
    if (value === 'with_sale') return 'Com venda';
    return 'Todas';
};

const formatFilterDate = (ymd) => {
    const raw = String(ymd ?? '').trim();
    if (!/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
        return raw;
    }
    const [y, m, d] = raw.split('-');
    return `${d}/${m}/${y}`;
};

const sellerNameById = (id) => {
    const found = props.sellers.find((s) => String(s.id) === String(id));
    return found?.name ?? String(id);
};

const filterQuery = () => {
    const params = {};
    if (String(filterState.search ?? '').trim() !== '') {
        params.search = String(filterState.search).trim();
    }
    if (String(filterState.seller_id ?? '') !== '') {
        params.seller_id = filterState.seller_id;
    }
    if (String(filterState.status ?? '') !== '') {
        params.status = filterState.status;
    }
    if (String(filterState.sale_situation ?? '') !== '') {
        params.sale_situation = filterState.sale_situation;
    }
    if (String(filterState.created_from ?? '') !== '') {
        params.created_from = filterState.created_from;
    }
    if (String(filterState.created_to ?? '') !== '') {
        params.created_to = filterState.created_to;
    }
    return params;
};

const activeFilterChips = computed(() => {
    const chips = [];
    if (String(filterState.search ?? '').trim() !== '') {
        chips.push({
            key: 'search',
            label: `Busca: ${String(filterState.search).trim()}`,
        });
    }
    if (String(filterState.seller_id ?? '') !== '') {
        chips.push({
            key: 'seller_id',
            label: `Vendedor: ${sellerNameById(filterState.seller_id)}`,
        });
    }
    if (String(filterState.status ?? '') !== '') {
        chips.push({
            key: 'status',
            label: `Status: ${statusFilterLabel(filterState.status)}`,
        });
    }
    if (String(filterState.sale_situation ?? '') !== '') {
        chips.push({
            key: 'sale_situation',
            label: saleSituationLabel(filterState.sale_situation),
        });
    }
    if (filterState.created_from || filterState.created_to) {
        const from = filterState.created_from ? formatFilterDate(filterState.created_from) : '…';
        const to = filterState.created_to ? formatFilterDate(filterState.created_to) : '…';
        chips.push({
            key: 'created',
            label: `Criada: ${from} – ${to}`,
        });
    }
    return chips;
});

const listStatusBadgeClass = (status) => {
    if (status === 'negotiation' || status === 'in_progress') {
        return 'bg-indigo-100 text-indigo-800';
    }
    if (status === 'approved' || status === 'closed') {
        return 'bg-emerald-100 text-emerald-800';
    }
    if (status === 'ended') {
        return 'bg-slate-100 text-slate-600';
    }
    return 'bg-amber-100 text-amber-800';
};

const listStatusLabel = (proposal) => proposal.list_status_label
    ?? (proposal.list_status === 'negotiation' || proposal.list_status === 'in_progress'
        ? 'Em negociação'
        : proposal.list_status === 'approved' || proposal.list_status === 'closed' || proposal.is_closed
            ? 'Aprovada'
            : proposal.list_status === 'ended'
                ? 'Encerrada'
                : 'Em aberto');

const installmentsProgressLabel = (proposal) => {
    const paid = proposal.paid_installments;
    const total = proposal.total_installments;
    if (paid == null || total == null || Number(total) < 1) {
        return null;
    }
    return `${paid}/${total} pagas`;
};

const statusModalOpen = ref(false);
const statusProposal = ref(null);
const statusForm = useForm({
    status: 'open',
});

const openStatusModal = (proposal) => {
    statusProposal.value = proposal;
    statusForm.clearErrors();
    statusForm.status = proposal.list_status
        ?? (proposal.is_closed ? 'approved' : 'open');
    if (statusForm.status === 'in_progress') {
        statusForm.status = 'negotiation';
    }
    if (statusForm.status === 'closed') {
        statusForm.status = 'approved';
    }
    statusModalOpen.value = true;
};

const closeStatusModal = () => {
    if (statusForm.processing) {
        return;
    }
    statusModalOpen.value = false;
    statusProposal.value = null;
    statusForm.reset();
    statusForm.clearErrors();
};

const submitStatus = () => {
    if (!statusProposal.value) {
        return;
    }
    statusForm.patch(route('admin.comercial.propostas.status', statusProposal.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            statusModalOpen.value = false;
            statusProposal.value = null;
            statusForm.reset();
            statusForm.clearErrors();
        },
    });
};

const applyFilters = () => {
    router.get(route('admin.comercial.propostas.index'), filterQuery(), {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const applyStatusChip = (status) => {
    filterState.status = status;
    applyFilters();
};

const clearFilters = () => {
    filterState.search = '';
    filterState.seller_id = '';
    filterState.status = '';
    filterState.sale_situation = '';
    filterState.created_from = '';
    filterState.created_to = '';
    applyFilters();
};

const clearActiveFilter = (key) => {
    if (key === 'search') {
        filterState.search = '';
    } else if (key === 'seller_id') {
        filterState.seller_id = '';
    } else if (key === 'status') {
        filterState.status = '';
    } else if (key === 'sale_situation') {
        filterState.sale_situation = '';
    } else if (key === 'created') {
        filterState.created_from = '';
        filterState.created_to = '';
    }
    applyFilters();
};

const destroy = (proposal) => {
    if (confirm(`Excluir a proposta ${proposal.code}? Essa ação não pode ser desfeita.`)) {
        router.delete(route('admin.comercial.propostas.destroy', proposal.id), { preserveScroll: true });
    }
};

const formatDate = (iso) => (iso ? new Date(iso).toLocaleDateString('pt-BR') : '—');

const contractModalOpen = ref(false);
const contractProposal = ref(null);
const contractTemplateId = ref('');
const generatedContractId = ref(null);
const generatedTemplateName = ref('');
const contractGenerating = ref(false);
const zapsignSending = ref(false);
const zapsignSent = ref(false);
const zapsignSignUrl = ref('');

const selectedTemplateName = computed(() => {
    const id = Number(contractTemplateId.value);
    return props.templates.find((t) => t.id === id)?.name ?? '';
});

const emailLooksValid = (s) => {
    const t = String(s ?? '').trim();
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(t);
};

const digitsOnly = (s) => String(s ?? '').replace(/\D/g, '');

/** Celular BR suficiente para tentativa ZapSign (backend valida com DDD). */
const phoneLooksValid = (s) => {
    let d = digitsOnly(s);
    if (d.startsWith('55')) {
        d = d.slice(2);
    }
    return d.length >= 10 && d.length <= 11;
};

const clienteRepresentanteNome = computed(() => {
    const p = contractProposal.value;
    if (!p) return '—';
    return String(p.client_representative || p.client_name || '').trim() || '—';
});

const zapsignClienteContatoOk = computed(() => {
    const p = contractProposal.value;
    if (!p) return false;
    return phoneLooksValid(p.client_phone) || emailLooksValid(p.client_email);
});

const zapsignContratadaContatoOk = computed(() => {
    const z = props.zapsignParties || {};
    return phoneLooksValid(z.contratada_telefone) || emailLooksValid(z.contratada_email);
});

const pdfPreviewUrl = computed(() => {
    if (!generatedContractId.value) return '';
    try {
        return new URL(
            route('admin.comercial.contratos.pdf', generatedContractId.value),
            window.location.origin,
        ).href;
    } catch {
        return '';
    }
});

const openContractModal = (proposal) => {
    contractProposal.value = proposal;
    contractTemplateId.value = props.templates[0]?.id ? String(props.templates[0].id) : '';
    generatedContractId.value = null;
    generatedTemplateName.value = '';
    zapsignSent.value = false;
    zapsignSignUrl.value = '';
    contractModalOpen.value = true;
};

const closeContractModal = () => {
    contractModalOpen.value = false;
    contractProposal.value = null;
    generatedContractId.value = null;
    generatedTemplateName.value = '';
    zapsignSent.value = false;
    zapsignSignUrl.value = '';
};

const submitContract = () => {
    if (!contractProposal.value || !contractTemplateId.value) return;
    contractGenerating.value = true;
    zapsignSent.value = false;
    zapsignSignUrl.value = '';
    router.post(
        route('admin.comercial.propostas.contratos.store', contractProposal.value.id),
        { template_id: Number(contractTemplateId.value) },
        {
            preserveScroll: true,
            onFinish: () => {
                contractGenerating.value = false;
            },
            onSuccess: (page) => {
                nextTick(() => {
                    const id = page.props.flash?.contract_id;
                    if (id) {
                        generatedContractId.value = id;
                        generatedTemplateName.value = selectedTemplateName.value;
                    }
                });
            },
        },
    );
};

const openPdfNewTab = () => {
    if (!generatedContractId.value) return;
    window.open(route('admin.comercial.contratos.pdf', generatedContractId.value), '_blank', 'noopener');
};

const sendZapSign = () => {
    if (!generatedContractId.value || zapsignSending.value || zapsignSent.value) return;
    zapsignSending.value = true;
    router.post(
        route('admin.comercial.contratos.zapsign', generatedContractId.value),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                zapsignSending.value = false;
            },
            onSuccess: (page) => {
                const url = page.props.flash?.zapsign_sign_url;
                if (url) {
                    zapsignSignUrl.value = url;
                }
                if (page.props.flash?.success && !page.props.flash?.error) {
                    zapsignSent.value = true;
                }
            },
        },
    );
};

const convertModalOpen = ref(false);
const convertProposal = ref(null);
const convertClientErrors = ref([]);

const MIX_METHOD_OPTIONS = [
    { value: 'pix', label: 'PIX' },
    { value: 'boleto', label: 'Boleto' },
    { value: 'cartao', label: 'Cartão' },
];

const localTodayDate = () => {
    const d = new Date();
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
};

const defaultMixParts = () => [
    { method: 'pix', percent: 50 },
    { method: 'cartao', percent: 50 },
];

const convertForm = useForm({
    payment_method: 'pix',
    installments_count: 1,
    first_due_date: localTodayDate(),
    notes: '',
    mix_parts: [],
});

const isConvertMisto = computed(() => convertForm.payment_method === 'misto');

const mixPercentSum = computed(() => (
    (convertForm.mix_parts || []).reduce((sum, part) => sum + (Number(part.percent) || 0), 0)
));

const mixPartsWithAmounts = computed(() => {
    const total = Number(convertProposal.value?.total_final_cents ?? 0);
    const parts = convertForm.mix_parts || [];
    let allocated = 0;

    return parts.map((part, index) => {
        const percent = Number(part.percent) || 0;
        let amountCents = 0;
        if (total > 0 && percent > 0) {
            if (index === parts.length - 1) {
                amountCents = Math.max(0, total - allocated);
            } else {
                amountCents = Math.round(total * (percent / 100));
                allocated += amountCents;
            }
        }

        return {
            ...part,
            amount_cents: amountCents,
            label: MIX_METHOD_OPTIONS.find((o) => o.value === part.method)?.label ?? part.method,
        };
    });
});

const mixPreviewLabel = computed(() => {
    const parts = mixPartsWithAmounts.value.filter((p) => p.method && Number(p.percent) > 0);
    if (!parts.length) {
        return '';
    }
    return parts.map((p) => `${p.label} ${formatBRL(p.amount_cents)}`).join(' · ');
});

watch(
    () => convertForm.payment_method,
    (method, previous) => {
        if (method === 'misto' && previous !== 'misto') {
            convertForm.mix_parts = defaultMixParts();
            convertForm.installments_count = convertForm.mix_parts.length;
        }
        if (method !== 'misto' && previous === 'misto') {
            convertForm.mix_parts = [];
            convertForm.installments_count = 1;
        }
        convertClientErrors.value = [];
        convertForm.clearErrors('mix_parts', 'installments_count');
    },
);

const canConvert = (proposal) => {
    if (proposal.sale) {
        return false;
    }
    const status = proposal.list_status
        ?? (proposal.is_closed ? 'approved' : 'open');

    return status === 'approved' || status === 'closed' || proposal.is_closed;
};

const addMixPart = () => {
    convertForm.mix_parts.push({ method: 'pix', percent: '' });
};

const removeMixPart = (index) => {
    if ((convertForm.mix_parts?.length ?? 0) <= 2) {
        return;
    }
    convertForm.mix_parts.splice(index, 1);
};

const validateConvertForm = () => {
    convertClientErrors.value = [];
    convertForm.clearErrors();

    if (!convertForm.payment_method) {
        convertForm.setError('payment_method', 'Selecione a forma de pagamento.');
    }

    if (!convertForm.first_due_date) {
        convertForm.setError('first_due_date', 'Informe o 1º vencimento.');
    }

    if (isConvertMisto.value) {
        const parts = convertForm.mix_parts || [];
        if (parts.length < 2) {
            convertClientErrors.value.push('Informe pelo menos 2 partes na composição.');
        }
        parts.forEach((part, index) => {
            if (!part.method) {
                convertForm.setError(`mix_parts.${index}.method`, 'Selecione a forma.');
            }
            const percent = Number(part.percent);
            if (!Number.isFinite(percent) || percent <= 0) {
                convertForm.setError(`mix_parts.${index}.percent`, 'Informe um percentual maior que zero.');
            }
        });
        if (Math.abs(mixPercentSum.value - 100) > 0.05) {
            convertClientErrors.value.push('A soma dos percentuais deve ser 100%.');
        }
    } else {
        const count = Number(convertForm.installments_count);
        if (!Number.isInteger(count) || count < 1 || count > 60) {
            convertForm.setError('installments_count', 'Informe o número de parcelas (1 a 60).');
        }
    }

    return Object.keys(convertForm.errors).length === 0 && convertClientErrors.value.length === 0;
};

const openConvertModal = (proposal) => {
    convertProposal.value = proposal;
    convertClientErrors.value = [];
    convertForm.clearErrors();
    const today = localTodayDate();
    convertForm.defaults({
        payment_method: 'pix',
        installments_count: 1,
        first_due_date: today,
        notes: '',
        mix_parts: [],
    });
    convertForm.reset();
    convertModalOpen.value = true;
};

const closeConvertModal = () => {
    convertModalOpen.value = false;
    convertProposal.value = null;
    convertClientErrors.value = [];
    convertForm.reset();
    convertForm.clearErrors();
};

const saleSuccessModalOpen = ref(false);
const saleFeedbackPhase = ref('success'); // 'pending' | 'success'
const createdSale = ref({ id: null, code: null });

const openSaleSuccessModal = (saleId, saleCode) => {
    if (!saleId) {
        return;
    }
    createdSale.value = {
        id: Number(saleId),
        code: saleCode ? String(saleCode) : null,
    };
    saleFeedbackPhase.value = 'success';
    saleSuccessModalOpen.value = true;
};

const openSalePendingFeedback = () => {
    createdSale.value = { id: null, code: null };
    saleFeedbackPhase.value = 'pending';
    saleSuccessModalOpen.value = true;
};

const closeSaleSuccessModal = () => {
    if (saleFeedbackPhase.value === 'pending') {
        return;
    }
    saleSuccessModalOpen.value = false;
};

const goToCreatedSale = () => {
    if (!createdSale.value.id) {
        return;
    }
    router.visit(route('admin.financeiro.vendas.show', createdSale.value.id));
};

const syncSaleSuccessFromFlash = () => {
    const flash = inertiaPage.props.flash ?? {};
    if (flash.sale_id) {
        openSaleSuccessModal(flash.sale_id, flash.sale_code);
    }
};

onMounted(() => {
    syncSaleSuccessFromFlash();
});

watch(
    () => inertiaPage.props.flash?.sale_id,
    (saleId) => {
        if (saleId) {
            openSaleSuccessModal(saleId, inertiaPage.props.flash?.sale_code);
        }
    },
);

const submitConvert = () => {
    if (!convertProposal.value) return;
    if (convertForm.processing) return;
    if (!validateConvertForm()) return;

    if (isConvertMisto.value) {
        convertForm.installments_count = convertForm.mix_parts.length;
        convertForm.mix_parts = (convertForm.mix_parts || []).map((part) => ({
            method: part.method,
            percent: Number(part.percent),
        }));
    } else {
        convertForm.mix_parts = [];
    }

    convertForm.post(route('admin.comercial.propostas.converter', convertProposal.value.id), {
        preserveScroll: true,
        // Sem `only`: o redirect precisa repor a lista (venda na linha) e o flash (modal de sucesso).
        onStart: () => {
            convertModalOpen.value = false;
            openSalePendingFeedback();
        },
        onSuccess: (page) => {
            convertProposal.value = null;
            convertClientErrors.value = [];
            convertForm.reset();
            convertForm.clearErrors();

            const flash = page?.props?.flash ?? inertiaPage.props.flash ?? {};
            if (flash.sale_id) {
                openSaleSuccessModal(flash.sale_id, flash.sale_code);
                return;
            }

            nextTick(() => {
                syncSaleSuccessFromFlash();
                if (saleFeedbackPhase.value === 'pending') {
                    // Fallback: não ficar preso em «A gerar…» se o flash falhar.
                    saleSuccessModalOpen.value = false;
                    saleFeedbackPhase.value = 'success';
                }
            });
        },
        onError: () => {
            saleSuccessModalOpen.value = false;
            saleFeedbackPhase.value = 'success';
            convertModalOpen.value = true;
        },
        onFinish: () => {
            // Se ainda estiver pendente após o pedido, tenta sync final do flash.
            if (saleFeedbackPhase.value === 'pending') {
                nextTick(() => syncSaleSuccessFromFlash());
            }
        },
    });
};
</script>

<template>
    <Head title="Comercial — Propostas" />

    <AdminLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-sm text-slate-500">Comercial</p>
                    <h2 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">
                        Propostas
                    </h2>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        :href="route('admin.comercial.propostas.create')"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-talents-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-talents-700"
                        title="Nova proposta"
                    >
                        <PlusIcon class="h-4 w-4" />
                        Nova
                    </Link>
                </div>
            </div>
        </template>

        <CommercialModuleNav />

        <div
            v-if="inertiaPage.props.flash?.success && !generatedContractId && !inertiaPage.props.flash?.sale_id"
            class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
            role="status"
        >
            {{ inertiaPage.props.flash.success }}
        </div>

        <div class="surface-card p-6">
            <div class="flex flex-wrap items-center gap-2">
                <button
                    v-for="chip in statusChipOptions"
                    :key="chip.value || 'all'"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition"
                    :class="
                        (filterState.status || '') === chip.value
                            ? 'bg-talents-700 text-white shadow-sm'
                            : 'border border-slate-200 bg-white text-slate-700 hover:border-talents-200 hover:bg-talents-50 hover:text-talents-800'
                    "
                    @click="applyStatusChip(chip.value)"
                >
                    <span>{{ chip.label }}</span>
                    <span
                        class="rounded-full px-1.5 py-0.5 text-[10px] font-bold tabular-nums"
                        :class="
                            (filterState.status || '') === chip.value
                                ? 'bg-white/20 text-white'
                                : 'bg-slate-100 text-slate-600'
                        "
                    >
                        {{ chip.count }}
                    </span>
                </button>
            </div>

            <form class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-6" @submit.prevent="applyFilters">
                <div class="xl:col-span-2">
                    <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Buscar</label>
                    <input
                        v-model="filterState.search"
                        type="text"
                        class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        placeholder="Cliente, código ou CNPJ"
                    />
                </div>
                <div>
                    <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Vendedor</label>
                    <select
                        v-model="filterState.seller_id"
                        class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    >
                        <option value="">Todos</option>
                        <option v-for="s in sellers" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Situação da venda</label>
                    <select
                        v-model="filterState.sale_situation"
                        class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    >
                        <option value="">Todas</option>
                        <option value="without_sale">Sem venda</option>
                        <option value="with_sale">Com venda</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Criada de</label>
                    <input
                        v-model="filterState.created_from"
                        type="date"
                        class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    />
                    <p v-if="inertiaPage.props.errors?.created_from" class="mt-1 text-xs text-rose-600">
                        {{ inertiaPage.props.errors.created_from }}
                    </p>
                </div>
                <div>
                    <label class="text-xs font-medium uppercase tracking-wide text-slate-500">até</label>
                    <input
                        v-model="filterState.created_to"
                        type="date"
                        class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    />
                    <p v-if="inertiaPage.props.errors?.created_to" class="mt-1 text-xs text-rose-600">
                        {{ inertiaPage.props.errors.created_to }}
                    </p>
                </div>
                <div class="flex items-end justify-end gap-2 md:col-span-2 xl:col-span-6">
                    <button
                        type="button"
                        class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                        @click="clearFilters"
                    >
                        Limpar
                    </button>
                    <button
                        type="submit"
                        class="inline-flex items-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800"
                    >
                        Filtrar
                    </button>
                </div>
            </form>

            <div
                v-if="activeFilterChips.length"
                class="mt-4 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4"
            >
                <button
                    v-for="chip in activeFilterChips"
                    :key="chip.key"
                    type="button"
                    class="inline-flex items-center gap-1 rounded-full border border-talents-200 bg-talents-50 px-2.5 py-1 text-xs font-medium text-talents-800 transition hover:bg-talents-100"
                    :title="`Remover filtro ${chip.label}`"
                    @click="clearActiveFilter(chip.key)"
                >
                    <span>{{ chip.label }}</span>
                    <XMarkIcon class="h-3.5 w-3.5" aria-hidden="true" />
                </button>
                <button
                    type="button"
                    class="text-xs font-semibold text-slate-500 underline-offset-2 transition hover:text-talents-700 hover:underline"
                    @click="clearFilters"
                >
                    Limpar tudo
                </button>
            </div>
        </div>

        <div class="mt-6 surface-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">Código</th>
                            <th class="px-4 py-3 text-left font-medium">Cliente</th>
                            <th class="px-4 py-3 text-left font-medium">Vendedor</th>
                            <th class="px-4 py-3 text-right font-medium">Funcionários</th>
                            <th class="px-4 py-3 text-right font-medium">Total</th>
                            <th class="px-4 py-3 text-left font-medium">Status</th>
                            <th class="px-4 py-3 text-right font-medium">Criada</th>
                            <th class="px-4 py-3 text-right font-medium">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <tr v-for="p in proposals.data" :key="p.id" class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ p.code }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ p.client_name }}</div>
                                <div v-if="p.client_cnpj" class="text-xs text-slate-500">{{ formatCnpj(p.client_cnpj) }}</div>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ p.seller?.name ?? '—' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ p.employee_count }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-semibold">
                                {{ formatBRL(p.total_final_cents) }}
                            </td>
                            <td class="px-4 py-3">
                                <button
                                    type="button"
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium transition hover:ring-2 hover:ring-slate-200 focus:outline-none focus:ring-2 focus:ring-talents-300"
                                    :class="listStatusBadgeClass(p.list_status ?? (p.is_closed ? 'approved' : 'open'))"
                                    title="Alterar status"
                                    :aria-label="`Alterar status de ${p.code}`"
                                    @click="openStatusModal(p)"
                                >
                                    {{ listStatusLabel(p) }}
                                </button>
                                <div
                                    v-if="installmentsProgressLabel(p)"
                                    class="mt-0.5 text-[11px] tabular-nums text-slate-500"
                                >
                                    {{ installmentsProgressLabel(p) }}
                                </div>
                                <Link
                                    v-if="p.sale"
                                    :href="route('admin.financeiro.vendas.show', p.sale.id)"
                                    class="mt-1 block text-xs font-medium text-talents-700 hover:underline"
                                >
                                    Venda {{ p.sale.code }}
                                </Link>
                            </td>
                            <td class="px-4 py-3 text-right text-xs text-slate-500">
                                {{ formatDate(p.created_at) }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex items-center justify-end gap-0.5">
                                    <Link
                                        :href="route('admin.comercial.propostas.edit', p.id)"
                                        class="rounded-lg p-1.5 text-slate-500 transition hover:bg-slate-100 hover:text-slate-900"
                                        title="Editar"
                                    >
                                        <PencilSquareIcon class="h-4 w-4" />
                                    </Link>
                                    <a
                                        :href="route('admin.comercial.propostas.pdf', p.id)"
                                        class="rounded-lg p-1.5 text-slate-500 transition hover:bg-slate-100 hover:text-slate-900"
                                        title="PDF da proposta"
                                        target="_blank"
                                        rel="noopener"
                                    >
                                        <DocumentArrowDownIcon class="h-4 w-4" />
                                    </a>
                                    <button
                                        v-if="canConvert(p)"
                                        type="button"
                                        class="rounded-lg bg-emerald-50 p-1.5 text-emerald-700 transition hover:bg-emerald-100 hover:text-emerald-900"
                                        title="Converter em venda"
                                        aria-label="Converter em venda"
                                        @click="openConvertModal(p)"
                                    >
                                        <BanknotesIcon class="h-4 w-4" />
                                    </button>
                                    <span
                                        v-else-if="p.is_closed && p.sale"
                                        class="inline-flex items-center rounded-lg p-1.5 text-slate-300"
                                        title="Já convertida em venda"
                                    >
                                        <BanknotesIcon class="h-4 w-4" />
                                    </span>
                                    <button
                                        type="button"
                                        class="rounded-lg p-1.5 text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 disabled:cursor-not-allowed disabled:opacity-40"
                                        title="Gerar contrato"
                                        :disabled="!templates.length"
                                        @click="openContractModal(p)"
                                    >
                                        <DocumentTextIcon class="h-4 w-4" />
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-lg p-1.5 text-slate-500 transition hover:bg-rose-50 hover:text-rose-700"
                                        title="Excluir"
                                        @click="destroy(p)"
                                    >
                                        <TrashIcon class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!proposals.data.length">
                            <td colspan="8" class="px-4 py-10 text-center text-slate-500">
                                Nenhuma proposta encontrada.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="proposals.links?.length > 3" class="flex flex-wrap items-center justify-end gap-1 border-t border-slate-100 bg-slate-50 px-4 py-3 text-sm">
                <template v-for="link in proposals.links" :key="link.label">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="rounded-lg px-3 py-1 text-slate-700 transition hover:bg-white"
                        :class="link.active ? 'bg-talents-600 text-white hover:bg-talents-600' : ''"
                        v-html="link.label"
                    />
                    <span
                        v-else
                        class="cursor-not-allowed rounded-lg px-3 py-1 text-slate-400"
                        v-html="link.label"
                    />
                </template>
            </div>
        </div>

        <FullScreenOverlay :show="contractModalOpen" @close="closeContractModal">
            <div
                class="max-h-[92vh] w-full overflow-y-auto rounded-2xl bg-white p-6 shadow-xl"
                :class="generatedContractId ? 'max-w-4xl' : 'max-w-md'"
            >
                <h3 class="text-lg font-semibold text-slate-900">Gerar contrato</h3>
                <p class="mt-1 text-sm text-slate-600">
                    Proposta <span class="font-mono text-xs">{{ contractProposal?.code }}</span>
                </p>

                <div
                    v-if="inertiaPage.props.flash?.error"
                    class="mt-4 rounded-xl bg-rose-50 px-3 py-2 text-sm text-rose-900"
                >
                    {{ inertiaPage.props.flash.error }}
                </div>
                <div
                    v-if="inertiaPage.props.flash?.success && generatedContractId"
                    class="mt-4 rounded-xl bg-emerald-50 px-3 py-2 text-sm text-emerald-900"
                >
                    {{ inertiaPage.props.flash.success }}
                </div>

                <div
                    v-if="contractProposal && zapsign_configured"
                    class="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm text-slate-800"
                >
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Assinatura ZapSign (ordem)
                    </p>
                    <ol class="mt-2 list-decimal space-y-2 pl-5">
                        <li>
                            <strong class="text-slate-900">CONTRATANTE</strong>
                            — {{ clienteRepresentanteNome }}
                            <span class="block text-xs text-slate-600">
                                Envio:
                                <template v-if="phoneLooksValid(contractProposal.client_phone)">
                                    WhatsApp/celular {{ contractProposal.client_phone }}
                                    <template v-if="emailLooksValid(contractProposal.client_email)">
                                        (e-mail também cadastrado; prioridade WhatsApp)
                                    </template>
                                </template>
                                <template v-else-if="emailLooksValid(contractProposal.client_email)">
                                    e-mail {{ contractProposal.client_email }}
                                </template>
                                <template v-else>
                                    <span class="text-amber-800">cadastre celular com DDD ou e-mail na proposta</span>
                                </template>
                            </span>
                        </li>
                        <li>
                            <strong class="text-slate-900">CONTRATADA</strong>
                            — {{ zapsignParties.contratada_signatario || '—' }}
                            <span class="block text-xs text-slate-600">
                                Envio:
                                <template v-if="phoneLooksValid(zapsignParties.contratada_telefone)">
                                    WhatsApp/celular {{ zapsignParties.contratada_telefone }}
                                    <template v-if="emailLooksValid(zapsignParties.contratada_email)">
                                        (e-mail também cadastrado; prioridade WhatsApp)
                                    </template>
                                </template>
                                <template v-else-if="emailLooksValid(zapsignParties.contratada_email)">
                                    e-mail {{ zapsignParties.contratada_email }}
                                </template>
                                <template v-else>
                                    <span class="text-amber-800">configure telefone ou e-mail em Empresa Talents</span>
                                </template>
                            </span>
                        </li>
                    </ol>
                </div>

                <template v-if="!generatedContractId">
                    <div v-if="!templates.length" class="mt-4 rounded-xl bg-amber-50 px-3 py-2 text-sm text-amber-900">
                        Nenhum modelo ativo. Cadastre em Comercial → Configurações → aba Contratos.
                    </div>
                    <div v-else class="mt-4">
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Modelo</label>
                        <select
                            v-model="contractTemplateId"
                            class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        >
                            <option v-for="t in templates" :key="t.id" :value="String(t.id)">{{ t.name }}</option>
                        </select>
                    </div>
                    <div class="mt-6 flex justify-end gap-2">
                        <button
                            type="button"
                            class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                            @click="closeContractModal"
                        >
                            Cancelar
                        </button>
                        <button
                            type="button"
                            class="rounded-xl bg-talents-600 px-4 py-2 text-sm font-semibold text-white hover:bg-talents-700 disabled:opacity-50"
                            :disabled="!templates.length || !contractTemplateId || contractGenerating"
                            @click="submitContract"
                        >
                            {{ contractGenerating ? 'Gerando…' : 'Gerar contrato' }}
                        </button>
                    </div>
                </template>

                <template v-else>
                    <div class="mt-4 rounded-xl bg-slate-50 px-3 py-2 text-sm text-slate-800">
                        <span class="font-medium text-slate-600">Modelo:</span>
                        {{ generatedTemplateName || '—' }}
                    </div>

                    <div class="mt-4">
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Pré-visualização</p>
                        <iframe
                            :key="pdfPreviewUrl"
                            :src="pdfPreviewUrl"
                            title="Pré-visualização do contrato"
                            class="mt-2 h-[min(70vh,560px)] w-full min-h-[320px] rounded-xl border border-slate-200 bg-slate-100"
                        />
                    </div>

                    <div v-if="!zapsign_configured" class="mt-3 text-xs text-amber-800">
                        Configure o token ZapSign em Comercial → Configurações → PDF para habilitar o envio à assinatura.
                    </div>
                    <div
                        v-else-if="(!zapsignClienteContatoOk || !zapsignContratadaContatoOk) && !zapsignSent"
                        class="mt-3 rounded-xl bg-amber-50 px-3 py-2 text-xs text-amber-950"
                    >
                        Complete os contatos acima antes de enviar: é obrigatório
                        <strong>e-mail válido ou celular com DDD</strong>
                        na proposta (cliente) e em Empresa Talents (Talents). Com celular, a ZapSign envia o link por WhatsApp.
                    </div>
                    <div v-if="zapsignSignUrl" class="mt-3 rounded-xl bg-slate-50 px-3 py-2 text-sm">
                        <span class="text-slate-600">Link do 1º signatário:</span>
                        <a
                            :href="zapsignSignUrl"
                            class="ml-1 font-medium text-talents-700 underline break-all"
                            target="_blank"
                            rel="noopener noreferrer"
                        >{{ zapsignSignUrl }}</a>
                    </div>

                    <div class="mt-6 flex flex-wrap justify-end gap-2">
                        <button
                            type="button"
                            class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                            @click="closeContractModal"
                        >
                            Fechar
                        </button>
                        <button
                            type="button"
                            class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                            @click="openPdfNewTab"
                        >
                            Abrir PDF em nova aba
                        </button>
                        <button
                            type="button"
                            class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-50"
                            :disabled="
                                !zapsign_configured
                                    || zapsignSending
                                    || zapsignSent
                                    || !zapsignClienteContatoOk
                                    || !zapsignContratadaContatoOk
                            "
                            :title="zapsignSent ? 'Este contrato já foi enviado ao ZapSign nesta sessão.' : ''"
                            @click="sendZapSign"
                        >
                            {{ zapsignSending ? 'Enviando…' : zapsignSent ? 'Enviado ao ZapSign' : 'Enviar ao ZapSign' }}
                        </button>
                    </div>
                </template>
            </div>
        </FullScreenOverlay>

        <FullScreenOverlay :show="convertModalOpen" @close="closeConvertModal">
            <div
                class="relative flex max-h-[calc(100vh-2rem)] w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-white shadow-xl"
            >
                <div class="shrink-0 border-b border-slate-100 px-6 pb-4 pt-6">
                    <h3 class="text-lg font-semibold text-slate-900">Converter em venda</h3>
                    <p v-if="convertProposal" class="mt-1 text-sm text-slate-600">
                        {{ convertProposal.code }} — {{ convertProposal.client_name }}
                        · {{ formatBRL(convertProposal.total_final_cents) }}
                    </p>
                </div>

                <form class="flex min-h-0 flex-1 flex-col" @submit.prevent="submitConvert">
                    <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-6 py-4">
                        <div
                            v-if="convertClientErrors.length || Object.keys(convertForm.errors).length"
                            class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800"
                            role="alert"
                        >
                            <p v-for="(msg, idx) in convertClientErrors" :key="`ce-${idx}`">{{ msg }}</p>
                            <p
                                v-for="(msg, key) in convertForm.errors"
                                :key="`fe-${key}`"
                            >
                                {{ msg }}
                            </p>
                        </div>

                        <div>
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Forma de pagamento</label>
                            <select
                                v-model="convertForm.payment_method"
                                class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            >
                                <option value="pix">PIX</option>
                                <option value="boleto">Boleto</option>
                                <option value="cartao">Cartão</option>
                                <option value="misto">Misto</option>
                            </select>
                            <p class="mt-1 text-xs text-slate-500">
                                Em «Misto», informe a composição do valor (ex.: 50% PIX + 50% cartão).
                            </p>
                            <p v-if="convertForm.errors.payment_method" class="mt-1 text-xs text-rose-600">
                                {{ convertForm.errors.payment_method }}
                            </p>
                        </div>

                        <div v-if="!isConvertMisto">
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Nº de parcelas</label>
                            <input
                                v-model.number="convertForm.installments_count"
                                type="number"
                                min="1"
                                max="60"
                                class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            />
                            <p class="mt-1 text-xs text-slate-500">
                                Parcelas iguais com a forma escolhida acima.
                            </p>
                            <p v-if="convertForm.errors.installments_count" class="mt-1 text-xs text-rose-600">
                                {{ convertForm.errors.installments_count }}
                            </p>
                        </div>

                        <div
                            v-else
                            class="space-y-3 rounded-xl border border-slate-200 bg-slate-50/80 p-4"
                        >
                            <div>
                                <h4 class="text-sm font-semibold text-slate-900">Composição do pagamento</h4>
                                <p class="mt-1 text-xs text-slate-600">
                                    Informe em quantas partes o valor será pago e o meio de cada uma. A soma dos percentuais deve ser 100%.
                                </p>
                            </div>

                            <div
                                v-for="(part, index) in convertForm.mix_parts"
                                :key="`mix-${index}`"
                                class="grid grid-cols-12 items-start gap-2"
                            >
                                <div class="col-span-5">
                                    <label class="text-[10px] font-medium uppercase tracking-wide text-slate-500">Forma</label>
                                    <select
                                        v-model="part.method"
                                        class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                    >
                                        <option
                                            v-for="opt in MIX_METHOD_OPTIONS"
                                            :key="opt.value"
                                            :value="opt.value"
                                        >
                                            {{ opt.label }}
                                        </option>
                                    </select>
                                    <p
                                        v-if="convertForm.errors[`mix_parts.${index}.method`]"
                                        class="mt-1 text-xs text-rose-600"
                                    >
                                        {{ convertForm.errors[`mix_parts.${index}.method`] }}
                                    </p>
                                </div>
                                <div class="col-span-4">
                                    <label class="text-[10px] font-medium uppercase tracking-wide text-slate-500">%</label>
                                    <input
                                        v-model.number="part.percent"
                                        type="number"
                                        min="0.01"
                                        max="100"
                                        step="0.01"
                                        class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                    />
                                    <p class="mt-1 text-[11px] tabular-nums text-slate-500">
                                        {{ formatBRL(mixPartsWithAmounts[index]?.amount_cents ?? 0) }}
                                    </p>
                                    <p
                                        v-if="convertForm.errors[`mix_parts.${index}.percent`]"
                                        class="mt-1 text-xs text-rose-600"
                                    >
                                        {{ convertForm.errors[`mix_parts.${index}.percent`] }}
                                    </p>
                                </div>
                                <div class="col-span-3 flex items-end justify-end pb-5">
                                    <button
                                        type="button"
                                        class="rounded-lg p-2 text-slate-400 transition hover:bg-rose-50 hover:text-rose-700 disabled:opacity-30"
                                        title="Remover parte"
                                        :disabled="convertForm.mix_parts.length <= 2"
                                        @click="removeMixPart(index)"
                                    >
                                        <TrashIcon class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                                    @click="addMixPart"
                                >
                                    <PlusIcon class="h-3.5 w-3.5" />
                                    Adicionar parte
                                </button>
                                <p
                                    class="text-xs font-medium tabular-nums"
                                    :class="Math.abs(mixPercentSum - 100) <= 0.05 ? 'text-emerald-700' : 'text-amber-700'"
                                >
                                    Soma: {{ mixPercentSum.toFixed(2) }}%
                                </p>
                            </div>

                            <p v-if="mixPreviewLabel" class="text-sm font-medium text-slate-800">
                                {{ mixPreviewLabel }}
                            </p>
                        </div>

                        <div>
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">1º vencimento</label>
                            <input
                                v-model="convertForm.first_due_date"
                                type="date"
                                class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            />
                            <p class="mt-1 text-xs text-slate-500">
                                As demais partes/parcelas seguem mensalmente a partir desta data.
                            </p>
                            <p v-if="convertForm.errors.first_due_date" class="mt-1 text-xs text-rose-600">
                                {{ convertForm.errors.first_due_date }}
                            </p>
                        </div>
                        <div>
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Observações</label>
                            <textarea
                                v-model="convertForm.notes"
                                rows="2"
                                class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            />
                        </div>
                    </div>

                    <div class="flex shrink-0 justify-end gap-2 border-t border-slate-100 bg-white px-6 py-4">
                        <button
                            type="button"
                            class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50"
                            :disabled="convertForm.processing"
                            @click="closeConvertModal"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="convertForm.processing"
                        >
                            {{ convertForm.processing ? 'Gerando…' : 'Gerar venda' }}
                        </button>
                    </div>
                </form>

                <div
                    v-if="convertForm.processing"
                    class="absolute inset-0 z-10 flex flex-col items-center justify-center rounded-2xl bg-white/80 backdrop-blur-[1px]"
                    aria-live="polite"
                >
                    <ArrowPathIcon class="h-10 w-10 animate-spin text-talents-600" aria-hidden="true" />
                    <p class="mt-3 text-sm font-semibold text-slate-800">A gerar venda…</p>
                </div>
            </div>
        </FullScreenOverlay>

        <FullScreenOverlay :show="statusModalOpen" @close="closeStatusModal">
            <div
                class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl"
                role="dialog"
                aria-modal="true"
                aria-labelledby="proposal-status-title"
            >
                <h3 id="proposal-status-title" class="text-lg font-semibold text-slate-900">
                    Alterar status
                </h3>
                <p v-if="statusProposal" class="mt-1 text-sm text-slate-600">
                    {{ statusProposal.code }} — {{ statusProposal.client_name }}
                </p>

                <form class="mt-4 space-y-4" @submit.prevent="submitStatus">
                    <div
                        v-if="Object.keys(statusForm.errors).length"
                        class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800"
                        role="alert"
                    >
                        <p v-for="(msg, key) in statusForm.errors" :key="key">{{ msg }}</p>
                    </div>

                    <div>
                        <label
                            for="proposal-status-select"
                            class="text-xs font-medium uppercase tracking-wide text-slate-500"
                        >
                            Status
                        </label>
                        <select
                            id="proposal-status-select"
                            v-model="statusForm.status"
                            class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            required
                        >
                            <option value="open">Em aberto</option>
                            <option value="negotiation">Em negociação</option>
                            <option value="approved">Aprovada</option>
                            <option value="ended">Encerrada</option>
                        </select>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button
                            type="button"
                            class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                            :disabled="statusForm.processing"
                            @click="closeStatusModal"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-talents-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-talents-700 disabled:opacity-60"
                            :disabled="statusForm.processing"
                        >
                            <ArrowPathIcon
                                v-if="statusForm.processing"
                                class="h-4 w-4 animate-spin"
                                aria-hidden="true"
                            />
                            Salvar
                        </button>
                    </div>
                </form>
            </div>
        </FullScreenOverlay>

        <FullScreenOverlay
            :show="saleSuccessModalOpen"
            @close="closeSaleSuccessModal"
        >
            <div
                class="w-full max-w-md rounded-2xl bg-talents-600 p-8 text-center shadow-xl"
                role="dialog"
                aria-modal="true"
                aria-labelledby="sale-success-title"
            >
                <ArrowPathIcon
                    v-if="saleFeedbackPhase === 'pending'"
                    class="mx-auto h-20 w-20 animate-spin text-emerald-300"
                    aria-hidden="true"
                />
                <CheckCircleIcon
                    v-else
                    class="mx-auto h-20 w-20 text-emerald-300"
                    aria-hidden="true"
                />
                <h3 id="sale-success-title" class="mt-5 text-2xl font-semibold text-white">
                    {{ saleFeedbackPhase === 'pending' ? 'A gerar venda…' : 'Venda gerada com sucesso!' }}
                </h3>
                <p class="mt-3 text-sm leading-relaxed text-talents-50">
                    <template v-if="saleFeedbackPhase === 'pending'">
                        Aguarde um momento. Estamos a registar a venda em
                        <span class="font-semibold text-white">Financeiro → Vendas</span>.
                    </template>
                    <template v-else>
                        A conversão está disponível em
                        <span class="font-semibold text-white">Financeiro → Vendas</span>.
                        <template v-if="createdSale.code">
                            Código da venda:
                            <span class="font-mono font-semibold text-white">{{ createdSale.code }}</span>.
                        </template>
                    </template>
                </p>
                <div
                    v-if="saleFeedbackPhase === 'success'"
                    class="mt-8 flex flex-col gap-2 sm:flex-row sm:justify-center"
                >
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-talents-800 shadow-sm transition hover:bg-talents-50 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-talents-600"
                        @click="goToCreatedSale"
                    >
                        Ir para a venda
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-xl border border-white/40 bg-transparent px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-talents-700"
                        @click="closeSaleSuccessModal"
                    >
                        Fechar
                    </button>
                </div>
            </div>
        </FullScreenOverlay>
    </AdminLayout>
</template>

