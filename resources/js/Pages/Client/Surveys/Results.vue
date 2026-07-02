<script setup>
import Nr1SurveyResultsPanel from '@/Components/Nr1SurveyResultsPanel.vue';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { marked } from 'marked';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

marked.setOptions({ breaks: true, gfm: true });

const props = defineProps({
    survey: Object,
    overall: Object,
    bySection: Array,
    deptOveralls: Array,
    deptSectionsByDepartment: Array,
    insights: Array,
    questionDistributions: Array,
    departmentParticipation: Array,
    questionDistributionsByDepartment: Array,
    aiEnabled: { type: Boolean, default: false },
    aiAnalysis: { type: Object, default: null },
    aiAnalysisPending: { type: Boolean, default: false },
    riskScenarioLabel: { type: String, default: null },
});

const recalculate = () => {
    router.post(route('client.surveys.recalculate', props.survey.id));
};

/** Mia — typewriter + markdown */
const displayedContent = ref('');
const miaTyping = ref(false);
let miaTypeTimer = null;

const clearMiaTypewriter = () => {
    if (miaTypeTimer) {
        clearInterval(miaTypeTimer);
        miaTypeTimer = null;
    }
    miaTyping.value = false;
};

const startMiaTypewriter = (fullText) => {
    clearMiaTypewriter();
    displayedContent.value = '';
    if (!fullText) {
        return;
    }
    miaTyping.value = true;
    let i = 0;
    const chunk = 20;
    const delay = 30;
    miaTypeTimer = setInterval(() => {
        i = Math.min(i + chunk, fullText.length);
        displayedContent.value = fullText.slice(0, i);
        if (i >= fullText.length) {
            clearMiaTypewriter();
        }
    }, delay);
};

const miaRenderedHtml = computed(() => {
    try {
        return marked.parse(displayedContent.value || '');
    } catch {
        return '';
    }
});

const requestAiAnalysis = () => {
    clearMiaTypewriter();
    displayedContent.value = '';
    router.post(route('client.surveys.ai-analysis', props.survey.id));
};

/** null = ainda não definido; false = já vimos página sem análise; true = já há/houve análise em cache */
const initialHadContent = ref(null);

watch(
    () => ({
        pending: props.aiAnalysisPending,
        content: props.aiAnalysis?.content ?? null,
    }),
    ({ pending, content }) => {
        if (pending) {
            clearMiaTypewriter();
            displayedContent.value = '';
            return;
        }
        if (!content) {
            clearMiaTypewriter();
            displayedContent.value = '';
            if (initialHadContent.value === null) {
                initialHadContent.value = false;
            }
            return;
        }
        if (initialHadContent.value === null) {
            initialHadContent.value = true;
            displayedContent.value = content;
            clearMiaTypewriter();
            return;
        }
        if (initialHadContent.value === false) {
            startMiaTypewriter(content);
            initialHadContent.value = true;
            return;
        }
        startMiaTypewriter(content);
    },
    { immediate: true },
);

watch(
    () => props.survey?.id,
    () => {
        initialHadContent.value = null;
        displayedContent.value = '';
        clearMiaTypewriter();
    },
);

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
    clearMiaTypewriter();
});
</script>

<template>
    <Head :title="`Resultados - ${survey.title}`" />

    <ClientLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <h2 class="text-xl font-semibold leading-tight text-talents-900">Resultados</h2>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="rounded-md border border-gray-300 px-3 py-1 text-sm" @click="recalculate">Recalcular</button>
                    <span
                        v-if="overall?.risk_level"
                        class="inline-flex items-center rounded-md border border-gray-200 bg-gray-50 px-3 py-1 text-xs font-medium text-gray-800"
                    >
                        {{ riskScenarioLabel }}
                    </span>
                    <a
                        :href="route('client.surveys.reports.executive', survey.id)"
                        class="rounded-md bg-talents-700 px-3 py-1 text-sm font-semibold text-white"
                        target="_blank"
                    >
                        Relatório executivo
                    </a>
                    <a
                        :href="route('client.surveys.reports.action-plan', survey.id)"
                        class="rounded-md border border-talents-300 px-3 py-1 text-sm font-semibold text-talents-900"
                        target="_blank"
                    >
                        Plano de ação
                    </a>
                    <a
                        :href="route('client.surveys.reports.referral', survey.id)"
                        class="rounded-md border border-talents-300 px-3 py-1 text-sm font-semibold text-talents-900"
                        target="_blank"
                    >
                        Encaminhamento técnico
                    </a>
                    <a
                        :href="route('client.surveys.reports.technical', survey.id)"
                        class="rounded-md border border-gray-300 px-3 py-1 text-sm font-semibold text-gray-800"
                        target="_blank"
                    >
                        Dados técnicos (RH)
                    </a>
                    <a
                        :href="route('client.surveys.export.json', survey.id)"
                        class="rounded-md border border-gray-300 px-3 py-1 text-sm font-semibold text-gray-800"
                        target="_blank"
                    >
                        Exportar JSON (BI)
                    </a>
                    <a
                        :href="route('client.surveys.export.csv', survey.id)"
                        class="rounded-md border border-gray-300 px-3 py-1 text-sm font-semibold text-gray-800"
                        target="_blank"
                    >
                        Exportar CSV
                    </a>
                    <Link :href="route('client.surveys.show', survey.id)" class="text-sm text-talents-700 hover:underline">Voltar</Link>
                </div>
            </div>
        </template>

        <div
            v-if="$page.props.flash?.success"
            class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
        >
            {{ $page.props.flash.success }}
        </div>
        <div
            v-if="$page.props.flash?.error"
            class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900"
        >
            {{ $page.props.flash.error }}
        </div>
        <div
            v-if="$page.props.flash?.info"
            class="mb-4 rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900"
        >
            {{ $page.props.flash.info }}
        </div>

        <div
            v-if="aiEnabled && overall"
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
                            <span class="text-sm font-normal text-gray-500">Assistente NR-1</span>
                        </div>
                        <details class="group mt-2">
                            <summary
                                class="cursor-pointer list-none text-xs text-talents-700 hover:text-talents-900 [&::-webkit-details-marker]:hidden"
                            >
                                <span class="underline decoration-talents-300 decoration-dotted underline-offset-2 group-open:no-underline"
                                    >Sobre esta análise</span
                                >
                            </summary>
                            <p class="mt-2 text-xs leading-relaxed text-gray-500">
                                A Mia avalia o cenário a partir dos dados agregados desta pesquisa; não substitui parecer com especialista
                                nem plano de ação personalizado. Não substitui avaliação por profissionais habilitados nem o cumprimento das
                                obrigações legais da empresa. Use como apoio à decisão e ao programa de gerenciamento de riscos psicossociais.
                            </p>
                        </details>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="rounded-full bg-talents-700 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-talents-800 disabled:opacity-50"
                                :disabled="aiAnalysisPending"
                                @click="requestAiAnalysis"
                            >
                                {{ aiAnalysis ? 'Pedir nova análise' : 'Perguntar a Mia' }}
                            </button>
                            <button
                                v-if="aiAnalysisPending"
                                type="button"
                                class="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm text-slate-700 hover:bg-slate-50"
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

                <div v-else-if="aiAnalysis" class="mt-6 border-t border-talents-100/80 pt-5">
                    <div class="flex items-end gap-1">
                        <div
                            class="mia-prose prose prose-sm min-w-0 flex-1 max-w-none text-gray-800 prose-headings:text-talents-900 prose-a:text-talents-700 prose-strong:text-talents-900"
                        >
                            <div class="mia-md" v-html="miaRenderedHtml" />
                        </div>
                        <span
                            v-if="miaTyping"
                            class="mia-cursor mb-1.5 inline-block h-4 w-0.5 shrink-0 bg-talents-600"
                            aria-hidden="true"
                        />
                    </div>
                </div>

                <p v-else class="mt-4 text-sm text-gray-500">Nenhuma análise ainda — peça à Mia quando quiser.</p>
            </div>
        </div>

        <div
            v-if="aiEnabled && overall"
            class="relative mb-8 overflow-hidden rounded-2xl border-2 border-talents-200 bg-gradient-to-br from-talents-50/90 via-white to-amber-50/40 p-6 shadow-md"
        >
            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,_var(--tw-gradient-stops))] from-talents-100/30 via-transparent to-transparent" />
            <div class="relative flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex min-w-0 flex-1 gap-4">
                    <div
                        class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl border border-talents-200 bg-white/90 text-talents-800 shadow-sm"
                        aria-hidden="true"
                    >
                        <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.75"
                                d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"
                            />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-lg font-semibold text-talents-900">Parecer técnico com especialista</h3>
                        <p class="mt-2 text-sm leading-relaxed text-gray-600">
                            Tenha acesso ao parecer técnico completo, com recomendações personalizadas de um especialista em saúde mental
                            organizacional e alinhamento à NR-1 — disponível em planos superiores.
                        </p>
                    </div>
                </div>
                <a
                    href="https://wa.me/5511952512752"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex shrink-0 items-center justify-center gap-2 rounded-full bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700"
                >
                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path
                            d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.123 1.035 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"
                        />
                    </svg>
                    Falar com especialista
                </a>
            </div>
        </div>

        <Nr1SurveyResultsPanel
            :survey="survey"
            :overall="overall"
            :by-section="bySection"
            :dept-overalls="deptOveralls"
            :dept-sections-by-department="deptSectionsByDepartment"
            :insights="insights"
            :question-distributions="questionDistributions"
            :department-participation="departmentParticipation"
            :question-distributions-by-department="questionDistributionsByDepartment"
        />
    </ClientLayout>
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
.mia-cursor {
    animation: mia-cursor-blink 1s step-end infinite;
}
@keyframes mia-cursor-blink {
    50% {
        opacity: 0;
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
