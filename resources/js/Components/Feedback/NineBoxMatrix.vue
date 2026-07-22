<script setup>
import { computed } from 'vue';

const props = defineProps({
    nineBox: {
        type: Object,
        required: true,
    },
});

/** Metadados visuais alinhados ao modelo 9Box (comportamento × desempenho). */
const CELL_META = {
    'abaixo|abaixo': {
        number: 1,
        title: 'Insuficientes',
        className: 'border-[#f3c1c1] bg-[#f8d7d7] text-[#7f1d1d]',
    },
    'abaixo|dentro': {
        number: 2,
        title: 'Trabalhar valores',
        className: 'border-[#f5d9b8] bg-[#fbe8d2] text-[#9a3412]',
    },
    'abaixo|acima': {
        number: 4,
        title: 'Desenvolver comportamento',
        className: 'border-[#f3c9b0] bg-[#f8dccb] text-[#9a3412]',
    },
    'dentro|abaixo': {
        number: 3,
        title: 'Verificar situação',
        className: 'border-[#f3d0c4] bg-[#f9e4dc] text-[#9a3412]',
    },
    'dentro|dentro': {
        number: 6,
        title: 'Aprimorar comportamento e técnica',
        className: 'border-[#f0c08a] bg-[#f7d7a8] text-[#9a3412]',
    },
    'dentro|acima': {
        number: 7,
        title: 'Aprimorar comportamento',
        className: 'border-[#e8d58a] bg-[#f3e7a8] text-[#854d0e]',
    },
    'acima|abaixo': {
        number: 5,
        title: 'Desenvolver técnica',
        className: 'border-[#f0c9a8] bg-[#f7dac4] text-[#9a3412]',
    },
    'acima|dentro': {
        number: 8,
        title: 'Aprimorar técnica',
        className: 'border-[#cfe3a8] bg-[#e2efc2] text-[#3f6212]',
    },
    'acima|acima': {
        number: 9,
        title: 'Destaques',
        className: 'border-[#a8d48a] bg-[#c6ebad] text-[#14532d]',
    },
};

const LEVEL_LABELS = {
    abaixo: 'Abaixo da expectativa',
    dentro: 'Dentro da expectativa',
    acima: 'Acima da expectativa',
};

const xLevels = computed(() =>
    (props.nineBox?.x_axis?.levels ?? []).map((level) => ({
        ...level,
        label: LEVEL_LABELS[level.value] ?? level.label,
    })),
);

const yLevelsTopFirst = computed(() =>
    [...(props.nineBox?.y_axis?.levels ?? [])].reverse().map((level) => ({
        ...level,
        label: LEVEL_LABELS[level.value] ?? level.label,
    })),
);

const cellMap = computed(() => {
    const map = {};
    for (const cell of props.nineBox?.cells ?? []) {
        map[`${cell.y}|${cell.x}`] = cell;
    }
    return map;
});

function cellAt(y, x) {
    return cellMap.value[`${y}|${x}`] ?? { count: 0, employees: [] };
}

function metaAt(y, x) {
    return (
        CELL_META[`${y}|${x}`] ?? {
            number: null,
            title: 'Quadrante',
            className: 'border-slate-200 bg-slate-50 text-slate-600',
        }
    );
}
const flatCells = computed(() => {
    const rows = [];
    for (const yLevel of yLevelsTopFirst.value) {
        for (const xLevel of xLevels.value) {
            const cell = cellAt(yLevel.value, xLevel.value);
            const meta = metaAt(yLevel.value, xLevel.value);
            rows.push({
                key: `${yLevel.value}-${xLevel.value}`,
                behavior: yLevel.label,
                performance: xLevel.label,
                title: meta.title,
                number: meta.number,
                className: meta.className,
                count: cell.count ?? 0,
                employees: cell.employees ?? [],
            });
        }
    }
    return rows;
});
</script>

<template>
    <div>
        <p class="mb-5 max-w-3xl text-sm leading-relaxed text-slate-600">
            Cruze a avaliação de comportamento com a de desempenho para identificar o quadrante e os
            direcionamentos recomendados para o desenvolvimento.
        </p>

        <!-- Mobile: lista compacta por quadrante -->
        <ul class="space-y-2 md:hidden" aria-label="Matriz 9Box em lista">
            <li
                v-for="row in flatCells"
                :key="row.key"
                class="rounded-xl border p-3 shadow-sm"
                :class="row.className"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-[11px] font-bold uppercase tracking-wide">
                            {{ row.number }}. {{ row.title }}
                        </p>
                        <p class="mt-1 text-[10px] font-medium uppercase leading-snug opacity-80">
                            {{ row.behavior }} × {{ row.performance }}
                        </p>
                    </div>
                    <p class="shrink-0 text-xl font-semibold tabular-nums">{{ row.count }}</p>
                </div>
                <ul v-if="row.employees.length" class="mt-2 space-y-0.5">
                    <li
                        v-for="name in row.employees.slice(0, 4)"
                        :key="name"
                        class="truncate text-[11px] font-medium leading-tight opacity-90"
                    >
                        {{ name }}
                    </li>
                    <li v-if="row.employees.length > 4" class="text-[11px] font-medium opacity-70">
                        +{{ row.employees.length - 4 }}
                    </li>
                </ul>
                <p v-else class="mt-1 text-[11px] font-medium opacity-55">Sem avaliações</p>
            </li>
        </ul>
        <p class="mt-3 text-center text-xs text-slate-500 md:hidden">
            {{ nineBox.total ?? 0 }}
            {{ (nineBox.total ?? 0) === 1 ? 'avaliação mapeada' : 'avaliações mapeadas' }}
        </p>

        <!-- Desktop/tablet: matriz completa com scroll horizontal se necessário -->
        <div class="hidden overflow-x-auto pb-1 md:block">
            <div class="min-w-[40rem] lg:min-w-[44rem]">

                <!--
                  Layout do eixo Y (como no exemplo amarelo):
                  [COMPORTAMENTO] | [linha/seta] | [níveis] | [grade]
                -->
                <div
                    class="grid items-stretch gap-x-2 gap-y-2.5"
                    style="grid-template-columns: 2.75rem 0.75rem 8.75rem minmax(0, 1fr)"
                >
                    <!-- Cabeçalho X -->
                    <div class="col-span-3" />
                    <div class="grid grid-cols-3 gap-2 sm:gap-2.5">
                        <div
                            v-for="level in xLevels"
                            :key="'x-' + level.value"
                            class="px-1 text-center text-[10px] font-semibold uppercase leading-tight tracking-wide text-slate-600 sm:text-[11px]"
                        >
                            {{ level.label }}
                        </div>
                    </div>

                    <!-- Título Y (ocupa as 3 linhas da matriz) -->
                    <div class="row-span-3 flex items-center justify-center">
                        <span
                            class="origin-center -rotate-90 whitespace-nowrap text-xs font-bold uppercase tracking-[0.2em] text-slate-900"
                        >
                            Comportamento
                        </span>
                    </div>

                    <!-- Linha/seta Y (ocupa as 3 linhas da matriz) -->
                    <div class="relative row-span-3">
                        <div class="absolute inset-y-0 left-1/2 w-px -translate-x-1/2 bg-slate-800" aria-hidden="true" />
                        <div
                            class="absolute -top-1 left-1/2 h-0 w-0 -translate-x-1/2 border-x-[5px] border-b-[8px] border-x-transparent border-b-slate-800"
                            aria-hidden="true"
                        />
                    </div>

                    <!-- Linhas da matriz: nível Y + células -->
                    <template v-for="yLevel in yLevelsTopFirst" :key="'row-' + yLevel.value">
                        <div
                            class="flex items-center justify-end pr-1 text-right text-[10px] font-semibold uppercase leading-tight tracking-wide text-slate-600 sm:text-[11px]"
                        >
                            {{ yLevel.label }}
                        </div>

                        <div class="grid grid-cols-3 gap-2 sm:gap-2.5">
                            <div
                                v-for="xLevel in xLevels"
                                :key="yLevel.value + '-' + xLevel.value"
                                class="relative flex min-h-[7.25rem] flex-col rounded-2xl border p-3 shadow-sm sm:min-h-[8rem]"
                                :class="metaAt(yLevel.value, xLevel.value).className"
                                :title="
                                    cellAt(yLevel.value, xLevel.value).employees?.length
                                        ? cellAt(yLevel.value, xLevel.value).employees.join(', ')
                                        : 'Sem avaliações neste quadrante'
                                "
                            >
                                <p class="pr-6 text-[11px] font-bold uppercase leading-snug tracking-wide">
                                    {{ metaAt(yLevel.value, xLevel.value).title }}
                                </p>

                                <div class="mt-2 flex-1">
                                    <p
                                        v-if="cellAt(yLevel.value, xLevel.value).count > 0"
                                        class="text-2xl font-semibold tabular-nums leading-none"
                                    >
                                        {{ cellAt(yLevel.value, xLevel.value).count }}
                                    </p>
                                    <ul
                                        v-if="cellAt(yLevel.value, xLevel.value).employees?.length"
                                        class="mt-2 space-y-0.5"
                                    >
                                        <li
                                            v-for="name in cellAt(yLevel.value, xLevel.value).employees.slice(0, 3)"
                                            :key="name"
                                            class="truncate text-[11px] font-medium leading-tight opacity-90"
                                        >
                                            {{ name }}
                                        </li>
                                        <li
                                            v-if="cellAt(yLevel.value, xLevel.value).employees.length > 3"
                                            class="text-[11px] font-medium opacity-70"
                                        >
                                            +{{ cellAt(yLevel.value, xLevel.value).employees.length - 3 }}
                                        </li>
                                    </ul>
                                    <p v-else class="mt-1 text-[11px] font-medium opacity-55">Sem avaliações</p>
                                </div>

                                <span
                                    class="absolute bottom-2 right-3 text-sm font-bold tabular-nums opacity-80"
                                    aria-hidden="true"
                                >
                                    {{ metaAt(yLevel.value, xLevel.value).number }}
                                </span>
                            </div>
                        </div>
                    </template>

                    <!-- Rodapé X -->
                    <div class="col-span-3" />
                    <div class="relative pt-1">
                        <div class="h-px w-full bg-slate-800" aria-hidden="true" />
                        <div
                            class="absolute top-0 right-0 h-0 w-0 -translate-y-[3px] border-y-[5px] border-l-[8px] border-y-transparent border-l-slate-800"
                            aria-hidden="true"
                        />
                        <p class="mt-3 text-center text-xs font-bold uppercase tracking-[0.18em] text-slate-900">
                            Desempenho
                        </p>
                        <p class="mt-1 text-center text-xs text-slate-500">
                            {{ nineBox.total ?? 0 }}
                            {{ (nineBox.total ?? 0) === 1 ? 'avaliação mapeada' : 'avaliações mapeadas' }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
