<script setup>
import FormPageHeader from '@/Components/FormPageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AttachmentList from '@/Components/StrategicCalendar/AttachmentList.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { PlusIcon } from '@heroicons/vue/24/outline';
import { computed, ref } from 'vue';

const props = defineProps({
    item: Object,
    companies: Array,
    kinds: Array,
    recurrences: Array,
    maxAttachmentMb: { type: Number, default: 512 },
});

const occursOn =
    typeof props.item.occurs_on === 'string'
        ? props.item.occurs_on.slice(0, 10)
        : props.item.occurs_on;

const recurrenceEndsOn = props.item.recurrence_ends_on
    ? String(props.item.recurrence_ends_on).slice(0, 10)
    : '';

const form = useForm({
    title: props.item.title,
    description: props.item.description ?? '',
    kind: props.item.kind,
    occurs_on: occursOn,
    recurrence: props.item.recurrence ?? '',
    recurrence_ends_on: recurrenceEndsOn,
    company_id: props.item.company_id ? String(props.item.company_id) : '',
});

const showRecurrenceEnd = computed(() => Boolean(form.recurrence));
const attachments = computed(() => props.item.attachments ?? []);
const uploadProgress = ref(0);
const uploadProcessing = ref(false);
const uploadError = ref('');

const submit = () => {
    form.transform((data) => ({
        ...data,
        company_id: data.company_id || null,
        recurrence: data.recurrence || null,
        recurrence_ends_on: data.recurrence ? data.recurrence_ends_on || null : null,
    })).put(route('admin.strategic-calendar.update', props.item.id));
};

function uploadAttachments(event) {
    const files = event.target.files;
    if (!files?.length) return;

    uploadError.value = '';
    const maxBytes = props.maxAttachmentMb * 1024 * 1024;
    const oversized = Array.from(files).find((file) => file.size > maxBytes);
    if (oversized) {
        uploadError.value = `O arquivo "${oversized.name}" excede ${props.maxAttachmentMb} MB.`;
        event.target.value = '';
        return;
    }

    const fd = new FormData();
    for (const file of files) {
        fd.append('files[]', file);
    }

    uploadProcessing.value = true;
    uploadProgress.value = 0;

    router.post(route('admin.strategic-calendar.attachments.store', props.item.id), fd, {
        forceFormData: true,
        preserveScroll: true,
        onProgress: (p) => {
            uploadProgress.value = p?.percentage ?? 0;
        },
        onFinish: () => {
            uploadProcessing.value = false;
            uploadProgress.value = 0;
        },
        onError: (errors) => {
            const first = Object.values(errors ?? {})[0];
            uploadError.value = Array.isArray(first) ? first[0] : String(first ?? 'Falha ao enviar anexo(s).');
        },
    });

    event.target.value = '';
}

function destroyAttachment(attachmentId) {
    if (!window.confirm('Remover este anexo?')) return;

    router.delete(route('admin.strategic-calendar.attachment.destroy', attachmentId), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Editar item — Calendário estratégico" />

    <AdminLayout>
        <template #header>
            <FormPageHeader
                :back-href="route('admin.strategic-calendar.index')"
                title="Editar evento ou rito"
            />
        </template>

        <form class="surface-card max-w-2xl space-y-4 p-6 text-slate-900" @submit.prevent="submit">
            <div>
                <InputLabel for="title" value="Nome" />
                <TextInput id="title" v-model="form.title" class="mt-1 block w-full" required />
            </div>
            <div>
                <InputLabel for="kind" value="Tipo" />
                <select
                    id="kind"
                    v-model="form.kind"
                    class="mt-1 block w-full rounded-md border border-gray-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                >
                    <option v-for="k in kinds" :key="k.value" :value="k.value">{{ k.label }}</option>
                </select>
            </div>
            <div>
                <InputLabel for="occurs_on" value="Data inicial" />
                <TextInput id="occurs_on" v-model="form.occurs_on" type="date" class="mt-1 block w-full max-w-[12rem]" required />
            </div>
            <div>
                <InputLabel for="recurrence" value="Repetição" />
                <select
                    id="recurrence"
                    v-model="form.recurrence"
                    class="mt-1 block w-full rounded-md border border-gray-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                >
                    <option value="">Não se repete</option>
                    <option v-for="r in recurrences" :key="r.value" :value="r.value">{{ r.label }}</option>
                </select>
            </div>
            <div v-if="showRecurrenceEnd">
                <InputLabel for="recurrence_ends_on" value="Repetir até (opcional)" />
                <TextInput
                    id="recurrence_ends_on"
                    v-model="form.recurrence_ends_on"
                    type="date"
                    class="mt-1 block w-full max-w-[12rem]"
                />
            </div>
            <div>
                <InputLabel for="description" value="Como fazer (orientações)" />
                <textarea
                    id="description"
                    v-model="form.description"
                    rows="6"
                    class="mt-1 block w-full rounded-md border border-gray-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                />
            </div>
            <div>
                <InputLabel value="Anexos" />
                <AttachmentList
                    v-if="attachments.length"
                    class="mt-2"
                    :attachments="attachments"
                    :link-prefix="''"
                    removable
                    @remove="destroyAttachment"
                />
                <p v-else class="mt-2 text-sm text-slate-500">Nenhum anexo enviado.</p>
                <label class="mt-3 inline-flex cursor-pointer items-center gap-2 text-sm font-medium text-talents-700 hover:text-talents-800">
                    <PlusIcon class="h-4 w-4" aria-hidden="true" />
                    Adicionar anexos
                    <input
                        type="file"
                        multiple
                        accept="application/pdf,image/*,video/*,.doc,.docx,.xls,.xlsx,.ppt,.pptx"
                        class="sr-only"
                        :disabled="uploadProcessing"
                        @change="uploadAttachments"
                    />
                </label>
                <p class="mt-1 text-xs text-gray-500">
                    PDF, imagens, documentos ou vídeos (máx. {{ maxAttachmentMb }} MB cada).
                </p>
                <p v-if="uploadError" class="mt-1 text-sm text-red-600">{{ uploadError }}</p>
                <div v-if="uploadProcessing" class="mt-2 space-y-1">
                    <p class="text-sm text-gray-600">Enviando arquivo… {{ Math.round(uploadProgress) }}%</p>
                    <div class="h-2 overflow-hidden rounded-full bg-slate-200">
                        <div
                            class="h-full rounded-full bg-talents-600 transition-all"
                            :style="{ width: `${uploadProgress}%` }"
                        />
                    </div>
                </div>
            </div>
            <div>
                <InputLabel for="company_id" value="Empresa (opcional)" />
                <p class="mt-0.5 text-xs text-gray-500">Em branco = todas as empresas com o módulo habilitado.</p>
                <select
                    id="company_id"
                    v-model="form.company_id"
                    class="mt-1 block w-full rounded-md border border-gray-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                >
                    <option value="">Todas</option>
                    <option v-for="c in companies" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
                </select>
            </div>
            <PrimaryButton :disabled="form.processing">Atualizar</PrimaryButton>
        </form>
    </AdminLayout>
</template>
