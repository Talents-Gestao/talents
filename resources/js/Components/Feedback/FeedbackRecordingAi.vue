<script setup>
import FeedbackDisclosure from '@/Components/Feedback/FeedbackDisclosure.vue';
import FeedbackNotice from '@/Components/Feedback/FeedbackNotice.vue';
import FeedbackTranscriptView from '@/Components/Feedback/FeedbackTranscriptView.vue';
import { feedbackRoute } from '@/composables/useFeedbackRoutes';
import { formatDateTime } from '@/utils/dateOnly';
import { canAdjustSpeakers, speakersLine } from '@/utils/feedbackRecordingView';
import { commitmentsView, keyQuotesView, pointsView } from '@/utils/feedbackReportView';
import {
    chooseRole,
    confirmSentence,
    displayText,
    initialRoles,
    invertRoles,
    missingRolesText,
    personLabel,
    speakerDisplay,
    swapAnnouncement,
    useQuickConfirm,
} from '@/utils/feedbackSpeakerConfirm';
import { ArrowPathIcon, ArrowsRightLeftIcon, CheckIcon, DocumentTextIcon, PencilSquareIcon, SparklesIcon, UserGroupIcon } from '@heroicons/vue/24/outline';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

/**
 * IA da gravação do feedback, uma etapa por vez (estado em recordingState): confirmar falantes, aguardar a
 * liberação da IA, processar, andamento, erro com «Tentar de novo» e o resultado (abas Relatório / Transcrição limpa
 * / Original). O servidor decide tudo (permissões, trava, limite); aqui só mostramos o estado e enviamos as ações.
 * Botões que enviam algo levam data-feedback-ai-action (a prévia local usa isso para simular sem enviar).
 */
const props = defineProps({
    sessionId: { type: Number, required: true },
    ai: { type: Object, required: true },
    access: { type: Object, required: true },
    /** Estado da seção (recordingState). */
    state: { type: String, required: true },
    original: { type: Object, default: null },
    loadingOriginal: { type: Boolean, default: false },
    originalError: { type: String, default: '' },
    /** Nomes da sessão: { leader, employee }. */
    people: { type: Object, default: () => ({}) },
    /** Muda quando o usuário escolhe «Alterar falantes» no menu «Ações»: abre a lista. */
    adjustSignal: { type: Number, default: 0 },
});

const emit = defineEmits(['show-original']);

const page = usePage();
const errors = computed(() => page.props.errors ?? {});

const roleOptions = computed(() => [
    { value: 'leader', label: personLabel('leader', props.people) },
    { value: 'employee', label: personLabel('employee', props.people) },
    { value: 'other', label: personLabel('other', props.people) },
]);

const gateAllowed = computed(() => props.ai.gate?.allowed === true);
const canAct = computed(() => props.access.canProcess && !props.access.locked && gateAllowed.value && !props.ai.limit_reached);
const processing = computed(() => props.ai.status === 'processing');
const busy = ref(false);

// ── Falantes ──
// Papel inicial: o já confirmado, senão a sugestão do servidor (sem IA), senão a ordem de fala.
const speakerRoles = ref({});
const resetRoles = () => (speakerRoles.value = initialRoles(props.ai.speakers, props.ai.speaker_suggestion));
watch(() => props.ai.speakers, resetRoles, { immediate: true });

const canAdjust = computed(() => canAdjustSpeakers(props.ai, props.access));
/** Lista aberta por «Ajustar», «Alterar» ou «Alterar falantes» no menu. */
const manual = ref(false);
const speakersSection = ref(null);
async function openAdjust() {
    if (!canAdjust.value) return;
    manual.value = true;
    await nextTick();
    speakersSection.value?.scrollIntoView?.({ behavior: 'smooth', block: 'start' });
    speakersSection.value?.querySelector('select')?.focus();
}
watch(() => props.adjustSignal, openAdjust);
function cancelAdjust() {
    manual.value = false;
    resetRoles();
    announcement.value = '';
}

const showSpeakersStep = computed(() => props.state === 'speakers' || (manual.value && canAdjust.value));
const quick = computed(() => useQuickConfirm(props.ai, manual.value) && !props.ai.speakers_mapped);
/** «Falante 1 = Rafael Mendes (líder) · Falante 2 = …» com os papéis já confirmados. */
const summary = computed(() => (props.ai.speakers_mapped && props.ai.speakers.length ? speakersLine(props.ai.speakers, props.people) : ''));

/** Aviso visível antes de enviar (o servidor continua validando do mesmo jeito). */
const mappingHint = computed(() => missingRolesText(speakerRoles.value));

/** Escolha no select: líder e colaborador são exclusivos (troca automática, anunciada ao leitor de tela). */
const announcement = ref('');
function setRole(label, role) {
    const { roles, swappedWith } = chooseRole(speakerRoles.value, label, role);
    speakerRoles.value = roles;
    announcement.value = swappedWith ? swapAnnouncement(swappedWith, roles, props.people) : '';
}
function invert() {
    speakerRoles.value = invertRoles(speakerRoles.value);
    announcement.value = 'Papéis invertidos: ' + confirmSentence(props.ai.speakers, speakerRoles.value, props.people, '').trim();
}

/** Pode processar logo após confirmar? (trava do provedor e limite mensal). */
const canProcessNow = computed(() => gateAllowed.value && !props.ai.limit_reached);
const processBlockReason = computed(() => {
    if (!gateAllowed.value) return 'A IA ainda não foi liberada.';
    if (props.ai.limit_reached) return 'Limite mensal de IA atingido.';
    return '';
});

// «Ver mais trechos»: frases buscadas só ao abrir (JSON sob demanda, fora do HTML; cada abertura fica na trilha).
const samples = ref(null);
const samplesLoading = ref(false);
const samplesError = ref('');
async function onSamplesToggle(event) {
    if (!event.target.open || samples.value || samplesLoading.value) return;
    // Prévia local: as frases fictícias já vêm nos dados.
    if (props.ai.speakers.some((s) => Array.isArray(s.samples))) {
        samples.value = props.ai.speakers.map((s) => ({ label: s.label, count: s.count, samples: s.samples ?? [] }));
        return;
    }
    samplesLoading.value = true;
    samplesError.value = '';
    try {
        const { data } = await axios.get(feedbackRoute('sessions.recording.samples', props.sessionId));
        samples.value = data.speakers;
    } catch {
        samplesError.value = 'Não foi possível abrir os trechos. Tente de novo.';
    } finally {
        samplesLoading.value = false;
    }
}

/** «Confirmar e processar»: as duas ações de sempre, em sequência (falantes, depois processar). */
function confirmAndProcess() {
    if (busy.value) return;
    router.post(feedbackRoute('sessions.recording.speakers', props.sessionId), { speakers: speakerRoles.value }, {
        preserveScroll: true,
        preserveState: true,
        onStart: () => (busy.value = true),
        onSuccess: () => {
            manual.value = false;
            if (!canProcessNow.value) return;
            router.post(feedbackRoute('sessions.recording.process', props.sessionId), {}, {
                preserveScroll: true,
                preserveState: true,
                onStart: () => (busy.value = true),
                onFinish: () => (busy.value = false),
            });
        },
        onFinish: () => (busy.value = false),
    });
}

function post(name, data = {}) {
    if (busy.value) return;
    router.post(feedbackRoute(name, props.sessionId), data, {
        preserveScroll: true,
        preserveState: true,
        onStart: () => (busy.value = true),
        onFinish: () => (busy.value = false),
    });
}

function confirmSpeakers() {
    if (busy.value) return;
    router.post(feedbackRoute('sessions.recording.speakers', props.sessionId), { speakers: speakerRoles.value }, {
        preserveScroll: true,
        preserveState: true,
        onStart: () => (busy.value = true),
        onSuccess: () => (manual.value = false),
        onFinish: () => (busy.value = false),
    });
}
const startProcessing = () => post('sessions.recording.process');
const retry = () => post('sessions.recording.retry');

// ── Andamento (recarrega só a IA enquanto processa) ──
let timer = null;
function stopPolling() {
    if (timer) clearInterval(timer);
    timer = null;
}
watch(
    processing,
    (now) => {
        stopPolling();
        if (now) {
            timer = setInterval(() => router.reload({ only: ['recordingAi', 'recording'], preserveScroll: true }), 4000);
        }
    },
    { immediate: true },
);
onBeforeUnmount(stopPolling);

const progressText = computed(() => {
    if (props.ai.stage === 'report') return 'Gerando o relatório…';
    if (props.ai.chunks_total > 0) return `Limpando a transcrição (bloco ${Math.min(props.ai.chunks_done + 1, props.ai.chunks_total)} de ${props.ai.chunks_total})…`;
    return 'Na fila…';
});
/** Progresso visual: limpeza ocupa até 90%; o relatório, o restante. */
const progressPercent = computed(() => {
    if (props.ai.stage === 'report') return 92;
    if (props.ai.chunks_total > 0) return Math.round((props.ai.chunks_done / props.ai.chunks_total) * 90);
    return 3;
});

// ── Resultado: abas (relatório pronto, ou erro com resultado anterior) ──
const showResult = computed(() => props.state === 'ready' || (props.state === 'failed' && !!(props.ai.report || props.ai.clean)));
const tabs = computed(() => [
    { key: 'report', label: 'Relatório' },
    { key: 'clean', label: 'Transcrição limpa' },
    ...(props.access.canViewOriginal ? [{ key: 'original', label: 'Original' }] : []),
]);
const tab = ref('report');
watch(
    () => props.state,
    (now, before) => {
        if (now === 'ready' && before !== 'ready') tab.value = 'report';
    },
);
watch(tab, (t) => {
    if (t === 'original' && !props.original && !props.loadingOriginal) emit('show-original');
});
const tabIds = (key) => ({ tab: `feedback-ai-tab-${key}`, panel: `feedback-ai-panel-${key}` });
const tabRefs = ref({});
async function onTabKeydown(event, index) {
    const keys = tabs.value.map((t) => t.key);
    const next = { ArrowRight: index + 1, ArrowLeft: index - 1, Home: 0, End: keys.length - 1 }[event.key];
    if (next === undefined) return;
    event.preventDefault();
    tab.value = keys[(next + keys.length) % keys.length];
    await nextTick();
    tabRefs.value[tab.value]?.focus();
}

// ── Edição da transcrição limpa ──
const editing = ref(false);
const draft = ref('');
const saving = ref(false);
function startEdit() {
    draft.value = props.ai.clean?.text ?? '';
    editing.value = true;
}
function saveClean() {
    if (saving.value) return;
    router.put(
        feedbackRoute('sessions.recording.clean', props.sessionId),
        { clean_text: draft.value, revision: props.ai.clean?.revision ?? 0 },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => (saving.value = true),
            onFinish: () => (saving.value = false),
            onSuccess: () => (editing.value = false),
        },
    );
}
const revisionText = computed(() => {
    const c = props.ai.clean;
    if (!c) return '';
    return c.edited_at
        ? `Revisão ${c.revision} · editada por ${c.edited_by ?? '—'} em ${formatDateTime(c.edited_at)}`
        : `Revisão ${c.revision} · gerada pela IA em ${formatDateTime(c.generated_at)}`;
});

// Leitura do relatório sem repetição (rótulos genéricos, citações iguais à frase, trechos já exibidos).
const strengths = computed(() => pointsView(props.ai.report?.pontos_fortes ?? []));
const attention = computed(() => pointsView(props.ai.report?.pontos_atencao ?? []));
const commitments = computed(() => commitmentsView(props.ai.report?.compromissos ?? [], props.people));
const keyQuotes = computed(() => keyQuotesView(props.ai.report));

const sentimentClass = (s) =>
    ({
        positivo: 'bg-emerald-50 text-emerald-900 ring-emerald-200',
        atencao: 'bg-amber-50 text-amber-950 ring-amber-300',
        misto: 'bg-sky-50 text-sky-900 ring-sky-200',
    })[s] ?? 'bg-slate-50 text-slate-800 ring-slate-200';

const btnPrimary =
    'inline-flex min-h-[44px] items-center justify-center gap-2 rounded-xl bg-talents-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-talents-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-600 disabled:shadow-none';
const btnSecondary =
    'inline-flex min-h-[44px] items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50';
const btnLink =
    'inline-flex min-h-[44px] items-center rounded-lg px-2 text-sm font-semibold text-talents-700 underline underline-offset-2 hover:text-talents-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500';
</script>

<template>
    <div class="space-y-5" data-feedback-recording-ai>
        <FeedbackNotice v-if="errors.ai" tone="danger" title="Não foi possível continuar">{{ errors.ai }}</FeedbackNotice>

        <!-- ── Confirmar falantes (ou «Alterar» antes de processar) ── -->
        <section v-if="showSpeakersStep" ref="speakersSection" class="space-y-3" aria-labelledby="feedback_ai_speakers_title" data-feedback-ai-speakers>
            <div class="flex items-start gap-3">
                <UserGroupIcon class="mt-0.5 h-5 w-5 shrink-0 text-talents-700" aria-hidden="true" />
                <div class="min-w-0">
                    <h4 id="feedback_ai_speakers_title" class="font-semibold text-slate-900">Confirmar falantes</h4>
                    <p class="text-sm text-slate-700">Confirme quem é quem antes de processar.</p>
                </div>
            </div>

            <!-- Confiança alta (2 falantes): confirmação em um clique -->
            <div v-if="quick" class="space-y-3 rounded-xl border border-talents-200 bg-talents-50/60 p-4" data-feedback-ai-speakers-quick>
                <p class="text-sm font-medium leading-relaxed text-slate-900">{{ confirmSentence(ai.speakers, speakerRoles, people) }}</p>
                <p v-if="ai.speaker_suggestion?.reason" class="text-[13px] text-slate-700">Sugestão automática: {{ displayText(ai.speaker_suggestion.reason) }}</p>
                <p v-if="canProcessNow" class="text-[13px] text-slate-700" data-feedback-ai-provider>
                    O texto vai para <strong>{{ ai.gate.provider_label }} · {{ ai.gate.model }}</strong>. «Reflexão do líder» não é enviada.
                </p>
                <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                    <button
                        type="button"
                        :class="[btnPrimary, 'w-full sm:w-auto']"
                        :disabled="busy"
                        :aria-describedby="processBlockReason ? 'feedback_ai_quick_reason' : undefined"
                        data-feedback-ai-action="speakers-process"
                        @click="canProcessNow ? confirmAndProcess() : confirmSpeakers()"
                    >
                        <CheckIcon class="h-4 w-4" aria-hidden="true" />
                        {{ canProcessNow ? 'Confirmar e processar' : 'Confirmar' }}
                    </button>
                    <button type="button" :class="[btnSecondary, 'w-full sm:w-auto']" :disabled="busy" @click="manual = true">Ajustar</button>
                    <span v-if="processBlockReason" id="feedback_ai_quick_reason" class="text-sm text-slate-700">{{ processBlockReason }}</span>
                </div>
                <p v-if="errors.speakers" class="text-sm font-medium text-rose-700" role="alert">{{ errors.speakers }}</p>
            </div>

            <!-- Lista: uma linha por falante, com select -->
            <div v-else class="space-y-3" data-feedback-ai-speakers-list>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="min-w-0 flex-1 text-sm text-slate-700">
                        <template v-if="ai.speakers.length > 2">Há {{ ai.speakers.length }} falantes. Indique o líder e o colaborador; os demais ficam como outra pessoa.</template>
                        <template v-else-if="ai.speaker_suggestion?.confidence === 'low' && !manual">Não deu para identificar quem é quem com segurança. Confira.</template>
                        <template v-else>Escolha quem é cada falante.</template>
                    </p>
                    <button v-if="ai.speakers.length === 2" type="button" :class="btnSecondary" :disabled="busy" @click="invert">
                        <ArrowsRightLeftIcon class="h-4 w-4" aria-hidden="true" />
                        Inverter
                    </button>
                </div>

                <ul class="divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white">
                    <li v-for="(speaker, si) in ai.speakers" :key="speaker.label" class="flex flex-col gap-2 p-3 sm:flex-row sm:items-center sm:gap-4">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-slate-900">
                                {{ speakerDisplay(speaker.label) }} · <span class="font-normal text-slate-700">{{ speaker.count }} {{ speaker.count === 1 ? 'fala' : 'falas' }}</span>
                            </p>
                            <p v-if="speaker.sample" class="truncate text-[13px] italic text-slate-700" :title="speaker.sample">«{{ speaker.sample }}»</p>
                        </div>
                        <div class="w-full shrink-0 sm:w-80">
                            <label :for="`feedback_ai_speaker_${si}`" class="sr-only">Quem é o {{ speakerDisplay(speaker.label) }}?</label>
                            <select
                                :id="`feedback_ai_speaker_${si}`"
                                :value="speakerRoles[speaker.label]"
                                class="block min-h-[44px] w-full rounded-xl border-slate-300 bg-white text-sm text-slate-900 shadow-sm focus:border-talents-500 focus:ring-2 focus:ring-talents-500"
                                @change="setRole(speaker.label, $event.target.value)"
                            >
                                <option v-for="o in roleOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                            </select>
                        </div>
                    </li>
                </ul>

                <p class="sr-only" aria-live="polite">{{ announcement }}</p>
                <p v-if="mappingHint" class="text-sm font-medium text-amber-900" role="status">{{ mappingHint }}</p>
                <p v-if="errors.speakers" class="text-sm font-medium text-rose-700" role="alert">{{ errors.speakers }}</p>
                <p v-if="canProcessNow" class="text-[13px] text-slate-700" data-feedback-ai-provider>
                    O texto vai para <strong>{{ ai.gate.provider_label }} · {{ ai.gate.model }}</strong>. «Reflexão do líder» não é enviada.
                </p>

                <!-- Rodapé: no celular empilha com o principal em cima; no desktop, trechos à esquerda e ação à direita -->
                <div class="flex flex-col gap-3 sm:flex-row-reverse sm:items-start sm:justify-between">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <button
                            type="button"
                            :class="[btnPrimary, 'w-full sm:w-auto']"
                            :disabled="busy"
                            :aria-describedby="processBlockReason ? 'feedback_ai_list_reason' : undefined"
                            data-feedback-ai-action="speakers"
                            @click="canProcessNow ? confirmAndProcess() : confirmSpeakers()"
                        >
                            <CheckIcon class="h-4 w-4" aria-hidden="true" />
                            {{ canProcessNow ? 'Confirmar e processar' : 'Confirmar' }}
                        </button>
                        <button v-if="manual" type="button" :class="[btnSecondary, 'w-full sm:w-auto']" @click="cancelAdjust">Cancelar</button>
                        <span v-if="processBlockReason" id="feedback_ai_list_reason" class="text-sm text-slate-700">{{ processBlockReason }}</span>
                    </div>

                    <FeedbackDisclosure label="Ver mais trechos" class="sm:max-w-md sm:flex-1" @toggle="onSamplesToggle">
                        <p v-if="samplesLoading" class="flex items-center gap-2 text-sm text-slate-700" role="status">
                            <span class="h-4 w-4 animate-spin rounded-full border-2 border-slate-300 border-t-slate-600 motion-reduce:animate-none" aria-hidden="true" />
                            Abrindo os trechos…
                        </p>
                        <FeedbackNotice v-else-if="samplesError" tone="danger">{{ samplesError }}</FeedbackNotice>
                        <ul v-else-if="samples" class="space-y-3">
                            <li v-for="speaker in samples" :key="speaker.label">
                                <p class="text-sm font-semibold text-slate-900">
                                    {{ speakerDisplay(speaker.label) }} · <span class="font-normal text-slate-700">{{ speaker.count }} {{ speaker.count === 1 ? 'fala' : 'falas' }}</span>
                                </p>
                                <ul class="mt-1 space-y-1">
                                    <li v-for="(s, i) in speaker.samples" :key="i" class="max-w-[75ch] border-l-4 border-slate-200 pl-3 text-sm leading-relaxed text-slate-800">«{{ s }}»</li>
                                </ul>
                            </li>
                        </ul>
                        <p class="mt-3 text-[13px] text-slate-700">Trechos do original; cada abertura fica registrada.</p>
                    </FeedbackDisclosure>
                </div>
            </div>
        </section>

        <template v-else>
            <!-- Falantes confirmados: uma linha (antes de processar e durante o processamento) -->
            <p
                v-if="summary && ['queued', 'waiting', 'limit', 'processing', 'idle'].includes(state)"
                class="flex flex-wrap items-center gap-x-2 gap-y-1 rounded-xl bg-slate-50 px-3 py-1.5 text-sm text-slate-900 ring-1 ring-slate-200/70"
                data-feedback-ai-speakers-summary
            >
                <CheckIcon class="h-4 w-4 shrink-0 text-emerald-700" aria-hidden="true" />
                <span class="font-semibold">Falantes:</span>
                <span class="min-w-0 break-words">{{ summary }}</span>
                <button v-if="canAdjust" type="button" :class="[btnLink, '-ml-1']" aria-label="Alterar falantes" @click="openAdjust">Alterar</button>
            </p>

            <!-- Aguardando liberação da IA / limite mensal -->
            <FeedbackNotice v-if="state === 'waiting'" tone="locked" data-feedback-ai-locked>
                Transcrição guardada. O relatório fica disponível quando a Talents liberar a IA para Feedbacks.
            </FeedbackNotice>
            <FeedbackNotice v-else-if="state === 'limit'" tone="warning" data-feedback-ai-limit>
                Transcrição guardada. O limite mensal de IA da empresa foi atingido; a Talents já foi avisada.
            </FeedbackNotice>

            <!-- Pronto para processar -->
            <section v-if="state === 'queued'" class="space-y-3" aria-labelledby="feedback_ai_process_title">
                <h4 id="feedback_ai_process_title" class="font-semibold text-slate-900">Processar com IA</h4>
                <p class="text-sm text-slate-700" data-feedback-ai-provider>
                    O texto vai para <strong>{{ ai.gate.provider_label }} · {{ ai.gate.model }}</strong> para limpeza e relatório. «Reflexão do líder» não é enviada.
                </p>
                <button type="button" :class="[btnPrimary, 'w-full sm:w-auto']" :disabled="busy" data-feedback-ai-action="process" @click="startProcessing">
                    <SparklesIcon class="h-4 w-4" aria-hidden="true" />
                    Processar com IA
                </button>
            </section>

            <p v-if="state === 'idle'" class="text-sm text-slate-700">Transcrição guardada. Ainda não processada.</p>

            <!-- Transcrição antes do relatório (só o original, para quem pode ver; cada abertura fica registrada) -->
            <section v-if="['waiting', 'limit', 'queued', 'idle'].includes(state) && access.canViewOriginal" class="space-y-2" aria-labelledby="feedback_ai_transcript_title" data-feedback-ai-transcript>
                <h4 id="feedback_ai_transcript_title" class="font-semibold text-slate-900">Transcrição</h4>
                <FeedbackTranscriptView v-if="original" :text="original.text" :speakers="ai.speakers" :people="people" id-prefix="feedback_ai_waiting" label="Transcrição original" />
                <button v-else type="button" :class="[btnSecondary, 'w-full sm:w-auto']" :disabled="loadingOriginal" @click="emit('show-original')">
                    <DocumentTextIcon class="h-4 w-4" aria-hidden="true" />
                    {{ loadingOriginal ? 'Abrindo…' : 'Ver transcrição' }}
                </button>
                <FeedbackNotice v-if="originalError" tone="danger">{{ originalError }}</FeedbackNotice>
                <p class="text-[13px] text-slate-600">Original, não editável. Cada abertura fica registrada.</p>
            </section>

            <!-- Processando -->
            <section
                v-if="state === 'processing'"
                class="space-y-3 rounded-xl border border-talents-200 bg-talents-50/60 px-4 py-4"
                role="status"
                aria-live="polite"
                data-feedback-ai-processing
            >
                <div class="flex items-center gap-3">
                    <span class="h-5 w-5 shrink-0 animate-spin rounded-full border-2 border-talents-300 border-t-talents-700 motion-reduce:animate-none" aria-hidden="true" />
                    <p class="font-semibold text-talents-950">{{ progressText }}</p>
                </div>
                <div
                    class="h-2.5 w-full overflow-hidden rounded-full bg-white ring-1 ring-talents-200"
                    role="progressbar"
                    aria-label="Andamento do processamento"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    :aria-valuenow="progressPercent"
                    :aria-valuetext="progressText"
                >
                    <div class="h-full rounded-full bg-talents-600 transition-[width] duration-500 motion-reduce:transition-none" :style="{ width: `${progressPercent}%` }" />
                </div>
                <p class="text-sm text-talents-950">Pode sair desta tela; o resultado aparece aqui.</p>
            </section>

            <!-- Erro -->
            <FeedbackNotice v-if="state === 'failed'" tone="danger" title="Não foi possível processar." data-feedback-ai-failed>
                <p>{{ ai.error }}</p>
                <p>A transcrição continua guardada.</p>
                <template v-if="canAct" #actions>
                    <button type="button" :class="btnSecondary" :disabled="busy" data-feedback-ai-action="retry" @click="retry">
                        <ArrowPathIcon class="h-4 w-4" aria-hidden="true" />
                        Tentar de novo
                    </button>
                </template>
            </FeedbackNotice>

            <!-- ── Relatório pronto: abas ── -->
            <section v-if="showResult" class="space-y-4" aria-label="Resultado da gravação" data-feedback-ai-result>
                <div role="tablist" aria-label="Conteúdo da gravação" class="grid gap-1 rounded-xl bg-slate-100 p-1" :class="tabs.length === 3 ? 'grid-cols-3' : 'grid-cols-2'">
                    <button
                        v-for="(t, i) in tabs"
                        :id="tabIds(t.key).tab"
                        :key="t.key"
                        :ref="(el) => (tabRefs[t.key] = el)"
                        type="button"
                        role="tab"
                        :aria-selected="tab === t.key"
                        :aria-controls="tabIds(t.key).panel"
                        :tabindex="tab === t.key ? 0 : -1"
                        class="min-h-[44px] rounded-lg px-2 py-2 text-center text-sm font-semibold leading-tight transition focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500 sm:px-4"
                        :class="tab === t.key ? 'bg-white text-talents-800 shadow-sm ring-1 ring-slate-200' : 'text-slate-700 hover:bg-white/60 hover:text-slate-900'"
                        @click="tab = t.key"
                        @keydown="onTabKeydown($event, i)"
                    >
                        {{ t.label }}
                    </button>
                </div>

                <p
                    v-if="tab !== 'original'"
                    class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-3 py-1 text-[13px] font-semibold text-amber-950 ring-1 ring-amber-300"
                >
                    <SparklesIcon class="h-4 w-4" aria-hidden="true" />
                    Gerado por IA. Revise antes de usar.
                </p>

                <!-- Relatório -->
                <div
                    v-if="tab === 'report'"
                    :id="tabIds('report').panel"
                    role="tabpanel"
                    :aria-labelledby="tabIds('report').tab"
                    tabindex="0"
                    class="space-y-4 text-sm text-slate-800 focus:outline-none"
                    data-feedback-ai-report
                >
                    <p v-if="!ai.report" class="rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-slate-700">O relatório ainda não foi gerado.</p>
                    <template v-else>
                        <!-- Resumo (medida de leitura ~75 caracteres) com os temas ao lado em telas largas -->
                        <article class="grid gap-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5 xl:grid-cols-[minmax(0,75ch)_minmax(14rem,1fr)]">
                            <div>
                                <h4 class="text-[13px] font-semibold uppercase tracking-wide text-slate-600">Resumo</h4>
                                <p class="mt-2 max-w-[75ch] whitespace-pre-line text-base leading-relaxed text-slate-900">{{ ai.report.resumo }}</p>
                            </div>
                            <div v-if="ai.report.temas.length" class="xl:border-l xl:border-slate-100 xl:pl-5">
                                <h4 class="text-[13px] font-semibold uppercase tracking-wide text-slate-600">Temas</h4>
                                <ul class="mt-2 flex flex-wrap gap-2">
                                    <li v-for="t in ai.report.temas" :key="t.chave" class="rounded-full px-3 py-1 text-[13px] font-semibold ring-1" :class="sentimentClass(t.sentimento)">
                                        {{ t.label }} · {{ t.sentimento_label }}
                                    </li>
                                </ul>
                            </div>
                        </article>

                        <div class="grid gap-4 lg:grid-cols-2">
                            <article class="rounded-2xl border border-emerald-200 bg-white p-4 shadow-sm sm:p-5">
                                <h4 class="font-semibold text-emerald-900">Pontos fortes</h4>
                                <p v-if="!strengths.length" class="mt-2 text-slate-700">Nenhum identificado.</p>
                                <ul v-else class="mt-3 space-y-3">
                                    <li v-for="(p, i) in strengths" :key="i">
                                        <p v-if="p.texto" class="max-w-[75ch] leading-relaxed text-slate-900">{{ p.texto }}</p>
                                        <blockquote v-if="p.trecho" class="max-w-[75ch] border-l-4 border-emerald-300 pl-3 leading-relaxed text-slate-800" :class="p.texto ? 'mt-1' : ''">«{{ p.trecho }}»</blockquote>
                                    </li>
                                </ul>
                            </article>
                            <article class="rounded-2xl border border-amber-200 bg-white p-4 shadow-sm sm:p-5">
                                <h4 class="font-semibold text-amber-900">Pontos de atenção</h4>
                                <p v-if="!attention.length" class="mt-2 text-slate-700">Nenhum identificado.</p>
                                <ul v-else class="mt-3 space-y-3">
                                    <li v-for="(p, i) in attention" :key="i">
                                        <p v-if="p.texto" class="max-w-[75ch] leading-relaxed text-slate-900">{{ p.texto }}</p>
                                        <blockquote v-if="p.trecho" class="max-w-[75ch] border-l-4 border-amber-300 pl-3 leading-relaxed text-slate-800" :class="p.texto ? 'mt-1' : ''">«{{ p.trecho }}»</blockquote>
                                        <p v-else class="mt-1 text-[13px] text-slate-700">Sem trecho verificado na transcrição.</p>
                                    </li>
                                </ul>
                            </article>
                        </div>

                        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                            <h4 class="font-semibold text-slate-900">Compromissos</h4>
                            <p v-if="!commitments.length" class="mt-2 text-slate-700">Nenhum compromisso explícito.</p>
                            <ul v-else class="mt-3 divide-y divide-slate-100">
                                <li v-for="c in commitments" :key="c.id" class="grid gap-2 py-3 first:pt-0 last:pb-0 md:grid-cols-[minmax(0,1fr)_auto] md:items-start md:gap-6">
                                    <div class="min-w-0">
                                        <p class="max-w-[75ch] leading-relaxed text-slate-900">{{ c.descricao }}</p>
                                        <blockquote v-if="c.trecho" class="mt-1 max-w-[75ch] border-l-4 border-slate-300 pl-3 leading-relaxed text-slate-800">«{{ c.trecho }}»</blockquote>
                                    </div>
                                    <div class="flex flex-wrap gap-2 text-[13px] font-semibold md:justify-end">
                                        <span class="rounded-full bg-talents-50 px-2.5 py-1 text-talents-900 ring-1 ring-talents-200">Responsável: {{ c.owner }}</span>
                                        <span v-if="c.prazo" class="rounded-full bg-slate-50 px-2.5 py-1 text-slate-800 ring-1 ring-slate-300">Prazo: {{ c.prazo }}</span>
                                    </div>
                                </li>
                            </ul>
                        </article>

                        <article v-if="keyQuotes.length" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                            <h4 class="font-semibold text-slate-900">Trechos-chave</h4>
                            <ul class="mt-3 grid gap-3 lg:grid-cols-2">
                                <li v-for="(t, i) in keyQuotes" :key="i">
                                    <blockquote class="max-w-[75ch] border-l-4 border-slate-300 pl-3 leading-relaxed text-slate-800">
                                        <span class="font-semibold text-slate-900">{{ t.falante_label }}:</span> «{{ t.texto }}»
                                    </blockquote>
                                </li>
                            </ul>
                        </article>

                        <div class="grid gap-3 lg:grid-cols-2">
                            <FeedbackNotice v-if="ai.report.assunto_sensivel_citado" tone="locked" data-feedback-ai-sensitive>
                                {{ ai.report.sensitive_note }}
                            </FeedbackNotice>
                            <div v-if="ai.report.nao_afirmado.length" class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                                <h4 class="text-sm font-semibold text-slate-900">O que a IA não conseguiu afirmar</h4>
                                <ul class="mt-1 list-disc space-y-1 pl-5 text-slate-800">
                                    <li v-for="(n, i) in ai.report.nao_afirmado" :key="i">{{ n }}</li>
                                </ul>
                            </div>
                        </div>

                        <p class="text-[13px] leading-relaxed text-slate-700">
                            {{ ai.report.meta.provider_label }} · {{ ai.report.meta.model }} · {{ formatDateTime(ai.report.meta.generated_at) }}<span v-if="ai.report.meta.generated_by"> · por {{ ai.report.meta.generated_by }}</span>
                            · versão {{ ai.report.meta.versions }}. Não substitui as respostas do feedback nem entra no termômetro ou no 9-box.
                        </p>
                    </template>
                </div>

                <!-- Transcrição limpa -->
                <div
                    v-else-if="tab === 'clean'"
                    :id="tabIds('clean').panel"
                    role="tabpanel"
                    :aria-labelledby="tabIds('clean').tab"
                    tabindex="0"
                    class="space-y-3 text-sm text-slate-800 focus:outline-none"
                    data-feedback-ai-clean
                >
                    <p v-if="!ai.clean" class="rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-slate-700">A transcrição limpa ainda não foi gerada.</p>
                    <template v-else-if="editing">
                        <label for="feedback_ai_clean_editor" class="block text-sm font-semibold text-slate-900">Editar transcrição limpa</label>
                        <p class="text-[13px] text-slate-700">Deixe uma linha em branco entre as falas. O original não muda.</p>
                        <textarea
                            id="feedback_ai_clean_editor"
                            v-model="draft"
                            rows="18"
                            class="block w-full rounded-xl border-slate-300 font-sans text-sm leading-relaxed focus:border-talents-500 focus:ring-talents-500"
                            :aria-invalid="errors.clean_text ? 'true' : undefined"
                        />
                        <FeedbackNotice v-if="errors.clean_text" tone="danger" title="A transcrição não foi salva">{{ errors.clean_text }}</FeedbackNotice>
                        <div class="sticky bottom-0 z-10 -mx-1 flex flex-wrap items-center gap-2 rounded-xl border border-slate-200 bg-white/95 p-2 shadow-sm backdrop-blur">
                            <button type="button" :class="btnPrimary" :disabled="saving || !draft.trim()" data-feedback-ai-action="save-clean" @click="saveClean">
                                {{ saving ? 'Salvando…' : 'Salvar' }}
                            </button>
                            <button type="button" :class="btnSecondary" @click="editing = false">Cancelar</button>
                            <span v-if="!draft.trim()" class="text-[13px] text-slate-700">A transcrição não pode ficar vazia.</span>
                        </div>
                    </template>
                    <template v-else>
                        <FeedbackTranscriptView :text="ai.clean.text" :people="people" id-prefix="feedback_ai_clean" label="Transcrição limpa" />
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-[13px] text-slate-600">{{ revisionText }}</p>
                            <button v-if="access.canEditClean && !processing" type="button" :class="[btnSecondary, 'w-full sm:w-auto']" @click="startEdit">
                                <PencilSquareIcon class="h-4 w-4" aria-hidden="true" />
                                Editar
                            </button>
                        </div>
                    </template>
                </div>

                <!-- Original -->
                <div
                    v-else-if="tab === 'original'"
                    :id="tabIds('original').panel"
                    role="tabpanel"
                    :aria-labelledby="tabIds('original').tab"
                    tabindex="0"
                    class="space-y-2 focus:outline-none"
                >
                    <p v-if="loadingOriginal" class="flex items-center gap-2 text-sm text-slate-700" role="status">
                        <span class="h-4 w-4 animate-spin rounded-full border-2 border-slate-300 border-t-slate-600 motion-reduce:animate-none" aria-hidden="true" />
                        Abrindo…
                    </p>
                    <FeedbackNotice v-if="originalError" tone="danger">{{ originalError }}</FeedbackNotice>
                    <FeedbackTranscriptView v-if="original" :text="original.text" :speakers="ai.speakers" :people="people" id-prefix="feedback_ai_original" label="Transcrição original" />
                    <p class="text-[13px] text-slate-600">Original, não editável. Cada abertura fica registrada.</p>
                </div>
            </section>
        </template>
    </div>
</template>
