<script setup>
import FeedbackNotice from '@/Components/Feedback/FeedbackNotice.vue';
import { feedbackRoute } from '@/composables/useFeedbackRoutes';
import { formatDateTime } from '@/utils/dateOnly';
import { emptyStateText, shouldPoll, toneStyle } from '@/utils/feedbackEvolutionView';
import {
    ArrowPathIcon,
    ArrowTrendingUpIcon,
    ChartBarIcon,
    ExclamationTriangleIcon,
    MinusIcon,
    SparklesIcon,
} from '@heroicons/vue/24/outline';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

/**
 * Evolução do colaborador entre feedbacks. Camada 1 (sem IA): linha do tempo com tom e temas por período,
 * pontos de atenção, compromissos e temas de atenção que se repetem. Camada 2 (IA, sob demanda): resumo da
 * evolução com fontes por sessão. O servidor já limita tudo às sessões que o usuário vê.
 */
const props = defineProps({
    employeeId: { type: Number, required: true },
    evolution: { type: Object, required: true },
    narrative: { type: Object, default: null },
    ai: { type: Object, required: true },
});

const page = usePage();
const errors = computed(() => page.props.errors ?? {});
const busy = ref(false);

const toneIcon = { up: ArrowTrendingUpIcon, mixed: ChartBarIcon, attention: ExclamationTriangleIcon, neutral: MinusIcon };

function generate() {
    if (busy.value) return;
    router.post(feedbackRoute('employees.evolution', props.employeeId), {}, {
        preserveScroll: true,
        preserveState: true,
        onStart: () => (busy.value = true),
        onFinish: () => (busy.value = false),
    });
}

let timer = null;
const stop = () => {
    if (timer) clearInterval(timer);
    timer = null;
};
watch(
    () => shouldPoll(props.narrative),
    (now) => {
        stop();
        if (now) timer = setInterval(() => router.reload({ only: ['narrative'], preserveScroll: true }), 4000);
    },
    { immediate: true },
);
onBeforeUnmount(stop);

const processing = computed(() => props.narrative?.status === 'processing');
const report = computed(() => (props.narrative?.status === 'ready' ? props.narrative.report : null));
const canRequest = computed(() => props.ai.can_generate && props.ai.allowed && !props.ai.limit_reached && !processing.value);

const btnPrimary =
    'inline-flex min-h-[44px] items-center justify-center gap-2 rounded-xl bg-talents-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-talents-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-700';
const chip = 'inline-flex items-center rounded-full px-2.5 py-1 text-[13px] font-semibold ring-1';
</script>

<template>
    <div class="space-y-6" data-feedback-evolution>
        <!-- Estado vazio -->
        <div
            v-if="!evolution.ready"
            class="flex flex-col items-center gap-3 rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center"
            data-feedback-evolution-empty
        >
            <ChartBarIcon class="h-8 w-8 text-talents-600" aria-hidden="true" />
            <h3 class="text-base font-semibold text-slate-900">Ainda não dá para comparar períodos</h3>
            <p class="max-w-[60ch] text-sm leading-relaxed text-slate-700">{{ emptyStateText(evolution) }}</p>
        </div>

        <template v-else>
            <div v-if="evolution.warnings.length" class="space-y-2">
                <FeedbackNotice v-for="(w, i) in evolution.warnings" :key="i" tone="warning">{{ w }}</FeedbackNotice>
            </div>

            <!-- Camada 1: linha do tempo (sem IA) -->
            <section aria-labelledby="feedback_evolution_timeline" class="space-y-3">
                <div>
                    <h3 id="feedback_evolution_timeline" class="text-base font-semibold text-slate-900">Linha do tempo</h3>
                    <p class="text-sm text-slate-700">Dados dos relatórios de cada feedback, sem nova análise por IA.</p>
                </div>

                <ol class="space-y-3">
                    <li
                        v-for="item in evolution.timeline"
                        :key="item.session_id"
                        class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5"
                        :aria-label="`${item.ref}, ${item.period_label}`"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-[13px] font-semibold uppercase tracking-wide text-slate-600">{{ item.ref }} · {{ item.period_label }}</p>
                                <Link
                                    :href="feedbackRoute('sessions.show', item.session_id)"
                                    class="mt-0.5 inline-block rounded font-semibold text-talents-800 underline decoration-talents-300 underline-offset-2 hover:decoration-talents-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500"
                                >
                                    {{ item.title }}
                                </Link>
                                <p class="mt-0.5 text-[13px] text-slate-700">
                                    {{ item.status_label }}<span v-if="item.leader"> · Líder: {{ item.leader }}</span><span v-if="item.template"> · {{ item.template }}</span>
                                </p>
                            </div>
                            <span :class="[chip, toneStyle(item.tone).classes]" class="gap-1.5">
                                <component :is="toneIcon[toneStyle(item.tone).icon]" class="h-4 w-4" aria-hidden="true" />
                                Tom: {{ item.tone_label }}
                            </span>
                        </div>

                        <ul v-if="item.temas.length" class="mt-3 flex flex-wrap gap-2" aria-label="Temas">
                            <li v-for="t in item.temas" :key="t.chave" :class="[chip, toneStyle(t.sentimento).classes]">{{ t.label }} · {{ t.sentimento_label }}</li>
                        </ul>

                        <div class="mt-4 grid gap-4 lg:grid-cols-2">
                            <div v-if="item.pontos_atencao.length">
                                <h4 class="text-sm font-semibold text-amber-900">Pontos de atenção</h4>
                                <ul class="mt-1 space-y-2">
                                    <li v-for="(p, i) in item.pontos_atencao" :key="i" class="max-w-[75ch] text-sm leading-relaxed text-slate-800">
                                        <blockquote v-if="p.trecho" class="border-l-4 border-amber-300 pl-3">«{{ p.trecho }}»</blockquote>
                                        <p v-else>{{ p.texto }}</p>
                                    </li>
                                </ul>
                            </div>
                            <div v-if="item.compromissos.length">
                                <h4 class="text-sm font-semibold text-slate-900">Compromissos</h4>
                                <ul class="mt-1 space-y-2">
                                    <li v-for="c in item.compromissos" :key="c.id" class="text-sm leading-relaxed text-slate-800">
                                        <p class="max-w-[75ch]">{{ c.descricao }}</p>
                                        <p class="mt-1 flex flex-wrap gap-2">
                                            <span :class="[chip, 'bg-talents-50 text-talents-900 ring-talents-200']">Responsável: {{ c.responsavel_label }}</span>
                                            <span v-if="c.prazo" :class="[chip, 'bg-slate-50 text-slate-800 ring-slate-300']">Prazo: {{ c.prazo }}</span>
                                        </p>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <p v-if="item.assunto_sensivel_citado" class="mt-3 text-[13px] text-slate-700">Assunto sensível citado nesta conversa (não detalhado).</p>
                    </li>
                </ol>

                <div v-if="evolution.recurring_attention.length" class="rounded-2xl border border-amber-200 bg-amber-50/60 p-4 sm:p-5">
                    <h4 class="font-semibold text-amber-950">Temas de atenção que se repetem</h4>
                    <ul class="mt-2 flex flex-wrap gap-2">
                        <li v-for="r in evolution.recurring_attention" :key="r.chave" :class="[chip, 'bg-white text-amber-950 ring-amber-300']">
                            {{ r.label }} · em {{ r.refs.join(', ') }}
                        </li>
                    </ul>
                </div>
            </section>

            <!-- Camada 2: resumo por IA (sob demanda) -->
            <section aria-labelledby="feedback_evolution_ai" class="space-y-4 rounded-2xl border border-slate-200 bg-slate-50/60 p-4 sm:p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 id="feedback_evolution_ai" class="text-base font-semibold text-slate-900">Resumo da evolução (IA)</h3>
                        <p class="text-sm text-slate-700">Opcional. Usa só os relatórios estruturados acima; nunca a transcrição.</p>
                    </div>
                    <button v-if="canRequest" type="button" :class="btnPrimary" :disabled="busy" data-feedback-ai-action="evolution" @click="generate">
                        <SparklesIcon class="h-4 w-4" aria-hidden="true" />
                        {{ narrative ? 'Gerar novamente' : 'Gerar resumo da evolução' }}
                    </button>
                </div>

                <FeedbackNotice v-if="errors.ai" tone="danger" title="Não foi possível continuar">{{ errors.ai }}</FeedbackNotice>
                <FeedbackNotice v-if="ai.can_generate && !ai.allowed" tone="locked" title="Processamento por IA ainda não liberado">
                    A linha do tempo acima continua disponível. O resumo por IA fica disponível quando a Talents aprovar o provedor de IA para dados de Feedbacks.
                </FeedbackNotice>
                <FeedbackNotice v-else-if="ai.can_generate && ai.limit_reached" tone="warning" title="Limite mensal de IA atingido">
                    O limite mensal de IA de Feedbacks desta empresa foi atingido. A Talents foi avisada.
                </FeedbackNotice>
                <FeedbackNotice v-else-if="canRequest && !narrative" tone="info">
                    Os relatórios estruturados dos feedbacks acima (sem transcrição e sem a «Reflexão do líder») serão enviados a
                    <strong>{{ ai.provider_label }} · {{ ai.model }}</strong>.
                </FeedbackNotice>

                <div v-if="processing" class="flex items-center gap-3 rounded-xl border border-talents-200 bg-white px-4 py-3 text-sm text-talents-950" role="status" aria-live="polite">
                    <span class="h-5 w-5 shrink-0 animate-spin rounded-full border-2 border-talents-300 border-t-talents-700 motion-reduce:animate-none" aria-hidden="true" />
                    Gerando o resumo da evolução… Esta área se atualiza sozinha.
                </div>

                <FeedbackNotice v-if="narrative?.status === 'failed'" tone="danger" title="Não foi possível gerar o resumo.">
                    <p>{{ narrative.error }}</p>
                    <template v-if="canRequest" #actions>
                        <button type="button" :class="btnPrimary" :disabled="busy" data-feedback-ai-action="evolution-retry" @click="generate">
                            <ArrowPathIcon class="h-4 w-4" aria-hidden="true" />
                            Tentar de novo
                        </button>
                    </template>
                </FeedbackNotice>

                <article v-if="report" class="space-y-4" data-feedback-evolution-narrative>
                    <p class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-3 py-1 text-[13px] font-semibold text-amber-950 ring-1 ring-amber-300">
                        <SparklesIcon class="h-4 w-4" aria-hidden="true" />
                        {{ narrative.seal }}
                    </p>
                    <FeedbackNotice v-if="narrative.outdated" tone="info">
                        Há feedbacks diferentes dos usados nesta versão. Gere de novo para incluir os mais recentes.
                    </FeedbackNotice>

                    <p class="max-w-[75ch] whitespace-pre-line text-base leading-relaxed text-slate-900">{{ report.resumo }}</p>

                    <div v-if="report.mudancas.length">
                        <h4 class="text-sm font-semibold text-slate-900">O que mudou ou se manteve</h4>
                        <ul class="mt-2 space-y-2">
                            <li v-for="(m, i) in report.mudancas" :key="i" class="max-w-[75ch] text-sm leading-relaxed text-slate-800">
                                {{ m.texto }}
                                <span class="mt-1 flex flex-wrap gap-1.5">
                                    <Link
                                        v-for="f in m.fontes"
                                        :key="f.ref"
                                        :href="feedbackRoute('sessions.show', f.session_id)"
                                        :class="[chip, 'bg-white text-talents-900 ring-talents-200 hover:bg-talents-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500']"
                                        :title="f.title"
                                    >Fonte: {{ f.label }}</Link>
                                </span>
                            </li>
                        </ul>
                    </div>

                    <div v-if="report.temas_recorrentes.length">
                        <h4 class="text-sm font-semibold text-slate-900">Temas que voltaram</h4>
                        <ul class="mt-2 flex flex-wrap gap-2">
                            <li v-for="t in report.temas_recorrentes" :key="t.chave" :class="[chip, 'bg-white text-slate-800 ring-slate-300']">
                                {{ t.label }} · {{ t.fontes.map((f) => f.ref).join(', ') }}
                            </li>
                        </ul>
                    </div>

                    <div v-if="report.compromissos_retomados.length">
                        <h4 class="text-sm font-semibold text-slate-900">Compromissos ao longo dos períodos</h4>
                        <ul class="mt-2 space-y-2">
                            <li v-for="(c, i) in report.compromissos_retomados" :key="i" class="max-w-[75ch] text-sm leading-relaxed text-slate-800">
                                {{ c.texto }}
                                <span class="mt-1 flex flex-wrap gap-1.5">
                                    <Link
                                        v-for="f in c.fontes"
                                        :key="f.ref"
                                        :href="feedbackRoute('sessions.show', f.session_id)"
                                        :class="[chip, 'bg-white text-talents-900 ring-talents-200 hover:bg-talents-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500']"
                                    >Fonte: {{ f.label }}</Link>
                                </span>
                            </li>
                        </ul>
                    </div>

                    <div class="grid gap-3 lg:grid-cols-2">
                        <FeedbackNotice v-if="report.assunto_sensivel_citado" tone="locked">{{ report.sensitive_note }}</FeedbackNotice>
                        <div v-if="report.nao_afirmado.length" class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                            <h4 class="text-sm font-semibold text-slate-900">O que não dá para afirmar</h4>
                            <ul class="mt-1 list-disc space-y-1 pl-5 text-sm text-slate-800">
                                <li v-for="(n, i) in report.nao_afirmado" :key="i">{{ n }}</li>
                            </ul>
                        </div>
                    </div>

                    <p class="text-[13px] leading-relaxed text-slate-700">
                        {{ narrative.meta.provider_label }} · {{ narrative.meta.model }} · {{ formatDateTime(narrative.meta.generated_at) }}<span v-if="narrative.meta.generated_by"> · pedido por {{ narrative.meta.generated_by }}</span>
                        · descreve o que foi conversado e combinado; não é avaliação da pessoa.
                    </p>
                </article>
            </section>
        </template>
    </div>
</template>
