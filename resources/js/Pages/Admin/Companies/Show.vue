<script setup>
import CompanyDashboardSummary from '@/Components/Admin/Companies/CompanyDashboardSummary.vue';
import CompanyShortcutDestinationModal from '@/Components/Admin/Companies/CompanyShortcutDestinationModal.vue';
import CompanyShortcutGrid from '@/Components/Admin/Companies/CompanyShortcutGrid.vue';
import CompanyShowTabFrame from '@/Components/Admin/Companies/CompanyShowTabFrame.vue';
import CompanyRhidCredentialsForm from '@/Components/Admin/Rhid/CompanyRhidCredentialsForm.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import BackLink from '@/Components/BackLink.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useAdminPermissions } from '@/composables/useAdminPermissions';
import {
    BuildingOffice2Icon,
    ChevronDownIcon,
    DocumentTextIcon,
    EnvelopeIcon,
    FingerPrintIcon,
    GlobeAltIcon,
    KeyIcon,
    PencilSquareIcon,
    StarIcon,
    UserGroupIcon,
} from '@heroicons/vue/24/outline';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { formatCnpj } from '@/utils/formatCnpj';
import { confirmDialog } from '@/composables/useConfirmDialog';

const props = defineProps({
    company: Object,
    nr1ShellUrls: { type: Object, default: () => ({}) },
    tab: { type: String, default: 'empresa' },
    rhidConfigured: { type: Boolean, default: false },
    rhidEnabled: { type: Boolean, default: false },
    rhidSettings: {
        type: Object,
        default: () => ({
            rhid_base_url: '',
            rhid_email: '',
            rhid_domain: '',
            has_password: false,
        }),
    },
    planIncludesMetodologia: { type: Boolean, default: false },
    pendingRegistration: { type: Boolean, default: false },
    registrationAdminEmail: { type: String, default: null },
    complaintsPublicUrl: { type: String, default: null },
    plans: Array,
    templates: Array,
    methodologyTemplates: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
    regulations: { type: Array, default: () => [] },
    dashboard: {
        type: Object,
        default: () => ({
            users_count: 0,
            active_users_count: 0,
            surveys_count: 0,
            active_surveys_count: 0,
            latest_survey: null,
            has_active_subscription: false,
            active_plan_name: null,
            employees_count: 0,
            regulations_count: 0,
            purpose_maps_count: 0,
            rhid_enabled: false,
            rhid_configured: false,
            recent_surveys: [],
            recent_purpose_maps: [],
            shortcuts: [],
        }),
    },
});

const { canAdmin } = useAdminPermissions();

const showDeleteModal = ref(false);
const showDeleteSurveyModal = ref(false);
const surveyToDelete = ref(null);
const resendingInvitation = ref(false);
const destinationSection = ref(null);

/** Atalhos de gestão: o clique abre o modal para escolher o destino. */
const configShortcuts = [
    {
        key: 'dados',
        label: 'Dados',
        hint: 'Informações cadastrais da empresa',
        section: 'dados',
        enabled: true,
    },
    {
        key: 'assinaturas',
        label: 'Assinaturas',
        hint: 'Planos vinculados a esta empresa',
        section: 'assinaturas',
        enabled: true,
    },
    {
        key: 'direcionamento',
        label: 'Direcionamento Estratégico',
        hint: 'Acesso e templates de satisfação',
        section: 'direcionamento',
        enabled: true,
    },
    {
        key: 'mapeamentos',
        label: 'Mapeamentos disponíveis',
        hint: 'Templates de pesquisa NR-1',
        section: 'mapeamentos',
        enabled: true,
    },
    {
        key: 'pesquisas',
        label: 'Pesquisas e parecer',
        hint: 'Escolher a campanha e abrir o painel',
        section: 'pesquisas',
        enabled: true,
    },
    {
        key: 'excluir',
        label: 'Excluir empresa',
        hint: 'Ação irreversível',
        section: 'excluir',
        tone: 'danger',
        enabled: true,
    },
];

const operationalDestination = {
    users: {
        section: 'usuarios',
        hint: 'Lista, convite e ficha de cada usuário',
    },
    mapa: {
        section: 'mapa_proposito',
        hint: 'Dashboard de resultados',
    },
};

const allShortcuts = computed(() => {
    const operational = (props.dashboard?.shortcuts || []).map((item) => {
        const extra = operationalDestination[item.key];
        return extra ? { ...item, ...extra } : item;
    });

    return [...operational, ...configShortcuts];
});

const openShortcutSection = (section) => {
    if (section === 'excluir') {
        showDeleteModal.value = true;
        return;
    }
    destinationSection.value = section;
};

const tabs = computed(() => {
    const items = [{ id: 'empresa', label: 'Empresa' }];

    if (canAdmin('rhid')) {
        items.push({ id: 'ponto', label: 'Gestão de ponto' });
    }

    if (canAdmin('companies')) {
        items.push({ id: 'colaboradores', label: 'Colaboradores' });
        items.push({ id: 'regulamento', label: 'Regulamento interno' });
    }

    return items;
});

const activeTab = computed(() => {
    const ids = tabs.value.map((t) => t.id);
    return ids.includes(props.tab) ? props.tab : 'empresa';
});

const selectTab = (tabId) => {
    if (tabId === activeTab.value) {
        return;
    }
    router.get(
        route('admin.companies.show', props.company.id),
        { tab: tabId },
        { preserveScroll: true, replace: true },
    );
};

const resendInvitation = async () => {
    if (!props.registrationAdminEmail || resendingInvitation.value) {
        return;
    }
    const email = props.registrationAdminEmail || props.company.contact_email || 'o e-mail de contato';
    const message = props.pendingRegistration
        ? `Reenviar o convite de cadastro para ${email}?`
        : `Enviar link para redefinir a senha para ${email}?`;
    if (!(await confirmDialog(message))) {
        return;
    }
    resendingInvitation.value = true;
    router.post(
        route('admin.companies.resend-invitation', props.company.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                resendingInvitation.value = false;
            },
        },
    );
};

const locationLabel = computed(() => {
    const city = props.company.address_city;
    const state = props.company.address_state;
    if (city && state) {
        return `${city} / ${state}`;
    }
    return city || state || null;
});

const attach = (templateId) => {
    router.post(route('admin.companies.templates.attach', [props.company.id, templateId]));
};

const detach = (templateId) => {
    router.delete(route('admin.companies.templates.detach', [props.company.id, templateId]));
};

const attachMethodologyTemplate = (templateId) => {
    router.post(route('admin.companies.methodology-templates.attach', [props.company.id, templateId]));
};

const detachMethodologyTemplate = (templateId) => {
    router.delete(route('admin.companies.methodology-templates.detach', [props.company.id, templateId]));
};

const deleteCompany = () => {
    router.delete(route('admin.companies.destroy', props.company.id), {
        onFinish: () => {
            showDeleteModal.value = false;
        },
    });
};

const openDeleteSurveyModal = (survey) => {
    surveyToDelete.value = survey;
    destinationSection.value = null;
    showDeleteSurveyModal.value = true;
};

const deleteSurvey = () => {
    if (!surveyToDelete.value) {
        return;
    }

    router.delete(
        route('admin.companies.surveys.destroy', [props.company.id, surveyToDelete.value.id]),
        {
            onFinish: () => {
                showDeleteSurveyModal.value = false;
                surveyToDelete.value = null;
            },
        },
    );
};

const formatDate = (iso) => {
    if (!iso) {
        return '—';
    }
    try {
        return new Date(iso).toLocaleDateString('pt-BR');
    } catch {
        return '—';
    }
};

const removeEmployee = async (id) => {
    if (await confirmDialog('Remover este colaborador?')) {
        router.delete(route('admin.colaboradores.destroy', id), { preserveScroll: true });
    }
};

const removeRegulation = async (id) => {
    if (await confirmDialog('Remover este regulamento?')) {
        router.delete(route('admin.regulamento-interno.destroy', id), { preserveScroll: true });
    }
};
</script>

<template>
    <Head :title="company.name" />

    <AdminLayout>
        <template #header>
            <BackLink
                :href="route('admin.companies.index')"
                label="Empresas"
                :prefer-history="false"
            />
        </template>

        <div class="space-y-6">
            <!-- Hero -->
            <section
                class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-talents-800 via-talents-700 to-talents-600 p-6 text-white shadow-sm sm:p-8"
            >
                <div class="pointer-events-none absolute -right-8 -top-10 h-40 w-40 rounded-full bg-white/10 blur-2xl" />
                <div class="relative flex flex-col gap-6 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-start gap-4">
                            <div
                                v-if="company.logo_url"
                                class="h-16 w-16 shrink-0 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-white/30 sm:h-20 sm:w-20"
                            >
                                <img
                                    :src="company.logo_url"
                                    :alt="`Logo de ${company.name}`"
                                    class="h-full w-full object-cover"
                                />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold"
                                        :class="
                                            company.is_active
                                                ? 'bg-emerald-400/20 text-emerald-100 ring-1 ring-emerald-200/30'
                                                : 'bg-white/10 text-white/80 ring-1 ring-white/20'
                                        "
                                    >
                                        {{ company.is_active ? 'Ativa' : 'Inativa' }}
                                    </span>
                                    <span
                                        v-if="rhidConfigured"
                                        class="inline-flex items-center rounded-full bg-white/15 px-2.5 py-1 text-[11px] font-semibold text-white ring-1 ring-white/25"
                                    >
                                        RHID configurado
                                    </span>
                                    <span
                                        v-if="pendingRegistration"
                                        class="inline-flex items-center rounded-full bg-amber-400/20 px-2.5 py-1 text-[11px] font-semibold text-amber-50 ring-1 ring-amber-200/30"
                                    >
                                        Aguarda cadastro
                                    </span>
                                </div>
                                <h1 class="mt-3 text-2xl font-bold tracking-tight sm:text-3xl">
                                    {{ company.name }}
                                </h1>
                                <p
                                    v-if="company.legal_name && company.legal_name !== company.name"
                                    class="mt-1 text-sm text-white/75"
                                >
                                    {{ company.legal_name }}
                                </p>
                                <dl class="mt-4 flex flex-wrap gap-x-6 gap-y-2 text-sm text-white/80">
                                    <div v-if="company.cnpj">
                                        <dt class="sr-only">CNPJ</dt>
                                        <dd><span class="text-white/50">CNPJ</span> {{ formatCnpj(company.cnpj) }}</dd>
                                    </div>
                                    <div v-if="company.segment">
                                        <dt class="sr-only">Segmento</dt>
                                        <dd><span class="text-white/50">Segmento</span> {{ company.segment }}</dd>
                                    </div>
                                    <div v-if="locationLabel">
                                        <dt class="sr-only">Localização</dt>
                                        <dd><span class="text-white/50">Local</span> {{ locationLabel }}</dd>
                                    </div>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div class="flex shrink-0 flex-wrap gap-2">
                        <button
                            v-if="registrationAdminEmail"
                            type="button"
                            class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-medium transition disabled:opacity-50"
                            :class="
                                pendingRegistration
                                    ? 'bg-amber-400/20 text-amber-50 ring-1 ring-amber-200/30 hover:bg-amber-400/30'
                                    : 'bg-white/15 text-white ring-1 ring-white/25 hover:bg-white/25'
                            "
                            :disabled="resendingInvitation"
                            @click="resendInvitation"
                        >
                            {{
                                resendingInvitation
                                    ? 'Enviando…'
                                    : pendingRegistration
                                      ? 'Reenviar convite de cadastro'
                                      : 'Redefinir senha'
                            }}
                        </button>
                        <Link
                            :href="route('admin.client-evaluations.create', { company_id: company.id })"
                            class="inline-flex items-center gap-2 rounded-full bg-white/15 px-4 py-2 text-sm font-medium text-white ring-1 ring-white/25 transition hover:bg-white/25"
                        >
                            <StarIcon class="h-4 w-4" aria-hidden="true" />
                            Avaliação
                        </Link>
                        <Link
                            :href="route('admin.companies.edit', company.id)"
                            class="inline-flex items-center gap-2 rounded-full bg-white/15 px-4 py-2 text-sm font-medium text-white ring-1 ring-white/25 transition hover:bg-white/25"
                        >
                            <PencilSquareIcon class="h-4 w-4" aria-hidden="true" />
                            Editar
                        </Link>
                        <Link
                            :href="route('admin.companies.users.index', company.id)"
                            class="inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-semibold text-talents-800 shadow-sm transition hover:bg-talents-50"
                        >
                            <UserGroupIcon class="h-4 w-4" aria-hidden="true" />
                            Gerenciar usuários
                        </Link>
                    </div>
                </div>
            </section>

            <!-- Tabs -->
            <div class="border-b border-slate-200">
                <nav class="-mb-px flex gap-1 overflow-x-auto" aria-label="Secções da empresa">
                    <button
                        v-for="item in tabs" 
                        :key="item.id"
                        type="button"
                        class="inline-flex shrink-0 items-center gap-2 border-b-2 px-3 py-2.5 text-sm font-semibold transition"
                        :class="
                            activeTab === item.id
                                ? 'border-talents-600 text-talents-800'
                                : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-800'
                        "
                        @click="selectTab(item.id)">
                        {{ item.label }}
                        <span
                            v-if="item.badge"
                            class="rounded-full bg-amber-50 px-2 py-0.5 text-[0.65rem] font-bold uppercase tracking-wide text-amber-800 ring-1 ring-amber-200/80"
                        >
                            {{ item.badge }}
                        </span>
                    </button>
                </nav>
            </div>

            <!-- Tab: Empresa -->
            <div v-if="activeTab === 'empresa'">
                <CompanyShowTabFrame
                    accent="slate"
                    eyebrow="Painel operacional"
                    title="Empresa"
                    description="Indicadores, atalhos e configuração desta empresa."
                >
                    <template #icon>
                        <BuildingOffice2Icon class="h-6 w-6" aria-hidden="true" />
                    </template>
                    <template #actions>
                        <Link
                            :href="route('admin.companies.edit', company.id)"
                            class="inline-flex items-center justify-center rounded-full bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-900"
                        >
                            Editar empresa
                        </Link>
                    </template>
                    <div class="space-y-8">
                <CompanyDashboardSummary
                    :dashboard="dashboard"
                    :registration-admin-email="registrationAdminEmail"
                    @resend-invitation="resendInvitation"
                />
                <CompanyShortcutGrid :shortcuts="allShortcuts" @open-section="openShortcutSection" />
                    </div>
                </CompanyShowTabFrame>
            </div>

            <!-- Tab: Ponto -->
            <div v-else-if="activeTab === 'ponto'">
                <CompanyShowTabFrame
                    accent="talents"
                    eyebrow="Integração Control iD"
                    title="Gestão de ponto"
                    description="Indicadores, marcações e justificativas ficam no hub. Credenciais e horários ficam na tela de configuração; os espelhos entram automaticamente após o fechamento do dia."
                >
                    <template #icon>
                        <FingerPrintIcon class="h-6 w-6" aria-hidden="true" />
                    </template>
                    <template #badges>
                        <span
                            class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1"
                            :class="
                                rhidEnabled
                                    ? 'bg-emerald-50 text-emerald-800 ring-emerald-200'
                                    : 'bg-slate-100 text-slate-600 ring-slate-200'
                            "
                        >
                            Módulo {{ rhidEnabled ? 'ligado' : 'desligado' }}
                        </span>
                        <span
                            class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1"
                            :class="
                                rhidConfigured
                                    ? 'bg-talents-50 text-talents-800 ring-talents-200'
                                    : 'bg-amber-50 text-amber-900 ring-amber-200'
                            "
                        >
                            Credenciais {{ rhidConfigured ? 'ok' : 'em falta' }}
                        </span>
                    </template>
                    <template #actions>
                        <Link
                            v-if="rhidEnabled || rhidConfigured"
                            :href="route('admin.ponto.companies.settings.edit', company.id)"
                            class="inline-flex shrink-0 items-center justify-center rounded-full bg-white px-4 py-2.5 text-sm font-semibold text-talents-800 ring-1 ring-talents-200 transition hover:bg-talents-50"
                        >
                            Configuração RHID
                        </Link>
                        <Link
                            v-if="rhidConfigured"
                            :href="route('admin.ponto.index', { company: company.id })"
                            class="inline-flex shrink-0 items-center justify-center rounded-full bg-talents-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-talents-800"
                        >
                            Abrir gestão de ponto
                        </Link>
                    </template>
                    <div class="space-y-4">
                        <p
                            v-if="!rhidEnabled"
                            class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700"
                        >
                            O módulo RHID / Ponto está desligado no plano ou na ficha desta empresa. Ligue em
                            <Link
                                :href="route('admin.companies.edit', company.id)"
                                class="font-semibold text-talents-800 underline"
                            >
                                Editar empresa
                            </Link>
                            (campo RHID / Ponto). Sem o módulo, o menu some no painel do cliente.
                        </p>
                        <p
                            v-else-if="!rhidConfigured"
                            class="rounded-xl border border-amber-100 bg-amber-50 px-4 py-3 text-sm text-amber-900"
                        >
                            O módulo está ligado, mas faltam e-mail e senha da API Control iD. Preencha abaixo ou
                            abra <strong>Configuração RHID</strong> — horários também ficam nessa tela. Sem
                            credenciais, a importação automática não roda.
                        </p>

                        <dl v-if="rhidConfigured" class="grid gap-3 sm:grid-cols-3">
                            <div class="rounded-2xl border border-slate-100 bg-slate-50/80 p-4">
                                <dt class="flex items-center gap-2 text-xs font-medium text-slate-500">
                                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-talents-100 text-talents-700">
                                        <EnvelopeIcon class="h-4 w-4" aria-hidden="true" />
                                    </span>
                                    E-mail RHID
                                </dt>
                                <dd class="mt-2 truncate text-sm font-semibold text-slate-900">
                                    {{ rhidSettings.rhid_email || '—' }}
                                </dd>
                            </div>
                            <div class="rounded-2xl border border-slate-100 bg-slate-50/80 p-4">
                                <dt class="flex items-center gap-2 text-xs font-medium text-slate-500">
                                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-talents-100 text-talents-700">
                                        <GlobeAltIcon class="h-4 w-4" aria-hidden="true" />
                                    </span>
                                    Domínio
                                </dt>
                                <dd class="mt-2 truncate text-sm font-semibold text-slate-900">
                                    {{ rhidSettings.rhid_domain || 'Não definido' }}
                                </dd>
                            </div>
                            <div class="rounded-2xl border border-slate-100 bg-slate-50/80 p-4">
                                <dt class="flex items-center gap-2 text-xs font-medium text-slate-500">
                                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-talents-100 text-talents-700">
                                        <KeyIcon class="h-4 w-4" aria-hidden="true" />
                                    </span>
                                    Senha
                                </dt>
                                <dd
                                    class="mt-2 text-sm font-semibold"
                                    :class="rhidSettings.has_password ? 'text-emerald-700' : 'text-amber-800'"
                                >
                                    {{ rhidSettings.has_password ? 'Cadastrada' : 'Em falta' }}
                                </dd>
                            </div>
                        </dl>

                        <CompanyRhidCredentialsForm
                            v-if="!rhidConfigured"
                            :company-id="company.id"
                            :settings="rhidSettings"
                        />
                        <details
                            v-else
                            class="group overflow-hidden rounded-2xl border border-slate-200 bg-white"
                        >
                            <summary
                                class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 marker:content-none [&::-webkit-details-marker]:hidden"
                            >
                                <span>Alterar credenciais da API RHID</span>
                                <span class="flex items-center gap-2 text-xs font-normal text-slate-500">
                                    <span class="group-open:hidden">Recolhido</span>
                                    <span class="hidden group-open:inline">E-mail e senha da Control iD</span>
                                    <ChevronDownIcon class="h-4 w-4 transition group-open:rotate-180" aria-hidden="true" />
                                </span>
                            </summary>
                            <div class="border-t border-slate-100">
                                <CompanyRhidCredentialsForm
                                    :company-id="company.id"
                                    :settings="rhidSettings"
                                    embedded
                                />
                            </div>
                        </details>
                    </div>
                </CompanyShowTabFrame>
            </div>

            <!-- Tab: Colaboradores -->
            <div v-else-if="activeTab === 'colaboradores'">
                <CompanyShowTabFrame
                    accent="teal"
                    eyebrow="Cadastro"
                    title="Colaboradores"
                    description="Ficha dos colaboradores desta empresa."
                >
                    <template #icon>
                        <UserGroupIcon class="h-6 w-6" aria-hidden="true" />
                    </template>
                    <template #badges>
                        <span class="inline-flex items-center rounded-full bg-teal-50 px-2.5 py-1 text-[11px] font-semibold text-teal-800 ring-1 ring-teal-200">
                            {{ employees.length }} {{ employees.length === 1 ? 'colaborador' : 'colaboradores' }}
                        </span>
                    </template>
                    <template #actions>
                        <Link
                            :href="route('admin.colaboradores.create', { company_id: company.id })"
                            class="inline-flex items-center justify-center rounded-full bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-800"
                        >
                            Novo colaborador
                        </Link>
                    </template>
                    <ul v-if="employees.length" class="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-100">
                        <li
                            v-for="e in employees"
                            :key="e.id"
                            class="flex flex-wrap items-center justify-between gap-3 bg-white px-4 py-3"
                        >
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-slate-800">{{ e.name }}</p>
                                <p class="text-xs text-slate-500">
                                    {{ e.email || '—' }}
                                    <span v-if="e.position"> · {{ e.position }}</span>
                                    <span v-if="e.department"> · {{ e.department }}</span>
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-semibold ring-1"
                                    :class="
                                        e.is_active
                                            ? 'bg-emerald-50 text-emerald-800 ring-emerald-200/80'
                                            : 'bg-slate-100 text-slate-600 ring-slate-200/80'
                                    "
                                >
                                    {{ e.is_active ? 'Ativo' : 'Inativo' }}
                                </span>
                                <Link
                                    :href="route('admin.colaboradores.edit', e.id)"
                                    class="text-sm font-medium text-talents-700 hover:underline"
                                >
                                    Editar
                                </Link>
                                <button
                                    type="button"
                                    class="text-sm font-medium text-red-600 hover:underline"
                                    @click="removeEmployee(e.id)"
                                >
                                    Remover
                                </button>
                            </div>
                        </li>
                    </ul>
                    <p v-else class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/80 px-4 py-8 text-center text-sm text-slate-500">
                        Nenhum colaborador cadastrado para esta empresa.
                    </p>
                </CompanyShowTabFrame>
            </div>

            <!-- Tab: Regulamento -->
            <div v-else-if="activeTab === 'regulamento'">
                <CompanyShowTabFrame
                    accent="amber"
                    eyebrow="Documentos"
                    title="Regulamento interno"
                    description="Documentos de regulamento desta empresa."
                >
                    <template #icon>
                        <DocumentTextIcon class="h-6 w-6" aria-hidden="true" />
                    </template>
                    <template #badges>
                        <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-900 ring-1 ring-amber-200">
                            {{ regulations.length }} {{ regulations.length === 1 ? 'documento' : 'documentos' }}
                        </span>
                    </template>
                    <template #actions>
                        <Link
                            :href="route('admin.regulamento-interno.create', { company_id: company.id })"
                            class="inline-flex items-center justify-center rounded-full bg-amber-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-amber-700"
                        >
                            Novo regulamento
                        </Link>
                    </template>
                    <ul v-if="regulations.length" class="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-100">
                        <li
                            v-for="r in regulations"
                            :key="r.id"
                            class="flex flex-wrap items-center justify-between gap-3 bg-white px-4 py-3"
                        >
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-slate-800">{{ r.title }}</p>
                                <p class="text-xs text-slate-500">Atualizado em {{ formatDate(r.updated_at) }}</p>
                                <a
                                    v-if="r.has_file"
                                    :href="route('admin.regulamento-interno.download', r.id)"
                                    class="mt-0.5 inline-block text-xs font-medium text-talents-700 hover:underline"
                                >
                                    {{ r.file_name || 'Descarregar anexo' }}
                                </a>
                            </div>
                            <div class="flex items-center gap-2">
                                <Link
                                    :href="route('admin.regulamento-interno.edit', r.id)"
                                    class="text-sm font-medium text-talents-700 hover:underline"
                                >
                                    Editar
                                </Link>
                                <button
                                    type="button"
                                    class="text-sm font-medium text-red-600 hover:underline"
                                    @click="removeRegulation(r.id)"
                                >
                                    Remover
                                </button>
                            </div>
                        </li>
                    </ul>
                    <p v-else class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/80 px-4 py-8 text-center text-sm text-slate-500">
                        Nenhum regulamento cadastrado.
                    </p>
                </CompanyShowTabFrame>
            </div>
        </div>

        <CompanyShortcutDestinationModal
            :show="destinationSection !== null"
            :section="destinationSection"
            :company="company"
            :nr1-shell-urls="nr1ShellUrls"
            :plan-includes-metodologia="planIncludesMetodologia"
            :complaints-public-url="complaintsPublicUrl"
            :templates="templates"
            :methodology-templates="methodologyTemplates"
            @close="destinationSection = null"
            @delete-survey="openDeleteSurveyModal"
            @attach-template="attach"
            @detach-template="detach"
            @attach-methodology="attachMethodologyTemplate"
            @detach-methodology="detachMethodologyTemplate"
        />

        <Modal :show="showDeleteSurveyModal" @close="showDeleteSurveyModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Confirmar exclusão</h2>
                <p class="mt-2 text-sm text-gray-600">
                    Excluir a pesquisa <strong>{{ surveyToDelete?.title }}</strong> e todos os resultados? A empresa
                    deixará de ver esta pesquisa. Os dados serão arquivados internamente.
                </p>
                <div class="mt-6 flex justify-end gap-2">
                    <SecondaryButton type="button" @click="showDeleteSurveyModal = false">Cancelar</SecondaryButton>
                    <DangerButton type="button" @click="deleteSurvey">Sim, excluir pesquisa</DangerButton>
                </div>
            </div>
        </Modal>

        <Modal :show="showDeleteModal" @close="showDeleteModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Confirmar exclusão</h2>
                <p class="mt-2 text-sm text-gray-600">
                    Tem certeza que deseja excluir <strong>{{ company.name }}</strong
                    >? Todos os dados desta empresa serão apagados permanentemente.
                </p>
                <div class="mt-6 flex justify-end gap-2">
                    <SecondaryButton type="button" @click="showDeleteModal = false">Cancelar</SecondaryButton>
                    <DangerButton type="button" @click="deleteCompany">Sim, excluir</DangerButton>
                </div>
            </div>
        </Modal>
    </AdminLayout>
</template>
