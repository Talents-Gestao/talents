<script setup>
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import {
    ChatBubbleOvalLeftEllipsisIcon,
    CheckCircleIcon,
    PaperClipIcon,
} from '@heroicons/vue/24/outline';
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    show: Boolean,
    card: { type: Object, default: null },
    boardPayload: { type: Object, required: true },
    companyUsers: { type: Array, default: () => [] },
    companies: { type: Array, default: () => [] },
    isAdmin: { type: Boolean, default: false },
    visibilityCardOptions: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'refresh']);
const activeTab = ref('details');

const cardUpdate = useForm({
    title: '',
    description: '',
    visibility: 'inherit',
    due_date: '',
    start_date: '',
    company_id: '',
    member_ids: [],
    label_ids: [],
});

const commentForm = useForm({ body: '', mentioned_user_ids: [] });

watch(
    () => props.card,
    (c) => {
        if (!c) return;
        activeTab.value = 'details';
        cardUpdate.title = c.title || '';
        cardUpdate.description = c.description || '';
        cardUpdate.visibility = c.visibility || 'inherit';
        cardUpdate.due_date = c.due_date || '';
        cardUpdate.start_date = c.start_date || '';
        cardUpdate.company_id = c.company_id || '';
        cardUpdate.member_ids = (c.members || []).map((m) => m.id);
        cardUpdate.label_ids = (c.labels || []).map((l) => l.id);
    },
    { immediate: true },
);

const usersForSelectedCompany = computed(() => {
    if (!props.isAdmin) return props.companyUsers || [];
    if (!cardUpdate.company_id) return [];

    return (props.companyUsers || []).filter(
        (u) => Number(u.company_id) === Number(cardUpdate.company_id),
    );
});

function saveCard() {
    if (!props.card) return;
    const url = props.isAdmin
        ? route('admin.tarefas.cards.update', props.card.id)
        : route('client.tarefas.cards.update', props.card.id);

    if (props.isAdmin) {
        cardUpdate
            .transform((data) => ({
                ...data,
                company_id: data.company_id || null,
                due_date: data.due_date || null,
                start_date: data.start_date || null,
            }))
            .patch(url, {
                preserveScroll: true,
                onSuccess: () => emit('refresh'),
            });
    } else {
        cardUpdate
            .transform((data) => ({
                title: data.title,
                description: data.description,
                due_date: data.due_date || null,
                start_date: data.start_date || null,
            }))
            .patch(url, {
                preserveScroll: true,
                onSuccess: () => emit('refresh'),
            });
    }
}

function submitComment() {
    if (!props.card || !commentForm.body.trim()) return;
    const url = props.isAdmin
        ? route('admin.tarefas.cards.comentarios.store', props.card.id)
        : route('client.tarefas.cards.comentarios.store', props.card.id);

    commentForm.post(url, {
        preserveScroll: true,
        onSuccess: () => {
            commentForm.reset('body');
            emit('refresh');
        },
    });
}

function toggleItem(item) {
    const url = props.isAdmin
        ? route('admin.tarefas.checklist-itens.update', item.id)
        : route('client.tarefas.checklist-itens.update', item.id);

    router.patch(
        url,
        { is_completed: !item.is_completed },
        {
            preserveScroll: true,
            onSuccess: () => emit('refresh'),
        },
    );
}

function uploadAttachment(e) {
    const file = e.target.files?.[0];
    if (!file || !props.card) return;

    const url = props.isAdmin
        ? route('admin.tarefas.cards.anexos.store', props.card.id)
        : route('client.tarefas.cards.anexos.store', props.card.id);

    const fd = new FormData();
    fd.append('file', file);

    router.post(url, fd, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => emit('refresh'),
    });
    e.target.value = '';
}

function newLabel() {
    if (!props.isAdmin || !props.card) return;
    const color = '#' + Math.floor(Math.random() * 0xffffff).toString(16).padStart(6, '0');
    router.post(
        route('admin.tarefas.quadros.labels.store', props.boardPayload.id),
        { name: 'Nova', color },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                router.reload({
                    only: ['boardPayload'],
                    preserveState: true,
                    preserveScroll: true,
                });
            },
        },
    );
}

function formatDateTime(value) {
    if (!value) return '';
    const dt = new Date(value);
    if (Number.isNaN(dt.getTime())) return value;
    return dt.toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <Modal :show="show" max-width="lg" @close="emit('close')">
        <div v-if="card" class="flex max-h-[85vh] flex-col overflow-hidden rounded-xl bg-white">
            <div class="border-b border-slate-100 px-6 pt-5">
                <p class="text-sm font-semibold text-slate-900">Planejar atividade</p>
                <div class="mt-4 flex items-center gap-5 text-xs">
                    <button
                        type="button"
                        class="border-b-2 pb-2 font-medium transition"
                        :class="activeTab === 'details' ? 'border-talents-600 text-talents-700' : 'border-transparent text-slate-500 hover:text-slate-700'"
                        @click="activeTab = 'details'"
                    >
                        Detalhes
                    </button>
                    <button
                        type="button"
                        class="border-b-2 pb-2 font-medium transition"
                        :class="activeTab === 'comments' ? 'border-talents-600 text-talents-700' : 'border-transparent text-slate-500 hover:text-slate-700'"
                        @click="activeTab = 'comments'"
                    >
                        Comentários {{ (card.comments || []).length ? `${(card.comments || []).length}` : '' }}
                    </button>
                </div>
            </div>

            <div v-if="activeTab === 'details'" class="space-y-4 overflow-y-auto p-6">
                <div class="space-y-4 rounded-lg border border-slate-100 p-4">
                    <div class="space-y-1">
                        <InputLabel value="Título" />
                        <TextInput
                            v-model="cardUpdate.title"
                            class="mt-1 w-full border-slate-200 bg-white text-sm shadow-none focus:border-talents-500 focus:ring-talents-500"
                        />
                    </div>
                    <div class="space-y-1">
                        <InputLabel value="Descrição" />
                        <textarea
                            v-model="cardUpdate.description"
                            rows="4"
                            class="mt-1 w-full rounded-md border border-slate-200 bg-white text-sm shadow-none focus:border-talents-500 focus:ring-talents-500"
                        />
                    </div>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div>
                            <InputLabel value="Início" />
                            <TextInput
                                v-model="cardUpdate.start_date"
                                type="date"
                                class="mt-1 w-full border-slate-200 bg-white text-sm shadow-none"
                            />
                        </div>
                        <div>
                            <InputLabel value="Vencimento" />
                            <TextInput
                                v-model="cardUpdate.due_date"
                                type="date"
                                class="mt-1 w-full border-slate-200 bg-white text-sm shadow-none"
                            />
                        </div>
                    </div>

                    <div v-if="isAdmin && visibilityCardOptions.length">
                        <InputLabel value="Visibilidade do cartão" />
                        <select
                            v-model="cardUpdate.visibility"
                            class="mt-1 block w-full rounded-md border border-slate-200 bg-white text-sm shadow-none focus:border-talents-500 focus:ring-talents-500"
                        >
                            <option v-for="o in visibilityCardOptions" :key="o.value" :value="o.value">
                                {{ o.label }}
                            </option>
                        </select>
                    </div>

                    <div v-if="isAdmin" class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <InputLabel value="Empresa responsável (cliente)" />
                            <select
                                v-model="cardUpdate.company_id"
                                class="mt-1 block w-full rounded-md border border-slate-200 bg-white text-sm shadow-none focus:border-talents-500 focus:ring-talents-500"
                            >
                                <option value="">Não compartilhar com empresa</option>
                                <option v-for="c in companies" :key="c.id" :value="c.id">
                                    {{ c.name }}
                                </option>
                            </select>
                            <p class="mt-1 text-xs text-slate-500">
                                Quando definida, a tarefa aparece para essa empresa no portal cliente.
                            </p>
                        </div>
                        <div>
                            <InputLabel value="Membros" />
                            <div class="mt-1 max-h-32 space-y-1 overflow-y-auto rounded-md border border-slate-200 bg-slate-50/60 p-2 text-sm">
                                <label
                                    v-for="u in usersForSelectedCompany"
                                    :key="u.id"
                                    class="flex items-center gap-2 rounded px-1 py-0.5 hover:bg-white"
                                >
                                    <input v-model="cardUpdate.member_ids" type="checkbox" :value="u.id" />
                                    {{ u.name }}
                                </label>
                                <p v-if="!usersForSelectedCompany.length" class="text-xs text-slate-500">
                                    Selecione uma empresa para listar os responsáveis.
                                </p>
                            </div>
                        </div>
                        <div>
                            <InputLabel value="Etiquetas" />
                            <div class="mt-1 max-h-32 space-y-1 overflow-y-auto rounded-md border border-slate-200 bg-slate-50/60 p-2 text-sm">
                                <label
                                    v-for="l in boardPayload.labels"
                                    :key="l.id"
                                    class="flex items-center gap-2 rounded px-1 py-0.5 hover:bg-white"
                                >
                                    <input v-model="cardUpdate.label_ids" type="checkbox" :value="l.id" />
                                    <span class="inline-block h-3 w-3 rounded" :style="{ backgroundColor: l.color }" />
                                    {{ l.name || l.color }}
                                </label>
                            </div>
                            <button type="button" class="mt-1 text-xs text-talents-700 underline" @click="newLabel">
                                + Criar etiqueta
                            </button>
                        </div>
                    </div>
                </div>

                <div class="space-y-2 rounded-lg border border-slate-100 p-4">
                    <h4 class="flex items-center gap-2 text-sm font-semibold text-slate-800">
                        <CheckCircleIcon class="h-4 w-4 text-slate-500" />
                        Checklist
                    </h4>
                    <ul class="mt-2 space-y-1 text-sm">
                        <template v-for="cl in card.checklists || []" :key="cl.id">
                            <li v-for="it in cl.items || []" :key="it.id" class="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    :checked="it.is_completed"
                                    class="rounded border-slate-300"
                                    @change="toggleItem(it)"
                                />
                                <span :class="it.is_completed ? 'text-slate-400 line-through' : ''">{{ it.text }}</span>
                            </li>
                        </template>
                    </ul>
                </div>

                <div class="space-y-2 rounded-lg border border-slate-100 p-4">
                    <h4 class="flex items-center gap-2 text-sm font-semibold text-slate-800">
                        <PaperClipIcon class="h-4 w-4 text-slate-500" />
                        Anexos
                    </h4>
                    <input type="file" class="mt-2 block text-sm" @change="uploadAttachment" />
                    <ul class="mt-2 space-y-1 text-xs">
                        <li v-for="a in card.attachments || []" :key="a.id">
                            <a :href="a.url" target="_blank" class="text-talents-700 underline">{{ a.original_name }}</a>
                        </li>
                    </ul>
                </div>
            </div>

            <div v-else class="overflow-y-auto p-6">
                <div class="space-y-2 rounded-lg border border-slate-100 p-4">
                    <h4 class="flex items-center gap-2 text-sm font-semibold text-slate-800">
                        <ChatBubbleOvalLeftEllipsisIcon class="h-4 w-4 text-slate-500" />
                        Comentários
                    </h4>
                    <ul class="mt-2 max-h-52 space-y-2 overflow-y-auto text-sm">
                        <li v-for="c in card.comments || []" :key="c.id" class="rounded-md border border-slate-200 bg-slate-50/70 p-2">
                            <span class="font-medium">{{ c.user?.name }}</span>
                            <span class="text-xs text-slate-500"> · {{ formatDateTime(c.created_at) }}</span>
                            <p class="mt-1 whitespace-pre-wrap text-slate-800">{{ c.body }}</p>
                        </li>
                    </ul>
                    <textarea
                        v-model="commentForm.body"
                        rows="3"
                        class="mt-2 w-full rounded-md border border-slate-200 bg-white text-sm shadow-none focus:border-talents-500 focus:ring-talents-500"
                        placeholder="Escrever comentário..."
                    />
                    <div class="flex justify-end">
                        <PrimaryButton
                            type="button"
                            class="mt-1"
                            :disabled="commentForm.processing"
                            @click="submitComment"
                        >
                            Comentar
                        </PrimaryButton>
                    </div>
                </div>
            </div>

            <div class="sticky bottom-0 flex items-center justify-end gap-2 border-t border-slate-200 bg-white/95 px-6 py-4 backdrop-blur">
                <button
                    type="button"
                    class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                    @click="emit('close')"
                >
                    Cancelar
                </button>
                <PrimaryButton type="button" :disabled="cardUpdate.processing" @click="saveCard">
                    Salvar alterações
                </PrimaryButton>
            </div>
        </div>
    </Modal>
</template>
