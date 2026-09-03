<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { confirmDialog } from '@/composables/useConfirmDialog';

const props = defineProps({
    meeting: Object,
    maxUploadMb: { type: Number, default: 500 },
});

const activeTab = ref('ata');
const isRecording = ref(false);
const isUploading = ref(false);
const recorderError = ref('');
const elapsedSeconds = ref(0);
const uploadError = ref('');

let mediaRecorder = null;
let mediaStream = null;
let recordedChunks = [];
let timerInterval = null;
let pollTimer = null;
let recordingStartedAt = 0;

const minutesForm = useForm({
    minutes_text: props.meeting.minutes_text ?? '',
});

watch(
    () => props.meeting.minutes_text,
    (value) => {
        if (!minutesForm.isDirty) {
            minutesForm.minutes_text = value ?? '';
        }
    },
);

const statusClass = (value) => {
    const map = {
        draft: 'bg-slate-100 text-slate-800',
        queued: 'bg-slate-100 text-slate-800',
        transcribing: 'bg-amber-100 text-amber-900',
        generating_minutes: 'bg-blue-100 text-blue-900',
        completed: 'bg-emerald-100 text-emerald-900',
        failed: 'bg-red-100 text-red-900',
    };
    return map[value] ?? 'bg-slate-100 text-slate-800';
};

const formattedElapsed = computed(() => {
    const total = elapsedSeconds.value;
    const h = Math.floor(total / 3600);
    const m = Math.floor((total % 3600) / 60);
    const s = total % 60;
    if (h > 0) {
        return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
    }
    return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
});

const pickMimeType = () => {
    const candidates = ['audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus', 'audio/mp4'];
    for (const type of candidates) {
        if (window.MediaRecorder && MediaRecorder.isTypeSupported(type)) {
            return type;
        }
    }
    return '';
};

const clearTimer = () => {
    if (timerInterval) {
        clearInterval(timerInterval);
        timerInterval = null;
    }
};

const stopMediaTracks = () => {
    if (mediaStream) {
        mediaStream.getTracks().forEach((track) => track.stop());
        mediaStream = null;
    }
};

const startRecording = async () => {
    recorderError.value = '';
    uploadError.value = '';

    if (!navigator.mediaDevices?.getUserMedia || !window.MediaRecorder) {
        recorderError.value = 'Seu navegador não suporta gravação de áudio nesta página.';
        return;
    }

    try {
        mediaStream = await navigator.mediaDevices.getUserMedia({ audio: true });
        recordedChunks = [];
        const mimeType = pickMimeType();
        mediaRecorder = mimeType
            ? new MediaRecorder(mediaStream, { mimeType })
            : new MediaRecorder(mediaStream);

        mediaRecorder.ondataavailable = (event) => {
            if (event.data && event.data.size > 0) {
                recordedChunks.push(event.data);
            }
        };

        mediaRecorder.onstop = () => {
            clearTimer();
            stopMediaTracks();
            const blobType = mediaRecorder?.mimeType || mimeType || 'audio/webm';
            const blob = new Blob(recordedChunks, { type: blobType });
            mediaRecorder = null;
            void uploadRecording(blob);
        };

        mediaRecorder.start(1000);
        isRecording.value = true;
        recordingStartedAt = Date.now();
        elapsedSeconds.value = 0;
        clearTimer();
        timerInterval = setInterval(() => {
            elapsedSeconds.value = Math.floor((Date.now() - recordingStartedAt) / 1000);
        }, 250);
    } catch (error) {
        stopMediaTracks();
        recorderError.value =
            error?.name === 'NotAllowedError'
                ? 'Permissão do microfone negada. Libere o acesso nas configurações do navegador.'
                : 'Não foi possível iniciar a gravação. Verifique o microfone.';
    }
};

const stopRecording = () => {
    if (!mediaRecorder || mediaRecorder.state === 'inactive') {
        return;
    }
    isRecording.value = false;
    mediaRecorder.stop();
};

const uploadRecording = (blob) => {
    const maxBytes = props.maxUploadMb * 1024 * 1024;
    if (blob.size > maxBytes) {
        uploadError.value = `A gravação excede ${props.maxUploadMb} MB.`;
        return;
    }

    const extension = blob.type.includes('ogg') ? 'ogg' : blob.type.includes('mp4') ? 'm4a' : 'webm';
    const file = new File([blob], `reuniao.${extension}`, { type: blob.type || 'audio/webm' });
    const duration = Math.max(1, Math.round((Date.now() - recordingStartedAt) / 1000));

    isUploading.value = true;
    uploadError.value = '';

    router.post(
        route('admin.reunioes.audio.store', props.meeting.id),
        {
            audio: file,
            duration_seconds: duration,
        },
        {
            forceFormData: true,
            preserveScroll: true,
            onError: (errors) => {
                uploadError.value = errors.audio || 'Falha ao enviar o áudio.';
            },
            onFinish: () => {
                isUploading.value = false;
            },
        },
    );
};

const saveMinutes = () => {
    minutesForm.patch(route('admin.reunioes.minutes.update', props.meeting.id), {
        preserveScroll: true,
    });
};

const reprocess = async () => {
    if (!(await confirmDialog('Reprocessar esta reunião? A ata e a transcrição atuais serão substituídas.'))) {
        return;
    }
    router.post(route('admin.reunioes.reprocess', props.meeting.id), {}, { preserveScroll: true });
};

const destroyMeeting = async () => {
    if (!(await confirmDialog('Excluir esta reunião permanentemente?'))) {
        return;
    }
    router.delete(route('admin.reunioes.destroy', props.meeting.id));
};

const startPollingIfNeeded = () => {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
    if (!props.meeting.is_processing) {
        return;
    }
    pollTimer = setInterval(() => {
        router.reload({ only: ['meeting'], preserveScroll: true });
    }, 5000);
};

onMounted(startPollingIfNeeded);
watch(
    () => props.meeting.is_processing,
    () => startPollingIfNeeded(),
);
onUnmounted(() => {
    if (pollTimer) {
        clearInterval(pollTimer);
    }
    clearTimer();
    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
        mediaRecorder.stop();
    }
    stopMediaTracks();
});
</script>

<template>
    <Head :title="`Reunião — ${meeting.title}`" />

    <AdminLayout>
        <template #header>
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-sm text-gray-500">
                        <Link :href="route('admin.reunioes.index')" class="text-talents-700 hover:underline">
                            Reuniões
                        </Link>
                        / {{ meeting.title }}
                    </p>
                    <h2 class="text-xl font-semibold leading-tight text-gray-900">{{ meeting.title }}</h2>
                    <p v-if="meeting.company" class="mt-1 text-sm text-gray-600">
                        Empresa: {{ meeting.company.name }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <SecondaryButton
                        v-if="meeting.has_audio && !meeting.is_processing"
                        type="button"
                        @click="reprocess"
                    >
                        Reprocessar
                    </SecondaryButton>
                    <SecondaryButton type="button" class="!text-red-700 ring-red-200" @click="destroyMeeting">
                        Excluir
                    </SecondaryButton>
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

        <div class="mb-6 surface-card p-4">
            <div class="flex flex-wrap items-center gap-3">
                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium" :class="statusClass(meeting.status)">
                    {{ meeting.status_label }}
                </span>
                <span v-if="meeting.duration_seconds" class="text-sm text-gray-600">
                    Duração: {{ Math.floor(meeting.duration_seconds / 60) }}min
                    {{ meeting.duration_seconds % 60 }}s
                </span>
                <span v-if="meeting.created_by" class="text-sm text-gray-600">
                    Por: {{ meeting.created_by.name }}
                </span>
            </div>
            <p v-if="meeting.participants_text" class="mt-2 text-sm text-gray-700">
                Participantes: {{ meeting.participants_text }}
            </p>
            <p v-if="meeting.is_processing" class="mt-3 text-sm text-amber-800">
                Processamento em andamento… esta página atualiza automaticamente a cada 5 segundos.
            </p>
            <p
                v-if="meeting.failure_reason"
                class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-900"
            >
                {{ meeting.failure_reason }}
            </p>
        </div>

        <div v-if="meeting.can_receive_audio" class="mb-6 surface-card p-5">
            <h3 class="text-base font-semibold text-gray-900">Gravação</h3>
            <p class="mt-1 text-sm text-gray-600">
                Ao parar, o áudio é enviado e a ata é gerada automaticamente (até {{ maxUploadMb }} MB).
            </p>

            <div class="mt-4 flex flex-wrap items-center gap-3">
                <p class="font-mono text-2xl tabular-nums text-talents-900">{{ formattedElapsed }}</p>
                <PrimaryButton
                    v-if="!isRecording"
                    type="button"
                    :disabled="isUploading"
                    @click="startRecording"
                >
                    Iniciar gravação
                </PrimaryButton>
                <SecondaryButton v-else type="button" class="!text-red-700 ring-red-200" @click="stopRecording">
                    Parar e gerar ata
                </SecondaryButton>
                <span v-if="isUploading" class="text-sm text-amber-800">Enviando áudio…</span>
            </div>

            <p v-if="recorderError" class="mt-3 text-sm text-red-700">{{ recorderError }}</p>
            <p v-if="uploadError" class="mt-3 text-sm text-red-700">{{ uploadError }}</p>
            <p
                v-if="meeting.has_audio && meeting.status !== 'draft'"
                class="mt-3 text-xs text-slate-500"
            >
                Já existe uma gravação. Uma nova gravação substitui a anterior e reinicia o processamento.
            </p>
        </div>

        <div class="mb-4 flex gap-2 border-b border-slate-200">
            <button
                type="button"
                class="px-3 py-2 text-sm font-medium"
                :class="
                    activeTab === 'ata'
                        ? 'border-b-2 border-talents-600 text-talents-800'
                        : 'text-slate-500 hover:text-slate-800'
                "
                @click="activeTab = 'ata'"
            >
                Ata
            </button>
            <button
                type="button"
                class="px-3 py-2 text-sm font-medium"
                :class="
                    activeTab === 'transcricao'
                        ? 'border-b-2 border-talents-600 text-talents-800'
                        : 'text-slate-500 hover:text-slate-800'
                "
                @click="activeTab = 'transcricao'"
            >
                Transcrição
            </button>
        </div>

        <div v-show="activeTab === 'ata'" class="surface-card p-5">
            <form class="space-y-3" @submit.prevent="saveMinutes">
                <textarea
                    v-model="minutesForm.minutes_text"
                    rows="18"
                    class="block w-full rounded-md border-gray-300 font-mono text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    :placeholder="
                        meeting.is_processing
                            ? 'A ata será preenchida automaticamente…'
                            : 'A ata aparecerá aqui após o processamento. Você pode editar e salvar.'
                    "
                    :disabled="meeting.is_processing && !meeting.minutes_text"
                />
                <InputError :message="minutesForm.errors.minutes_text" />
                <PrimaryButton
                    type="submit"
                    :disabled="minutesForm.processing || !minutesForm.minutes_text"
                >
                    Salvar ata
                </PrimaryButton>
            </form>
        </div>

        <div v-show="activeTab === 'transcricao'" class="surface-card p-5">
            <pre
                v-if="meeting.transcript_text"
                class="whitespace-pre-wrap font-sans text-sm leading-relaxed text-slate-800"
            >{{ meeting.transcript_text }}</pre>
            <p v-else class="text-sm italic text-slate-500">
                {{
                    meeting.is_processing
                        ? 'Transcrição em andamento…'
                        : 'Nenhuma transcrição disponível ainda.'
                }}
            </p>
        </div>
    </AdminLayout>
</template>
