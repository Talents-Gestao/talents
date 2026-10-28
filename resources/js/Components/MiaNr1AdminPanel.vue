<script setup>
import { marked } from 'marked';
import { computed, onMounted, onUnmounted, watch } from 'vue';
import { router } from '@inertiajs/vue3';

marked.setOptions({ breaks: true, gfm: true });

const props = defineProps({
    generatePostUrl: { type: String, required: true },
    aiEnabled: { type: Boolean, default: false },
    aiAnalysis: { type: Object, default: null },
    aiAnalysisPending: { type: Boolean, default: false },
});

const miaHtml = computed(() => {
    const text = props.aiAnalysis?.content ?? '';
    if (!text) {
        return '';
    }
    try {
        return marked.parse(text);
    } catch {
        return '';
    }
});

const requestAiAnalysis = () => {
    router.post(props.generatePostUrl);
};

let pollTimer = null;

const startPollIfPending = () => {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
    if (props.aiAnalysisPending) {
        pollTimer = setInterval(() => {
            router.reload({
                only: ['aiAnalysis', 'aiAnalysisPending', 'flash'],
            });
        }, 5000);
    }
};

onMounted(() => {
    startPollIfPending();
});

watch(
    () => props.aiAnalysisPending,
    () => {
        startPollIfPending();
    },
);

onUnmounted(() => {
    if (pollTimer) {
        clearInterval(pollTimer);
    }
});
</script>

<template>
    <div
        v-if="aiEnabled"
        class="mb-8 overflow-hidden rounded-2xl border border-talents-100 bg-gradient-to-br from-white via-white to-talents-50/40 shadow-sm"
    >
        <div class="p-6">
            <div class="flex items-start gap-4">
                <div
                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-talents-100 text-talents-800 shadow-inner"
                    aria-hidden="true"
                >
                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="1.5"
                            d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z"
                        />
                    </svg>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0">
                        <h3 class="text-lg font-semibold text-talents-900">Mia</h3>
                        <span class="text-sm font-normal text-gray-500">Apoio ao plano de ação (admin)</span>
                    </div>
                    <p class="mt-2 text-sm text-gray-600">
                        A Mia resume o cenário dos dados agregados. Use como referência ao redigir o parecer técnico para a empresa — não
                        substitui o parecer do especialista.
                    </p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="rounded-full bg-talents-700 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-talents-800 disabled:opacity-50"
                            :disabled="aiAnalysisPending"
                            @click="requestAiAnalysis"
                        >
                            {{ aiAnalysis ? 'Gerar nova análise' : 'Gerar análise da Mia' }}
                        </button>
                        <button
                            v-if="aiAnalysisPending"
                            type="button"
                            class="rounded-full border border-gray-200 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                            @click="router.reload({ only: ['aiAnalysis', 'aiAnalysisPending', 'flash'] })"
                        >
                            Atualizar agora
                        </button>
                    </div>
                </div>
            </div>

            <div v-if="aiAnalysisPending" class="mt-6 flex items-center gap-3 rounded-xl border border-talents-100 bg-talents-50/50 px-4 py-3">
                <div class="mia-dots flex gap-1" aria-hidden="true">
                    <span class="mia-dot" />
                    <span class="mia-dot" />
                    <span class="mia-dot" />
                </div>
                <p class="text-sm text-talents-900">Mia está analisando os resultados…</p>
            </div>

            <div v-else-if="aiAnalysis" class="mia-prose prose prose-sm mt-6 border-t border-talents-100/80 pt-5 text-gray-800 prose-headings:text-talents-900 prose-strong:text-talents-900">
                <div class="mia-md" v-html="miaHtml" />
            </div>

            <p v-else class="mt-4 text-sm text-gray-500">Nenhuma análise ainda — gere a Mia para ver o resumo do cenário.</p>
        </div>
    </div>
</template>

<style scoped>
.mia-dot {
    display: inline-block;
    width: 6px;
    height: 6px;
    border-radius: 9999px;
    background-color: #632a7e;
    animation: mia-dot-bounce 1.2s ease-in-out infinite;
}
.mia-dot:nth-child(2) {
    animation-delay: 0.15s;
}
.mia-dot:nth-child(3) {
    animation-delay: 0.3s;
}
@keyframes mia-dot-bounce {
    0%,
    80%,
    100% {
        transform: translateY(0);
        opacity: 0.35;
    }
    40% {
        transform: translateY(-6px);
        opacity: 1;
    }
}
.mia-prose :deep(h2) {
    margin-top: 1.5em;
    margin-bottom: 0.5em;
    font-weight: 700;
}
.mia-prose :deep(h2:first-child) {
    margin-top: 0;
}
.mia-prose :deep(h3) {
    margin-top: 1.25em;
    margin-bottom: 0.4em;
    font-weight: 700;
}
.mia-prose :deep(p) {
    line-height: 1.65;
}
.mia-prose :deep(p + p) {
    margin-top: 1em;
}
.mia-prose :deep(ul),
.mia-prose :deep(ol) {
    margin-top: 0.75em;
    margin-bottom: 0.75em;
}
.mia-prose :deep(li + li) {
    margin-top: 0.35em;
}
</style>
