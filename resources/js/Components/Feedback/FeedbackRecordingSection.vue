<script setup>
import FeedbackSectionAccordion from '@/Components/Feedback/FeedbackSectionAccordion.vue';
import { formatDateTime } from '@/utils/dateOnly';
import { LockClosedIcon } from '@heroicons/vue/24/outline';
import { computed, ref, watch } from 'vue';

/**
 * Seção «Gravação e relatório» (só apresentação): transparência, envio da transcrição do Plaud, metadados,
 * texto original e botão de excluir. Não faz requisições: emite eventos para FeedbackRecordingPanel.
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
});

const emit = defineEmits(['upload', 'file-change', 'show-original', 'hide-original', 'request-delete']);

const fileInput = ref(null);
const file = ref(null);
const consent = ref(false);

watch(
    () => props.resetKey,
    () => {
        file.value = null;
        consent.value = false;
        if (fileInput.value) fileInput.value.value = '';
    },
);

const meta = computed(() => (props.recording ? 'Transcrição recebida' : 'Sem gravação'));

function onFileChange(event) {
    file.value = event.target.files?.[0] ?? null;
    emit('file-change');
}

function submit() {
    if (!file.value || !consent.value || props.uploading) return;
    emit('upload', { file: file.value, consent: consent.value });
}

const formatNumber = (n) => (n == null ? '—' : Number(n).toLocaleString('pt-BR'));
const formatSize = (bytes) => {
    if (bytes == null) return '—';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toLocaleString('pt-BR', { maximumFractionDigits: 1 })} KB`;
    return `${(bytes / 1024 / 1024).toLocaleString('pt-BR', { maximumFractionDigits: 1 })} MB`;
};
</script>

<template>
    <FeedbackSectionAccordion
        title="Gravação e relatório"
        icon="🎙️"
        description="Transcrição da conversa exportada do gravador Plaud."
        :meta="meta"
        :default-open="defaultOpen"
        data-feedback-recording
    >
        <div class="space-y-4 p-5 sm:p-6">
            <!-- Transparência: 1 linha + detalhes sob demanda (acessível por teclado, sem depender de hover) -->
            <details class="group text-sm text-slate-600" data-feedback-recording-privacy>
                <summary class="flex cursor-pointer list-none flex-wrap items-center gap-x-2 marker:content-none [&::-webkit-details-marker]:hidden">
                    <LockClosedIcon class="h-4 w-4 shrink-0 text-slate-400" aria-hidden="true" />
                    <span>Guardamos só o texto da transcrição, criptografado; o arquivo é apagado logo após a leitura.</span>
                    <span class="font-semibold text-talents-700 underline-offset-2 hover:underline">
                        <span class="group-open:hidden">Saiba mais</span><span class="hidden group-open:inline">Ocultar</span>
                    </span>
                </summary>
                <ul class="mt-2 list-disc space-y-1 rounded-xl bg-slate-50 py-3 pl-9 pr-4 text-slate-600">
                    <li>Guardado: o texto extraído (criptografado), o hash e o tamanho do arquivo, quem enviou e quem confirmou o consentimento.</li>
                    <li>Texto original: só administradores da empresa e a Talents veem; cada abertura fica registrada.</li>
                    <li>Líder do feedback: vê a transcrição limpa e o relatório gerados por IA, não o original. O colaborador não vê nada disso.</li>
                    <li>IA: só depois que a Talents aprova o provedor; a tela mostra quem recebe o texto. «Reflexão do líder» nunca é enviada.</li>
                    <li>Excluir o feedback, o modelo ou a gravação exclui também o texto guardado.</li>
                </ul>
            </details>

            <!-- Sem gravação -->
            <template v-if="!recording">
                <p v-if="access.locked" class="text-sm text-slate-600">
                    Feedback concluído ou cancelado: não é possível enviar gravação.
                </p>
                <p v-else-if="!access.canUpload" class="text-sm text-slate-600">Nenhuma gravação enviada.</p>

                <form v-else class="space-y-4" @submit.prevent="submit">
                    <div class="rounded-xl border border-talents-100 bg-talents-50/60 px-4 py-3 text-sm text-talents-900">
                        <p>
                            No app do Plaud, exporte a transcrição <strong>com marcação de tempo e identificação de quem fala</strong>.
                            Aceitamos TXT, SRT ou DOCX (preferidos) e PDF, até 10 MB.
                        </p>
                        <details class="group mt-2" data-feedback-recording-steps>
                            <summary class="cursor-pointer list-none font-semibold text-talents-700 marker:content-none hover:underline [&::-webkit-details-marker]:hidden">
                                <span class="group-open:hidden">Como exportar no Plaud</span>
                                <span class="hidden group-open:inline">Ocultar passo a passo</span>
                            </summary>
                            <ol class="mt-2 list-decimal space-y-1 pl-5 text-talents-900">
                                <li>No app do Plaud, abra a gravação do feedback e gere a transcrição, se ainda não houver.</li>
                                <li>Toque em compartilhar/exportar e escolha a transcrição em TXT, SRT ou DOCX.</li>
                                <li>Antes de exportar, ligue a marcação de tempo e a identificação de quem fala (falantes).</li>
                                <li>Salve o arquivo e envie aqui.</li>
                            </ol>
                            <p class="mt-2 text-xs text-talents-800/80">O nome das opções pode variar conforme a versão do app.</p>
                        </details>
                    </div>

                    <div>
                        <label for="feedback_recording_file" class="block text-sm font-medium text-gray-700">Arquivo da transcrição</label>
                        <input
                            id="feedback_recording_file"
                            ref="fileInput"
                            type="file"
                            accept=".txt,.srt,.docx,.pdf"
                            class="mt-1 block w-full text-sm text-slate-700 file:mr-3 file:rounded-lg file:border-0 file:bg-talents-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-talents-700 hover:file:bg-talents-100"
                            @change="onFileChange"
                        />
                        <p v-if="errors.file" class="mt-1 text-sm font-medium text-rose-700" role="alert">{{ errors.file }}</p>
                    </div>

                    <div>
                        <label class="flex items-start gap-2 text-sm text-slate-800">
                            <input
                                v-model="consent"
                                type="checkbox"
                                class="mt-0.5 rounded border-slate-300 text-talents-600 focus:ring-talents-500"
                            />
                            <span>O colaborador foi informado e concordou com a gravação e o processamento por IA.</span>
                        </label>
                        <p v-if="errors.consent" class="mt-1 text-sm font-medium text-rose-700" role="alert">{{ errors.consent }}</p>
                    </div>

                    <button
                        type="submit"
                        class="inline-flex items-center rounded-xl bg-talents-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-talents-700 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="!file || !consent || uploading"
                    >
                        {{ uploading ? 'Enviando…' : 'Enviar transcrição do Plaud' }}
                    </button>
                </form>
            </template>

            <!-- Com gravação -->
            <template v-else>
                <p
                    v-if="!recording.has_speaker_labels"
                    class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
                    role="status"
                    data-feedback-recording-no-speakers
                >
                    Arquivo sem identificação de quem fala; o relatório ficará menos preciso. Exporte novamente com essa opção ligada.
                </p>

                <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Formato</dt>
                        <dd class="mt-1 text-slate-800">{{ recording.format_label }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Enviada</dt>
                        <dd class="mt-1 text-slate-800">
                            {{ formatDateTime(recording.uploaded_at) }}<span v-if="recording.uploaded_by"> · {{ recording.uploaded_by }}</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Consentimento confirmado</dt>
                        <dd class="mt-1 text-slate-800">
                            {{ formatDateTime(recording.consent_at) }}<span v-if="recording.consent_by"> · {{ recording.consent_by }}</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Texto extraído</dt>
                        <dd class="mt-1 text-slate-800">
                            {{ formatNumber(recording.chars) }} caracteres · arquivo de {{ formatSize(recording.size_bytes) }} (descartado)
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Quem fala</dt>
                        <dd class="mt-1 text-slate-800">{{ recording.has_speaker_labels ? 'Identificado' : 'Não identificado' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Marcação de tempo</dt>
                        <dd class="mt-1 text-slate-800">{{ recording.has_timestamps ? 'Sim' : 'Não' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Hash do arquivo (SHA-256)</dt>
                        <dd class="mt-1 break-all font-mono text-xs text-slate-700">{{ recording.sha256 }}</dd>
                    </div>
                </dl>

                <!-- IA (limpeza e relatório): FeedbackRecordingPanel preenche este slot; sem ele, só o original. -->
                <slot name="ai">
                <div v-if="access.canViewOriginal" class="space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            v-if="!original"
                            type="button"
                            class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:opacity-50"
                            :disabled="loadingOriginal"
                            @click="emit('show-original')"
                        >
                            {{ loadingOriginal ? 'Abrindo…' : 'Ver transcrição original' }}
                        </button>
                        <button
                            v-else
                            type="button"
                            class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                            @click="emit('hide-original')"
                        >
                            Ocultar transcrição original
                        </button>
                        <span class="text-xs text-slate-500">Visível só para administradores; cada abertura fica registrada.</span>
                    </div>
                    <p v-if="originalError" class="text-sm font-medium text-rose-700" role="alert">{{ originalError }}</p>
                    <div v-if="original" class="rounded-xl border border-slate-200 bg-slate-50">
                        <p class="border-b border-slate-200 px-4 py-2 text-xs text-slate-500">
                            Original (não editável) · {{ original.filename }}
                        </p>
                        <pre class="max-h-96 overflow-auto whitespace-pre-wrap break-words px-4 py-3 font-sans text-sm leading-relaxed text-slate-800">{{ original.text }}</pre>
                    </div>
                </div>
                <p v-else class="text-sm text-slate-500">O texto original é visível só para administradores.</p>
                </slot>

                <p v-if="access.locked" class="text-xs text-slate-500">Feedback concluído ou cancelado: a gravação não pode ser excluída por aqui.</p>
                <div v-else-if="access.canDelete" class="border-t border-slate-100 pt-4">
                    <button
                        type="button"
                        class="inline-flex items-center rounded-xl border border-rose-200 bg-white px-4 py-2 text-sm font-semibold text-rose-700 shadow-sm transition hover:bg-rose-50"
                        @click="emit('request-delete')"
                    >
                        Excluir gravação
                    </button>
                </div>
            </template>
        </div>
    </FeedbackSectionAccordion>
</template>
