<script setup>
import AttachmentList from '@/Components/StrategicCalendar/AttachmentList.vue';
import StrategicKindBadge from '@/Components/StrategicKindBadge.vue';
import { kindTheme, monthTheme } from '@/utils/strategicCalendarThemes';
import { CheckCircleIcon, CalendarDaysIcon, ChevronLeftIcon, ChevronRightIcon, PencilSquareIcon, ArrowPathIcon } from '@heroicons/vue/24/outline';
import { Link, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    year: { type: Number, required: true },
    month: { type: Number, required: true },
    items: { type: Array, default: () => [] },
    /** Itens para visão Agenda (ex.: próximos 60 dias), já filtrados no backend */
    agendaItems: { type: Array, default: () => [] },
    kindLabels: { type: Object, default: () => ({}) },
    compact: { type: Boolean, default: false },
    showViewToggle: { type: Boolean, default: true },
    /** Sem borda/sombra externa (ex.: dentro do StrategicCalendarWidget que já usa surface-card) */
    embedded: { type: Boolean, default: false },
    canNavigatePrev: { type: Boolean, default: true },
    canNavigateNext: { type: Boolean, default: true },
    /** Rótulo do período do plano (ex.: "2 meses") para banner informativo */
    periodLabel: { type: String, default: null },
    /** Janela navegável (cliente): { start: 'YYYY-MM-DD', end: 'YYYY-MM-DD' } */
    navigationRange: { type: Object, default: null },
    /** Permite editar data e abrir edição completa (admin) */
    editable: { type: Boolean, default: false },
    /** Nome da rota para atualizar data (ex.: admin.strategic-calendar.update-date) */
    updateDateRoute: { type: String, default: null },
    /** Nome da rota para editar item (ex.: admin.strategic-calendar.edit) */
    editItemRoute: { type: String, default: null },
    /** Habilita check de conclusão no painel cliente */
    completionEnabled: { type: Boolean, default: false },
    toggleCompletionRoute: { type: String, default: 'client.strategic-calendar.toggle-completion' },
    toggleTaskCompletionRoute: { type: String, default: 'client.strategic-calendar.toggle-task-completion' },
});

const emit = defineEmits(['navigate-month', 'pick-month', 'go-today', 'update:view', 'edit-day']);

const selectedDay = ref(1);
const currentView = ref('month');
const completingIds = ref(new Set());
const monthPickerOpen = ref(false);
const pickerYear = ref(props.year);
const monthPickerRef = ref(null);

const KIND_ORDER = ['rito', 'event', 'task'];

function sortItemsByKind(items) {
    const order = { rito: 0, event: 1, task: 2 };
    return [...items].sort(
        (a, b) => (order[a.kind] ?? 9) - (order[b.kind] ?? 9) || String(a.title).localeCompare(String(b.title)),
    );
}

function hasRecurrence(item) {
    return Boolean(item?.recurrence || item?.recurrence_label);
}

function maxItemsVisibleInCell() {
    return props.compact ? 2 : 3;
}

const monthPickerLabels = [
    'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun',
    'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez',
];

watch(
    () => props.year,
    (y) => {
        if (!monthPickerOpen.value) {
            pickerYear.value = y;
        }
    },
);

function isMonthSelectable(year, month) {
    if (!props.navigationRange?.start || !props.navigationRange?.end) {
        return true;
    }

    const monthStart = new Date(year, month - 1, 1);
    const monthEnd = new Date(year, month, 0);
    const rangeStart = new Date(`${props.navigationRange.start}T12:00:00`);
    const rangeEnd = new Date(`${props.navigationRange.end}T12:00:00`);

    return monthEnd >= rangeStart && monthStart <= rangeEnd;
}

const monthPickerOptions = computed(() =>
    monthPickerLabels.map((shortLabel, index) => {
        const month = index + 1;
        const theme = monthTheme(month);
        const isCurrent = props.year === pickerYear.value && props.month === month;
        const selectable = isMonthSelectable(pickerYear.value, month);

        return {
            month,
            shortLabel,
            theme,
            isCurrent,
            selectable,
        };
    }),
);

function toggleMonthPicker() {
    monthPickerOpen.value = !monthPickerOpen.value;
    if (monthPickerOpen.value) {
        pickerYear.value = props.year;
    }
}

function closeMonthPicker() {
    monthPickerOpen.value = false;
}

function shiftPickerYear(delta) {
    pickerYear.value += delta;
}

function pickMonth(month) {
    if (!isMonthSelectable(pickerYear.value, month)) return;

    emit('pick-month', { year: pickerYear.value, month });
    closeMonthPicker();
}

function onDocumentClick(event) {
    if (!monthPickerOpen.value) return;
    if (monthPickerRef.value && !monthPickerRef.value.contains(event.target)) {
        closeMonthPicker();
    }
}

onMounted(() => document.addEventListener('click', onDocumentClick));
onUnmounted(() => document.removeEventListener('click', onDocumentClick));

watch(
    () => props.showViewToggle,
    (show) => {
        if (!show) {
            currentView.value = 'month';
        }
    },
    { immediate: true },
);

const todayIso = computed(() => {
    const t = new Date();
    return `${t.getFullYear()}-${String(t.getMonth() + 1).padStart(2, '0')}-${String(t.getDate()).padStart(2, '0')}`;
});

const itemsByDay = computed(() => {
    const map = {};
    for (const item of props.items) {
        const key = item.occurs_on?.slice?.(0, 10) ?? String(item.occurs_on);
        if (!map[key]) map[key] = [];
        map[key].push(item);
    }
    return map;
});

const weeks = computed(() => {
    const y = props.year;
    const m = props.month;
    const first = new Date(y, m - 1, 1);
    const startPad = first.getDay();
    const daysInMonth = new Date(y, m, 0).getDate();
    const cells = [];
    for (let i = 0; i < startPad; i++) {
        cells.push({ day: null, iso: null, items: [] });
    }
    for (let d = 1; d <= daysInMonth; d++) {
        const iso = `${y}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
        cells.push({
            day: d,
            iso,
            items: sortItemsByKind(itemsByDay.value[iso] ?? []),
            isToday: iso === todayIso.value,
        });
    }
    while (cells.length % 7 !== 0) {
        cells.push({ day: null, iso: null, items: [] });
    }
    const rows = [];
    for (let i = 0; i < cells.length; i += 7) {
        rows.push(cells.slice(i, i + 7));
    }
    return rows;
});

const weekdayLabels = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

const monthTitleCapitalized = computed(() => {
    const d = new Date(props.year, props.month - 1, 1);
    return d.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' });
});

const currentMonthTheme = computed(() => monthTheme(props.month));

const visibleKindThemes = computed(() => {
    const kinds = new Set(['event', 'rito']);
    for (const item of [...props.items, ...props.agendaItems]) {
        if (item?.kind) kinds.add(item.kind);
    }

    return Array.from(kinds).map((kind) => ({
        kind,
        label: kindLabel(kind),
        ...kindTheme(kind),
    }));
});

function syncSelectedDay() {
    const t = new Date();
    if (props.year === t.getFullYear() && props.month === t.getMonth() + 1) {
        selectedDay.value = t.getDate();
    } else {
        selectedDay.value = 1;
    }
}

watch(
    () => [props.year, props.month],
    () => {
        syncSelectedDay();
    },
    { immediate: true },
);

const kindLabel = (kind) => props.kindLabels[kind] ?? kind;

function itemKindTheme(item) {
    return kindTheme(item?.kind);
}

const selectedDayIso = computed(() => {
    return `${props.year}-${String(props.month).padStart(2, '0')}-${String(selectedDay.value).padStart(2, '0')}`;
});

const selectedDayItems = computed(() => itemsByDay.value[selectedDayIso.value] ?? []);

const listRowsGrouped = computed(() => {
    const entries = Object.entries(itemsByDay.value)
        .filter(([iso]) => iso.startsWith(`${props.year}-${String(props.month).padStart(2, '0')}`))
        .sort(([a], [b]) => a.localeCompare(b));
    return entries.map(([iso, dayItems]) => ({
        iso,
        label: new Date(iso + 'T12:00:00').toLocaleDateString('pt-BR', {
            weekday: 'short',
            day: 'numeric',
            month: 'short',
        }),
        items: sortItemsByKind(dayItems),
    }));
});

const selectedDayProgress = computed(() => {
    const total = selectedDayItems.value.length;
    if (!total) return null;

    const done = selectedDayItems.value.filter((it) => it.completed).length;
    return { done, total };
});

const selectedDayItemsGrouped = computed(() => {
    const buckets = { rito: [], event: [], task: [] };
    for (const it of selectedDayItems.value) {
        const key = Object.prototype.hasOwnProperty.call(buckets, it.kind) ? it.kind : 'event';
        buckets[key].push(it);
    }

    return KIND_ORDER.filter((kind) => buckets[kind].length).map((kind) => ({
        kind,
        label: kindLabel(kind),
        items: buckets[kind],
    }));
});

const agendaTimeline = computed(() => {
    const groups = {};
    for (const item of props.agendaItems) {
        const key = item.occurs_on?.slice?.(0, 10) ?? String(item.occurs_on);
        if (!groups[key]) groups[key] = [];
        groups[key].push(item);
    }
    return Object.keys(groups)
        .sort()
        .map((iso) => ({
            iso,
            label: new Date(iso + 'T12:00:00').toLocaleDateString('pt-BR', {
                weekday: 'long',
                day: 'numeric',
                month: 'long',
                year: 'numeric',
            }),
            items: groups[iso],
        }));
});

function onPickDay(cell) {
    if (cell.day) {
        selectedDay.value = cell.day;
        if (props.editable && cell.iso) {
            emit('edit-day', cell.iso);
        }
    }
}

function openDayEditor() {
    if (props.editable && selectedDayIso.value) {
        emit('edit-day', selectedDayIso.value);
    }
}

function onPrev() {
    if (!props.canNavigatePrev) return;
    emit('navigate-month', -1);
}

function onNext() {
    if (!props.canNavigateNext) return;
    emit('navigate-month', 1);
}

function onGoToday() {
    emit('go-today');
}

function setView(v) {
    currentView.value = v;
    emit('update:view', v);
}

const rootPad = computed(() => (props.compact ? 'p-3 sm:p-4' : 'p-4 sm:p-6'));

const rootShellClass = computed(() =>
    props.embedded ? 'overflow-hidden' : 'surface-card overflow-hidden',
);

const cellMinH = computed(() =>
    props.compact ? 'min-h-[5rem]' : 'min-h-[5.75rem] sm:min-h-[6.5rem]',
);

function itemSourceId(item) {
    return item?.source_id ?? item?.id;
}

function editItemUrl(item) {
    if (!props.editItemRoute) return null;
    return route(props.editItemRoute, itemSourceId(item));
}

function updateItemDate(item, newDate) {
    if (!props.editable || !props.updateDateRoute || !newDate) return;
    const sourceId = itemSourceId(item);
    if (!sourceId || String(item.occurs_on) === newDate) return;

    router.patch(
        route(props.updateDateRoute, sourceId),
        { occurs_on: newDate },
        { preserveScroll: true, preserveState: true },
    );
}

function completionRouteFor(item) {
    if (item?.source_type === 'task') {
        return route(props.toggleTaskCompletionRoute, itemSourceId(item));
    }

    return route(props.toggleCompletionRoute, itemSourceId(item));
}

function canToggleCompletion(item) {
    return props.completionEnabled && !props.editable && itemSourceId(item);
}

function toggleCompletion(item) {
    if (!canToggleCompletion(item) || completingIds.value.has(item.id)) return;

    const nextCompleted = !item.completed;
    const next = new Set(completingIds.value);
    next.add(item.id);
    completingIds.value = next;

    const payload = item.source_type === 'task'
        ? { completed: nextCompleted }
        : { occurs_on: item.occurs_on, completed: nextCompleted };

    router.patch(completionRouteFor(item), payload, {
        preserveScroll: true,
        preserveState: false,
        onFinish: () => {
            const done = new Set(completingIds.value);
            done.delete(item.id);
            completingIds.value = done;
        },
    });
}

function monthCellClass(cell) {
    const base =
        'relative flex w-full flex-col rounded-xl border text-left transition focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500 focus-visible:ring-offset-2';
    if (!cell.day) {
        return `${base} border-transparent bg-transparent`;
    }
    const selected = selectedDay.value === cell.day;
    const today = cell.isToday && !selected;
    return [
        base,
        selected ? 'border-current shadow-sm' : 'border-slate-200/70 bg-white hover:border-slate-300 hover:shadow-sm',
        today ? 'ring-2 ring-offset-1' : '',
    ].join(' ');
}

function monthCellStyle(cell) {
    if (!cell.day) return {};

    const theme = currentMonthTheme.value;
    const selected = selectedDay.value === cell.day;
    const today = cell.isToday && !selected;

    if (selected) {
        return {
            borderColor: theme.color,
            background: `linear-gradient(180deg, ${theme.background} 0%, #ffffff 78%)`,
        };
    }

    if (today) {
        return {
            background: `linear-gradient(180deg, ${theme.background}dd 0%, #ffffff 68%)`,
            '--tw-ring-color': `${theme.color}66`,
        };
    }

    return {
        background: `linear-gradient(180deg, ${theme.background}bb 0%, #ffffff 72%)`,
    };
}

function itemChipStyle(item) {
    const theme = itemKindTheme(item);
    if (item.completed) {
        return {
            borderColor: '#e2e8f0',
            backgroundColor: '#f8fafc',
            color: '#94a3b8',
        };
    }

    return {
        borderColor: `${theme.color}55`,
        backgroundColor: theme.background,
        color: theme.color,
    };
}

function itemTextClass(item) {
    return item.completed ? 'text-slate-400 line-through' : 'text-slate-900';
}

function itemShellClass(item) {
    return item.completed ? 'border-slate-200 bg-slate-50/80 opacity-80' : 'border-slate-200/80 bg-white';
}
</script>

<template>
    <div :class="rootShellClass">
        <p
            v-if="periodLabel"
            class="border-b border-amber-200/80 bg-amber-50/90 px-4 py-2 text-xs text-amber-900 sm:text-sm"
            :class="compact ? 'px-3 sm:px-4' : 'px-4 sm:px-6'"
        >
            Visualização limitada ao período do seu plano:
            <span class="font-semibold">{{ periodLabel }}</span>
        </p>
        <!-- Toolbar -->
        <div
            class="flex flex-col gap-3 border-b border-slate-200/80 sm:flex-row sm:items-center sm:justify-between"
            :class="rootPad"
        >
            <div v-if="showViewToggle" class="flex flex-wrap items-center gap-2">
                <div class="inline-flex rounded-full border border-slate-200 bg-slate-50/90 p-0.5">
                    <button
                        type="button"
                        class="rounded-full px-3 py-1.5 text-xs font-semibold transition sm:text-sm"
                        :class="
                            currentView === 'month'
                                ? 'bg-white text-slate-900 shadow-sm'
                                : 'text-slate-600 hover:text-slate-900'
                        "
                        @click="setView('month')"
                    >
                        Mês
                    </button>
                    <button
                        type="button"
                        class="rounded-full px-3 py-1.5 text-xs font-semibold transition sm:text-sm"
                        :class="
                            currentView === 'list'
                                ? 'bg-white text-slate-900 shadow-sm'
                                : 'text-slate-600 hover:text-slate-900'
                        "
                        @click="setView('list')"
                    >
                        Lista
                    </button>
                    <button
                        type="button"
                        class="rounded-full px-3 py-1.5 text-xs font-semibold transition sm:text-sm"
                        :class="
                            currentView === 'agenda'
                                ? 'bg-white text-slate-900 shadow-sm'
                                : 'text-slate-600 hover:text-slate-900'
                        "
                        @click="setView('agenda')"
                    >
                        Agenda
                    </button>
                </div>
            </div>

            <div
                v-if="currentView !== 'agenda'"
                class="flex flex-wrap items-center justify-between gap-3 sm:justify-end"
                :class="!showViewToggle ? 'w-full sm:ml-auto' : ''"
            >
                <div ref="monthPickerRef" class="relative flex items-center gap-1">
                    <button
                        type="button"
                        class="rounded-lg p-2 transition"
                        :class="
                            canNavigatePrev
                                ? 'text-slate-400 hover:bg-slate-100 hover:text-slate-700'
                                : 'cursor-not-allowed text-slate-200'
                        "
                        :disabled="!canNavigatePrev"
                        aria-label="Mês anterior"
                        @click="onPrev"
                    >
                        <ChevronLeftIcon class="h-5 w-5" aria-hidden="true" />
                    </button>
                    <button
                        type="button"
                        class="flex min-w-[10rem] items-center justify-center gap-2 rounded-lg px-2 py-1.5 text-sm font-semibold capitalize text-slate-800 transition hover:bg-slate-100 sm:text-base"
                        :title="monthTitleCapitalized"
                        aria-haspopup="dialog"
                        :aria-expanded="monthPickerOpen"
                        @click.stop="toggleMonthPicker"
                    >
                        <CalendarDaysIcon class="h-5 w-5 shrink-0 text-talents-700" aria-hidden="true" />
                        {{ monthTitleCapitalized }}
                    </button>
                    <button
                        type="button"
                        class="rounded-lg p-2 transition"
                        :class="
                            canNavigateNext
                                ? 'text-slate-400 hover:bg-slate-100 hover:text-slate-700'
                                : 'cursor-not-allowed text-slate-200'
                        "
                        :disabled="!canNavigateNext"
                        aria-label="Próximo mês"
                        @click="onNext"
                    >
                        <ChevronRightIcon class="h-5 w-5" aria-hidden="true" />
                    </button>

                    <div
                        v-if="monthPickerOpen"
                        class="absolute left-1/2 top-full z-30 mt-2 w-[min(18rem,calc(100vw-2rem))] -translate-x-1/2 rounded-2xl border border-slate-200/90 bg-white p-3 shadow-lg"
                        role="dialog"
                        aria-label="Selecionar mês"
                        @click.stop
                    >
                        <div class="mb-3 flex items-center justify-between gap-2">
                            <button
                                type="button"
                                class="rounded-lg p-1.5 text-slate-500 transition hover:bg-slate-100 hover:text-slate-800"
                                aria-label="Ano anterior"
                                @click="shiftPickerYear(-1)"
                            >
                                <ChevronLeftIcon class="h-4 w-4" aria-hidden="true" />
                            </button>
                            <span class="text-sm font-semibold text-slate-900">{{ pickerYear }}</span>
                            <button
                                type="button"
                                class="rounded-lg p-1.5 text-slate-500 transition hover:bg-slate-100 hover:text-slate-800"
                                aria-label="Próximo ano"
                                @click="shiftPickerYear(1)"
                            >
                                <ChevronRightIcon class="h-4 w-4" aria-hidden="true" />
                            </button>
                        </div>
                        <div class="grid grid-cols-3 gap-1.5">
                            <button
                                v-for="option in monthPickerOptions"
                                :key="option.month"
                                type="button"
                                class="flex items-center gap-1.5 rounded-xl border px-2 py-2 text-left text-xs font-medium transition sm:text-sm"
                                :class="
                                    option.isCurrent
                                        ? 'border-talents-500 bg-talents-50 text-talents-900'
                                        : option.selectable
                                          ? 'border-slate-200 text-slate-700 hover:border-slate-300 hover:bg-slate-50'
                                          : 'cursor-not-allowed border-slate-100 bg-slate-50 text-slate-300'
                                "
                                :disabled="!option.selectable"
                                @click="pickMonth(option.month)"
                            >
                                <span
                                    class="h-2 w-2 shrink-0 rounded-full"
                                    :style="{ backgroundColor: option.selectable ? option.theme.color : '#cbd5e1' }"
                                    aria-hidden="true"
                                />
                                {{ option.shortLabel }}
                            </button>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn-ghost !px-4 !py-2 text-xs sm:text-sm" @click="onGoToday">
                    Hoje
                </button>
            </div>
            <div v-else-if="showViewToggle" class="flex flex-wrap items-center gap-2 sm:ml-auto">
                <p class="text-sm text-slate-500">Próximos eventos, ritos e tarefas (até 60 dias)</p>
                <button type="button" class="btn-ghost !px-4 !py-2 text-xs sm:text-sm" @click="onGoToday">
                    Ir para hoje
                </button>
            </div>
        </div>

        <div
            v-if="currentView !== 'agenda'"
            class="border-b border-slate-200/80"
            :class="rootPad"
            :style="{
                background: `linear-gradient(90deg, ${currentMonthTheme.background}cc 0%, #ffffff 78%)`,
            }"
        >
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <p
                        class="text-xs font-semibold uppercase tracking-wide"
                        :style="{ color: currentMonthTheme.color }"
                    >
                        Campanha do mês
                    </p>
                    <p class="mt-1 text-sm font-semibold sm:text-base">
                        <span
                            class="mr-2 inline-block h-2.5 w-2.5 rounded-full align-middle"
                            :style="{ backgroundColor: currentMonthTheme.color }"
                            aria-hidden="true"
                        />
                        <span :style="{ color: currentMonthTheme.color }">{{ currentMonthTheme.label }}</span>
                        <span class="text-slate-700"> — {{ currentMonthTheme.campaign }}</span>
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2 text-xs text-slate-600">
                    <span class="font-medium text-slate-700">Legenda:</span>
                    <span
                        v-for="theme in visibleKindThemes"
                        :key="theme.kind"
                        class="inline-flex items-center gap-1.5 rounded-full border px-2 py-1"
                        :style="{ borderColor: `${theme.color}66`, backgroundColor: theme.background }"
                    >
                        <span class="h-1.5 w-1.5 rounded-full" :style="{ backgroundColor: theme.color }" aria-hidden="true" />
                        <span class="font-medium text-slate-700">{{ theme.label }}</span>
                    </span>
                    <span v-if="completionEnabled" class="inline-flex items-center gap-1 text-slate-500">
                        <CheckCircleIcon class="h-4 w-4 text-emerald-600" aria-hidden="true" />
                        Concluído
                    </span>
                    <span class="inline-flex items-center gap-1 text-slate-500">
                        <ArrowPathIcon class="h-3.5 w-3.5" aria-hidden="true" />
                        Repete
                    </span>
                </div>
            </div>
        </div>

        <!-- Month + detail -->
        <div v-if="currentView === 'month'" :class="compact ? 'flex flex-col' : 'flex flex-col lg:flex-row'">
            <div class="min-w-0 flex-1" :class="rootPad">
                <div role="grid" class="grid grid-cols-7 gap-px rounded-xl bg-slate-200/90 p-px">
                    <div
                        v-for="w in weekdayLabels"
                        :key="w"
                        class="px-1 py-2 text-center text-[10px] font-semibold uppercase tracking-wide sm:text-xs"
                        :style="{
                            backgroundColor: `${currentMonthTheme.background}99`,
                            color: currentMonthTheme.color,
                        }"
                        role="columnheader"
                    >
                        {{ w }}
                    </div>
                    <template v-for="(row, ri) in weeks" :key="ri">
                        <template v-for="(cell, ci) in row" :key="`${ri}-${ci}`">
                            <div class="bg-white" role="gridcell">
                                <button
                                    v-if="cell.day"
                                    type="button"
                                    :class="[monthCellClass(cell), cellMinH]"
                                    :style="monthCellStyle(cell)"
                                    @click="onPickDay(cell)"
                                >
                                    <span
                                        class="px-2 pt-1.5 text-xs font-semibold tabular-nums"
                                        :class="
                                            selectedDay === cell.day
                                                ? ''
                                                : cell.isToday
                                                  ? 'text-talents-700'
                                                  : 'text-slate-500'
                                        "
                                        :style="selectedDay === cell.day ? { color: currentMonthTheme.color } : {}"
                                    >
                                        {{ cell.day }}
                                    </span>
                                    <div class="flex min-h-0 flex-1 flex-col gap-0.5 px-1 pb-1.5 pt-0.5">
                                        <div
                                            v-for="it in cell.items.slice(0, maxItemsVisibleInCell())"
                                            :key="it.id"
                                            class="flex min-w-0 items-center gap-0.5 rounded-md border px-1 py-0.5"
                                            :class="it.completed ? 'opacity-75' : ''"
                                            :style="itemChipStyle(it)"
                                            :title="it.title"
                                        >
                                            <ArrowPathIcon
                                                v-if="hasRecurrence(it)"
                                                class="h-2.5 w-2.5 shrink-0 opacity-80"
                                                aria-hidden="true"
                                            />
                                            <span
                                                class="min-w-0 truncate text-[10px] font-semibold leading-tight"
                                                :class="it.completed ? 'line-through opacity-80' : ''"
                                            >
                                                {{ it.title }}
                                            </span>
                                        </div>
                                        <span
                                            v-if="cell.items.length > maxItemsVisibleInCell()"
                                            class="px-0.5 text-[10px] font-semibold text-slate-500"
                                        >
                                            +{{ cell.items.length - maxItemsVisibleInCell() }} mais
                                        </span>
                                    </div>
                                </button>
                                <div v-else :class="['border-transparent', cellMinH]" />
                            </div>
                        </template>
                    </template>
                </div>
            </div>

            <!-- Painel detalhe -->
            <div
                v-if="!compact"
                class="border-t border-slate-200/80 bg-slate-50/40 lg:w-[min(380px,34%)] lg:border-l lg:border-t-0 lg:shrink-0"
                :class="rootPad"
            >
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    {{
                        selectedDayIso
                            ? new Date(selectedDayIso + 'T12:00:00').toLocaleDateString('pt-BR', {
                                  weekday: 'long',
                                  day: 'numeric',
                                  month: 'long',
                              })
                            : ''
                    }}
                </p>
                <div v-if="selectedDayProgress" class="mt-2 flex items-center justify-between gap-2 text-xs text-slate-600">
                    <span>{{ selectedDayProgress.done }} de {{ selectedDayProgress.total }} concluídos</span>
                    <span
                        class="h-1.5 flex-1 max-w-[8rem] overflow-hidden rounded-full bg-slate-200"
                        role="progressbar"
                        :aria-valuenow="selectedDayProgress.done"
                        :aria-valuemin="0"
                        :aria-valuemax="selectedDayProgress.total"
                    >
                        <span
                            class="block h-full rounded-full bg-emerald-500 transition-all"
                            :style="{ width: `${(selectedDayProgress.done / selectedDayProgress.total) * 100}%` }"
                        />
                    </span>
                </div>
                <button
                    v-if="editable"
                    type="button"
                    class="mt-2 text-xs font-semibold text-talents-700 hover:text-talents-800"
                    @click="openDayEditor"
                >
                    + Adicionar no dia
                </button>
                <template v-if="selectedDayItemsGrouped.length">
                    <div
                        v-for="group in selectedDayItemsGrouped"
                        :key="group.kind"
                        class="mt-4"
                    >
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                            {{ group.label }}
                            <span class="font-normal normal-case text-slate-400">({{ group.items.length }})</span>
                        </p>
                        <ul class="mt-2 space-y-3" aria-live="polite">
                            <li
                                v-for="it in group.items"
                                :key="it.id"
                                class="rounded-2xl border p-4 shadow-sm"
                                :class="itemShellClass(it)"
                            >
                                <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                                    <StrategicKindBadge :kind="it.kind" :label="kindLabel(it.kind)" />
                                    <button
                                        v-if="canToggleCompletion(it)"
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-semibold transition"
                                        :class="it.completed ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-white text-slate-600 hover:border-emerald-300 hover:text-emerald-700'"
                                        :disabled="completingIds.has(it.id)"
                                        @click.stop="toggleCompletion(it)"
                                    >
                                        <CheckCircleIcon class="h-4 w-4" aria-hidden="true" />
                                        {{ it.completed ? 'Concluído' : 'Marcar feito' }}
                                    </button>
                                    <div v-if="editable" class="flex items-center gap-2">
                                        <label class="inline-flex items-center gap-1 text-xs text-slate-500">
                                            <span>Data</span>
                                            <input
                                                type="date"
                                                :value="it.occurs_on"
                                                class="rounded-md border border-slate-200 px-1.5 py-0.5 text-xs text-slate-800"
                                                @change="updateItemDate(it, $event.target.value)"
                                            />
                                        </label>
                                        <Link
                                            v-if="editItemUrl(it)"
                                            :href="editItemUrl(it)"
                                            class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-800"
                                            title="Editar item"
                                        >
                                            <PencilSquareIcon class="h-4 w-4" aria-hidden="true" />
                                        </Link>
                                    </div>
                                </div>
                                <p class="font-semibold" :class="itemTextClass(it)">{{ it.title }}</p>
                                <p
                                    v-if="hasRecurrence(it)"
                                    class="mt-2 inline-flex items-center gap-1 text-xs text-slate-500"
                                >
                                    <ArrowPathIcon class="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
                                    Repete: {{ it.recurrence_label }}
                                </p>
                                <p v-if="it.description" class="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-slate-600">
                                    {{ it.description }}
                                </p>
                                <p v-if="it.list_title" class="mt-2 text-xs text-slate-500">Lista: {{ it.list_title }}</p>
                                <AttachmentList
                                    v-if="it.attachments?.length"
                                    class="mt-2"
                                    :attachments="it.attachments"
                                />
                                <p v-if="it.company?.name" class="mt-2 text-xs text-slate-500">
                                    Empresa: {{ it.company.name }}
                                </p>
                            </li>
                        </ul>
                    </div>
                </template>
                <p v-else class="mt-6 text-sm text-slate-500">Nenhum item neste dia.</p>
            </div>
            <div
                v-else-if="completionEnabled"
                class="border-t border-slate-200/80 bg-slate-50/40 px-3 py-3 sm:px-4"
            >
                <p class="text-xs font-semibold capitalize text-slate-700">
                    {{
                        selectedDayIso
                            ? new Date(selectedDayIso + 'T12:00:00').toLocaleDateString('pt-BR', {
                                  weekday: 'long',
                                  day: 'numeric',
                                  month: 'long',
                              })
                            : ''
                    }}
                </p>
                <p v-if="selectedDayProgress" class="mt-1 text-xs text-slate-600">
                    {{ selectedDayProgress.done }} de {{ selectedDayProgress.total }} concluídos
                </p>
                <template v-if="selectedDayItemsGrouped.length">
                    <div
                        v-for="group in selectedDayItemsGrouped"
                        :key="`compact-${group.kind}`"
                        class="mt-3"
                    >
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">{{ group.label }}</p>
                        <ul class="mt-1.5 space-y-2">
                            <li
                                v-for="it in group.items"
                                :key="it.id"
                                class="flex items-start justify-between gap-2 rounded-xl border border-slate-200/80 bg-white p-2.5"
                                :class="it.completed ? 'opacity-80' : ''"
                            >
                                <div class="min-w-0">
                                    <p class="text-sm font-medium" :class="itemTextClass(it)">{{ it.title }}</p>
                                    <p
                                        v-if="hasRecurrence(it)"
                                        class="mt-0.5 inline-flex items-center gap-1 text-[10px] text-slate-500"
                                    >
                                        <ArrowPathIcon class="h-3 w-3 shrink-0" aria-hidden="true" />
                                        {{ it.recurrence_label }}
                                    </p>
                                </div>
                                <button
                                    v-if="canToggleCompletion(it)"
                                    type="button"
                                    class="shrink-0 rounded-full border p-1.5 transition"
                                    :class="it.completed ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 text-slate-500 hover:border-emerald-300 hover:text-emerald-700'"
                                    :disabled="completingIds.has(it.id)"
                                    :title="it.completed ? 'Concluído' : 'Marcar feito'"
                                    @click.stop="toggleCompletion(it)"
                                >
                                    <CheckCircleIcon class="h-4 w-4" aria-hidden="true" />
                                </button>
                            </li>
                        </ul>
                    </div>
                </template>
                <p v-else class="mt-2 text-xs text-slate-500">Nenhum item neste dia.</p>
            </div>
            <div v-else class="border-t border-slate-200/80 px-3 py-2 sm:px-4">
                <p class="text-xs text-slate-600">
                    {{ selectedDayItems.length }} {{ selectedDayItems.length === 1 ? 'item' : 'itens' }} neste dia
                </p>
            </div>
        </div>

        <!-- List view -->
        <div v-else-if="currentView === 'list'" :class="rootPad">
            <div class="max-h-[min(70vh,720px)] overflow-y-auto rounded-2xl border border-slate-200/80">
                <template v-if="listRowsGrouped.length">
                    <template v-for="group in listRowsGrouped" :key="group.iso">
                        <div
                            class="sticky top-0 z-10 border-b border-slate-200/80 bg-slate-50/95 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500 backdrop-blur-sm"
                        >
                            {{ group.label }}
                        </div>
                        <ul class="divide-y divide-slate-100">
                            <li
                                v-for="it in group.items"
                                :key="it.id"
                                class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-start sm:justify-between"
                                :class="it.completed ? 'bg-slate-50/80' : ''"
                            >
                                <div class="min-w-0 flex-1">
                                    <div class="mb-1 flex flex-wrap items-center gap-2">
                                        <StrategicKindBadge :kind="it.kind" :label="kindLabel(it.kind)" />
                                        <label
                                            v-if="editable"
                                            class="inline-flex items-center gap-1 text-xs text-slate-500"
                                        >
                                            <input
                                                type="date"
                                                :value="it.occurs_on"
                                                class="rounded-md border border-slate-200 px-1.5 py-0.5 text-xs"
                                                @change="updateItemDate(it, $event.target.value)"
                                            />
                                        </label>
                                    </div>
                                    <p class="font-medium" :class="itemTextClass(it)">{{ it.title }}</p>
                                    <p v-if="it.description" class="mt-1 line-clamp-2 text-sm text-slate-600">
                                        {{ it.description }}
                                    </p>
                                    <p v-if="it.recurrence_label" class="mt-1 inline-flex items-center gap-1 text-xs text-slate-500">
                                        <ArrowPathIcon class="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
                                        {{ it.recurrence_label }}
                                    </p>
                                    <p v-if="it.list_title" class="mt-1 text-xs text-slate-500">{{ it.list_title }}</p>
                                    <AttachmentList
                                        v-if="it.attachments?.length"
                                        class="mt-1"
                                        :attachments="it.attachments"
                                        compact
                                        :link-prefix="''"
                                    />
                                    <p v-if="it.company?.name" class="mt-1 text-xs text-slate-500">{{ it.company.name }}</p>
                                </div>
                                <Link
                                    v-if="editable && editItemUrl(it)"
                                    :href="editItemUrl(it)"
                                    class="shrink-0 rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-800"
                                    title="Editar"
                                >
                                    <PencilSquareIcon class="h-5 w-5" aria-hidden="true" />
                                </Link>
                                <button
                                    v-else-if="canToggleCompletion(it)"
                                    type="button"
                                    class="shrink-0 rounded-full border px-3 py-1.5 text-xs font-semibold transition"
                                    :class="it.completed ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 text-slate-600 hover:border-emerald-300 hover:text-emerald-700'"
                                    :disabled="completingIds.has(it.id)"
                                    @click="toggleCompletion(it)"
                                >
                                    {{ it.completed ? 'Concluído' : 'Marcar feito' }}
                                </button>
                            </li>
                        </ul>
                    </template>
                </template>
                <p v-else class="px-4 py-10 text-center text-sm text-slate-500">Nenhum item neste mês.</p>
            </div>
        </div>

        <!-- Agenda -->
        <div v-else :class="rootPad">
            <div class="max-h-[min(70vh,720px)] overflow-y-auto space-y-6">
                <template v-if="agendaTimeline.length">
                    <div v-for="block in agendaTimeline" :key="block.iso" class="relative pl-6">
                        <span
                            class="absolute left-0 top-1.5 h-full w-px bg-slate-200"
                            aria-hidden="true"
                        />
                        <span
                            class="absolute left-0 top-2 h-2.5 w-2.5 -translate-x-[3px] rounded-full bg-talents-500"
                            aria-hidden="true"
                        />
                        <p class="text-sm font-semibold capitalize text-slate-800">{{ block.label }}</p>
                        <ul class="mt-3 space-y-3">
                            <li
                                v-for="it in block.items"
                                :key="it.id"
                                class="rounded-2xl border p-4 shadow-sm"
                                :class="itemShellClass(it)"
                            >
                                <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                                    <StrategicKindBadge :kind="it.kind" :label="kindLabel(it.kind)" />
                                    <button
                                        v-if="canToggleCompletion(it)"
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-semibold transition"
                                        :class="it.completed ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-white text-slate-600 hover:border-emerald-300 hover:text-emerald-700'"
                                        :disabled="completingIds.has(it.id)"
                                        @click="toggleCompletion(it)"
                                    >
                                        <CheckCircleIcon class="h-4 w-4" aria-hidden="true" />
                                        {{ it.completed ? 'Concluído' : 'Marcar feito' }}
                                    </button>
                                    <div v-if="editable" class="flex items-center gap-2">
                                        <input
                                            type="date"
                                            :value="it.occurs_on"
                                            class="rounded-md border border-slate-200 px-1.5 py-0.5 text-xs text-slate-800"
                                            @change="updateItemDate(it, $event.target.value)"
                                        />
                                        <Link
                                            v-if="editItemUrl(it)"
                                            :href="editItemUrl(it)"
                                            class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-800"
                                            title="Editar"
                                        >
                                            <PencilSquareIcon class="h-4 w-4" aria-hidden="true" />
                                        </Link>
                                    </div>
                                </div>
                                <p class="font-medium" :class="itemTextClass(it)">{{ it.title }}</p>
                                <p
                                    v-if="hasRecurrence(it)"
                                    class="mt-1 inline-flex items-center gap-1 text-xs text-slate-500"
                                >
                                    <ArrowPathIcon class="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
                                    {{ it.recurrence_label }}
                                </p>
                                <p v-if="it.description" class="mt-2 line-clamp-3 text-sm text-slate-600">
                                    {{ it.description }}
                                </p>
                                <AttachmentList
                                    v-if="it.attachments?.length"
                                    class="mt-2"
                                    :attachments="it.attachments"
                                />
                                <p v-if="it.company?.name" class="mt-1 text-xs text-slate-500">{{ it.company.name }}</p>
                                <p v-if="it.list_title" class="mt-1 text-xs text-slate-500">Lista: {{ it.list_title }}</p>
                            </li>
                        </ul>
                    </div>
                </template>
                <p v-else class="text-center text-sm text-slate-500">Nenhum item nos próximos dias.</p>
            </div>
        </div>
    </div>
</template>
