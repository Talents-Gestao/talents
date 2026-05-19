<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ColorPresetPicker from '@/Components/Tasks/ColorPresetPicker.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    template: Object,
    visibilityListOptions: Array,
    visibilityCardOptions: Array,
});

const metaForm = useForm({
    name: props.template.name,
    slug: props.template.slug,
    description: props.template.description ?? '',
    cover_color: props.template.cover_color ?? '',
    is_active: props.template.is_active,
});

function saveMeta() {
    metaForm.put(route('admin.tarefas.processos.update', props.template.id));
}

const newList = reactive({
    name: '',
    default_visibility: 'company',
    allow_company_drop_in: true,
});

function addList() {
    if (!newList.name.trim()) return;
    router.post(route('admin.tarefas.processos.listas.store', props.template.id), { ...newList }, {
        preserveScroll: true,
        onSuccess: () => {
            newList.name = '';
        },
    });
}

const newCards = reactive({});

function addCard(listId) {
    const title = (newCards[listId] || '').trim();
    if (!title) return;
    router.post(
        route('admin.tarefas.processo-listas.cards.store', listId),
        {
            title,
            default_visibility: 'inherit',
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                newCards[listId] = '';
            },
        },
    );
}

function deleteList(listId) {
    if (!confirm('Remover lista e cartões modelo?')) return;
    router.delete(route('admin.tarefas.processo-listas.destroy', listId));
}

function deleteCard(cardId) {
    if (!confirm('Remover cartão modelo?')) return;
    router.delete(route('admin.tarefas.processo-cards.destroy', cardId));
}
</script>

<template>
    <Head :title="`Processo: ${template.name}`" />

    <AdminLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-900">Editar modelo</h2>
        </template>

        <div class="space-y-6 p-4">
            <form class="surface-card space-y-4 p-6" @submit.prevent="saveMeta">
                <div>
                    <InputLabel value="Nome" />
                    <TextInput v-model="metaForm.name" class="mt-1 w-full" required />
                </div>
                <div>
                    <InputLabel value="Slug" />
                    <TextInput v-model="metaForm.slug" class="mt-1 w-full" required />
                </div>
                <div>
                    <InputLabel value="Descrição" />
                    <textarea v-model="metaForm.description" rows="2" class="mt-1 w-full rounded border border-slate-300 text-sm" />
                </div>
                <ColorPresetPicker v-model="metaForm.cover_color" label="Cor de capa" />
                <label class="flex items-center gap-2 text-sm">
                    <input v-model="metaForm.is_active" type="checkbox" class="rounded border-slate-300" />
                    Ativo
                </label>
                <PrimaryButton :disabled="metaForm.processing">Guardar modelo</PrimaryButton>
            </form>

            <div class="surface-card space-y-4 p-6">
                <h3 class="font-semibold text-slate-900">Listas do modelo</h3>

                <div
                    v-for="list in template.lists"
                    :key="list.id"
                    class="rounded-lg border border-slate-200 p-4"
                >
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="font-medium">{{ list.name }}</p>
                        <button type="button" class="text-xs text-red-600 underline" @click="deleteList(list.id)">
                            Remover lista
                        </button>
                    </div>
                    <ul class="mt-2 space-y-1 text-sm text-slate-700">
                        <li v-for="c in list.cards" :key="c.id" class="flex justify-between gap-2">
                            <span>{{ c.title }}</span>
                            <button type="button" class="text-xs text-red-600 underline" @click="deleteCard(c.id)">
                                Remover
                            </button>
                        </li>
                    </ul>
                    <div class="mt-2 flex gap-2">
                        <TextInput
                            v-model="newCards[list.id]"
                            class="flex-1 text-sm"
                            placeholder="Novo cartão modelo…"
                            @keyup.enter="addCard(list.id)"
                        />
                        <PrimaryButton type="button" class="py-1 text-xs" @click="addCard(list.id)">
                            Adicionar
                        </PrimaryButton>
                    </div>
                </div>

                <div class="flex flex-wrap items-end gap-2 border-t border-slate-100 pt-4">
                    <div class="flex-1 min-w-[12rem]">
                        <InputLabel value="Nova lista" />
                        <TextInput v-model="newList.name" class="mt-1 w-full" placeholder="Nome" />
                    </div>
                    <div>
                        <InputLabel value="Visibilidade padrão" />
                        <select v-model="newList.default_visibility" class="mt-1 rounded border border-slate-300 text-sm">
                            <option v-for="o in visibilityListOptions" :key="o.value" :value="o.value">
                                {{ o.label }}
                            </option>
                        </select>
                    </div>
                    <label class="flex items-center gap-1 text-xs">
                        <input v-model="newList.allow_company_drop_in" type="checkbox" />
                        Empresa pode largar cartões aqui
                    </label>
                    <PrimaryButton type="button" @click="addList">Adicionar lista</PrimaryButton>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
