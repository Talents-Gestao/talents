<script setup>
import { computed, ref } from 'vue';
import { INITIAL_VISIBLE, SORT_OPTIONS, prepareItems } from '@/utils/nr1Items';

const props = defineProps({
    items: { type: Array, default: () => [] },
    respondents: { type: Number, default: 0 },
});

const FREQUENCY_LABELS = ['Nunca', 'Raramente', 'Às vezes', 'Frequentemente', 'Sempre'];
// Escala de uma cor só (frequência, não risco): do mais claro ao mais escuro.
const SEGMENT_CLASSES = ['bg-sky-100', 'bg-sky-200', 'bg-sky-400', 'bg-sky-600', 'bg-sky-800'];

const query = ref('');
const sectionId = ref('');
const sort = ref('order');
const showAll = ref(false);

const sections = computed(() => {
    const seen = new Map();
    props.items.forEach((item) => {
        if (!seen.has(item.section_id)) {
            seen.set(item.section_id, item.section_title);
        }
    });

    return [...seen].map(([id, title]) => ({ id, title }));
});

const view = computed(() =>
    prepareItems(props.items, {
        query: query.value,
        sectionId: sectionId.value === '' ? null : Number(sectionId.value),
        sort: sort.value,
        showAll: showAll.value,
    }),
);

const pct = (value) => (value === null || value === undefined ? '—' : `${String(value).replace('.', ',')}%`);
</script>

<template>
    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="nr1-items-title">
        <h2 id="nr1-items-title" class="text-base font-semibold text-talents-900">O que dizem as {{ items.length }} perguntas</h2>
        <p class="mt-1 text-xs text-gray-500">
            Cada cartão mostra a distribuição das respostas da campanha inteira. Os percentuais favoráveis respeitam o sentido da afirmação.
        </p>

        <div class="mt-4 grid gap-3 sm:grid-cols-3">
            <div>
                <label for="nr1-items-search" class="block text-xs font-semibold text-gray-600">Buscar pergunta</label>
                <input
                    id="nr1-items-search"
                    v-model="query"
                    type="search"
                    placeholder="Digite uma palavra"
                    class="mt-1 w-full rounded-md border-gray-300 text-sm"
                    @input="showAll = false"
                />
            </div>
            <div>
                <label for="nr1-items-section" class="block text-xs font-semibold text-gray-600">Dimensão</label>
                <select id="nr1-items-section" v-model="sectionId" class="mt-1 w-full rounded-md border-gray-300 text-sm" @change="showAll = false">
                    <option value="">Todas as dimensões</option>
                    <option v-for="section in sections" :key="section.id" :value="section.id">{{ section.title }}</option>
                </select>
            </div>
            <div>
                <label for="nr1-items-sort" class="block text-xs font-semibold text-gray-600">Ordenar por</label>
                <select id="nr1-items-sort" v-model="sort" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                    <option v-for="option in SORT_OPTIONS" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
            </div>
        </div>

        <p class="mt-3 text-xs text-gray-500" role="status" aria-live="polite">
            Mostrando {{ view.shown.length }} de {{ view.total }} {{ view.total === 1 ? 'pergunta' : 'perguntas' }}.
        </p>

        <p v-if="!items.length" class="mt-4 text-sm text-gray-600">Ainda não há perguntas com respostas para exibir.</p>
        <p v-else-if="!view.total" class="mt-4 text-sm text-gray-600">Nenhuma pergunta corresponde à busca ou ao filtro.</p>

        <ul class="mt-4 grid gap-4 lg:grid-cols-2">
            <li v-for="item in view.shown" :key="item.id" class="rounded-lg border border-gray-100 p-4">
                <p class="text-xs text-gray-500">Pergunta {{ item.position }} · {{ item.section_title }}</p>
                <p class="mt-1 text-sm font-medium text-gray-900">{{ item.body }}</p>

                <div
                    v-if="item.n > 0"
                    class="mt-3 flex h-3 w-full overflow-hidden rounded-full bg-gray-100"
                    role="img"
                    :aria-label="`Distribuição: ${FREQUENCY_LABELS.map((label, index) => `${label} ${pct(item.distribution[index + 1])}`).join(', ')}`"
                >
                    <div
                        v-for="(label, index) in FREQUENCY_LABELS"
                        :key="label"
                        :class="SEGMENT_CLASSES[index]"
                        :style="{ width: `${item.distribution[index + 1] ?? 0}%` }"
                    />
                </div>
                <dl v-if="item.n > 0" class="mt-2 grid grid-cols-5 gap-1 text-center text-[11px] text-gray-600">
                    <div v-for="(label, index) in FREQUENCY_LABELS" :key="label">
                        <dt>{{ label }}</dt>
                        <dd class="font-semibold text-gray-900">{{ pct(item.distribution[index + 1]) }}</dd>
                    </div>
                </dl>
                <p v-else class="mt-3 text-sm text-gray-500">Sem respostas para esta pergunta.</p>

                <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs">
                    <span class="text-gray-700">
                        Favorável <strong class="text-gray-900">{{ item.favorable_pct_label ?? '—' }}</strong>
                        · Desfavorável <strong class="text-gray-900">{{ item.unfavorable_pct_label ?? '—' }}</strong>
                    </span>
                    <span class="text-gray-500">N = {{ item.n }}</span>
                </div>
                <p
                    v-if="item.quality_alert"
                    class="mt-2 rounded-md border border-amber-200 bg-amber-50 px-2 py-1 text-xs text-amber-800"
                >
                    Alerta de qualidade: N diferente dos {{ respondents }} respondentes.
                </p>
            </li>
        </ul>

        <div v-if="view.hiddenCount > 0 || (showAll && view.total > INITIAL_VISIBLE)" class="mt-4 text-center">
            <button
                type="button"
                class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-800"
                :aria-expanded="showAll"
                @click="showAll = !showAll"
            >
                {{ showAll ? `Mostrar só as ${INITIAL_VISIBLE} primeiras` : `Ver todas as ${view.total} perguntas` }}
            </button>
        </div>
    </section>
</template>
