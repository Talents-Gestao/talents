<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    year: { type: Number, required: true },
    month: { type: Number, required: true },
    items: { type: Array, default: () => [] },
    kindLabels: { type: Object, default: () => ({}) },
    /** URL da imagem do painel esquerdo (ex.: /images/calendar-hero.jpg); vazio = só gradiente */
    heroImageUrl: { type: String, default: '' },
    /** Widget do painel: tipografia e altura reduzidas */
    compact: { type: Boolean, default: false },
});

const emit = defineEmits(['navigate-month', 'go-today']);

const selectedDay = ref(1);

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
            items: itemsByDay.value[iso] ?? [],
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

const heroMonthYear = computed(() => {
    const d = new Date(props.year, props.month - 1, 1);
    const s = d.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' });
    return s.replace(/^\w/, (c) => c.toUpperCase()).toUpperCase();
});

const monthTitleCapitalized = computed(() => {
    const d = new Date(props.year, props.month - 1, 1);
    return d.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' });
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

/** Eventos do dia atualmente selecionado no hero */
const selectedDayItems = computed(() => {
    const iso = `${props.year}-${String(props.month).padStart(2, '0')}-${String(selectedDay.value).padStart(2, '0')}`;
    return itemsByDay.value[iso] ?? [];
});

function itemsTitle(cell) {
    if (!cell.items?.length) return '';
    return cell.items.map((it) => `${kindLabel(it.kind)}: ${it.title}`).join('\n');
}

function onPickDay(cell) {
    if (cell.day) {
        selectedDay.value = cell.day;
    }
}

function onPrev() {
    emit('navigate-month', -1);
}

function onNext() {
    emit('navigate-month', 1);
}

function onGoToday() {
    emit('go-today');
}

const heroBgStyle = computed(() => {
    if (!props.heroImageUrl) {
        return {};
    }
    return {
        backgroundImage: `url('${props.heroImageUrl}')`,
    };
});

const rootMinHeight = computed(() =>
    props.compact ? 'min-h-0' : 'min-h-[min(720px,85vh)]',
);

const dayNumClass = computed(() =>
    props.compact ? 'text-5xl sm:text-6xl' : 'text-7xl sm:text-8xl md:text-9xl',
);

const gridDayClass = computed(() =>
    props.compact ? 'text-xs' : 'text-lg sm:text-xl md:text-2xl',
);

const headerDayClass = computed(() =>
    props.compact ? 'text-[10px] sm:text-xs' : 'text-xs sm:text-sm',
);

/** No modo compacto, linhas de altura fixa para o mês inteiro caber sem scroll */
const gridCellWrapperClass = computed(() =>
    props.compact
        ? 'flex h-8 items-center justify-center p-px sm:h-9'
        : 'flex aspect-square items-center justify-center p-0.5 sm:p-1',
);

const gridButtonSizeClass = computed(() =>
    props.compact
        ? 'h-7 w-7 max-h-7 max-w-7 text-xs'
        : 'h-full w-full max-h-14 max-w-14 sm:max-h-16 sm:max-w-16',
);
</script>

<template>
    <div
        class="flex flex-col overflow-hidden rounded-2xl bg-white shadow-lg ring-1 ring-black/5 lg:flex-row"
        :class="rootMinHeight"
    >
        <!-- Hero -->
        <div
            class="relative flex min-h-[200px] w-full flex-col justify-between self-stretch p-6 text-white lg:w-[35%] lg:min-h-0 lg:rounded-l-2xl lg:rounded-r-none"
        >
            <div
                class="absolute inset-0 bg-gradient-to-br from-slate-950/85 via-slate-900/75 to-slate-800/90"
                aria-hidden="true"
            />
            <div
                v-if="heroImageUrl"
                class="absolute inset-0 bg-cover bg-center"
                :style="heroBgStyle"
                aria-hidden="true"
            />
            <div class="relative z-10 flex justify-end">
                <button
                    type="button"
                    class="rounded-md border border-white/50 bg-white/10 px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-white backdrop-blur-sm transition hover:bg-white/20"
                    aria-label="Ir para o dia de hoje"
                    @click="onGoToday"
                >
                    Hoje
                </button>
            </div>
            <div
                class="relative z-10 flex min-h-0 flex-1 flex-col items-center justify-center px-2 text-center sm:px-3"
            >
                <div class="flex w-full max-w-[16rem] flex-col items-center">
                    <p class="text-xs font-medium uppercase tracking-[0.2em] text-white/90 sm:text-sm">
                        {{ heroMonthYear }}
                    </p>
                    <p :class="['mt-2 font-bold tabular-nums leading-none tracking-tight', dayNumClass]">
                        {{ selectedDay }}
                    </p>

                    <ul
                        v-if="selectedDayItems.length"
                        class="mt-4 w-full space-y-2 text-left"
                        :class="compact ? 'max-h-28 overflow-y-auto pr-0.5' : 'max-h-48 overflow-y-auto sm:max-h-56'"
                        aria-live="polite"
                    >
                        <li
                            v-for="it in selectedDayItems"
                            :key="it.id"
                            class="rounded-lg border border-white/20 bg-black/25 px-2.5 py-2 text-white shadow-sm backdrop-blur-sm"
                            :class="compact ? 'py-1.5' : ''"
                        >
                            <span
                                class="block text-[10px] font-semibold uppercase tracking-wide text-white/75"
                                :class="it.kind === 'rito' ? 'text-violet-200' : 'text-sky-200'"
                            >
                                {{ kindLabel(it.kind) }}
                            </span>
                            <span
                                class="mt-0.5 block font-medium leading-snug text-white"
                                :class="compact ? 'text-[11px] line-clamp-2' : 'text-xs sm:text-sm line-clamp-4'"
                            >
                                {{ it.title }}
                            </span>
                            <p
                                v-if="it.description && !compact"
                                class="mt-1 text-[11px] leading-relaxed text-white/70 line-clamp-3"
                            >
                                {{ it.description }}
                            </p>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="relative z-10 h-3 shrink-0 sm:h-4" />
        </div>

        <!-- Grid -->
        <div
            class="flex min-h-0 flex-1 flex-col bg-white lg:rounded-r-2xl"
            :class="compact ? 'overflow-visible p-3 sm:p-4' : 'overflow-y-auto p-4 sm:p-6'"
        >
            <div class="flex items-center justify-between gap-2" :class="compact ? 'mb-2' : 'mb-4'">
                <button
                    type="button"
                    class="rounded-lg p-2 text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700"
                    aria-label="Mês anterior"
                    @click="onPrev"
                >
                    <span class="text-xl font-light" aria-hidden="true">‹</span>
                </button>
                <span
                    class="truncate text-center text-sm font-medium capitalize text-zinc-500 sm:text-base"
                    :title="monthTitleCapitalized"
                >
                    {{ monthTitleCapitalized }}
                </span>
                <button
                    type="button"
                    class="rounded-lg p-2 text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700"
                    aria-label="Próximo mês"
                    @click="onNext"
                >
                    <span class="text-xl font-light" aria-hidden="true">›</span>
                </button>
            </div>

            <div
                role="grid"
                class="grid grid-cols-7"
                :class="compact ? 'gap-x-0.5 gap-y-0' : 'gap-x-1 gap-y-1'"
            >
                <div
                    v-for="w in weekdayLabels"
                    :key="w"
                    class="text-center font-medium text-zinc-500"
                    :class="[headerDayClass, compact ? 'pb-1' : 'pb-2']"
                    role="columnheader"
                >
                    {{ w }}
                </div>
                <template v-for="(row, ri) in weeks" :key="ri">
                    <template v-for="(cell, ci) in row" :key="`${ri}-${ci}`">
                        <div :class="gridCellWrapperClass" role="gridcell">
                            <button
                                v-if="cell.day"
                                type="button"
                                class="relative flex shrink-0 items-center justify-center rounded-full font-medium text-zinc-700 transition"
                                :class="[
                                    gridButtonSizeClass,
                                    gridDayClass,
                                    selectedDay === cell.day
                                        ? 'bg-fuchsia-600 text-white shadow-md shadow-fuchsia-600/30'
                                        : 'hover:bg-zinc-100',
                                    cell.isToday && selectedDay !== cell.day ? 'ring-2 ring-fuchsia-300/80' : '',
                                ]"
                                :title="itemsTitle(cell) || undefined"
                                @click="onPickDay(cell)"
                            >
                                {{ cell.day }}
                                <span
                                    v-if="cell.items?.length"
                                    class="absolute left-1/2 -translate-x-1/2 rounded-full bg-fuchsia-500"
                                    :class="[
                                        compact ? 'bottom-0 h-1 w-1' : 'bottom-0.5 h-1.5 w-1.5',
                                        selectedDay === cell.day ? 'bg-white' : '',
                                    ]"
                                    aria-hidden="true"
                                />
                            </button>
                        </div>
                    </template>
                </template>
            </div>
        </div>
    </div>
</template>
