<script setup>
import FeedbackActionsMenu from '@/Components/Feedback/FeedbackActionsMenu.vue';
import FeedbackDisclosure from '@/Components/Feedback/FeedbackDisclosure.vue';
import FeedbackNotice from '@/Components/Feedback/FeedbackNotice.vue';
import { RECORDING_STATES } from '@/utils/feedbackRecordingView';
import { ArrowUpTrayIcon, ChevronDownIcon, DocumentTextIcon, LockClosedIcon, MicrophoneIcon } from '@heroicons/vue/24/outline';
import { computed, ref, watch } from 'vue';

/**
 * Seção «Gravação e relatório» (só apresentação). Cabeçalho com título, um selo de estado e o menu «Ações»; abaixo,
 * a barra de etapas e só a etapa atual: sem gravação, o envio; com gravação, o que FeedbackRecordingPanel põe no
 * slot «ai». Detalhes técnicos e privacidade ficam fora da tela (modal aberto pelo menu ou pelo cadeado).
 * Não faz requisições: emite eventos para FeedbackRecordingPanel.
 */
const props = defineProps({
    recording: { type: Object, default: null },
    access: {
        type: Object,
        default: () => ({ canUpload: false, canDelete: false, canViewOriginal: false, locked: false }),
    },
    /** Estado da seção (recordingState): define o selo. */
    state: { type: String, default: 'empty' },
    errors: { type: Object, default: () => ({}) },
    uploading: { type: Boolean, default: false },
    /** Muda após um envio bem-sucedido para limpar arquivo e consentimento. */
    resetKey: { type: Number, default: 0 },
    defaultOpen: { type: Boolean, default: false },
    /** Itens do menu «Ações», no formato do FeedbackActionsMenu. */
    menuItems: { type: Array, default: () => [] },
    /** Mostrar o aviso de arquivo sem identificação de quem fala (antes de processar). */
    showNoSpeakersWarning: { type: Boolean, default: false },
});

const emit = defineEmits(['upload', 'file-change', 'open-details']);

const open = ref(props.defaultOpen);
const bodyId = 'feedback_recording_body';

const fileInput = ref(null);
const file = ref(null);
const consent = ref(false);
const dragging = ref(false);

watch(
    () => props.resetKey,
    () => {
        file.value = null;
        consent.value = false;
        if (fileInput.value) fileInput.value.value = '';
    },
);

const badge = computed(() => RECORDING_STATES[props.state] ?? RECORDING_STATES.empty);
const badgeClass = computed(
    () =>
        ({
            action: 'bg-talents-50 text-talents-900 ring-talents-200',
            warning: 'bg-amber-50 text-amber-950 ring-amber-300',
            success: 'bg-emerald-50 text-emerald-900 ring-emerald-200',
            danger: 'bg-rose-50 text-rose-900 ring-rose-200',
        })[badge.value.tone] ?? 'bg-slate-100 text-slate-800 ring-slate-300',
);

/** Qualquer ação do menu abre a seção antes (ex.: «Alterar falantes» com a seção recolhida). */
const actions = computed(() =>
    props.menuItems.map((item) => ({
        ...item,
        onSelect: () => {
            open.value = true;
            item.onSelect?.();
        },
    })),
);

function pick(selected) {
    file.value = selected ?? null;
    emit('file-change');
}

function onFileChange(event) {
    pick(event.target.files?.[0]);
}

function onDrop(event) {
    dragging.value = false;
    const dropped = event.dataTransfer?.files?.[0];
    if (dropped) pick(dropped);
}

function submit() {
    if (!file.value || !consent.value || props.uploading) return;
    emit('upload', { file: file.value, consent: consent.value });
}

/** Por que o botão de enviar está desabilitado (dito em texto, não só pelo estado do botão). */
const blockReason = computed(() => {
    if (props.uploading) return '';
    if (!file.value) return 'Escolha o arquivo para enviar.';
    if (!consent.value) return 'Confirme o consentimento para enviar.';
    return '';
});

const formatSize = (bytes) => {
    if (bytes == null) return '—';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toLocaleString('pt-BR', { maximumFractionDigits: 1 })} KB`;
    return `${(bytes / 1024 / 1024).toLocaleString('pt-BR', { maximumFractionDigits: 1 })} MB`;
};

const btnPrimary =
    'inline-flex min-h-[44px] items-center justify-center gap-2 rounded-xl bg-talents-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-talents-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-600 disabled:shadow-none';
</script>

<template>
    <section
        class="rounded-2xl border border-slate-200/80 bg-white shadow-sm"
        :class="open ? 'border-talents-200/80 shadow-md' : ''"
        aria-labelledby="feedback_recording_title"
        data-feedback-recording
        :data-feedback-recording-state="state"
    >
        <!-- Cabeçalho: título e selo (abre e fecha) e o menu «Ações» -->
        <div class="flex items-center gap-2 rounded-t-2xl bg-gradient-to-r from-talents-50/60 to-white px-3 py-2 sm:px-4" :class="open ? '' : 'rounded-b-2xl'">
            <h3 id="feedback_recording_title" class="min-w-0 flex-1">
                <button
                    type="button"
                    class="flex min-h-[44px] w-full items-center gap-2 rounded-xl px-1 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500"
                    :aria-expanded="open ? 'true' : 'false'"
                    :aria-controls="bodyId"
                    @click="open = !open"
                >
                    <MicrophoneIcon class="h-5 w-5 shrink-0 text-talents-600" aria-hidden="true" />
                    <span class="flex min-w-0 flex-1 flex-wrap items-center gap-x-2 gap-y-1">
                        <span class="font-semibold text-talents-900">Gravação e relatório</span>
                        <span class="whitespace-nowrap rounded-full px-2.5 py-0.5 text-[13px] font-semibold ring-1" :class="badgeClass" data-feedback-recording-badge>
                            <span class="sr-only">Estado: </span>{{ badge.label }}
                        </span>
                    </span>
                    <ChevronDownIcon class="h-5 w-5 shrink-0 text-talents-600 transition-transform motion-reduce:transition-none" :class="open ? 'rotate-180' : ''" aria-hidden="true" />
                </button>
            </h3>
            <FeedbackActionsMenu v-if="actions.length" :items="actions" show-label label="Ações" />
        </div>

        <div v-show="open" :id="bodyId" class="space-y-5 border-t border-slate-100 p-4 sm:p-6">
            <!-- Etapas numa linha e o cadeado «Privacidade» -->
            <div class="flex items-center justify-between gap-x-3 rounded-xl bg-slate-50 px-3 py-1 ring-1 ring-slate-200/70">
                <slot name="steps" />
                <button
                    type="button"
                    class="-mr-1 inline-flex min-h-[44px] min-w-[44px] items-center justify-center gap-1.5 rounded-lg px-2 text-[13px] font-semibold text-slate-700 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500"
                    data-feedback-recording-privacy-open
                    @click="emit('open-details')"
                >
                    <LockClosedIcon class="h-4 w-4" aria-hidden="true" />
                    <span class="sr-only sm:not-sr-only">Privacidade</span>
                </button>
            </div>

            <!-- Sem gravação: o envio -->
            <template v-if="!recording">
                <FeedbackNotice v-if="access.locked" tone="locked">Feedback concluído ou cancelado: não dá para enviar gravação.</FeedbackNotice>
                <p v-else-if="!access.canUpload" class="rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-700">
                    Nenhuma gravação enviada.
                </p>

                <form v-else class="space-y-4" aria-labelledby="feedback_recording_upload_title" @submit.prevent="submit">
                    <div>
                        <h4 id="feedback_recording_upload_title" class="text-base font-semibold text-slate-900">Enviar transcrição do Plaud</h4>
                        <p class="mt-0.5 text-sm text-slate-700">Exporte <strong>com marcação de tempo e identificação de quem fala</strong>.</p>
                    </div>

                    <FeedbackDisclosure label="Como exportar no Plaud" tone="brand" data-feedback-recording-steps>
                        <ol class="list-decimal space-y-1 pl-5 leading-relaxed">
                            <li>Abra a gravação no app do Plaud e gere a transcrição.</li>
                            <li>Em compartilhar ou exportar, escolha TXT, SRT ou DOCX.</li>
                            <li>Ligue a marcação de tempo e a identificação de quem fala.</li>
                            <li>Salve o arquivo e envie aqui.</li>
                        </ol>
                        <p class="mt-2 text-[13px] text-slate-700">Os nomes das opções podem variar conforme a versão do app.</p>
                    </FeedbackDisclosure>

                    <!-- Área de soltar arquivo (o input continua sendo o campo real, com rótulo) -->
                    <div>
                        <label
                            for="feedback_recording_file"
                            class="flex min-h-[132px] cursor-pointer flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed px-4 py-6 text-center transition focus-within:ring-2 focus-within:ring-talents-500 focus-within:ring-offset-2"
                            :class="[
                                dragging ? 'border-talents-500 bg-talents-50' : 'border-slate-300 bg-slate-50/60 hover:border-talents-400 hover:bg-talents-50/40',
                                errors.file ? 'border-rose-400' : '',
                            ]"
                            data-feedback-recording-dropzone
                            @dragenter.prevent="dragging = true"
                            @dragover.prevent="dragging = true"
                            @dragleave.prevent="dragging = false"
                            @drop.prevent="onDrop"
                        >
                            <ArrowUpTrayIcon class="h-7 w-7 text-talents-600" aria-hidden="true" />
                            <span class="text-sm font-semibold text-slate-900">
                                <template v-if="file">Arquivo escolhido</template>
                                <template v-else>Arraste o arquivo ou <span class="text-talents-700 underline underline-offset-2">escolha no computador</span></template>
                            </span>
                            <span class="text-[13px] text-slate-700">TXT, SRT ou DOCX (preferidos) ou PDF, até 10 MB.</span>
                            <span v-if="file" class="mt-1 inline-flex max-w-full items-center gap-2 rounded-lg bg-white px-3 py-1.5 text-sm text-slate-800 ring-1 ring-slate-200">
                                <DocumentTextIcon class="h-4 w-4 shrink-0 text-slate-500" aria-hidden="true" />
                                <span class="truncate">{{ file.name }}</span>
                                <span class="shrink-0 text-[13px] text-slate-600">{{ formatSize(file.size) }}</span>
                            </span>
                            <input
                                id="feedback_recording_file"
                                ref="fileInput"
                                type="file"
                                accept=".txt,.srt,.docx,.pdf"
                                class="sr-only"
                                :aria-invalid="errors.file ? 'true' : undefined"
                                :aria-describedby="errors.file ? 'feedback_recording_file_error' : undefined"
                                @change="onFileChange"
                            />
                        </label>
                        <p v-if="errors.file" id="feedback_recording_file_error" class="mt-2 text-sm font-medium text-rose-700" role="alert">{{ errors.file }}</p>
                    </div>

                    <div>
                        <label
                            class="flex min-h-[44px] cursor-pointer items-start gap-3 rounded-xl border px-4 py-3 text-sm text-slate-900 transition"
                            :class="consent ? 'border-talents-300 bg-talents-50/60' : errors.consent ? 'border-rose-300 bg-rose-50/50' : 'border-slate-300 bg-white hover:bg-slate-50'"
                        >
                            <input
                                v-model="consent"
                                type="checkbox"
                                class="mt-0.5 h-5 w-5 shrink-0 rounded border-slate-400 text-talents-600 focus:ring-talents-500"
                                :aria-invalid="errors.consent ? 'true' : undefined"
                                :aria-describedby="errors.consent ? 'feedback_recording_consent_error' : undefined"
                            />
                            <span class="font-medium">O colaborador foi informado e concordou com a gravação e o processamento por IA.</span>
                        </label>
                        <p v-if="errors.consent" id="feedback_recording_consent_error" class="mt-2 text-sm font-medium text-rose-700" role="alert">{{ errors.consent }}</p>
                    </div>

                    <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center sm:gap-3">
                        <button
                            type="submit"
                            :class="[btnPrimary, 'w-full sm:w-auto']"
                            :disabled="!file || !consent || uploading"
                            :aria-describedby="blockReason ? 'feedback_recording_block_reason' : undefined"
                        >
                            <ArrowUpTrayIcon class="h-4 w-4" aria-hidden="true" />
                            {{ uploading ? 'Enviando…' : 'Enviar transcrição' }}
                        </button>
                        <p v-if="blockReason" id="feedback_recording_block_reason" class="text-sm text-slate-700">{{ blockReason }}</p>
                    </div>
                </form>
            </template>

            <!-- Com gravação: só a etapa atual (FeedbackRecordingPanel preenche o slot) -->
            <template v-else>
                <FeedbackNotice v-if="showNoSpeakersWarning" tone="warning" data-feedback-recording-no-speakers>
                    Arquivo sem identificação de quem fala: o relatório fica menos preciso. Se puder, exporte de novo com essa opção.
                </FeedbackNotice>
                <slot name="ai" />
                <p v-if="access.locked" class="flex items-center gap-2 text-[13px] text-slate-700">
                    <LockClosedIcon class="h-4 w-4 shrink-0 text-slate-500" aria-hidden="true" />
                    Feedback concluído ou cancelado: só leitura.
                </p>
            </template>
        </div>
    </section>
</template>
