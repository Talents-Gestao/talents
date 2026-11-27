<script setup>
import InterviewCandidatePicker from '@/Components/Hiring/InterviewCandidatePicker.vue';
import FormPageHeader from '@/Components/FormPageHeader.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { confirmationPayload, confirmationText, isTextSource } from '@/utils/interviewLink';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    questionnaires: { type: Array, default: () => [] },
    sources: { type: Array, default: () => [] },
    preselected: { type: Object, default: null },
    ai_enabled: { type: Boolean, default: false },
    maxUploadMb: { type: Number, default: 500 },
    maxTranscriptKb: { type: Number, default: 2048 },
});

const defaultQuestionnaireId = computed(() => {
    const def = props.questionnaires.find((q) => q.is_default);
    return def?.id ?? props.questionnaires[0]?.id ?? '';
});

const candidate = ref(props.preselected ? { ...props.preselected } : null);
const applicationId = ref(props.preselected?.application_id ? String(props.preselected.application_id) : '');
const confirmed = ref(false);
const textMode = ref('paste');

const form = useForm({
    questionnaire_id: defaultQuestionnaireId.value,
    transcript_source: 'audio_whisper',
    audio: null,
    transcript_file: null,
    transcript_text: '',
});

const isText = computed(() => isTextSource(props.sources, form.transcript_source));
const allowsPaste = computed(() => ['manual_text', 'plaud'].includes(form.transcript_source));
const allowsFile = computed(() => ['file_upload', 'plaud'].includes(form.transcript_source));
const fileAccept = computed(() => (form.transcript_source === 'plaud' ? '.txt,text/plain' : '.txt,.vtt,text/plain,text/vtt'));

watch(
    () => form.transcript_source,
    (value) => {
        textMode.value = value === 'file_upload' ? 'file' : 'paste';
        form.clearErrors();
    },
);
// Trocar candidato ou vaga desfaz a confirmação.
watch([candidate, applicationId], () => {
    confirmed.value = false;
});

const confirmation = computed(() => confirmationText(candidate.value, applicationId.value));
const canSubmit = computed(() => Boolean(candidate.value) && (!isText.value || confirmed.value));

const uploadProgress = ref(0);

const onAudio = (event) => {
    const file = event.target.files?.[0] ?? null;
    form.audio = file;
    if (file && file.size > props.maxUploadMb * 1024 * 1024) {
        form.setError('audio', `O arquivo excede ${props.maxUploadMb} MB.`);
    } else {
        form.clearErrors('audio');
    }
};

const onTranscriptFile = (event) => {
    const file = event.target.files?.[0] ?? null;
    form.transcript_file = file;
    if (file && file.size > props.maxTranscriptKb * 1024) {
        form.setError('transcript_file', 'O arquivo de transcrição pode ter até 2 MB.');
    } else {
        form.clearErrors('transcript_file');
    }
};

const submit = () => {
    if (!canSubmit.value) {
        return;
    }
    form.transform((data) => {
        const payload = {
            questionnaire_id: data.questionnaire_id,
            transcript_source: data.transcript_source,
            talent_candidate_id: candidate.value?.id,
            hiring_application_id: applicationId.value || null,
        };
        if (isText.value) {
            Object.assign(payload, confirmationPayload(candidate.value, applicationId.value));
            if (textMode.value === 'file' && allowsFile.value) {
                payload.transcript_file = data.transcript_file;
            } else {
                payload.transcript_text = data.transcript_text;
            }
        } else {
            payload.audio = data.audio;
        }
        return payload;
    }).post(route('admin.entrevistas.store'), {
        forceFormData: true,
        onProgress: (p) => {
            uploadProgress.value = p?.percentage ?? 0;
        },
    });
};

const fieldClass =
    'mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/70';
</script>

<template>
    <Head title="Nova entrevista" />

    <AdminLayout>
        <template #header>
            <FormPageHeader
                :back-href="route('admin.entrevistas.index')"
                title="Nova entrevista"
                subtitle="Vincule a entrevista a um candidato cadastrado e escolha de onde vem a transcrição."
            />
        </template>

        <form class="surface-card max-w-3xl space-y-6 p-6" @submit.prevent="submit">
            <section aria-labelledby="link-title" class="space-y-3">
                <h3 id="link-title" class="text-base font-semibold text-talents-900">Candidato e vaga</h3>
                <InterviewCandidatePicker
                    v-model:candidate="candidate"
                    v-model:application-id="applicationId"
                    id-prefix="create"
                    :error="form.errors.talent_candidate_id"
                    :application-error="form.errors.hiring_application_id"
                />
            </section>

            <div>
                <InputLabel for="questionnaire_id" value="Roteiro" />
                <select id="questionnaire_id" v-model="form.questionnaire_id" :class="fieldClass" required>
                    <option v-for="q in questionnaires" :key="q.id" :value="q.id">
                        {{ q.name }} · v{{ q.version }}{{ q.is_default ? ' (padrão)' : '' }}
                    </option>
                </select>
                <InputError class="mt-1" :message="form.errors.questionnaire_id" />
            </div>

            <fieldset>
                <legend class="text-sm font-medium text-slate-700">Fonte da transcrição</legend>
                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                    <label
                        v-for="s in sources"
                        :key="s.value"
                        class="flex cursor-pointer items-center gap-2 rounded-xl border px-3 py-2 text-sm"
                        :class="form.transcript_source === s.value ? 'border-talents-400 bg-talents-50 text-talents-900' : 'border-slate-200 text-slate-700'"
                    >
                        <input v-model="form.transcript_source" type="radio" :value="s.value" class="text-talents-600 focus:ring-talents-500" />
                        {{ s.label }}
                    </label>
                </div>
                <InputError class="mt-1" :message="form.errors.transcript_source" />
                <p class="mt-2 text-xs text-slate-500">
                    <template v-if="ai_enabled">
                        A IA sugere respostas a partir do texto, sempre para revisão humana. Fontes de texto não passam pelo Whisper.
                    </template>
                    <template v-else>
                        A IA está desligada nas configurações: a entrevista fica pronta para preenchimento manual das respostas.
                    </template>
                </p>
            </fieldset>

            <div v-if="!isText">
                <InputLabel for="audio" value="Arquivo de áudio" />
                <input
                    id="audio"
                    type="file"
                    accept="audio/*,video/mp4,video/webm"
                    class="mt-1 block w-full text-sm text-slate-700"
                    @change="onAudio"
                />
                <InputError class="mt-1" :message="form.errors.audio" />
                <p class="mt-2 text-xs text-amber-800">
                    Até {{ maxUploadMb }} MB. Custo estimado Whisper: ~US$ 0,006/min (ex.: 1h ≈ US$ 0,36). Formatos: MP3, M4A, WAV, OGG, WEBM, MP4.
                </p>
            </div>

            <div v-else class="space-y-3">
                <div v-if="allowsPaste && allowsFile" class="flex gap-1 rounded-xl bg-slate-100 p-1 text-sm font-semibold" role="group" aria-label="Como enviar o texto">
                    <button
                        type="button"
                        class="flex-1 rounded-lg px-3 py-1.5"
                        :class="textMode === 'paste' ? 'bg-white text-talents-800 shadow-sm' : 'text-slate-600'"
                        :aria-pressed="textMode === 'paste'"
                        @click="textMode = 'paste'"
                    >
                        Colar texto
                    </button>
                    <button
                        type="button"
                        class="flex-1 rounded-lg px-3 py-1.5"
                        :class="textMode === 'file' ? 'bg-white text-talents-800 shadow-sm' : 'text-slate-600'"
                        :aria-pressed="textMode === 'file'"
                        @click="textMode = 'file'"
                    >
                        Enviar TXT
                    </button>
                </div>

                <div v-if="textMode === 'paste' && allowsPaste">
                    <InputLabel for="transcript_text" value="Texto da entrevista" />
                    <textarea id="transcript_text" v-model="form.transcript_text" rows="10" :class="fieldClass" />
                    <InputError class="mt-1" :message="form.errors.transcript_text" />
                </div>
                <div v-else>
                    <InputLabel
                        for="transcript_file"
                        :value="form.transcript_source === 'plaud' ? 'TXT exportado do Plaud (até 2 MB)' : 'Arquivo TXT ou VTT (até 2 MB)'"
                    />
                    <input id="transcript_file" type="file" :accept="fileAccept" class="mt-1 block w-full text-sm text-slate-700" @change="onTranscriptFile" />
                    <p v-if="form.transcript_source === 'file_upload'" class="mt-1 text-xs text-slate-500">
                        VTT é convertido em texto sem as marcações de tempo.
                    </p>
                    <InputError class="mt-1" :message="form.errors.transcript_file" />
                </div>
                <InputError :message="form.errors.transcript" />

                <div v-if="candidate" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
                    <p class="text-sm font-semibold text-amber-900">Confira antes de gravar o texto</p>
                    <label class="mt-2 flex items-start gap-2 text-sm text-amber-900">
                        <input
                            v-model="confirmed"
                            type="checkbox"
                            class="mt-0.5 h-4 w-4 rounded border-amber-300 text-talents-600 focus:ring-talents-500"
                        />
                        {{ confirmation }}
                    </label>
                    <InputError class="mt-1" :message="form.errors.confirm" />
                </div>
            </div>

            <div v-if="form.processing" class="space-y-1">
                <p class="text-sm text-slate-600">Enviando… {{ Math.round(uploadProgress) }}%</p>
                <div class="h-2 overflow-hidden rounded-full bg-slate-200">
                    <div class="h-full rounded-full bg-talents-600 transition-all" :style="{ width: `${uploadProgress}%` }" />
                </div>
            </div>

            <div class="flex flex-wrap gap-3 pt-2">
                <PrimaryButton :disabled="form.processing || !canSubmit">Criar entrevista</PrimaryButton>
                <Link :href="route('admin.entrevistas.index')">
                    <SecondaryButton type="button">Cancelar</SecondaryButton>
                </Link>
            </div>
            <p v-if="!candidate" class="text-xs text-slate-500">Escolha o candidato para continuar.</p>
            <p v-else-if="isText && !confirmed" class="text-xs text-slate-500">Confirme o candidato e a vaga para gravar o texto.</p>
        </form>
    </AdminLayout>
</template>
