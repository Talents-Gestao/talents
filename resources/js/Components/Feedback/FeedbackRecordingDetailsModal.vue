<script setup>
import Modal from '@/Components/Modal.vue';
import { formatDateTime } from '@/utils/dateOnly';
import { LockClosedIcon, XMarkIcon } from '@heroicons/vue/24/outline';

/**
 * «Detalhes técnicos e privacidade» da gravação (só apresentação), fora da tela principal: formato, quem enviou e
 * quando, consentimento, texto extraído, quem fala, marcação de tempo, hash e o que é guardado. Abre pelo menu
 * «Ações» ou pelo cadeado «Privacidade». O <dialog> nativo (Modal) prende o foco e fecha com Esc.
 */
defineProps({
    show: { type: Boolean, default: false },
    /** Metadados da gravação (FeedbackRecordingAccess::payload); sem gravação, só a privacidade. */
    recording: { type: Object, default: null },
});

const emit = defineEmits(['close']);

const formatNumber = (n) => (n == null ? '—' : Number(n).toLocaleString('pt-BR'));
const formatSize = (bytes) => {
    if (bytes == null) return '—';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toLocaleString('pt-BR', { maximumFractionDigits: 1 })} KB`;
    return `${(bytes / 1024 / 1024).toLocaleString('pt-BR', { maximumFractionDigits: 1 })} MB`;
};
</script>

<template>
    <Modal :show="show" max-width="lg" aria-labelledby="feedback_recording_details_title" @close="emit('close')">
        <div class="max-h-[85vh] overflow-y-auto p-5 sm:p-6" data-feedback-recording-details>
            <div class="flex items-start justify-between gap-3">
                <h2 id="feedback_recording_details_title" class="text-lg font-semibold text-slate-900">
                    {{ recording ? 'Detalhes técnicos e privacidade' : 'Privacidade' }}
                </h2>
                <button
                    type="button"
                    class="-mr-2 -mt-2 inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500"
                    aria-label="Fechar"
                    @click="emit('close')"
                >
                    <XMarkIcon class="h-5 w-5" aria-hidden="true" />
                </button>
            </div>

            <dl v-if="recording" class="mt-4 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                <div>
                    <dt class="font-semibold text-slate-700">Formato</dt>
                    <dd class="text-slate-900">{{ recording.format_label }}</dd>
                </div>
                <div>
                    <dt class="font-semibold text-slate-700">Enviada</dt>
                    <dd class="text-slate-900">{{ formatDateTime(recording.uploaded_at) }}<span v-if="recording.uploaded_by"> por {{ recording.uploaded_by }}</span></dd>
                </div>
                <div>
                    <dt class="font-semibold text-slate-700">Consentimento</dt>
                    <dd class="text-slate-900">{{ formatDateTime(recording.consent_at) }}<span v-if="recording.consent_by"> por {{ recording.consent_by }}</span></dd>
                </div>
                <div>
                    <dt class="font-semibold text-slate-700">Texto extraído</dt>
                    <dd class="text-slate-900">{{ formatNumber(recording.chars) }} caracteres · arquivo de {{ formatSize(recording.size_bytes) }}, descartado</dd>
                </div>
                <div>
                    <dt class="font-semibold text-slate-700">Quem fala</dt>
                    <dd class="text-slate-900">{{ recording.has_speaker_labels ? 'Identificado' : 'Não identificado' }}</dd>
                </div>
                <div>
                    <dt class="font-semibold text-slate-700">Marcação de tempo</dt>
                    <dd class="text-slate-900">{{ recording.has_timestamps ? 'Sim' : 'Não' }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="font-semibold text-slate-700">Hash do arquivo (SHA-256)</dt>
                    <dd class="break-all font-mono text-[13px] text-slate-900">{{ recording.sha256 }}</dd>
                </div>
            </dl>

            <section class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm" aria-labelledby="feedback_recording_privacy_title" data-feedback-recording-privacy>
                <h3 id="feedback_recording_privacy_title" class="flex items-start gap-2 font-semibold text-slate-900">
                    <LockClosedIcon class="mt-0.5 h-4 w-4 shrink-0 text-slate-600" aria-hidden="true" />
                    Guardamos só o texto da transcrição, criptografado; o arquivo é apagado logo após a leitura.
                </h3>
                <ul class="mt-2 list-disc space-y-1.5 pl-5 leading-relaxed text-slate-800">
                    <li>Guardado: o texto extraído (criptografado), o hash e o tamanho do arquivo, quem enviou e quem confirmou o consentimento.</li>
                    <li>Original: só administradores da empresa e a Talents veem; cada abertura fica registrada.</li>
                    <li>Líder do feedback: vê a transcrição limpa e o relatório, não o original. O colaborador não vê nada disso.</li>
                    <li>IA: só depois que a Talents aprova o provedor. «Reflexão do líder» nunca é enviada.</li>
                    <li>Excluir o feedback, o modelo ou a gravação exclui também o texto guardado.</li>
                </ul>
            </section>

            <div class="mt-5 flex justify-end">
                <button
                    type="button"
                    class="inline-flex min-h-[44px] w-full items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500 focus-visible:ring-offset-2 sm:w-auto"
                    @click="emit('close')"
                >
                    Fechar
                </button>
            </div>
        </div>
    </Modal>
</template>
