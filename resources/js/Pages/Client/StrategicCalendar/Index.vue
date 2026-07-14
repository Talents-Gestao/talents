<script setup>
import StrategicCalendar from '@/Components/StrategicCalendar.vue';
import AttachmentList from '@/Components/StrategicCalendar/AttachmentList.vue';
import StrategicKindBadge from '@/Components/StrategicKindBadge.vue';
import DayModal from '@/Pages/Client/StrategicCalendar/DayModal.vue';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import { formatDateNumeric, formatRelativeDate } from '@/utils/dateOnly';
import { CheckCircleIcon, InformationCircleIcon } from '@heroicons/vue/24/outline';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    monthItems: Array,
    upcoming: Array,
    agendaItems: Array,
    calendarYear: Number,
    calendarMonth: Number,
    agendaFilter: { type: String, default: 'all' },
    kindLabels: Object,
    kinds: { type: Array, default: () => [] },
    recurrences: { type: Array, default: () => [] },
    recurrenceLabels: { type: Object, default: () => ({}) },
    visiblePeriod: { type: Object, default: null },
    canNavigatePrev: { type: Boolean, default: true },
    canNavigateNext: { type: Boolean, default: true },
    maxAttachmentMb: { type: Number, default: 512 },
});

const dayModalOpen = ref(false);
const dayModalIso = ref(null);

const dayModalItems = computed(() => {
    if (!dayModalIso.value) return [];
    return (props.monthItems ?? []).filter((item) => {
        const iso = item.occurs_on?.slice?.(0, 10) ?? String(item.occurs_on);
        return iso === dayModalIso.value;
    });
});

const agendaOptions = [
    { value: 'all', label: 'Ambas' },
    { value: 'talents', label: 'Talents' },
    { value: 'company', label: 'Empresa' },
];

const navigateMonth = (delta) => {
    if (delta < 0 && !props.canNavigatePrev) return;
    if (delta > 0 && !props.canNavigateNext) return;

    let y = props.calendarYear;
    let m = props.calendarMonth + delta;
    if (m < 1) {
        m = 12;
        y -= 1;
    } else if (m > 12) {
        m = 1;
        y += 1;
    }
    goToMonth(y, m);
};

const calendarQuery = (extra = {}) => ({
    year: props.calendarYear,
    month: props.calendarMonth,
    agenda: props.agendaFilter !== 'all' ? props.agendaFilter : undefined,
    ...extra,
});

const goToMonth = (year, month) => {
    router.get(
        route('client.strategic-calendar.index'),
        calendarQuery({ year, month }),
        { preserveState: true, replace: true },
    );
};

const setAgendaFilter = (agenda) => {
    router.get(
        route('client.strategic-calendar.index'),
        calendarQuery({
            year: props.calendarYear,
            month: props.calendarMonth,
            agenda: agenda !== 'all' ? agenda : undefined,
        }),
        { preserveState: true, replace: true },
    );
};

const goToday = () => {
    const t = new Date();
    router.get(
        route('client.strategic-calendar.index'),
        calendarQuery({ year: t.getFullYear(), month: t.getMonth() + 1 }),
        { preserveState: true, replace: true },
    );
};

const openDayModal = (iso) => {
    dayModalIso.value = iso;
    dayModalOpen.value = true;
};

const closeDayModal = () => {
    dayModalOpen.value = false;
};

const toggleUpcoming = (row) => {
    const routeName = row.source_type === 'task'
        ? 'client.strategic-calendar.toggle-task-completion'
        : 'client.strategic-calendar.toggle-completion';
    const payload = row.source_type === 'task'
        ? { completed: !row.completed }
        : { occurs_on: row.occurs_on, completed: !row.completed };

    router.patch(route(routeName, row.source_id), payload, {
        preserveScroll: true,
        preserveState: false,
    });
};

const agendaBadgeClass = (agenda) =>
    agenda === 'company'
        ? 'bg-sky-50 text-sky-800 ring-sky-200'
        : 'bg-violet-50 text-violet-800 ring-violet-200';
</script>

<template>
    <Head title="Calendário estratégico" />

    <ClientLayout>
        <template #header>
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-xl font-semibold leading-tight text-talents-900">Calendário estratégico</h2>
                    <span
                        class="inline-flex items-center gap-1 text-slate-400"
                        title="Agenda Talents (orientação) e agenda interna da empresa (seus lançamentos)."
                    >
                        <InformationCircleIcon class="h-5 w-5 shrink-0" aria-hidden="true" />
                        <span class="sr-only">Ajuda</span>
                    </span>
                </div>
                <p class="mt-1 max-w-2xl text-sm text-slate-500">
                    Duas agendas interligadas: eventos da Talents e a agenda interna da sua empresa.
                    Clique em um dia para lançar eventos internos.
                </p>
            </div>
        </template>

        <div class="mb-4 flex flex-wrap gap-2">
            <button
                v-for="opt in agendaOptions"
                :key="opt.value"
                type="button"
                class="rounded-full px-3 py-1.5 text-sm font-semibold transition"
                :class="
                    agendaFilter === opt.value
                        ? 'bg-talents-600 text-white'
                        : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50'
                "
                @click="setAgendaFilter(opt.value)"
            >
                {{ opt.label }}
            </button>
        </div>

        <div class="mb-10">
            <StrategicCalendar
                :year="calendarYear"
                :month="calendarMonth"
                :items="monthItems"
                :agenda-items="agendaItems"
                :kind-labels="kindLabels"
                :can-navigate-prev="canNavigatePrev"
                :can-navigate-next="canNavigateNext"
                :period-label="visiblePeriod?.label ?? null"
                :navigation-range="visiblePeriod"
                completion-enabled
                editable
                @navigate-month="navigateMonth"
                @pick-month="({ year, month }) => goToMonth(year, month)"
                @go-today="goToday"
                @edit-day="openDayModal"
            />
        </div>

        <DayModal
            :show="dayModalOpen"
            :iso="dayModalIso"
            :items="dayModalItems"
            :kinds="kinds"
            :recurrences="recurrences"
            :kind-labels="kindLabels"
            :recurrence-labels="recurrenceLabels"
            @close="closeDayModal"
        />

        <div class="surface-card overflow-hidden p-6 sm:p-8">
            <h3 class="text-sm font-semibold text-slate-900">Próximas datas</h3>
            <ul class="mt-4 divide-y divide-slate-100">
                <li v-for="row in upcoming" :key="row.id" class="py-4 first:pt-0">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <StrategicKindBadge :kind="row.kind" :label="kindLabels[row.kind] ?? row.kind" />
                                <span
                                    v-if="row.agenda_label || row.agenda"
                                    class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide ring-1 ring-inset"
                                    :class="agendaBadgeClass(row.agenda)"
                                >
                                    {{ row.agenda_label ?? (row.agenda === 'company' ? 'Empresa' : 'Talents') }}
                                </span>
                                <span
                                    class="font-medium"
                                    :class="row.completed ? 'text-slate-400 line-through' : 'text-slate-900'"
                                >
                                    {{ row.title }}
                                </span>
                                <button
                                    v-if="row.kind !== 'birthday'"
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded-full border px-2 py-1 text-xs font-semibold transition"
                                    :class="row.completed ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 text-slate-600 hover:border-emerald-300 hover:text-emerald-700'"
                                    @click="toggleUpcoming(row)"
                                >
                                    <CheckCircleIcon class="h-4 w-4" aria-hidden="true" />
                                    {{ row.completed ? 'Concluído' : 'Marcar feito' }}
                                </button>
                            </div>
                            <p v-if="row.description" class="mt-2 line-clamp-2 text-sm text-slate-600">
                                {{ row.description }}
                            </p>
                            <p v-if="row.list_title" class="mt-1 text-xs text-slate-500">Lista: {{ row.list_title }}</p>
                            <AttachmentList
                                v-if="row.attachments?.length"
                                class="mt-2"
                                :attachments="row.attachments"
                            />
                        </div>
                        <span
                            class="shrink-0 text-sm tabular-nums text-slate-500"
                            :title="row.occurs_on ? formatDateNumeric(row.occurs_on) : undefined"
                        >{{
                            row.occurs_on ? formatRelativeDate(row.occurs_on) : ''
                        }}</span>
                    </div>
                </li>
                <li v-if="!upcoming?.length" class="py-4 text-sm text-slate-500">Nenhum item encontrado.</li>
            </ul>
        </div>
    </ClientLayout>
</template>
