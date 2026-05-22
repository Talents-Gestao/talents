<script setup>
import StrategicCalendar from '@/Components/StrategicCalendar.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    items: { type: Array, default: () => [] },
    year: { type: Number, required: true },
    month: { type: Number, required: true },
    kindLabels: { type: Object, default: () => ({ event: 'Evento', rito: 'Rito' }) },
    title: { type: String, default: 'Calendário estratégico' },
    subtitle: { type: String, default: null },
    fullPageHref: { type: String, default: null },
    fullPageLabel: { type: String, default: 'Abrir calendário completo' },
    /** Nome da rota Ziggy (ex.: admin.dashboard ou client.dashboard) */
    dashboardRoute: { type: String, required: true },
    queryParamYear: { type: String, default: 'cal_year' },
    queryParamMonth: { type: String, default: 'cal_month' },
    canNavigatePrev: { type: Boolean, default: true },
    canNavigateNext: { type: Boolean, default: true },
    periodLabel: { type: String, default: null },
});

const localYear = ref(props.year);
const localMonth = ref(props.month);

watch(
    () => [props.year, props.month],
    ([y, m]) => {
        localYear.value = y;
        localMonth.value = m;
    },
);

const goMonth = (delta) => {
    if (delta < 0 && !props.canNavigatePrev) return;
    if (delta > 0 && !props.canNavigateNext) return;

    let y = localYear.value;
    let mo = localMonth.value + delta;
    if (mo < 1) {
        mo = 12;
        y -= 1;
    } else if (mo > 12) {
        mo = 1;
        y += 1;
    }
    router.get(
        route(props.dashboardRoute),
        {
            [props.queryParamYear]: y,
            [props.queryParamMonth]: mo,
        },
        { preserveScroll: true, replace: true },
    );
};

const goToday = () => {
    const t = new Date();
    router.get(
        route(props.dashboardRoute),
        {
            [props.queryParamYear]: t.getFullYear(),
            [props.queryParamMonth]: t.getMonth() + 1,
        },
        { preserveScroll: true, replace: true },
    );
};
</script>

<template>
    <div class="surface-card overflow-hidden">
        <div
            class="flex flex-col gap-2 border-b border-slate-200/80 p-4 sm:flex-row sm:items-start sm:justify-between sm:gap-4"
        >
            <div>
                <h3 class="text-base font-semibold text-slate-900">{{ title }}</h3>
                <p v-if="subtitle" class="mt-0.5 text-sm text-slate-500">{{ subtitle }}</p>
            </div>
            <Link
                v-if="fullPageHref"
                :href="fullPageHref"
                class="shrink-0 text-sm font-medium text-talents-700 hover:underline"
            >
                {{ fullPageLabel }}
            </Link>
        </div>
        <div class="p-3 sm:p-4">
            <StrategicCalendar
                :year="localYear"
                :month="localMonth"
                :items="items"
                :kind-labels="kindLabels"
                embedded
                compact
                :show-view-toggle="false"
                :can-navigate-prev="canNavigatePrev"
                :can-navigate-next="canNavigateNext"
                :period-label="periodLabel"
                @navigate-month="goMonth"
                @go-today="goToday"
            />
        </div>
    </div>
</template>
