<script setup>
import BackLink from '@/Components/BackLink.vue';
import FeedbackActionsMenu from '@/Components/Feedback/FeedbackActionsMenu.vue';
import FeedbackStatusBadge from '@/Components/Feedback/FeedbackStatusBadge.vue';
import { feedbackRoute } from '@/composables/useFeedbackRoutes';
import { formatDateTime } from '@/utils/dateOnly';
import { Link } from '@inertiajs/vue3';
import {
    ArrowRightIcon,
    CalendarDaysIcon,
    CalendarIcon,
    DocumentTextIcon,
    EnvelopeIcon,
    PencilSquareIcon,
    RectangleStackIcon,
    UserCircleIcon,
} from '@heroicons/vue/24/outline';
import { computed } from 'vue';

/**
 * Cabeçalho do detalhe do feedback (padrão Page header): voltar, «Feedback · colaborador» (link para a pessoa),
 * selo de status, metadados agrupados (modelo, líder, alinhamento, próximo alinhamento), uma ação principal que
 * muda com o estado e o menu kebab com as ações secundárias. Só apresentação: a ação principal só emite `primary`.
 */
const props = defineProps({
    session: { type: Object, required: true },
    /** primaryAction() de feedbackSessionView, ou null. */
    primary: { type: Object, default: null },
    primaryBusy: { type: Boolean, default: false },
    menuItems: { type: Array, default: () => [] },
});

const emit = defineEmits(['primary']);

const employeeName = computed(() => props.session.employee?.name ?? 'Colaborador');
const formatDay = (iso) => (iso ? new Date(iso).toLocaleDateString('pt-BR') : '');
/** Título próprio só aparece quando não é o padrão «Feedback — nome». */
const customTitle = computed(() => {
    const title = String(props.session.title ?? '').trim();
    return title && title !== `Feedback — ${employeeName.value}` ? title : '';
});
const meta = computed(() =>
    [
        { key: 'title', label: 'Título', icon: DocumentTextIcon, value: customTitle.value },
        { key: 'template', label: 'Modelo', icon: RectangleStackIcon, value: props.session.template?.title ?? '' },
        { key: 'leader', label: 'Líder', icon: UserCircleIcon, value: props.session.leader?.name ?? '' },
        { key: 'scheduled', label: 'Alinhamento', icon: CalendarIcon, value: props.session.scheduled_at ? formatDateTime(props.session.scheduled_at) : '' },
        { key: 'next', label: 'Próximo alinhamento', icon: CalendarDaysIcon, value: formatDay(props.session.next_alignment_at) },
    ].filter((m) => m.value),
);
const primaryIcon = computed(() => ({ continue: PencilSquareIcon, send: EnvelopeIcon, signatures: ArrowRightIcon })[props.primary?.key]);
</script>

<template>
    <header class="space-y-5" data-feedback-session-header>
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div class="min-w-0 space-y-2">
                <BackLink :href="feedbackRoute('index')" label="Feedbacks" :prefer-history="false" />
                <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                    <h2 class="min-w-0 text-balance text-2xl font-semibold leading-tight tracking-tight text-slate-900">
                        <span class="mr-2 font-medium text-slate-600">Feedback ·</span>
                        <Link
                            v-if="session.employee_profile_id"
                            :href="feedbackRoute('employees.show', session.employee_profile_id)"
                            class="rounded-md text-talents-800 underline decoration-talents-200 decoration-2 underline-offset-[6px] transition-colors hover:text-talents-900 hover:decoration-talents-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500 focus-visible:ring-offset-2"
                            :title="`Ver histórico e evolução de ${employeeName}`"
                        >{{ employeeName }}</Link>
                        <span v-else>{{ employeeName }}</span>
                    </h2>
                    <FeedbackStatusBadge :status="session.status" :label="session.status_label" />
                </div>
            </div>

            <div class="flex w-full shrink-0 items-center gap-2 md:w-auto">
                <button
                    v-if="primary"
                    type="button"
                    class="inline-flex min-h-[44px] flex-1 items-center justify-center gap-2 rounded-xl bg-talents-700 px-5 text-sm font-semibold text-white shadow-sm shadow-talents-900/10 transition hover:bg-talents-800 hover:shadow-md active:translate-y-px active:bg-talents-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 disabled:shadow-none md:flex-none"
                    :disabled="primaryBusy"
                    :data-feedback-primary-action="primary.key"
                    @click="emit('primary', primary.key)"
                >
                    <component :is="primaryIcon" class="h-4 w-4" aria-hidden="true" />
                    {{ primaryBusy ? 'Enviando…' : primary.label }}
                </button>
                <FeedbackActionsMenu v-if="menuItems.length" :items="menuItems" label="Mais ações do feedback" />
            </div>
        </div>

        <!-- Metadados numa faixa: rótulo curto sobre o valor e ícone do papel; colunas se ajustam à largura -->
        <dl
            v-if="meta.length"
            class="grid grid-cols-2 gap-x-4 gap-y-4 sm:gap-x-8 rounded-2xl border border-slate-200/80 bg-slate-50/60 px-4 py-3.5 sm:grid-cols-[repeat(auto-fit,minmax(11rem,1fr))] sm:px-5"
            data-feedback-session-meta
        >
            <div v-for="m in meta" :key="m.key" class="flex min-w-0 items-start gap-2.5">
                <component :is="m.icon" class="mt-0.5 h-5 w-5 shrink-0 text-talents-600" aria-hidden="true" />
                <div class="min-w-0">
                    <dt class="text-[11px] font-semibold uppercase tracking-wider text-slate-600">{{ m.label }}</dt>
                    <dd class="mt-0.5 min-w-0 break-words text-sm font-medium text-slate-900">{{ m.value }}</dd>
                </div>
            </div>
        </dl>
    </header>
</template>
