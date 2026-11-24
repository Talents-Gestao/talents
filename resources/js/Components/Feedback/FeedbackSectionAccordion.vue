<script setup>
import { ChevronDownIcon } from '@heroicons/vue/24/outline';
import { computed, onMounted, ref, useSlots } from 'vue';

const props = defineProps({
    title: { type: String, required: true },
    /** Ícone de linha (componente Heroicons, como na barra lateral). */
    icon: { type: [Object, Function], default: null },
    description: { type: String, default: '' },
    defaultOpen: { type: Boolean, default: false },
    meta: { type: String, default: '' },
    collapsible: { type: Boolean, default: true },
    /** Estado da seção em texto («Não iniciada», «Em andamento 2/5», «Concluída»); com `progress`, vem com barra fina. */
    status: { type: String, default: '' },
    /** { answered, total } para a barra de progresso. */
    progress: { type: Object, default: null },
    /** Destaque da primeira seção incompleta. */
    highlight: { type: Boolean, default: false },
});

const slots = useSlots();
const detailsRef = ref(null);

const hasSlotContent = computed(() => (slots.default?.() ?? []).length > 0);

// Não usar :open no template — o Vue reaplicava o atributo e impedia abrir/fechar.
onMounted(() => {
    if (props.defaultOpen && detailsRef.value) {
        detailsRef.value.open = true;
    }
});

const percent = computed(() => (props.progress?.total ? Math.round((props.progress.answered / props.progress.total) * 100) : 0));

/** Abre a seção (ex.: «Continuar preenchimento») e devolve o elemento para rolar até ele. */
function open() {
    if (detailsRef.value) detailsRef.value.open = true;
    return detailsRef.value;
}
defineExpose({ open });
</script>

<template>
    <details
        v-if="collapsible"
        ref="detailsRef"
        class="group overflow-hidden rounded-2xl border bg-white shadow-sm open:shadow-md"
        :class="highlight ? 'border-talents-300 ring-1 ring-talents-200' : 'border-slate-200/80 open:border-talents-200/80'"
    >
        <summary
            class="flex cursor-pointer list-none items-start justify-between gap-3 bg-gradient-to-r from-talents-50/60 to-white px-5 py-4 transition hover:from-talents-50/90 marker:content-none [&::-webkit-details-marker]:hidden"
        >
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <component :is="icon" v-if="icon" class="h-5 w-5 shrink-0 text-talents-600" aria-hidden="true" />
                    <h3 class="min-w-0 text-pretty font-semibold text-talents-900">{{ title }}</h3>
                    <span
                        v-if="meta"
                        class="shrink-0 whitespace-nowrap rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600"
                    >
                        {{ meta }}
                    </span>
                </div>
                <div v-if="status" class="mt-2 flex items-center gap-3" data-feedback-section-status>
                    <span class="shrink-0 text-[13px] font-medium" :class="progress && progress.total && progress.answered >= progress.total ? 'text-emerald-800' : 'text-slate-700'">{{ status }}</span>
                    <span
                        v-if="progress && progress.total"
                        class="h-1.5 w-full max-w-40 overflow-hidden rounded-full bg-slate-200"
                        role="progressbar"
                        :aria-label="`Progresso de ${title}`"
                        aria-valuemin="0"
                        :aria-valuemax="progress.total"
                        :aria-valuenow="progress.answered"
                        :aria-valuetext="status"
                    >
                        <span class="block h-full rounded-full" :class="percent >= 100 ? 'bg-emerald-600' : 'bg-talents-600'" :style="{ width: `${percent}%` }" />
                    </span>
                </div>
                <p
                    v-if="description"
                    class="mt-1 line-clamp-2 whitespace-pre-wrap text-sm text-slate-600 group-open:line-clamp-none"
                >
                    {{ description }}
                </p>
            </div>
            <ChevronDownIcon
                class="pointer-events-none mt-0.5 h-5 w-5 shrink-0 text-talents-500 transition-transform duration-200 group-open:rotate-180"
            />
        </summary>

        <div class="border-t border-slate-100">
            <slot />
        </div>
    </details>

    <div
        v-else
        class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm"
    >
        <div class="bg-gradient-to-r from-talents-50/60 to-white px-5 py-4">
            <div class="flex flex-wrap items-center gap-2">
                <component :is="icon" v-if="icon" class="h-5 w-5 shrink-0 text-talents-600" aria-hidden="true" />
                <h3 class="min-w-0 text-pretty font-semibold text-talents-900">{{ title }}</h3>
            </div>
            <p v-if="description" class="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-slate-600">
                {{ description }}
            </p>
        </div>
        <div v-if="hasSlotContent" class="border-t border-slate-100">
            <slot />
        </div>
    </div>
</template>
