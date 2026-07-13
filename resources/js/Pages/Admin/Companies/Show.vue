<script setup>
import RhidMetricsCard from '@/Components/Admin/Companies/RhidMetricsCard.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import {
    ArrowLeftIcon,
    BuildingOffice2Icon,
    ChartBarIcon,
    ClipboardDocumentListIcon,
    CreditCardIcon,
    DocumentTextIcon,
    ExclamationTriangleIcon,
    LinkIcon,
    MapIcon,
    PencilSquareIcon,
    UserGroupIcon,
} from '@heroicons/vue/24/outline';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { collectiveBargainingMonthLabel } from '@/utils/collectiveBargainingMonths';

const props = defineProps({
    company: Object,
    rhidConfigured: { type: Boolean, default: false },
    planIncludesMetodologia: { type: Boolean, default: false },
    pendingRegistration: { type: Boolean, default: false },
    registrationAdminEmail: { type: String, default: null },
    complaintsPublicUrl: { type: String, default: null },
    plans: Array,
    templates: Array,
    methodologyTemplates: { type: Array, default: () => [] },
});

const showDeleteModal = ref(false);
const resendingInvitation = ref(false);

const resendInvitation = () => {
    if (!props.registrationAdminEmail || resendingInvitation.value) {
        return;
    }
    const email = props.registrationAdminEmail || props.company.contact_email || 'o e-mail de contacto';
    const message = props.pendingRegistration
        ? `Reenviar o convite de cadastro para ${email}?`
        : `Enviar link para redefinir a senha para ${email}?`;
    if (!confirm(message)) {
        return;
    }
    resendingInvitation.value = true;
    router.post(route('admin.companies.resend-invitation', props.company.id), {}, {
        preserveScroll: true,
        onFinish: () => {
            resendingInvitation.value = false;
        },
    });
};

const locationLabel = computed(() => {
    const city = props.company.address_city;
    const state = props.company.address_state;
    if (city && state) {
        return `${city} / ${state}`;
    }
    return city || state || null;
});

const collectiveBargainingMonth = computed(() =>
    collectiveBargainingMonthLabel(props.company.collective_bargaining_month),
);

const subscriptionStatusClass = (status) => {
    const s = String(status || '').toLowerCase();
    if (s === 'active') {
        return 'bg-emerald-50 text-emerald-800 ring-emerald-200/80';
    }
    if (s === 'cancelled' || s === 'canceled') {
        return 'bg-slate-100 text-slate-600 ring-slate-200/80';
    }
    return 'bg-amber-50 text-amber-800 ring-amber-200/80';
};

const subscriptionStatusLabel = (status) => {
    const s = String(status || '').toLowerCase();
    if (s === 'active') {
        return 'Ativa';
    }
    if (s === 'cancelled' || s === 'canceled') {
        return 'Cancelada';
    }
    if (s === 'pending') {
        return 'Pendente';
    }
    if (s === 'expired') {
        return 'Expirada';
    }
    return status || '—';
};

const roleLabelClass = (role) => {
    const r = String(role || '').toLowerCase();
    if (r.includes('admin')) {
        return 'bg-talents-50 text-talents-800 ring-talents-200/80';
    }
    return 'bg-slate-100 text-slate-600 ring-slate-200/80';
};

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
</script>

<template>
    <Head :title="company.name" />

    <AdminLayout>
        <template #header>
            <Link
                :href="route('admin.companies.index')"
                class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 transition hover:text-talents-700"
            >
                <ArrowLeftIcon class="h-4 w-4" aria-hidden="true" />
                Empresas
            </Link>
        </template>

        <div class="space-y-6">
            <!-- Hero -->
            <section class="surface-card overflow-hidden p-6 sm:p-8">
                <div class="flex flex-col gap-6 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1"
                                :class="
                                    company.is_active
                                        ? 'bg-emerald-50 text-emerald-800 ring-emerald-200/80'
                                        : 'bg-slate-100 text-slate-600 ring-slate-200/80'
                                "
                            >
                                {{ company.is_active ? 'Ativa' : 'Inativa' }}
                            </span>
                            <span
                                v-if="rhidConfigured"
                                class="inline-flex items-center rounded-full bg-talents-50 px-2.5 py-0.5 text-xs font-semibold text-talents-800 ring-1 ring-talents-200/80"
                            >
                                RHID configurado
                            </span>
                            <span
                                v-if="pendingRegistration"
                                class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-800 ring-1 ring-amber-200/80"
                            >
                                Aguarda cadastro
                            </span>
                        </div>
                        <h1 class="mt-3 text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">
                            {{ company.name }}
                        </h1>
                        <p v-if="company.legal_name && company.legal_name !== company.name" class="mt-1 text-sm text-slate-500">
                            {{ company.legal_name }}
                        </p>
                        <dl class="mt-4 flex flex-wrap gap-x-6 gap-y-2 text-sm text-slate-600">
                            <div v-if="company.cnpj">
                                <dt class="sr-only">CNPJ</dt>
                                <dd><span class="text-slate-400">CNPJ</span> {{ company.cnpj }}</dd>
                            </div>
                            <div v-if="company.segment">
                                <dt class="sr-only">Segmento</dt>
                                <dd><span class="text-slate-400">Segmento</span> {{ company.segment }}</dd>
                            </div>
                            <div v-if="company.activity_branch">
                                <dt class="sr-only">Ramo de atividade</dt>
                                <dd><span class="text-slate-400">Ramo</span> {{ company.activity_branch }}</dd>
                            </div>
                            <div v-if="collectiveBargainingMonth">
                                <dt class="sr-only">Mês do dissídio</dt>
                                <dd><span class="text-slate-400">Dissídio</span> {{ collectiveBargainingMonth }}</dd>
                            </div>
                            <div v-if="locationLabel">
                                <dt class="sr-only">Localização</dt>
                                <dd><span class="text-slate-400">Local</span> {{ locationLabel }}</dd>
                            </div>
                            <div v-if="company.employee_count_estimate != null">
                                <dt class="sr-only">Funcionários</dt>
                                <dd>
                                    <span class="text-slate-400">Funcionários</span>
                                    {{ company.employee_count_estimate }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                    <div class="flex shrink-0 flex-wrap gap-2">
                        <button
                            v-if="registrationAdminEmail"
                            type="button"
                            class="inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-medium shadow-sm transition disabled:opacity-50"
                            :class="
                                pendingRegistration
                                    ? 'border-amber-200 bg-amber-50 text-amber-800 hover:bg-amber-100'
                                    : 'border-slate-200 bg-white text-slate-700 hover:border-talents-200 hover:bg-talents-50/50 hover:text-talents-800'
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
                            :href="route('admin.companies.edit', company.id)"
                            class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:border-talents-200 hover:bg-talents-50/50 hover:text-talents-800"
                        >
                            <PencilSquareIcon class="h-4 w-4" aria-hidden="true" />
                            Editar
                        </Link>
                        <Link
                            :href="route('admin.companies.users.index', company.id)"
                            class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:border-talents-200 hover:bg-talents-50/50 hover:text-talents-800"
                        >
                            <UserGroupIcon class="h-4 w-4" aria-hidden="true" />
                            Gerir usuários
                        </Link>
                    </div>
                </div>
            </section>

            <!-- Dados + Assinaturas -->
            <div class="grid gap-6 lg:grid-cols-2">
                <section class="surface-card p-6 sm:p-7">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-slate-100 text-slate-600"
                            aria-hidden="true"
                        >
                            <BuildingOffice2Icon class="h-5 w-5" />
                        </div>
                        <h2 class="text-base font-semibold text-slate-900">Dados</h2>
                    </div>
                    <dl class="mt-5 grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">E-mail</dt>
                            <dd class="mt-1 text-slate-800">{{ company.contact_email || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Razão social</dt>
                            <dd class="mt-1 text-slate-800">{{ company.legal_name || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">CNPJ</dt>
                            <dd class="mt-1 text-slate-800">{{ company.cnpj || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Segmento</dt>
                            <dd class="mt-1 text-slate-800">{{ company.segment || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Ramo de atividade</dt>
                            <dd class="mt-1 text-slate-800">{{ company.activity_branch || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Mês do dissídio</dt>
                            <dd class="mt-1 text-slate-800">{{ collectiveBargainingMonth || '—' }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Logradouro</dt>
                            <dd class="mt-1 text-slate-800">{{ company.address_street || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Bairro</dt>
                            <dd class="mt-1 text-slate-800">{{ company.address_neighborhood || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Município</dt>
                            <dd class="mt-1 text-slate-800">{{ company.address_city || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">UF</dt>
                            <dd class="mt-1 text-slate-800">{{ company.address_state || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">CEP</dt>
                            <dd class="mt-1 text-slate-800">{{ company.address_zip || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Regime tributário</dt>
                            <dd class="mt-1 text-slate-800">{{ company.tax_regime || '—' }}</dd>
                        </div>
                    </dl>
                    <div
                        v-if="complaintsPublicUrl"
                        class="mt-5 rounded-2xl border border-slate-100 bg-slate-50/80 px-4 py-3"
                    >
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Canal de denúncias (público)</p>
                        <p class="mt-1.5 break-all font-mono text-xs text-slate-700">{{ complaintsPublicUrl }}</p>
                    </div>
                </section>

                <section class="surface-card p-6 sm:p-7">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-slate-100 text-slate-600"
                            aria-hidden="true"
                        >
                            <CreditCardIcon class="h-5 w-5" />
                        </div>
                        <h2 class="text-base font-semibold text-slate-900">Assinaturas</h2>
                    </div>
                    <ul v-if="company.subscriptions?.length" class="mt-5 divide-y divide-slate-100">
                        <li
                            v-for="sub in company.subscriptions"
                            :key="sub.id"
                            class="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                        >
                            <span class="text-sm font-medium text-slate-800">{{ sub.plan?.name || 'Plano' }}</span>
                            <span
                                class="inline-flex shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1"
                                :class="subscriptionStatusClass(sub.status)"
                            >
                                {{ subscriptionStatusLabel(sub.status) }}
                            </span>
                        </li>
                    </ul>
                    <p v-else class="mt-5 text-sm text-slate-500">Nenhuma assinatura vinculada.</p>
                </section>
            </div>

            <!-- RHID -->
            <div class="grid gap-6 lg:grid-cols-2">
                <RhidMetricsCard :company-id="company.id" :rhid-configured="rhidConfigured" />
            </div>

            <!-- Direcionamento Estratégico -->
            <section class="surface-card p-6 sm:p-7">
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-talents-50 text-talents-700"
                        aria-hidden="true"
                    >
                        <ChartBarIcon class="h-5 w-5" />
                    </div>
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">Direcionamento Estratégico</h2>
                        <p class="mt-0.5 text-sm text-slate-500">
                            Acesso definido pelo plano em
                            <Link :href="route('admin.plans.index')" class="font-medium text-talents-700 hover:underline">Planos</Link>.
                        </p>
                    </div>
                </div>
                <p
                    class="mt-4 rounded-2xl px-4 py-3 text-sm font-medium"
                    :class="planIncludesMetodologia ? 'bg-emerald-50/90 text-emerald-900' : 'bg-amber-50/90 text-amber-900'"
                >
                    {{
                        planIncludesMetodologia
                            ? 'Plano ativo inclui Direcionamento Estratégico.'
                            : 'Plano ativo não inclui Direcionamento Estratégico — ajuste o plano da empresa.'
                    }}
                </p>
                <h3 class="mt-6 text-xs font-medium uppercase tracking-wide text-slate-400">Templates de satisfação (etapa 02)</h3>
                <ul v-if="methodologyTemplates.length" class="mt-3 divide-y divide-slate-100">
                    <li
                        v-for="t in methodologyTemplates"
                        :key="'m-' + t.id"
                        class="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                    >
                        <span class="text-sm text-slate-800">{{ t.title }}</span>
                        <button
                            v-if="!(company.methodology_form_templates || []).some((x) => x.id === t.id)"
                            type="button"
                            class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold text-talents-700 ring-1 ring-talents-200/80 transition hover:bg-talents-50"
                            @click="attachMethodologyTemplate(t.id)"
                        >
                            Vincular
                        </button>
                        <button
                            v-else
                            type="button"
                            class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold text-red-700 ring-1 ring-red-200/80 transition hover:bg-red-50"
                            @click="detachMethodologyTemplate(t.id)"
                        >
                            Remover
                        </button>
                    </li>
                </ul>
                <p v-else class="mt-3 text-sm text-slate-500">
                    Nenhum template cadastrado. Crie em Admin → Direcionamento Estratégico → Templates.
                </p>
            </section>

            <!-- Mapeamentos -->
            <section class="surface-card p-6 sm:p-7">
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-slate-100 text-slate-600"
                        aria-hidden="true"
                    >
                        <MapIcon class="h-5 w-5" />
                    </div>
                    <h2 class="text-base font-semibold text-slate-900">Mapeamentos disponíveis</h2>
                </div>
                <ul v-if="templates.length" class="mt-5 divide-y divide-slate-100">
                    <li
                        v-for="t in templates"
                        :key="t.id"
                        class="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                    >
                        <span class="text-sm text-slate-800">{{ t.title }}</span>
                        <button
                            v-if="!(company.survey_templates || []).some((x) => x.id === t.id)"
                            type="button"
                            class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold text-talents-700 ring-1 ring-talents-200/80 transition hover:bg-talents-50"
                            @click="attach(t.id)"
                        >
                            Vincular
                        </button>
                        <button
                            v-else
                            type="button"
                            class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold text-red-700 ring-1 ring-red-200/80 transition hover:bg-red-50"
                            @click="detach(t.id)"
                        >
                            Remover
                        </button>
                    </li>
                </ul>
                <p v-else class="mt-5 text-sm text-slate-500">Nenhum mapeamento disponível no catálogo.</p>
            </section>

            <!-- Pesquisas -->
            <section class="surface-card p-6 sm:p-7">
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-slate-100 text-slate-600"
                        aria-hidden="true"
                    >
                        <ClipboardDocumentListIcon class="h-5 w-5" />
                    </div>
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">Pesquisas e parecer</h2>
                        <p class="mt-0.5 text-sm text-slate-500">
                            Parecer e plano ficam visíveis para a empresa após publicação.
                        </p>
                    </div>
                </div>
                <ul v-if="(company.surveys || []).length" class="mt-5 divide-y divide-slate-100">
                    <li
                        v-for="s in company.surveys"
                        :key="s.id"
                        class="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                    >
                        <span class="text-sm font-medium text-slate-800">{{ s.title }}</span>
                        <Link
                            :href="route('admin.companies.surveys.action-plan.edit', [company.id, s.id])"
                            class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold text-talents-700 ring-1 ring-talents-200/80 transition hover:bg-talents-50"
                        >
                            <DocumentTextIcon class="h-3.5 w-3.5" aria-hidden="true" />
                            Parecer e plano
                        </Link>
                    </li>
                </ul>
                <p v-else class="mt-5 text-sm text-slate-500">Nenhuma pesquisa cadastrada para esta empresa.</p>
            </section>

            <!-- Usuários -->
            <section class="surface-card p-6 sm:p-7">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-slate-100 text-slate-600"
                            aria-hidden="true"
                        >
                            <UserGroupIcon class="h-5 w-5" />
                        </div>
                        <h2 class="text-base font-semibold text-slate-900">Usuários</h2>
                    </div>
                    <Link
                        :href="route('admin.companies.users.index', company.id)"
                        class="inline-flex items-center gap-1.5 text-sm font-medium text-talents-700 hover:underline"
                    >
                        <LinkIcon class="h-4 w-4" aria-hidden="true" />
                        Gerir utilizadores
                    </Link>
                </div>
                <ul v-if="company.users?.length" class="mt-5 divide-y divide-slate-100">
                    <li
                        v-for="u in company.users"
                        :key="u.id"
                        class="flex flex-wrap items-center justify-between gap-2 py-3 first:pt-0 last:pb-0"
                    >
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-800">{{ u.name }}</p>
                            <p class="text-xs text-slate-500">{{ u.email }}</p>
                        </div>
                        <span
                            class="inline-flex shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize ring-1"
                            :class="roleLabelClass(u.role)"
                        >
                            {{ u.role }}
                        </span>
                    </li>
                </ul>
                <p v-else class="mt-5 text-sm text-slate-500">Nenhum usuário vinculado.</p>
            </section>

            <!-- Danger zone -->
            <section class="rounded-3xl border border-red-200/80 bg-red-50/40 p-6 sm:p-7">
                <div class="flex items-start gap-3">
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-red-100/80 text-red-700"
                        aria-hidden="true"
                    >
                        <ExclamationTriangleIcon class="h-5 w-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <h2 class="text-base font-semibold text-red-900">Excluir empresa</h2>
                        <p class="mt-2 text-sm text-red-900/80">
                            Remove a empresa, usuários vinculados e todos os dados associados (pesquisas, assinaturas, etc.). Esta ação não
                            pode ser desfeita.
                        </p>
                        <DangerButton class="mt-4" type="button" @click="showDeleteModal = true">Excluir empresa</DangerButton>
                    </div>
                </div>
            </section>
        </div>

        <Modal :show="showDeleteModal" @close="showDeleteModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Confirmar exclusão</h2>
                <p class="mt-2 text-sm text-gray-600">
                    Tem certeza que deseja excluir <strong>{{ company.name }}</strong>? Todos os dados desta empresa serão apagados
                    permanentemente.
                </p>
                <div class="mt-6 flex justify-end gap-2">
                    <SecondaryButton type="button" @click="showDeleteModal = false">Cancelar</SecondaryButton>
                    <DangerButton type="button" @click="deleteCompany">Sim, excluir</DangerButton>
                </div>
            </div>
        </Modal>
    </AdminLayout>
</template>
