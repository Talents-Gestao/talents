<script setup>
import CommercialModuleNav from '@/Components/Comercial/CommercialModuleNav.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatBRL } from '@/composables/useCommercialPricing';
import { formatCnpj } from '@/utils/formatCnpj';
import {
    BanknotesIcon,
    DocumentArrowDownIcon,
    DocumentTextIcon,
    PencilSquareIcon,
    PlusIcon,
    TrashIcon,
} from '@heroicons/vue/24/outline';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, reactive, ref } from 'vue';

const inertiaPage = usePage();

const props = defineProps({
    proposals: { type: Object, required: true },
    sellers: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
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
});

const applyFilters = () => {
    router.get(route('admin.comercial.propostas.index'), filterState, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const clearFilters = () => {
    filterState.search = '';
    filterState.seller_id = '';
    filterState.status = '';
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

const convertForm = useForm({
    payment_method: 'pix',
    installments_count: 1,
    first_due_date: new Date().toISOString().slice(0, 10),
    notes: '',
});

const canConvert = (proposal) => proposal.is_closed && !proposal.sale;

const openConvertModal = (proposal) => {
    convertProposal.value = proposal;
    convertForm.reset();
    convertForm.payment_method = 'pix';
    convertForm.installments_count = 1;
    convertForm.first_due_date = new Date().toISOString().slice(0, 10);
    convertForm.notes = '';
    convertModalOpen.value = true;
};

const closeConvertModal = () => {
    convertModalOpen.value = false;
    convertProposal.value = null;
    convertForm.reset();
};

const submitConvert = () => {
    if (!convertProposal.value) return;
    convertForm.post(route('admin.comercial.propostas.converter', convertProposal.value.id), {
        preserveScroll: true,
        onSuccess: () => closeConvertModal(),
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
                    <h2 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">Propostas</h2>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        v-if="$page.props.auth?.user?.can_commercial_settings"
                        :href="route('admin.comercial.settings.edit')"
                        class="inline-flex items-center rounded-xl border border-talents-200 bg-talents-50 px-3 py-2 text-sm font-semibold text-talents-800 shadow-sm transition hover:bg-talents-100"
                    >
                        Valores e contratos
                    </Link>
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

        <div class="surface-card p-6">
            <form class="grid gap-4 sm:grid-cols-4" @submit.prevent="applyFilters">
                <div class="sm:col-span-2">
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
                    <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Status</label>
                    <select
                        v-model="filterState.status"
                        class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    >
                        <option value="">Todos</option>
                        <option value="abertas">Em aberto</option>
                        <option value="fechadas">Fechadas</option>
                    </select>
                </div>
                <div class="sm:col-span-4 flex justify-end gap-2">
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
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="p.is_closed ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                                >
                                    {{ p.is_closed ? 'Fechada' : 'Em aberto' }}
                                </span>
                                <Link
                                    v-if="p.sale"
                                    :href="route('admin.financeiro.vendas.show', p.sale.id)"
                                    class="mt-1 block text-xs font-medium text-talents-700 hover:underline"
                                >
                                    Venda {{ p.sale.code }}
                                </Link>
                            </td>
                            <td class="px-4 py-3 text-right text-xs text-slate-500">{{ formatDate(p.created_at) }}</td>
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
                                        class="rounded-lg p-1.5 text-emerald-600 transition hover:bg-emerald-50 hover:text-emerald-800"
                                        title="Converter em venda"
                                        @click="openConvertModal(p)"
                                    >
                                        <BanknotesIcon class="h-4 w-4" />
                                    </button>
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
                            <td colspan="8" class="px-4 py-10 text-center text-slate-500">Nenhuma proposta encontrada.</td>
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

        <div
            v-if="contractModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            role="dialog"
            aria-modal="true"
            @click.self="closeContractModal"
        >
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
        </div>

        <div
            v-if="convertModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            role="dialog"
            aria-modal="true"
            @click.self="closeConvertModal"
        >
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                <h3 class="text-lg font-semibold text-slate-900">Converter em venda</h3>
                <p v-if="convertProposal" class="mt-1 text-sm text-slate-600">
                    {{ convertProposal.code }} — {{ convertProposal.client_name }}
                    · {{ formatBRL(convertProposal.total_final_cents) }}
                </p>

                <form class="mt-4 space-y-4" @submit.prevent="submitConvert">
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
                    </div>
                    <div>
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Nº de parcelas</label>
                        <input
                            v-model.number="convertForm.installments_count"
                            type="number"
                            min="1"
                            max="60"
                            class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        />
                    </div>
                    <div>
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-500">1º vencimento</label>
                        <input
                            v-model="convertForm.first_due_date"
                            type="date"
                            class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        />
                    </div>
                    <div>
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Observações</label>
                        <textarea
                            v-model="convertForm.notes"
                            rows="2"
                            class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        />
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button
                            type="button"
                            class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                            @click="closeConvertModal"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
                            :disabled="convertForm.processing"
                        >
                            {{ convertForm.processing ? 'Gerando…' : 'Gerar venda' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
