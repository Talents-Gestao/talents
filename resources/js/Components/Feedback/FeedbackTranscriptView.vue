<script setup>
import { filterRows, highlightParts, parseTranscript } from '@/utils/feedbackRecordingView';
import { MagnifyingGlassIcon } from '@heroicons/vue/24/outline';
import { computed, ref } from 'vue';

/**
 * Transcrição como conversa (só apresentação): uma fala por linha com tempo (mm:ss), quem fala (nome mapeado ou
 * «Falante N») e o texto; colunas no desktop, empilhado no celular; altura limitada com rolagem interna e busca
 * simples. O texto chega por prop (quem usa decide de onde: limpa da página, original sob demanda).
 */
const props = defineProps({
    text: { type: String, required: true },
    /** Rótulos com o papel confirmado: [{ label, role }]. */
    speakers: { type: Array, default: () => [] },
    people: { type: Object, default: () => ({}) },
    /** Prefixo único dos ids (mais de uma transcrição na página). */
    idPrefix: { type: String, required: true },
    label: { type: String, default: 'Transcrição' },
});

const query = ref('');
const rows = computed(() => parseTranscript(props.text, { speakers: props.speakers, people: props.people }));
const visible = computed(() => filterRows(rows.value, query.value));
const countText = computed(() => {
    const total = rows.value.length;
    const unit = (n) => (n === 1 ? 'fala' : 'falas');
    return query.value.trim() ? `${visible.value.length} de ${total} ${unit(total)}` : `${total} ${unit(total)}`;
});

const whoClass = (role) =>
    ({ leader: 'text-talents-800', employee: 'text-sky-800' })[role] ?? 'text-slate-800';
</script>

<template>
    <div class="space-y-2" data-feedback-transcript>
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div class="relative w-full sm:max-w-xs">
                <label :for="`${idPrefix}_search`" class="sr-only">Buscar em {{ label.toLowerCase() }}</label>
                <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" aria-hidden="true" />
                <input
                    :id="`${idPrefix}_search`"
                    v-model="query"
                    type="search"
                    placeholder="Buscar no texto"
                    autocomplete="off"
                    class="block min-h-[44px] w-full rounded-xl border-slate-300 pl-9 text-sm text-slate-900 placeholder:text-slate-500 focus:border-talents-500 focus:ring-2 focus:ring-talents-500"
                    :aria-controls="`${idPrefix}_list`"
                />
            </div>
            <p class="text-[13px] text-slate-700" aria-live="polite">{{ countText }}</p>
        </div>

        <ol
            :id="`${idPrefix}_list`"
            class="max-h-[28rem] divide-y divide-slate-100 overflow-y-auto rounded-xl border border-slate-200 bg-white focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500"
            tabindex="0"
            :aria-label="label"
        >
            <li
                v-for="(row, i) in visible"
                :key="i"
                class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-3 gap-y-0.5 px-3 py-2.5 sm:grid-cols-[3.5rem_10rem_minmax(0,1fr)] sm:gap-x-4"
            >
                <span class="font-mono text-[13px] leading-6 text-slate-600">
                    <template v-if="row.time"><span class="sr-only">Tempo </span>{{ row.time }}</template>
                </span>
                <span class="min-w-0 text-sm font-semibold leading-6" :class="whoClass(row.role)">
                    {{ row.who }}<span v-if="row.roleNoun" class="font-normal text-slate-600"><span class="sm:hidden" aria-hidden="true"> · </span><span class="sr-only sm:hidden">, </span><span class="sm:block">{{ row.roleNoun }}</span></span>
                </span>
                <p class="col-span-2 min-w-0 break-words text-sm leading-relaxed text-slate-900 sm:col-span-1">
                    <template v-for="(part, pi) in highlightParts(row.text, query)" :key="pi">
                        <mark v-if="part.match" class="rounded-sm bg-amber-200 text-slate-950">{{ part.text }}</mark>
                        <template v-else>{{ part.text }}</template>
                    </template>
                </p>
            </li>
            <li v-if="!visible.length" class="px-3 py-6 text-center text-sm text-slate-700">
                {{ query.trim() ? `Nada encontrado para «${query.trim()}».` : 'Sem falas para mostrar.' }}
            </li>
        </ol>
    </div>
</template>
