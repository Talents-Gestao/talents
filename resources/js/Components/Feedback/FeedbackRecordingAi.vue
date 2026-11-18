<script setup>
import FeedbackActionsMenu from '@/Components/Feedback/FeedbackActionsMenu.vue';
import { feedbackRoute } from '@/composables/useFeedbackRoutes';
import { formatDateTime } from '@/utils/dateOnly';
import { ArrowPathIcon } from '@heroicons/vue/24/outline';
import { router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

/**
 * IA da gravação do feedback (fatias 2 e 3): trava do provedor, mapear falantes, processar, andamento, falha com
 * «Tentar de novo» e as abas Relatório / Transcrição limpa / Original. O servidor decide tudo (permissões,
 * trava, limite); aqui só mostramos o estado e enviamos as ações.
 */
const props = defineProps({
    sessionId: { type: Number, required: true },
    ai: { type: Object, required: true },
    access: { type: Object, required: true },
    original: { type: Object, default: null },
    loadingOriginal: { type: Boolean, default: false },
    originalError: { type: String, default: '' },
});

const emit = defineEmits(['show-original']);

const page = usePage();
const errors = computed(() => page.props.errors ?? {});

const roleOptions = [
    { value: 'leader', label: 'Líder' },
    { value: 'employee', label: 'Colaborador' },
    { value: 'other', label: 'Outra pessoa' },
];

const gateAllowed = computed(() => props.ai.gate?.allowed === true);
const canAct = computed(() => props.access.canProcess && !props.access.locked && gateAllowed.value && !props.ai.limit_reached);
const processing = computed(() => props.ai.status === 'processing');
const needsMapping = computed(() => props.ai.speakers.length > 0 && !props.ai.speakers_mapped);
const busy = ref(false);

// ── Falantes ──
const speakerRoles = ref({});
function resetRoles() {
    const roles = {};
    props.ai.speakers.forEach((s, i) => {
        roles[s.label] = s.role ?? (i === 0 ? 'leader' : i === 1 ? 'employee' : 'other');
    });
    speakerRoles.value = roles;
}
watch(() => props.ai.speakers, resetRoles, { immediate: true });

function post(name, data = {}) {
    if (busy.value) return;
    router.post(feedbackRoute(name, props.sessionId), data, {
        preserveScroll: true,
        preserveState: true,
        onStart: () => (busy.value = true),
        onFinish: () => (busy.value = false),
    });
}

const confirmSpeakers = () => post('sessions.recording.speakers', { speakers: speakerRoles.value });
const startProcessing = () => post('sessions.recording.process');
const retry = () => post('sessions.recording.retry');
const regenerate = () => post('sessions.recording.report');

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
    return 'Na fila para processar…';
});

// ── Abas ──
const tabs = computed(() => [
    { key: 'report', label: 'Relatório' },
    { key: 'clean', label: 'Transcrição limpa' },
    ...(props.access.canViewOriginal ? [{ key: 'original', label: 'Original' }] : []),
]);
const tab = ref(props.ai.report ? 'report' : props.ai.clean ? 'clean' : 'report');
watch(tab, (t) => {
    if (t === 'original' && !props.original && !props.loadingOriginal) emit('show-original');
});

const canRegenerate = computed(() => canAct.value && !processing.value && !!props.ai.clean && ['ready', 'failed'].includes(props.ai.status));
const actionItems = computed(() =>
    canRegenerate.value ? [{ key: 'regenerate', label: 'Gerar relatório novamente', icon: ArrowPathIcon, onSelect: regenerate }] : [],
);

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

const paragraphs = computed(() => (props.ai.clean?.text ?? '').split(/\n{2,}/).map((p) => p.trim()).filter(Boolean));
const splitSpeaker = (p) => {
    const m = p.match(/^(\[[\d:]+\]\s*)?([^:\n]{2,40}):\s+([\s\S]*)$/);
    return m ? { time: (m[1] ?? '').trim(), who: m[2], text: m[3] } : { time: '', who: '', text: p };
};

const sentimentClass = (s) =>
    ({
        positivo: 'bg-emerald-50 text-emerald-800 ring-emerald-200',
        atencao: 'bg-amber-50 text-amber-900 ring-amber-200',
        misto: 'bg-sky-50 text-sky-900 ring-sky-200',
    })[s] ?? 'bg-slate-50 text-slate-700 ring-slate-200';

const btn = 'inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold shadow-sm transition disabled:cursor-not-allowed disabled:opacity-50';
</script>

<template>
    <div class="space-y-4 border-t border-slate-100 pt-4" data-feedback-recording-ai>
        <p v-if="errors.ai" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-900" role="alert">
            {{ errors.ai }}
        </p>
        <p v-if="errors.speakers" class="text-sm font-medium text-rose-700" role="alert">{{ errors.speakers }}</p>

        <!-- Trava de conformidade -->
        <div
            v-if="!gateAllowed && !access.locked && ai.status !== 'ready'"
            class="rounded-xl border border-slate-300 bg-slate-100 px-4 py-3 text-sm text-slate-800"
            role="status"
            data-feedback-ai-locked
        >
            <p class="font-semibold">Processamento por IA ainda não liberado</p>
            <p class="mt-1 text-slate-600">
                A transcrição está guardada. A limpeza e o relatório ficam disponíveis quando a Talents aprovar o provedor de IA para dados de Feedbacks.
            </p>
        </div>
        <div
            v-else-if="ai.limit_reached && !access.locked && !processing"
            class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
            role="status"
        >
            O limite mensal de IA de Feedbacks desta empresa foi atingido. A Talents foi avisada; o processamento volta no próximo mês ou quando o limite for ampliado.
        </div>

        <!-- Mapear falantes + processar -->
        <template v-if="ai.status === 'uploaded' && access.canProcess && !access.locked">
            <div v-if="ai.speakers.length" class="space-y-3" data-feedback-ai-speakers>
                <p class="text-sm text-slate-700">
                    Antes de processar, diga quem é quem na transcrição.
                    <span v-if="ai.speakers_mapped" class="text-emerald-700">Falantes confirmados<span v-if="ai.speakers_mapped_by"> por {{ ai.speakers_mapped_by }}</span>.</span>
                </p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label v-for="speaker in ai.speakers" :key="speaker.label" class="block text-sm">
                        <span class="font-medium text-slate-700">{{ speaker.label }}</span>
                        <select
                            v-model="speakerRoles[speaker.label]"
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm focus:border-talents-500 focus:ring-talents-500"
                        >
                            <option v-for="o in roleOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                        <span v-if="speaker.sample" class="mt-1 block text-xs text-slate-500">Primeira fala: «{{ speaker.sample }}»</span>
                    </label>
                </div>
                <button type="button" :class="[btn, 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50']" :disabled="busy" @click="confirmSpeakers">
                    {{ ai.speakers_mapped ? 'Salvar falantes' : 'Confirmar falantes' }}
                </button>
            </div>

            <template v-if="gateAllowed && !ai.limit_reached">
                <p class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700" data-feedback-ai-provider>
                    O texto será enviado a <strong>{{ ai.gate.provider_label }} · {{ ai.gate.model }}</strong> para limpeza e relatório.
                    As seções «Reflexão do líder» nunca são enviadas.
                </p>
                <div class="flex flex-wrap items-center gap-3">
                    <button
                        type="button"
                        :class="[btn, 'bg-talents-600 text-white hover:bg-talents-700']"
                        :disabled="busy || needsMapping"
                        @click="startProcessing"
                    >
                        Processar com IA
                    </button>
                    <span v-if="needsMapping" class="text-xs text-slate-500">Confirme os falantes para liberar.</span>
                </div>
            </template>
        </template>
        <p v-else-if="ai.status === 'uploaded'" class="text-sm text-slate-500">A gravação ainda não foi processada.</p>

        <!-- Processando -->
        <div
            v-if="processing"
            class="flex items-center gap-3 rounded-xl border border-talents-100 bg-talents-50/60 px-4 py-3 text-sm text-talents-900"
            role="status"
            data-feedback-ai-processing
        >
            <span class="h-4 w-4 shrink-0 animate-spin rounded-full border-2 border-talents-300 border-t-talents-700 motion-reduce:animate-none" aria-hidden="true" />
            <span>{{ progressText }} Você pode sair desta tela; o resultado aparece aqui quando ficar pronto.</span>
        </div>

        <!-- Falhou -->
        <div v-if="ai.status === 'failed'" class="space-y-3" data-feedback-ai-failed>
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900" role="alert">
                <p class="font-semibold">Não foi possível concluir o processamento.</p>
                <p class="mt-1">{{ ai.error }} A transcrição original continua guardada.</p>
            </div>
            <button v-if="canAct" type="button" :class="[btn, 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50']" :disabled="busy" @click="retry">
                Tentar de novo
            </button>
        </div>

        <!-- Abas -->
        <div class="space-y-3">
            <div class="flex flex-wrap items-end justify-between gap-2 border-b border-slate-200">
                <div role="tablist" class="flex flex-wrap gap-1">
                    <button
                        v-for="t in tabs"
                        :key="t.key"
                        type="button"
                        role="tab"
                        :aria-selected="tab === t.key"
                        class="rounded-t-lg px-3 py-2 text-sm font-semibold"
                        :class="tab === t.key ? 'border-b-2 border-talents-600 text-talents-800' : 'text-slate-500 hover:text-slate-700'"
                        @click="tab = t.key"
                    >
                        {{ t.label }}
                    </button>
                </div>
                <div v-if="actionItems.length" class="pb-1">
                    <FeedbackActionsMenu :items="actionItems" show-label label="Ações" />
                </div>
            </div>

            <p v-if="tab !== 'original' && (ai.report || ai.clean)" class="inline-flex rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-900">
                Gerado por IA — apoio à conversa; revise antes de usar
            </p>

            <!-- Relatório -->
            <div v-if="tab === 'report'" class="space-y-4 text-sm text-slate-800" role="tabpanel" data-feedback-ai-report>
                <p v-if="!ai.report" class="text-slate-500">O relatório ainda não foi gerado.</p>
                <template v-else>
                    <section>
                        <h4 class="font-semibold text-slate-900">Resumo</h4>
                        <p class="mt-1 whitespace-pre-line">{{ ai.report.resumo }}</p>
                    </section>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <section>
                            <h4 class="font-semibold text-emerald-800">Pontos fortes</h4>
                            <p v-if="!ai.report.pontos_fortes.length" class="mt-1 text-slate-500">Nenhum identificado.</p>
                            <ul v-else class="mt-1 space-y-2">
                                <li v-for="(p, i) in ai.report.pontos_fortes" :key="i">
                                    <p>{{ p.texto }}</p>
                                    <p v-if="p.trecho" class="mt-0.5 text-xs text-slate-500">«{{ p.trecho }}»</p>
                                </li>
                            </ul>
                        </section>
                        <section>
                            <h4 class="font-semibold text-amber-800">Pontos de atenção</h4>
                            <p v-if="!ai.report.pontos_atencao.length" class="mt-1 text-slate-500">Nenhum identificado.</p>
                            <ul v-else class="mt-1 space-y-2">
                                <li v-for="(p, i) in ai.report.pontos_atencao" :key="i">
                                    <p>{{ p.texto }}</p>
                                    <p v-if="p.trecho" class="mt-0.5 text-xs text-slate-500">«{{ p.trecho }}»</p>
                                    <p v-else class="mt-0.5 text-xs text-slate-400">Sem trecho verificado na transcrição.</p>
                                </li>
                            </ul>
                        </section>
                    </div>
                    <section v-if="ai.report.temas.length">
                        <h4 class="font-semibold text-slate-900">Temas</h4>
                        <ul class="mt-2 flex flex-wrap gap-2">
                            <li v-for="t in ai.report.temas" :key="t.chave" class="rounded-full px-3 py-1 text-xs font-semibold ring-1" :class="sentimentClass(t.sentimento)">
                                {{ t.label }} · {{ t.sentimento_label }}
                            </li>
                        </ul>
                    </section>
                    <section>
                        <h4 class="font-semibold text-slate-900">Compromissos</h4>
                        <p v-if="!ai.report.compromissos.length" class="mt-1 text-slate-500">Nenhum compromisso explícito.</p>
                        <ul v-else class="mt-1 space-y-2">
                            <li v-for="c in ai.report.compromissos" :key="c.id">
                                <p>
                                    {{ c.descricao }} — <span class="font-medium">{{ c.responsavel_label }}</span><span v-if="c.prazo"> — {{ c.prazo }}</span>
                                </p>
                                <p v-if="c.trecho" class="mt-0.5 text-xs text-slate-500">«{{ c.trecho }}»</p>
                            </li>
                        </ul>
                    </section>
                    <section v-if="ai.report.trechos_chave.length">
                        <h4 class="font-semibold text-slate-900">Trechos-chave</h4>
                        <ul class="mt-1 space-y-1">
                            <li v-for="(t, i) in ai.report.trechos_chave" :key="i" class="text-slate-700">
                                <span class="font-medium">{{ t.falante_label }}:</span> «{{ t.texto }}»
                            </li>
                        </ul>
                    </section>
                    <p v-if="ai.report.assunto_sensivel_citado" class="rounded-lg bg-slate-100 px-3 py-2 text-xs text-slate-600" data-feedback-ai-sensitive>
                        {{ ai.report.sensitive_note }}
                    </p>
                    <section v-if="ai.report.nao_afirmado.length">
                        <h4 class="font-semibold text-slate-900">O que a IA não conseguiu afirmar</h4>
                        <ul class="mt-1 list-disc space-y-1 pl-5 text-slate-600">
                            <li v-for="(n, i) in ai.report.nao_afirmado" :key="i">{{ n }}</li>
                        </ul>
                    </section>
                    <p class="text-xs text-slate-500">
                        {{ ai.report.meta.provider_label }} · {{ ai.report.meta.model }} · {{ formatDateTime(ai.report.meta.generated_at) }}<span v-if="ai.report.meta.generated_by"> · pedido por {{ ai.report.meta.generated_by }}</span>
                        · versão {{ ai.report.meta.versions }} · não substitui as respostas do feedback nem alimenta termômetro ou 9-box.
                    </p>
                </template>
            </div>

            <!-- Transcrição limpa -->
            <div v-else-if="tab === 'clean'" class="space-y-3 text-sm text-slate-800" role="tabpanel" data-feedback-ai-clean>
                <p v-if="!ai.clean" class="text-slate-500">A transcrição limpa ainda não foi gerada.</p>
                <template v-else-if="editing">
                    <textarea
                        v-model="draft"
                        rows="16"
                        class="block w-full rounded-xl border-slate-300 font-sans text-sm leading-relaxed focus:border-talents-500 focus:ring-talents-500"
                        aria-label="Transcrição limpa"
                    />
                    <p v-if="errors.clean_text" class="text-sm font-medium text-rose-700" role="alert">{{ errors.clean_text }}</p>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" :class="[btn, 'bg-talents-600 text-white hover:bg-talents-700']" :disabled="saving || !draft.trim()" @click="saveClean">
                            {{ saving ? 'Salvando…' : 'Salvar transcrição limpa' }}
                        </button>
                        <button type="button" :class="[btn, 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50']" @click="editing = false">Cancelar</button>
                    </div>
                </template>
                <template v-else>
                    <div class="max-h-[28rem] space-y-3 overflow-auto leading-relaxed">
                        <p v-for="(p, i) in paragraphs" :key="i">
                            <span v-if="splitSpeaker(p).time" class="mr-1 font-mono text-xs text-slate-400">{{ splitSpeaker(p).time }}</span>
                            <strong v-if="splitSpeaker(p).who">{{ splitSpeaker(p).who }}:</strong>
                            {{ splitSpeaker(p).text }}
                        </p>
                    </div>
                    <p class="text-xs text-slate-500">
                        Revisão {{ ai.clean.revision }}<span v-if="ai.clean.edited_at"> · editada por {{ ai.clean.edited_by ?? '—' }} em {{ formatDateTime(ai.clean.edited_at) }}</span><span v-else> · gerada em {{ formatDateTime(ai.clean.generated_at) }}</span>
                    </p>
                    <button
                        v-if="access.canEditClean && !processing"
                        type="button"
                        :class="[btn, 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50']"
                        @click="startEdit"
                    >
                        Editar transcrição limpa
                    </button>
                </template>
            </div>

            <!-- Original -->
            <div v-else-if="tab === 'original'" class="space-y-2" role="tabpanel">
                <p v-if="loadingOriginal" class="text-sm text-slate-500">Abrindo…</p>
                <p v-if="originalError" class="text-sm font-medium text-rose-700" role="alert">{{ originalError }}</p>
                <div v-if="original" class="rounded-xl border border-slate-200 bg-slate-50">
                    <p class="border-b border-slate-200 px-4 py-2 text-xs text-slate-500">
                        Original (não editável) · {{ original.filename }} · visível só para administradores; cada abertura fica registrada.
                    </p>
                    <pre class="max-h-96 overflow-auto whitespace-pre-wrap break-words px-4 py-3 font-sans text-sm leading-relaxed text-slate-800">{{ original.text }}</pre>
                </div>
            </div>
        </div>

        <p v-if="access.locked" class="text-xs text-slate-500">Feedback concluído ou cancelado: relatório e transcrição ficam só para leitura.</p>
    </div>
</template>
