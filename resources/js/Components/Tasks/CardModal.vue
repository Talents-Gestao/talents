<script setup>
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { router, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

const props = defineProps({
    show: Boolean,
    card: { type: Object, default: null },
    boardPayload: { type: Object, required: true },
    companyUsers: { type: Array, default: () => [] },
    isAdmin: { type: Boolean, default: false },
    visibilityCardOptions: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'refresh']);

const cardUpdate = useForm({
    title: '',
    description: '',
    visibility: 'inherit',
    due_date: '',
    start_date: '',
    member_ids: [],
    label_ids: [],
});

const commentForm = useForm({ body: '', mentioned_user_ids: [] });

watch(
    () => props.card,
    (c) => {
        if (!c) return;
        cardUpdate.title = c.title || '';
        cardUpdate.description = c.description || '';
        cardUpdate.visibility = c.visibility || 'inherit';
        cardUpdate.due_date = c.due_date || '';
        cardUpdate.start_date = c.start_date || '';
        cardUpdate.member_ids = (c.members || []).map((m) => m.id);
        cardUpdate.label_ids = (c.labels || []).map((l) => l.id);
    },
    { immediate: true },
);

function saveCard() {
    if (!props.card) return;
    const url = props.isAdmin
        ? route('admin.tarefas.cards.update', props.card.id)
        : route('client.tarefas.cards.update', props.card.id);

    if (props.isAdmin) {
        cardUpdate.patch(url, {
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
            onSuccess: () => emit('refresh'),
        },
    );
}
</script>

<template>
    <Modal :show="show" max-width="2xl" @close="emit('close')">
        <div v-if="card" class="space-y-4 p-6">
            <div class="space-y-3">
                <div>
                    <InputLabel value="Título" />
                    <TextInput v-model="cardUpdate.title" class="mt-1 w-full" />
                </div>
                <div>
                    <InputLabel value="Descrição" />
                    <textarea
                        v-model="cardUpdate.description"
                        rows="4"
                        class="mt-1 w-full rounded-md border border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <InputLabel value="Início" />
                        <TextInput v-model="cardUpdate.start_date" type="date" class="mt-1 w-full" />
                    </div>
                    <div>
                        <InputLabel value="Vencimento" />
                        <TextInput v-model="cardUpdate.due_date" type="date" class="mt-1 w-full" />
                    </div>
                </div>

                <div v-if="isAdmin && visibilityCardOptions.length">
                    <InputLabel value="Visibilidade do cartão" />
                    <select
                        v-model="cardUpdate.visibility"
                        class="mt-1 block w-full rounded-md border border-slate-300 text-sm"
                    >
                        <option v-for="o in visibilityCardOptions" :key="o.value" :value="o.value">
                            {{ o.label }}
                        </option>
                    </select>
                </div>

                <div v-if="isAdmin" class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div>
                        <InputLabel value="Membros" />
                        <div
                            class="mt-1 max-h-32 space-y-1 overflow-y-auto rounded border border-slate-200 p-2 text-sm"
                        >
                            <label v-for="u in companyUsers" :key="u.id" class="flex items-center gap-2">
                                <input v-model="cardUpdate.member_ids" type="checkbox" :value="u.id" />
                                {{ u.name }}
                            </label>
                        </div>
                    </div>
                    <div>
                        <InputLabel value="Etiquetas" />
                        <div
                            class="mt-1 max-h-32 space-y-1 overflow-y-auto rounded border border-slate-200 p-2 text-sm"
                        >
                            <label v-for="l in boardPayload.labels" :key="l.id" class="flex items-center gap-2">
                                <input v-model="cardUpdate.label_ids" type="checkbox" :value="l.id" />
                                <span
                                    class="inline-block h-3 w-3 rounded"
                                    :style="{ backgroundColor: l.color }"
                                />
                                {{ l.name || l.color }}
                            </label>
                        </div>
                        <button
                            type="button"
                            class="mt-1 text-xs text-talents-700 underline"
                            @click="newLabel"
                        >
                            + Criar etiqueta
                        </button>
                    </div>
                </div>

                <PrimaryButton type="button" :disabled="cardUpdate.processing" @click="saveCard">
                    Guardar cartão
                </PrimaryButton>
            </div>

            <div class="border-t border-slate-200 pt-4">
                <h4 class="font-semibold text-slate-800">Checklist</h4>
                <ul class="mt-2 space-y-1 text-sm">
                    <template v-for="cl in card.checklists || []" :key="cl.id">
                        <li v-for="it in cl.items || []" :key="it.id" class="flex items-center gap-2">
                            <input
                                type="checkbox"
                                :checked="it.is_completed"
                                class="rounded border-slate-300"
                                @change="toggleItem(it)"
                            />
                            <span :class="it.is_completed ? 'text-slate-400 line-through' : ''">{{
                                it.text
                            }}</span>
                        </li>
                    </template>
                </ul>
            </div>

            <div class="border-t border-slate-200 pt-4">
                <h4 class="font-semibold text-slate-800">Anexos</h4>
                <input type="file" class="mt-2 block text-sm" @change="uploadAttachment" />
                <ul class="mt-2 space-y-1 text-xs">
                    <li v-for="a in card.attachments || []" :key="a.id">
                        <a :href="a.url" target="_blank" class="text-talents-700 underline">{{
                            a.original_name
                        }}</a>
                    </li>
                </ul>
            </div>

            <div class="border-t border-slate-200 pt-4">
                <h4 class="font-semibold text-slate-800">Comentários</h4>
                <ul class="mt-2 max-h-40 space-y-2 overflow-y-auto text-sm">
                    <li v-for="c in card.comments || []" :key="c.id" class="rounded bg-slate-50 p-2">
                        <span class="font-medium">{{ c.user?.name }}</span>
                        <span class="text-xs text-slate-500"> · {{ c.created_at }}</span>
                        <p class="mt-1 whitespace-pre-wrap text-slate-800">{{ c.body }}</p>
                    </li>
                </ul>
                <textarea
                    v-model="commentForm.body"
                    rows="2"
                    class="mt-2 w-full rounded-md border border-slate-300 text-sm"
                    placeholder="Escrever comentário…"
                />
                <PrimaryButton
                    type="button"
                    class="mt-2"
                    :disabled="commentForm.processing"
                    @click="submitComment"
                >
                    Comentar
                </PrimaryButton>
            </div>
        </div>
    </Modal>
</template>
