<script setup>
import Modal from '@/Components/Modal.vue';
import DateRangeFields from '@/Components/StrategicCalendar/DateRangeFields.vue';
import StrategicKindBadge from '@/Components/StrategicKindBadge.vue';
import { formatStrategicCalendarDateRange } from '@/utils/strategicCalendarDate';
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    iso: { type: String, default: null },
    items: { type: Array, default: () => [] },
    kinds: { type: Array, default: () => [] },
    recurrences: { type: Array, default: () => [] },
    kindLabels: { type: Object, default: () => ({}) },
    recurrenceLabels: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['close']);

const showCreateForm = ref(false);
const editingSourceId = ref(null);

const createForm = useForm({
    title: '',
    description: '',
    kind: props.kinds[0]?.value ?? 'event',
    occurs_on: '',
    ends_on: '',
    recurrence: '',
    recurrence_ends_on: '',
});

const editForm = useForm({
    title: '',
    description: '',
    kind: 'event',
    occurs_on: '',
    ends_on: '',
    recurrence: '',
    recurrence_ends_on: '',
});

const dayLabel = computed(() => {
    if (!props.iso) return '';
    return new Date(`${props.iso}T12:00:00`).toLocaleDateString('pt-BR', {
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

watch(
    () => [props.show, props.iso],
    ([open, iso]) => {
        if (!open) {
            showCreateForm.value = false;
            editingSourceId.value = null;
            return;
        }
        createForm.occurs_on = iso ?? '';
        createForm.ends_on = '';
        createForm.reset(
            'title',
            'description',
            'kind',
            'recurrence',
            'recurrence_ends_on',
        );
        createForm.kind = props.kinds[0]?.value ?? 'event';
        createForm.occurs_on = iso ?? '';
        createForm.clearErrors();
    },
);

function close() {
    emit('close');
}

function startCreate() {
    editingSourceId.value = null;
    showCreateForm.value = true;
}

function startEdit(item) {
    if (!item.can_manage) return;
    showCreateForm.value = false;
    editingSourceId.value = item.source_id;
    editForm.title = item.title ?? '';
    editForm.description = item.description ?? '';
    editForm.kind = item.kind ?? 'event';
    editForm.occurs_on = (item.range_starts_on ?? item.occurs_on)?.slice?.(0, 10) ?? item.occurs_on ?? '';
    editForm.ends_on = item.ends_on?.slice?.(0, 10) ?? '';
    editForm.recurrence = item.recurrence ?? '';
    editForm.recurrence_ends_on = item.recurrence_ends_on?.slice?.(0, 10) ?? '';
    editForm.clearErrors();
}

function cancelEdit() {
    editingSourceId.value = null;
}

function submitCreate() {
    createForm.post(route('client.strategic-calendar.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showCreateForm.value = false;
            createForm.reset();
            createForm.occurs_on = props.iso ?? '';
            createForm.kind = props.kinds[0]?.value ?? 'event';
        },
    });
}

function submitEdit() {
    if (!editingSourceId.value) return;
    editForm.put(route('client.strategic-calendar.update', editingSourceId.value), {
        preserveScroll: true,
        onSuccess: () => {
            editingSourceId.value = null;
        },
    });
}

function removeItem(item) {
    if (!item.can_manage) return;
    if (!confirm('Remover este evento da agenda interna?')) return;
    router.delete(route('client.strategic-calendar.destroy', item.source_id), {
        preserveScroll: true,
    });
}

function agendaBadgeClass(agenda) {
    return agenda === 'company'
        ? 'bg-sky-50 text-sky-800 ring-sky-200'
        : 'bg-violet-50 text-violet-800 ring-violet-200';
}
</script>

<template>
    <Modal :show="show" max-width="2xl" @close="close">
        <div class="border-b border-slate-100 px-5 py-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Dia</p>
                    <h3 class="mt-1 text-lg font-semibold capitalize text-slate-900">{{ dayLabel }}</h3>
                </div>
                <button
                    type="button"
                    class="rounded-lg px-2 py-1 text-sm text-slate-500 hover:bg-slate-100"
                    @click="close"
                >
                    Fechar
                </button>
            </div>
        </div>

        <div class="max-h-[70vh] space-y-4 overflow-y-auto px-5 py-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-sm text-slate-600">
                    Agendas Talents e Empresa neste dia. Só a agenda interna pode ser editada aqui.
                </p>
                <button
                    type="button"
                    class="rounded-lg bg-talents-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-talents-700"
                    @click="startCreate"
                >
                    Novo evento interno
                </button>
            </div>

            <form
                v-if="showCreateForm"
                class="space-y-3 rounded-xl border border-talents-200 bg-talents-50/40 p-4"
                @submit.prevent="submitCreate"
            >
                <p class="text-sm font-semibold text-talents-900">Novo evento — agenda Empresa</p>
                <div>
                    <label class="text-xs font-medium text-slate-600">Título</label>
                    <input
                        v-model="createForm.title"
                        type="text"
                        required
                        class="mt-1 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                    />
                    <p v-if="createForm.errors.title" class="mt-1 text-xs text-rose-600">{{ createForm.errors.title }}</p>
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
                />
                <div>
                    <label class="text-xs font-medium text-slate-600">Repetir</label>
                    <select
                        v-model="createForm.recurrence"
                        class="mt-1 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                    >
                        <option value="">Não repetir</option>
                        <option v-for="r in recurrences" :key="r.value" :value="r.value">{{ r.label }}</option>
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
                <div>
                    <label class="text-xs font-medium text-slate-600">Descrição</label>
                    <textarea
                        v-model="createForm.description"
                        rows="2"
                        class="mt-1 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                    />
                </div>
                <div class="flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-lg px-3 py-1.5 text-sm text-slate-600 hover:bg-white"
                        @click="showCreateForm = false"
                    >
                        Cancelar
                    </button>
                    <button
                        type="submit"
                        class="rounded-lg bg-talents-600 px-3 py-1.5 text-sm font-semibold text-white"
                        :disabled="createForm.processing"
                    >
                        Salvar
                    </button>
                </div>
            </form>

            <ul class="divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white">
                <li v-if="!items.length" class="px-4 py-6 text-center text-sm text-slate-500">
                    Nenhum item neste dia.
                </li>
                <li v-for="item in items" :key="item.id" class="space-y-3 px-4 py-3">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <StrategicKindBadge :kind="item.kind" :label="kindLabels[item.kind] ?? item.kind" />
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide ring-1 ring-inset"
                                    :class="agendaBadgeClass(item.agenda)"
                                >
                                    {{ item.agenda_label ?? (item.agenda === 'company' ? 'Empresa' : 'Talents') }}
                                </span>
                                <p class="font-medium text-slate-900">{{ item.title }}</p>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ formatStrategicCalendarDateRange(item.range_starts_on ?? item.occurs_on, item.ends_on) }}
                                <template v-if="item.recurrence_label"> · {{ item.recurrence_label }}</template>
                            </p>
                            <p v-if="item.description" class="mt-1 text-sm text-slate-600">{{ item.description }}</p>
                        </div>
                        <div v-if="item.can_manage" class="flex gap-2">
                            <button
                                type="button"
                                class="text-xs font-semibold text-talents-700 hover:underline"
                                @click="startEdit(item)"
                            >
                                Editar
                            </button>
                            <button
                                type="button"
                                class="text-xs font-semibold text-rose-600 hover:underline"
                                @click="removeItem(item)"
                            >
                                Excluir
                            </button>
                        </div>
                    </div>

                    <form
                        v-if="editingSourceId === item.source_id"
                        class="space-y-3 rounded-lg border border-slate-200 bg-slate-50 p-3"
                        @submit.prevent="submitEdit"
                    >
                        <div>
                            <label class="text-xs font-medium text-slate-600">Título</label>
                            <input
                                v-model="editForm.title"
                                type="text"
                                required
                                class="mt-1 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                            />
                        </div>
                        <div>
                            <label class="text-xs font-medium text-slate-600">Tipo</label>
                            <select
                                v-model="editForm.kind"
                                class="mt-1 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                            >
                                <option v-for="k in kinds" :key="k.value" :value="k.value">{{ k.label }}</option>
                            </select>
                        </div>
                        <DateRangeFields
                            v-model:occurs-on="editForm.occurs_on"
                            v-model:ends-on="editForm.ends_on"
                            compact
                            :disable-ends-on="showEditRecurrenceEnd"
                        />
                        <div>
                            <label class="text-xs font-medium text-slate-600">Repetir</label>
                            <select
                                v-model="editForm.recurrence"
                                class="mt-1 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                            >
                                <option value="">Não repetir</option>
                                <option v-for="r in recurrences" :key="r.value" :value="r.value">{{ r.label }}</option>
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
                        <div>
                            <label class="text-xs font-medium text-slate-600">Descrição</label>
                            <textarea
                                v-model="editForm.description"
                                rows="2"
                                class="mt-1 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                            />
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="button" class="rounded-lg px-3 py-1.5 text-sm text-slate-600" @click="cancelEdit">
                                Cancelar
                            </button>
                            <button
                                type="submit"
                                class="rounded-lg bg-talents-600 px-3 py-1.5 text-sm font-semibold text-white"
                                :disabled="editForm.processing"
                            >
                                Atualizar
                            </button>
                        </div>
                    </form>
                </li>
            </ul>
        </div>
    </Modal>
</template>
