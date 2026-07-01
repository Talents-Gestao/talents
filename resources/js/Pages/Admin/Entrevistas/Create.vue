<script setup>
import FormPageHeader from '@/Components/FormPageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    questionnaires: Array,
    companies: Array,
    maxUploadMb: { type: Number, default: 500 },
});

const defaultQuestionnaireId = computed(() => {
    const def = props.questionnaires?.find((q) => q.is_default);
    return def?.id ?? props.questionnaires?.[0]?.id ?? '';
});

const form = useForm({
    candidate_name: '',
    position_title: '',
    questionnaire_id: defaultQuestionnaireId.value,
    company_id: '',
    audio: null,
});

const uploadProgress = ref(0);
const audioFileName = ref('');

const onFileChange = (event) => {
    const file = event.target.files?.[0] ?? null;
    form.audio = file;
    audioFileName.value = file?.name ?? '';

    if (file && file.size > props.maxUploadMb * 1024 * 1024) {
        form.setError('audio', `O arquivo excede ${props.maxUploadMb} MB.`);
    } else {
        form.clearErrors('audio');
    }
};

const submit = () => {
    form.post(route('admin.entrevistas.store'), {
        forceFormData: true,
        onProgress: (p) => {
            uploadProgress.value = p?.percentage ?? 0;
        },
    });
};
</script>

<template>
    <Head title="Nova entrevista" />

    <AdminLayout>
        <template #header>
            <FormPageHeader
                :back-href="route('admin.entrevistas.index')"
                title="Nova entrevista"
                :subtitle="`Envie a gravação (até ${maxUploadMb} MB). O processamento pode levar vários minutos para áudios longos.`"
            />
        </template>

        <form class="surface-card max-w-2xl space-y-4 p-6" @submit.prevent="submit">
            <div>
                <InputLabel for="candidate_name" value="Nome do candidato" />
                <TextInput id="candidate_name" v-model="form.candidate_name" class="mt-1 block w-full" required />
                <p v-if="form.errors.candidate_name" class="mt-1 text-sm text-red-600">{{ form.errors.candidate_name }}</p>
            </div>

            <div>
                <InputLabel for="position_title" value="Vaga / cargo" />
                <TextInput id="position_title" v-model="form.position_title" class="mt-1 block w-full" />
            </div>

            <div>
                <InputLabel for="questionnaire_id" value="Roteiro" />
                <select
                    id="questionnaire_id"
                    v-model="form.questionnaire_id"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    required
                >
                    <option v-for="q in questionnaires" :key="q.id" :value="q.id">
                        {{ q.name }}{{ q.is_default ? ' (padrão)' : '' }}
                    </option>
                </select>
            </div>

            <div>
                <InputLabel for="company_id" value="Empresa (opcional)" />
                <select
                    id="company_id"
                    v-model="form.company_id"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                >
                    <option value="">— Nenhuma —</option>
                    <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
            </div>

            <div>
                <InputLabel for="audio" value="Arquivo de áudio" />
                <input
                    id="audio"
                    type="file"
                    accept="audio/*,video/mp4,video/webm"
                    class="mt-1 block w-full text-sm text-gray-700"
                    required
                    @change="onFileChange"
                />
                <p v-if="audioFileName" class="mt-1 text-xs text-gray-500">Selecionado: {{ audioFileName }}</p>
                <p v-if="form.errors.audio" class="mt-1 text-sm text-red-600">{{ form.errors.audio }}</p>
                <p class="mt-2 text-xs text-amber-800">
                    Custo estimado Whisper: ~US$ 0,006/min (ex.: 1h ≈ US$ 0,36). Formatos: MP3, M4A, WAV, OGG, WEBM, MP4.
                </p>
            </div>

            <div v-if="form.processing" class="space-y-1">
                <p class="text-sm text-gray-600">Enviando arquivo… {{ Math.round(uploadProgress) }}%</p>
                <div class="h-2 overflow-hidden rounded-full bg-slate-200">
                    <div
                        class="h-full rounded-full bg-talents-600 transition-all"
                        :style="{ width: `${uploadProgress}%` }"
                    />
                </div>
            </div>

            <div class="flex flex-wrap gap-3 pt-2">
                <PrimaryButton :disabled="form.processing">Iniciar processamento</PrimaryButton>
                <Link :href="route('admin.entrevistas.index')">
                    <SecondaryButton type="button">Cancelar</SecondaryButton>
                </Link>
            </div>
        </form>
    </AdminLayout>
</template>
