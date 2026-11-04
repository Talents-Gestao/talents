<script setup>
import CountUp from '@/Components/AdminHome/CountUp.vue';
import {
    ClipboardDocumentListIcon,
    CreditCardIcon,
    DocumentTextIcon,
    ExclamationTriangleIcon,
    FingerPrintIcon,
    SparklesIcon,
    UserGroupIcon,
} from '@heroicons/vue/24/outline';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useAdminPermissions } from '@/composables/useAdminPermissions';

const props = defineProps({
    dashboard: { type: Object, required: true },
    registrationAdminEmail: { type: String, default: null },
});

const emit = defineEmits(['resend-invitation']);

const { canAdmin } = useAdminPermissions();

const surveyStatusLabel = (status) => {
    const map = {
        active: 'Ativa',
        draft: 'Rascunho',
        closed: 'Encerrada',
        archived: 'Arquivada',
    };
    return map[String(status || '').toLowerCase()] ?? status ?? '—';
};

const alerts = computed(() => {
    const d = props.dashboard || {};
    const items = [];

    if (!d.is_active) {
        items.push({
            key: 'inactive',
            tone: 'amber',
            title: 'Empresa inativa',
            body: 'A empresa está marcada como inativa. O acesso do portal pode ficar limitado.',
        });
    }

    if (d.pending_registration) {
        items.push({
            key: 'pending',
            tone: 'amber',
            title: 'Cadastro pendente',
            body: props.registrationAdminEmail
                ? `O administrador ainda não concluiu o cadastro (${props.registrationAdminEmail}).`
                : 'O administrador ainda não concluiu o cadastro.',
            action: 'resend',
        });
    }

    if (d.rhid_enabled && !d.rhid_configured && canAdmin('rhid')) {
        items.push({
            key: 'rhid',
            tone: 'amber',
            title: 'RHID sem credenciais',
            body: 'O módulo de ponto está ligado, mas faltam e-mail e senha da API Control iD.',
            href: null,
            tabHint: 'ponto',
        });
    }

    if (!d.plan_includes_metodologia && (d.methodology_templates_linked || 0) > 0) {
        items.push({
            key: 'metodologia',
            tone: 'amber',
            title: 'Direcionamento sem plano',
            body: 'Há templates de satisfação vinculados, mas o plano ativo não inclui Direcionamento Estratégico.',
        });
    }

    if (d.complaints_module_enabled && !d.has_complaints_token) {
        items.push({
            key: 'denuncias',
            tone: 'amber',
            title: 'Canal de denúncias sem link',
            body: 'O módulo de denúncias está habilitado, mas a empresa ainda não tem token público.',
        });
    }

    return items;
});

const kpiCards = computed(() => {
    const d = props.dashboard || {};
    const cards = [
        {
            key: 'users',
            label: 'Usuários ativos',
            value: d.active_users_count ?? 0,
            hint: `${d.users_count ?? 0} no total`,
            icon: UserGroupIcon,
        },
        {
            key: 'surveys',
            label: 'Campanhas NR-1',
            value: d.surveys_count ?? 0,
            hint: `${d.active_surveys_count ?? 0} ativas`,
            icon: ClipboardDocumentListIcon,
        },
        {
            key: 'plan',
            label: 'Assinatura',
            valueLabel: d.has_active_subscription ? d.active_plan_name || 'Plano ativo' : 'Sem plano',
            hint: d.has_active_subscription ? 'Assinatura ativa' : 'Nenhuma assinatura ativa',
            icon: CreditCardIcon,
            isText: true,
        },
        {
            key: 'employees',
            label: 'Colaboradores',
            value: d.employees_count ?? 0,
            hint: 'Cadastro da empresa',
            icon: UserGroupIcon,
        },
        {
            key: 'regulations',
            label: 'Regulamentos',
            value: d.regulations_count ?? 0,
            hint: 'Documentos internos',
            icon: DocumentTextIcon,
        },
    ];

    if (canAdmin('rhid')) {
        cards.push({
            key: 'ponto',
            label: 'Ponto / RHID',
            valueLabel: d.rhid_enabled
                ? d.rhid_configured
                    ? 'Pronto'
                    : 'Pendente'
                : 'Desligado',
            hint: d.rhid_enabled
                ? d.rhid_configured
                    ? 'Módulo ligado e credenciais ok'
                    : 'Módulo ligado sem credenciais'
                : 'Módulo desligado',
            icon: FingerPrintIcon,
            isText: true,
        });
    } else {
        cards.push({
            key: 'purpose',
            label: 'Mapas de Propósito',
            value: d.purpose_maps_count ?? 0,
            hint: 'Campanhas cadastradas',
            icon: SparklesIcon,
        });
    }

    return cards;
});

const toneClass = (tone) =>
    tone === 'amber'
        ? 'border-amber-200 bg-amber-50 text-amber-950'
        : 'border-slate-200 bg-slate-50 text-slate-800';
</script>

<template>
    <div class="space-y-6">
        <section v-if="alerts.length" class="space-y-3" aria-label="Alertas operacionais">
            <div
                v-for="alert in alerts"
                :key="alert.key"
                class="flex flex-col gap-3 rounded-2xl border px-4 py-3 sm:flex-row sm:items-start sm:justify-between"
                :class="toneClass(alert.tone)"
            >
                <div class="flex min-w-0 gap-3">
                    <ExclamationTriangleIcon class="mt-0.5 h-5 w-5 shrink-0" aria-hidden="true" />
                    <div class="min-w-0">
                        <p class="text-sm font-semibold">{{ alert.title }}</p>
                        <p class="mt-0.5 text-sm opacity-90">{{ alert.body }}</p>
                    </div>
                </div>
                <button
                    v-if="alert.action === 'resend'"
                    type="button"
                    class="shrink-0 rounded-full bg-white/80 px-3 py-1.5 text-xs font-semibold text-amber-950 ring-1 ring-amber-200 transition hover:bg-white"
                    @click="emit('resend-invitation')"
                >
                    Reenviar convite
                </button>
            </div>
        </section>

        <section aria-labelledby="company-kpis-heading">
            <h3 id="company-kpis-heading" class="text-sm font-semibold text-slate-900">Indicadores</h3>
            <p class="mt-1 text-sm text-slate-500">Visão rápida do estado operacional desta empresa.</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="card in kpiCards"
                    :key="card.key"
                    class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ card.label }}</p>
                            <p v-if="card.isText" class="mt-2 truncate text-lg font-semibold text-slate-900">
                                {{ card.valueLabel }}
                            </p>
                            <p v-else class="mt-2 text-2xl font-semibold tabular-nums text-slate-900">
                                <CountUp :value="card.value" />
                            </p>
                            <p class="mt-1 text-xs text-slate-500">{{ card.hint }}</p>
                        </div>
                        <span
                            class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600"
                        >
                            <component :is="card.icon" class="h-5 w-5" aria-hidden="true" />
                        </span>
                    </div>
                </article>
            </div>
        </section>

        <section
            v-if="(dashboard.recent_surveys || []).length || (dashboard.recent_purpose_maps || []).length"
            class="grid gap-4 lg:grid-cols-2"
            aria-label="Atividade recente"
        >
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-center gap-2">
                    <ClipboardDocumentListIcon class="h-5 w-5 text-talents-700" aria-hidden="true" />
                    <h3 class="text-sm font-semibold text-slate-900">Campanhas NR-1 recentes</h3>
                </div>
                <ul v-if="(dashboard.recent_surveys || []).length" class="mt-3 divide-y divide-slate-100">
                    <li
                        v-for="survey in dashboard.recent_surveys"
                        :key="survey.id"
                        class="flex flex-wrap items-center justify-between gap-2 py-2.5 first:pt-0 last:pb-0"
                    >
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-slate-800">{{ survey.title }}</p>
                            <p class="text-xs text-slate-500">{{ surveyStatusLabel(survey.status) }}</p>
                        </div>
                        <Link
                            v-if="survey.nr1_url"
                            :href="survey.nr1_url"
                            class="shrink-0 rounded-full bg-talents-700 px-3 py-1 text-xs font-semibold text-white transition hover:bg-talents-800"
                        >
                            Abrir painel NR-1
                        </Link>
                    </li>
                </ul>
                <p v-else class="mt-3 text-sm text-slate-500">Nenhuma campanha cadastrada.</p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-center gap-2">
                    <SparklesIcon class="h-5 w-5 text-talents-700" aria-hidden="true" />
                    <h3 class="text-sm font-semibold text-slate-900">Mapas de Propósito</h3>
                </div>
                <ul v-if="(dashboard.recent_purpose_maps || []).length" class="mt-3 divide-y divide-slate-100">
                    <li
                        v-for="map in dashboard.recent_purpose_maps"
                        :key="map.id"
                        class="flex items-center justify-between gap-2 py-2.5 first:pt-0 last:pb-0"
                    >
                        <p class="truncate text-sm font-medium text-slate-800">{{ map.title }}</p>
                        <span class="shrink-0 text-xs text-slate-500">
                            {{ map.responses_count }}
                            {{ map.responses_count === 1 ? 'resposta' : 'respostas' }}
                        </span>
                    </li>
                </ul>
                <p v-else class="mt-3 text-sm text-slate-500">Nenhum mapa cadastrado.</p>
                <Link
                    :href="route('admin.mapa-proposito.index')"
                    class="mt-3 inline-flex text-xs font-semibold text-talents-700 hover:underline"
                >
                    Abrir dashboard de Mapas
                </Link>
            </div>
        </section>
    </div>
</template>
