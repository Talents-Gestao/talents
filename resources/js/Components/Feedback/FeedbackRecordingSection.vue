<script setup>
import FeedbackActionsMenu from '@/Components/Feedback/FeedbackActionsMenu.vue';
import FeedbackDisclosure from '@/Components/Feedback/FeedbackDisclosure.vue';
import FeedbackNotice from '@/Components/Feedback/FeedbackNotice.vue';
import PrivacyDisclosure from '@/Components/Feedback/FeedbackRecordingPrivacy.vue';
import FeedbackSectionAccordion from '@/Components/Feedback/FeedbackSectionAccordion.vue';
import { formatDateTime } from '@/utils/dateOnly';
import { ArrowUpTrayIcon, DocumentTextIcon, MicrophoneIcon, TrashIcon } from '@heroicons/vue/24/outline';
import { computed, ref, watch } from 'vue';

/**
 * Seção «Gravação e relatório» (só apresentação). Com gravação, o relatório (ou a ação pendente) vem primeiro;
 * arquivo, selos, detalhes técnicos e privacidade ficam recolhidos abaixo. Sem gravação, o envio é a ação principal. Não faz requisições: emite eventos para FeedbackRecordingPanel.
 */
const props = defineProps({
    recording: { type: Object, default: null },
    access: {
        type: Object,
        default: () => ({ canUpload: false, canDelete: false, canViewOriginal: false, locked: false }),
    },
    errors: { type: Object, default: () => ({}) },
    uploading: { type: Boolean, default: false },
    original: { type: Object, default: null },
    loadingOriginal: { type: Boolean, default: false },
    originalError: { type: String, default: '' },
    /** Muda após um envio bem-sucedido para limpar arquivo e consentimento. */
    resetKey: { type: Number, default: 0 },
    defaultOpen: { type: Boolean, default: false },
    /** Itens extras do menu «Ações» da gravação (antes de «Excluir gravação»), no formato do FeedbackActionsMenu. */
    extraActions: { type: Array, default: () => [] },
});

const emit = defineEmits(['upload', 'file-change', 'show-original', 'hide-original', 'request-delete']);

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

const meta = computed(() => (props.recording ? 'Transcrição recebida' : 'Sem gravação'));

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
    if (!file.value) return 'Escolha o arquivo da transcrição para liberar o envio.';
    if (!consent.value) return 'Marque a confirmação de consentimento para liberar o envio.';
    return '';
});

const menuItems = computed(() => [
    ...props.extraActions,
    ...(props.access.canDelete && !props.access.locked
        ? [{ key: 'delete-recording', label: 'Excluir gravação', icon: TrashIcon, danger: true, onSelect: () => emit('request-delete') }]
        : []),
]);

const formatNumber = (n) => (n == null ? '—' : Number(n).toLocaleString('pt-BR'));
const formatSize = (bytes) => {
    if (bytes == null) return '—';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toLocaleString('pt-BR', { maximumFractionDigits: 1 })} KB`;
    return `${(bytes / 1024 / 1024).toLocaleString('pt-BR', { maximumFractionDigits: 1 })} MB`;
};

const badge = (ok) =>
    ok
        ? 'bg-emerald-50 text-emerald-800 ring-emerald-200'
        : 'bg-amber-50 text-amber-950 ring-amber-300';
const btnPrimary =
    'inline-flex min-h-[44px] items-center justify-center gap-2 rounded-xl bg-talents-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-talents-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-600 disabled:shadow-none';
const btnSecondary =
    'inline-flex min-h-[44px] items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500 focus-visible:ring-offset-2 disabled:opacity-50';
</script>

<template>
    <FeedbackSectionAccordion
        title="Gravação e relatório"
        :icon="MicrophoneIcon"
        description="Transcrição da conversa exportada do gravador Plaud."
        :meta="meta"
        :default-open="defaultOpen"
        data-feedback-recording
    >
        <div class="space-y-5 p-4 sm:p-6">
            <!-- Barra compacta: etapas da gravação (secundária) e menu «Ações» -->
            <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-2 rounded-xl bg-slate-50 px-3 py-2 ring-1 ring-slate-200/70">
                <slot name="steps" />
                <FeedbackActionsMenu v-if="recording && menuItems.length" :items="menuItems" show-label label="Ações" />
            </div>

            <!-- Sem gravação: o envio é a ação principal -->
            <template v-if="!recording">
                <FeedbackNotice v-if="access.locked" tone="locked" title="Gravação travada">
                    Feedback concluído ou cancelado: não é possível enviar gravação.
                </FeedbackNotice>
                <p v-else-if="!access.canUpload" class="rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-700">
                    Nenhuma gravação enviada.
                </p>

                <form v-else class="space-y-4" aria-labelledby="feedback_recording_upload_title" @submit.prevent="submit">
                    <div>
                        <h4 id="feedback_recording_upload_title" class="text-base font-semibold text-slate-900">Enviar transcrição do Plaud</h4>
                        <p class="mt-0.5 text-sm text-slate-700">
                            No app do Plaud, exporte a transcrição <strong>com marcação de tempo e identificação de quem fala</strong>.
                        </p>
                    </div>

                    <FeedbackDisclosure label="Como exportar no Plaud" tone="brand" data-feedback-recording-steps>
                        <ol class="list-decimal space-y-1 pl-5 leading-relaxed">
                            <li>No app do Plaud, abra a gravação do feedback e gere a transcrição, se ainda não houver.</li>
                            <li>Toque em compartilhar/exportar e escolha a transcrição em TXT, SRT ou DOCX.</li>
                            <li>Antes de exportar, ligue a marcação de tempo e a identificação de quem fala (falantes).</li>
                            <li>Salve o arquivo e envie aqui.</li>
                        </ol>
                        <p class="mt-2 text-[13px] text-slate-700">O nome das opções pode variar conforme a versão do app.</p>
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
                                <template v-else>Arraste o arquivo aqui ou <span class="text-talents-700 underline underline-offset-2">escolha no computador</span></template>
                            </span>
                            <span class="text-[13px] text-slate-700">Aceitamos TXT, SRT ou DOCX (preferidos) e PDF, até 10 MB.</span>
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

                    <div class="flex flex-wrap items-center gap-3">
                        <button
                            type="submit"
                            :class="btnPrimary"
                            :disabled="!file || !consent || uploading"
                            :aria-describedby="blockReason ? 'feedback_recording_block_reason' : undefined"
                        >
                            <ArrowUpTrayIcon class="h-4 w-4" aria-hidden="true" />
                            {{ uploading ? 'Enviando…' : 'Enviar transcrição do Plaud' }}
                        </button>
                        <p v-if="blockReason" id="feedback_recording_block_reason" class="text-sm text-slate-700">{{ blockReason }}</p>
                    </div>
                </form>

                <PrivacyDisclosure />
            </template>

            <!-- Com gravação: o relatório (ou a ação pendente) vem primeiro -->
            <template v-else>
                <!-- IA (limpeza e relatório): FeedbackRecordingPanel preenche este slot; sem ele, só o original. -->
                <slot name="ai">
                    <div v-if="access.canViewOriginal" class="space-y-2">
                        <div class="flex flex-wrap items-center gap-3">
                            <button v-if="!original" type="button" :class="btnSecondary" :disabled="loadingOriginal" @click="emit('show-original')">
                                {{ loadingOriginal ? 'Abrindo…' : 'Ver transcrição original' }}
                            </button>
                            <button v-else type="button" :class="btnSecondary" @click="emit('hide-original')">Ocultar transcrição original</button>
                            <span class="text-[13px] text-slate-700">Visível só para administradores; cada abertura fica registrada.</span>
                        </div>
                        <FeedbackNotice v-if="originalError" tone="danger">{{ originalError }}</FeedbackNotice>
                        <div v-if="original" class="rounded-xl border border-slate-200 bg-slate-50">
                            <p class="border-b border-slate-200 px-4 py-2 text-[13px] text-slate-700">Original (não editável) · {{ original.filename }}</p>
                            <pre class="max-h-96 overflow-auto whitespace-pre-wrap break-words px-4 py-3 font-sans text-sm leading-relaxed text-slate-800">{{ original.text }}</pre>
                        </div>
                    </div>
                    <p v-else class="text-sm text-slate-700">O texto original é visível só para administradores.</p>
                </slot>

                <FeedbackNotice v-if="!recording.has_speaker_labels" tone="warning" title="Sem identificação de quem fala" data-feedback-recording-no-speakers>
                    Arquivo sem identificação de quem fala; o relatório ficará menos preciso. Exporte novamente com essa opção ligada.
                </FeedbackNotice>

                <!-- Metadados recolhidos: arquivo, selos e detalhes técnicos -->
                <FeedbackDisclosure
                    label="Arquivo da transcrição"
                    :hint="`${recording.format_label} · enviada em ${formatDateTime(recording.uploaded_at)}${recording.uploaded_by ? ` por ${recording.uploaded_by}` : ''}`"
                    data-feedback-recording-summary
                >
                    <template #icon><DocumentTextIcon class="h-5 w-5 shrink-0 text-slate-600" aria-hidden="true" /></template>
                    <ul class="flex flex-wrap gap-2 text-[13px] font-semibold" aria-label="Situação da transcrição">
                        <li class="rounded-full px-2.5 py-1 ring-1" :class="badge(recording.has_speaker_labels)">
                            Quem fala: {{ recording.has_speaker_labels ? 'Identificado' : 'Não identificado' }}
                        </li>
                        <li class="rounded-full px-2.5 py-1 ring-1" :class="badge(recording.has_timestamps)">
                            Marcação de tempo: {{ recording.has_timestamps ? 'Sim' : 'Não' }}
                        </li>
                        <li class="rounded-full bg-white px-2.5 py-1 text-slate-800 ring-1 ring-slate-200">
                            Consentimento confirmado: {{ formatDateTime(recording.consent_at) }}<span v-if="recording.consent_by"> · {{ recording.consent_by }}</span>
                        </li>
                    </ul>
                    <dl class="mt-4 grid gap-x-6 gap-y-3 sm:grid-cols-2">
                        <div>
                            <dt class="text-[13px] font-semibold uppercase tracking-wide text-slate-600">Texto extraído</dt>
                            <dd class="mt-0.5 text-slate-800">{{ formatNumber(recording.chars) }} caracteres · arquivo de {{ formatSize(recording.size_bytes) }} (descartado)</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-[13px] font-semibold uppercase tracking-wide text-slate-600">Detalhes técnicos · hash do arquivo (SHA-256)</dt>
                            <dd class="mt-0.5 break-all font-mono text-[13px] text-slate-800">{{ recording.sha256 }}</dd>
                        </div>
                    </dl>
                </FeedbackDisclosure>

                <PrivacyDisclosure />

                <FeedbackNotice v-if="access.locked" tone="locked" title="Gravação travada">
                    Feedback concluído ou cancelado: a gravação não pode ser excluída por aqui.
                </FeedbackNotice>
            </template>
        </div>
    </FeedbackSectionAccordion>
</template>
