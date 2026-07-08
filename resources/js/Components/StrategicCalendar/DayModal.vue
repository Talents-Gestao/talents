<script setup>
import Modal from '@/Components/Modal.vue';
import AttachmentList from '@/Components/StrategicCalendar/AttachmentList.vue';
import DateRangeFields from '@/Components/StrategicCalendar/DateRangeFields.vue';
import CompanyAudienceMultiSelect from '@/Components/StrategicCalendar/CompanyAudienceMultiSelect.vue';
import StrategicKindBadge from '@/Components/StrategicKindBadge.vue';
import { formatStrategicCalendarDateRange } from '@/utils/strategicCalendarDate';
import { router, useForm } from '@inertiajs/vue3';
import { PlusIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    iso: { type: String, default: null },
    items: { type: Array, default: () => [] },
    companies: { type: Array, default: () => [] },
    kinds: { type: Array, default: () => [] },
    recurrences: { type: Array, default: () => [] },
    kindLabels: { type: Object, default: () => ({}) },
    recurrenceLabels: { type: Object, default: () => ({}) },
    maxAttachmentMb: { type: Number, default: 512 },
});

const emit = defineEmits(['close']);

const showCreateForm = ref(false);
const editingSourceId = ref(null);
const uploadProgress = ref(0);
const uploadProcessing = ref(false);
const uploadError = ref('');

const createForm = useForm({
    title: '',
    description: '',
    kind: props.kinds[0]?.value ?? 'event',
    occurs_on: '',
    ends_on: '',
    recurrence: '',
    recurrence_ends_on: '',
    company_ids: [],
});

const editForm = useForm({
    title: '',
    description: '',
    kind: 'event',
    occurs_on: '',
    ends_on: '',
    recurrence: '',
    recurrence_ends_on: '',
    company_ids: [],
});

const dayLabel = computed(() => {
    if (!props.iso) return '';
    return new Date(props.iso + 'T12:00:00').toLocaleDateString('pt-BR', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
});

const showCreateRecurrenceEnd = computed(() => Boolean(createForm.recurrence));
const showEditRecurrenceEnd = computed(() => Boolean(editForm.recurrence));

watch(
    () => createForm.recurrence,
    (value) => {
        if (value) createForm.ends_on = '';
    },
);

watch(
    () => createForm.ends_on,
    (value) => {
        if (value) {
            createForm.recurrence = '';
            createForm.recurrence_ends_on = '';
        }
    },
);

watch(
    () => editForm.recurrence,
    (value) => {
        if (value) editForm.ends_on = '';
    },
);

watch(
    () => editForm.ends_on,
    (value) => {
        if (value) {
            editForm.recurrence = '';
            editForm.recurrence_ends_on = '';
        }
    },
);

const uniqueDayItems = computed(() => {
    const seen = new Set();
    const out = [];
    for (const item of props.items) {
        const sid = item.source_id ?? item.id;
        if (seen.has(sid)) continue;
        seen.add(sid);
        out.push(item);
    }
    return out;
});

watch(
    () => [props.show, props.iso],
    ([open, iso]) => {
        if (open && iso) {
            createForm.reset();
            createForm.kind = props.kinds[0]?.value ?? 'event';
            createForm.occurs_on = iso;
            showCreateForm.value = uniqueDayItems.value.length === 0;
            editingSourceId.value = null;
        }
    },
);

function close() {
    emit('close');
}

function kindLabel(kind) {
    return props.kindLabels[kind] ?? kind;
}

function startEdit(item) {
    const sid = item.source_id ?? item.id;
    editingSourceId.value = sid;
    showCreateForm.value = false;
    editForm.title = item.title ?? '';
    editForm.description = item.description ?? '';
    editForm.kind = item.kind ?? 'event';
    editForm.occurs_on = item.range_starts_on?.slice?.(0, 10)
        ?? item.occurs_on?.slice?.(0, 10)
        ?? props.iso
        ?? '';
    editForm.ends_on = item.ends_on?.slice?.(0, 10) ?? '';
    editForm.recurrence = item.recurrence ?? '';
    editForm.recurrence_ends_on = item.recurrence_ends_on?.slice?.(0, 10) ?? item.recurrence_ends_on ?? '';
    editForm.company_ids = audienceCompanyIds(item);
    editForm.clearErrors();
}

function cancelEdit() {
    editingSourceId.value = null;
    editForm.reset();
    editForm.clearErrors();
}

function audienceCompanyIds(item) {
    if (item.companies?.length) {
        return item.companies.map((company) => company.id);
    }

    if (item.company_id) {
        return [item.company_id];
    }

    return [];
}

function audienceLabel(item) {
    if (item.audience_label) {
        return item.audience_label;
    }

    if (item.companies?.length) {
        return item.companies.map((company) => company.name).join(', ');
    }

    return item.company?.name ?? 'Todas as empresas';
}

function submitCreate() {
    createForm
        .transform((data) => ({
            ...data,
            company_ids: data.company_ids ?? [],
            recurrence: data.recurrence || null,
            recurrence_ends_on: data.recurrence ? data.recurrence_ends_on || null : null,
            ends_on: data.recurrence ? null : data.ends_on || null,
        }))
        .post(route('admin.strategic-calendar.store'), {
            preserveScroll: true,
            onSuccess: () => {
                createForm.reset();
                createForm.kind = props.kinds[0]?.value ?? 'event';
                createForm.occurs_on = props.iso ?? '';
                showCreateForm.value = false;
            },
        });
}

function submitEdit(sourceId) {
    editForm
        .transform((data) => ({
            ...data,
            company_ids: data.company_ids ?? [],
            recurrence: data.recurrence || null,
            recurrence_ends_on: data.recurrence ? data.recurrence_ends_on || null : null,
            ends_on: data.recurrence ? null : data.ends_on || null,
        }))
        .put(route('admin.strategic-calendar.update', sourceId), {
            preserveScroll: true,
            onSuccess: () => cancelEdit(),
        });
}

function destroyItem(sourceId) {
    if (!window.confirm('Excluir este item do calendário? Esta ação não pode ser desfeita.')) return;

    router.delete(route('admin.strategic-calendar.destroy', sourceId), {
        preserveScroll: true,
        onSuccess: () => {
            if (editingSourceId.value === sourceId) {
                cancelEdit();
            }
        },
    });
}

function uploadAttachments(sourceId, event) {
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

    router.post(route('admin.strategic-calendar.attachments.store', sourceId), fd, {
        forceFormData: true,
        preserveScroll: true,
        preserveState: true,
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
        preserveState: true,
    });
}
</script>

<template>
    <Modal :show="show" max-width="3xl" @close="close">
        <div class="flex max-h-[min(90vh,820px)] flex-col">
            <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4 sm:px-6">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Gerir dia</p>
                    <h3 class="mt-1 text-lg font-semibold capitalize text-slate-900">{{ dayLabel }}</h3>
                </div>
                <button
                    type="button"
                    class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                    aria-label="Fechar"
                    @click="close"
                >
                    <XMarkIcon class="h-5 w-5" aria-hidden="true" />
                </button>
            </div>

            <div class="flex-1 space-y-4 overflow-y-auto px-5 py-4 sm:px-6">
                <section v-if="uniqueDayItems.length">
                    <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Itens neste dia ({{ uniqueDayItems.length }})
                    </p>
                    <ul class="space-y-3">
                        <li
                            v-for="item in uniqueDayItems"
                            :key="item.source_id ?? item.id"
                            class="rounded-2xl border border-slate-200 bg-white shadow-sm"
                        >
                            <template v-if="editingSourceId === (item.source_id ?? item.id)">
                                <form
                                    class="space-y-3 p-4"
                                    @submit.prevent="submitEdit(item.source_id ?? item.id)"
                                >
                                    <div>
                                        <label class="text-xs font-medium text-slate-600">Nome</label>
                                        <input
                                            v-model="editForm.title"
                                            type="text"
                                            required
                                            class="mt-1 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                                        />
                                        <p v-if="editForm.errors.title" class="mt-1 text-xs text-red-600">
                                            {{ editForm.errors.title }}
                                        </p>
                                    </div>
                                    <div>
                                        <label class="text-xs font-medium text-slate-600">Tipo</label>
                                        <select
                                            v-model="editForm.kind"
                                            class="mt-1 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                                        >
                                            <option v-for="k in kinds" :key="k.value" :value="k.value">
                                                {{ k.label }}
                                            </option>
                                        </select>
                                    </div>
                                    <DateRangeFields
                                        v-model:occurs-on="editForm.occurs_on"
                                        v-model:ends-on="editForm.ends_on"
                                        compact
                                        :disable-ends-on="showEditRecurrenceEnd"
                                        disable-recurrence-hint
                                    />
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <div>
                                            <label class="text-xs font-medium text-slate-600">Repetição</label>
                                            <select
                                                v-model="editForm.recurrence"
                                                class="mt-1 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                                            >
                                                <option value="">Não se repete</option>
                                                <option v-for="r in recurrences" :key="r.value" :value="r.value">
                                                    {{ r.label }}
                                                </option>
                                            </select>
                                        </div>
                                        <div v-if="showEditRecurrenceEnd">
                                            <label class="text-xs font-medium text-slate-600">Repetir até</label>
                                            <input
                                                v-model="editForm.recurrence_ends_on"
                                                type="date"
                                                class="mt-1 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                                            />
                                        </div>
                                    </div>
                                    <div>
                                        <label class="text-xs font-medium text-slate-600">Orientações</label>
                                        <textarea
                                            v-model="editForm.description"
                                            rows="3"
                                            class="mt-1 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                                        />
                                    </div>
                                    <div>
                                        <CompanyAudienceMultiSelect
                                            v-model="editForm.company_ids"
                                            :companies="companies"
                                            compact
                                        />
                                    </div>

                                    <div class="rounded-xl border border-slate-100 bg-slate-50/80 p-3">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                            Anexos
                                        </p>
                                        <AttachmentList
                                            v-if="item.attachments?.length"
                                            class="mt-2"
                                            :attachments="item.attachments"
                                            :link-prefix="''"
                                            compact
                                            removable
                                            @remove="destroyAttachment"
                                        />
                                        <p v-else class="mt-2 text-xs text-slate-500">Nenhum anexo.</p>
                                        <label class="mt-3 inline-flex cursor-pointer items-center gap-2 text-sm font-medium text-talents-700 hover:text-talents-800">
                                            <PlusIcon class="h-4 w-4" aria-hidden="true" />
                                            Adicionar anexos
                                            <input
                                                type="file"
                                                multiple
                                                accept="application/pdf,image/*,video/*,.doc,.docx,.xls,.xlsx,.ppt,.pptx"
                                                class="sr-only"
                                                :disabled="uploadProcessing"
                                                @change="uploadAttachments(item.source_id ?? item.id, $event)"
                                            />
                                        </label>
                                        <p class="mt-1 text-xs text-slate-500">
                                            PDF, imagens, documentos ou vídeos (máx. {{ maxAttachmentMb }} MB cada).
                                        </p>
                                        <p v-if="uploadError" class="mt-1 text-xs text-red-600">{{ uploadError }}</p>
                                        <div v-if="uploadProcessing" class="mt-2 space-y-1">
                                            <p class="text-xs text-slate-600">Enviando… {{ Math.round(uploadProgress) }}%</p>
                                            <div class="h-1.5 overflow-hidden rounded-full bg-slate-200">
                                                <div
                                                    class="h-full rounded-full bg-talents-600 transition-all"
                                                    :style="{ width: `${uploadProgress}%` }"
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap gap-2 pt-1">
                                        <button
                                            type="submit"
                                            class="btn-primary !py-2 text-sm"
                                            :disabled="editForm.processing"
                                        >
                                            Salvar
                                        </button>
                                        <button type="button" class="btn-ghost !py-2 text-sm" @click="cancelEdit">
                                            Cancelar
                                        </button>
                                    </div>
                                </form>
                            </template>
                            <div v-else class="flex items-start justify-between gap-3 p-4">
                                <div class="min-w-0 flex-1">
                                    <div class="mb-1 flex flex-wrap items-center gap-2">
                                        <StrategicKindBadge :kind="item.kind" :label="kindLabel(item.kind)" />
                                        <span
                                            v-if="item.recurrence && recurrenceLabels[item.recurrence]"
                                            class="text-xs font-medium text-violet-600"
                                        >
                                            ↻ {{ recurrenceLabels[item.recurrence] }}
                                        </span>
                                    </div>
                                    <p class="font-semibold text-slate-900">{{ item.title }}</p>
                                    <p
                                        v-if="item.range_starts_on || item.occurs_on"
                                        class="mt-0.5 text-xs font-medium text-slate-500"
                                    >
                                        {{ formatStrategicCalendarDateRange(item.range_starts_on ?? item.occurs_on, item.ends_on) }}
                                    </p>
                                    <p v-if="item.description" class="mt-1 line-clamp-2 text-sm text-slate-600">
                                        {{ item.description }}
                                    </p>
                                    <AttachmentList
                                        v-if="item.attachments?.length"
                                        class="mt-2"
                                        :attachments="item.attachments"
                                        compact
                                    />
                                    <p v-if="audienceLabel(item)" class="mt-1 text-xs text-slate-500">
                                        {{ audienceLabel(item) }}
                                    </p>
                                </div>
                                <div class="flex shrink-0 flex-col gap-1">
                                    <button
                                        type="button"
                                        class="rounded-lg px-2 py-1 text-xs font-medium text-slate-600 hover:bg-slate-100"
                                        @click="startEdit(item)"
                                    >
                                        Editar
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-lg px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50"
                                        @click="destroyItem(item.source_id ?? item.id)"
                                    >
                                        Excluir
                                    </button>
                                </div>
                            </div>
                        </li>
                    </ul>
                </section>

                <section v-else class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/60 px-4 py-8 text-center">
                    <p class="text-sm text-slate-500">Nenhum evento ou Ritual neste dia.</p>
                </section>

                <section>
                    <button
                        v-if="!showCreateForm"
                        type="button"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-dashed border-talents-300 bg-talents-50/50 px-4 py-3 text-sm font-semibold text-talents-800 transition hover:bg-talents-50"
                        @click="showCreateForm = true"
                    >
                        <PlusIcon class="h-4 w-4" aria-hidden="true" />
                        Adicionar item neste dia
                    </button>

                    <form
                        v-else
                        class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
                        @submit.prevent="submitCreate"
                    >
                        <p class="text-sm font-semibold text-slate-900">Novo item</p>
                        <div>
                            <label class="text-xs font-medium text-slate-600">Nome</label>
                            <input
                                v-model="createForm.title"
                                type="text"
                                required
                                class="mt-1 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                                placeholder="Ex.: Revisão de metas"
                            />
                            <p v-if="createForm.errors.title" class="mt-1 text-xs text-red-600">
                                {{ createForm.errors.title }}
                            </p>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-slate-600">Tipo</label>
                            <select
                                v-model="createForm.kind"
                                class="mt-1 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                            >
                                <option v-for="k in kinds" :key="k.value" :value="k.value">{{ k.label }}</option>
                            </select>
                        </div>
                        <DateRangeFields
                            v-model:occurs-on="createForm.occurs_on"
                            v-model:ends-on="createForm.ends_on"
                            compact
                            :disable-ends-on="showCreateRecurrenceEnd"
                            disable-recurrence-hint
                        />
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="text-xs font-medium text-slate-600">Repetição</label>
                                <select
                                    v-model="createForm.recurrence"
                                    class="mt-1 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                                >
                                    <option value="">Não se repete</option>
                                    <option v-for="r in recurrences" :key="r.value" :value="r.value">
                                        {{ r.label }}
                                    </option>
                                </select>
                            </div>
                            <div v-if="showCreateRecurrenceEnd">
                                <label class="text-xs font-medium text-slate-600">Repetir até</label>
                                <input
                                    v-model="createForm.recurrence_ends_on"
                                    type="date"
                                    class="mt-1 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                                />
                            </div>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-slate-600">Orientações</label>
                            <textarea
                                v-model="createForm.description"
                                rows="3"
                                class="mt-1 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                            />
                        </div>
                        <CompanyAudienceMultiSelect
                            v-model="createForm.company_ids"
                            :companies="companies"
                            compact
                        />
                        <p class="text-xs text-slate-500">
                            Após criar o item, você poderá anexar arquivos na edição.
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <button
                                type="submit"
                                class="btn-primary !py-2 text-sm"
                                :disabled="createForm.processing"
                            >
                                Criar item
                            </button>
                            <button
                                v-if="uniqueDayItems.length"
                                type="button"
                                class="btn-ghost !py-2 text-sm"
                                @click="showCreateForm = false"
                            >
                                Cancelar
                            </button>
                        </div>
                    </form>
                </section>
            </div>
        </div>
    </Modal>
</template>
