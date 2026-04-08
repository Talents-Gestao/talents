<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

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

const monthTitle = computed(() => {
    const d = new Date(localYear.value, localMonth.value - 1, 1);
    return d.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' });
});

const itemsByDay = computed(() => {
    const map = {};
    for (const item of props.items) {
        const key = typeof item.occurs_on === 'string' ? item.occurs_on.slice(0, 10) : String(item.occurs_on);
        if (!map[key]) map[key] = [];
        map[key].push(item);
    }
    return map;
});

const todayIso = computed(() => {
    const t = new Date();
    return `${t.getFullYear()}-${String(t.getMonth() + 1).padStart(2, '0')}-${String(t.getDate()).padStart(2, '0')}`;
});

const weeks = computed(() => {
    const y = localYear.value;
    const m = localMonth.value;
    const first = new Date(y, m - 1, 1);
    const startPad = first.getDay();
    const daysInMonth = new Date(y, m, 0).getDate();
    const cells = [];
    for (let i = 0; i < startPad; i++) {
        cells.push({ day: null, iso: null, items: [], muted: true });
    }
    for (let d = 1; d <= daysInMonth; d++) {
        const iso = `${y}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
        cells.push({
            day: d,
            iso,
            items: itemsByDay.value[iso] ?? [],
            muted: false,
            isToday: iso === todayIso.value,
        });
    }
    while (cells.length % 7 !== 0) {
        cells.push({ day: null, iso: null, items: [], muted: true });
    }
    const rows = [];
    for (let i = 0; i < cells.length; i += 7) {
        rows.push(cells.slice(i, i + 7));
    }
    return rows;
});

const weekdayLabels = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

const kindLabel = (kind) => props.kindLabels[kind] ?? kind;

const goMonth = (delta) => {
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
</script>

<template>
    <div class="dashboard-bs-calendar">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-3 p-md-4">
                <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3 mb-3">
                    <div>
                        <h3 class="h5 mb-1 fw-semibold text-body">{{ title }}</h3>
                        <p v-if="subtitle" class="small text-secondary mb-0">{{ subtitle }}</p>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <div class="btn-group" role="group" aria-label="Mês">
                            <button type="button" class="btn btn-sm btn-outline-secondary" @click="goMonth(-1)">‹</button>
                            <span class="btn btn-sm btn-light disabled text-capitalize px-3 border">{{ monthTitle }}</span>
                            <button type="button" class="btn btn-sm btn-outline-secondary" @click="goMonth(1)">›</button>
                        </div>
                        <Link
                            v-if="fullPageHref"
                            :href="fullPageHref"
                            class="btn btn-sm btn-primary"
                        >
                            {{ fullPageLabel }}
                        </Link>
                    </div>
                </div>

                <div class="table-responsive rounded-3 border">
                    <table class="table table-bordered mb-0 align-middle small">
                        <thead class="table-light">
                            <tr>
                                <th
                                    v-for="w in weekdayLabels"
                                    :key="w"
                                    scope="col"
                                    class="text-center text-secondary fw-medium py-2"
                                    style="width: 14.28%"
                                >
                                    {{ w }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, ri) in weeks" :key="ri">
                                <td
                                    v-for="(cell, ci) in row"
                                    :key="ci"
                                    class="cal-cell p-2"
                                    :class="{
                                        'cal-cell-muted': cell.muted,
                                        'cal-today': cell.isToday && cell.day,
                                    }"
                                >
                                    <div v-if="cell.day" class="d-flex justify-content-between align-items-start gap-1">
                                        <span class="fw-semibold text-body-secondary">{{ cell.day }}</span>
                                    </div>
                                    <ul v-if="cell.items?.length" class="list-unstyled mb-0 mt-1">
                                        <li v-for="it in cell.items" :key="it.id" class="mb-1">
                                            <span
                                                class="badge rounded-pill me-1"
                                                :class="it.kind === 'rito' ? 'text-bg-secondary' : 'text-bg-primary'"
                                            >
                                                {{ kindLabel(it.kind) }}
                                            </span>
                                            <span class="text-body">{{ it.title }}</span>
                                        </li>
                                    </ul>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Mantém células com altura mínima para o mês inteiro visível de uma vez */
.cal-cell {
    min-height: 5.5rem;
    vertical-align: top;
}

.cal-cell-muted {
    background-color: rgba(0, 0, 0, 0.02);
}

.cal-today {
    box-shadow: inset 0 0 0 2px rgba(99, 42, 126, 0.35);
}
</style>
