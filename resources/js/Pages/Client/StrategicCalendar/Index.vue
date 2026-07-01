<script setup>
import StrategicCalendar from '@/Components/StrategicCalendar.vue';
import AttachmentList from '@/Components/StrategicCalendar/AttachmentList.vue';
import StrategicKindBadge from '@/Components/StrategicKindBadge.vue';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import { CheckCircleIcon, InformationCircleIcon } from '@heroicons/vue/24/outline';
import { Head, router } from '@inertiajs/vue3';

const props = defineProps({
    monthItems: Array,
    upcoming: Array,
    agendaItems: Array,
    calendarYear: Number,
    calendarMonth: Number,
    kindLabels: Object,
    visiblePeriod: { type: Object, default: null },
    canNavigatePrev: { type: Boolean, default: true },
    canNavigateNext: { type: Boolean, default: true },
});

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

const goToMonth = (year, month) => {
    router.get(
        route('client.strategic-calendar.index'),
        { year, month },
        { preserveState: true, replace: true },
    );
};

const goToday = () => {
    const t = new Date();
    router.get(
        route('client.strategic-calendar.index'),
        { year: t.getFullYear(), month: t.getMonth() + 1 },
        { preserveState: true, replace: true },
    );
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
                        title="Datas e orientações definidas pela Talents para sua jornada NR-1."
                    >
                        <InformationCircleIcon class="h-5 w-5 shrink-0" aria-hidden="true" />
                        <span class="sr-only">Ajuda</span>
                    </span>
                </div>
                <p class="mt-1 max-w-2xl text-sm text-slate-500">
                    Veja eventos, ritos e tarefas no calendário ou nas próximas datas abaixo.
                </p>
            </div>
        </template>

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
                @navigate-month="navigateMonth"
                @pick-month="({ year, month }) => goToMonth(year, month)"
                @go-today="goToday"
            />
        </div>

        <div class="surface-card overflow-hidden p-6 sm:p-8">
            <h3 class="text-sm font-semibold text-slate-900">Próximas datas</h3>
            <ul class="mt-4 divide-y divide-slate-100">
                <li v-for="row in upcoming" :key="row.id" class="py-4 first:pt-0">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <StrategicKindBadge :kind="row.kind" :label="kindLabels[row.kind] ?? row.kind" />
                                <span
                                    class="font-medium"
                                    :class="row.completed ? 'text-slate-400 line-through' : 'text-slate-900'"
                                >
                                    {{ row.title }}
                                </span>
                                <button
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
                        <span class="shrink-0 text-sm tabular-nums text-slate-500">{{
                            row.occurs_on ? new Date(row.occurs_on).toLocaleDateString('pt-BR') : ''
                        }}</span>
                    </div>
                </li>
                <li v-if="!upcoming?.length" class="py-4 text-sm text-slate-500">Nenhum item encontrado.</li>
            </ul>
        </div>
    </ClientLayout>
</template>
