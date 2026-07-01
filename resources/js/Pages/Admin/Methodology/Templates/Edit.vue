<script setup>
import FormPageHeader from '@/Components/FormPageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({ template: Object });

const state = reactive({
    sections: JSON.parse(JSON.stringify(props.template.sections || [])).map((s) => ({
        title: s.title,
        description: s.description || '',
        questions: (s.questions || []).map((q) => ({
            body: q.body,
            type: q.type === 'text' ? 'text' : 'scale',
            is_required: !!q.is_required,
            scale_min: q.scale_min != null ? Number(q.scale_min) : 0,
            scale_max: q.scale_max != null ? Number(q.scale_max) : 5,
        })),
    })),
});

if (!state.sections.length) {
    state.sections.push({
        title: 'Seção',
        description: '',
        questions: [{ body: 'Pergunta', type: 'scale', is_required: true, scale_min: 0, scale_max: 5 }],
    });
}

const form = useForm({
    title: props.template.title,
    description: props.template.description || '',
    step_number: props.template.step_number ?? 2,
    is_active: props.template.is_active,
    sections: [],
});

const addSection = () => {
    state.sections.push({
        title: 'Seção',
        description: '',
        questions: [{ body: '', type: 'scale', is_required: true, scale_min: 0, scale_max: 5 }],
    });
};

const addQuestion = (section) => {
    section.questions.push({ body: '', type: 'scale', is_required: true, scale_min: 0, scale_max: 5 });
};

const submit = () => {
    form.transform(() => ({
        title: form.title,
        description: form.description,
        step_number: form.step_number,
        is_active: form.is_active,
        sections: state.sections,
    })).put(route('admin.methodology-templates.update', props.template.id));
};
</script>

<template>
    <Head title="Editar template — Direcionamento Estratégico" />

    <AdminLayout>
        <template #header>
            <FormPageHeader :back-href="route('admin.methodology-templates.index')" title="Editar template" />
        </template>

        <form class="space-y-6 text-gray-900" @submit.prevent="submit">
            <div class="surface-card p-6">
                <div>
                    <InputLabel for="title" value="Título" />
                    <TextInput id="title" v-model="form.title" class="mt-1 block w-full" required />
                </div>
                <div class="mt-4">
                    <InputLabel for="description" value="Descrição" />
                    <textarea
                        id="description"
                        v-model="form.description"
                        rows="3"
                        class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    />
                </div>
                <div class="mt-4">
                    <InputLabel for="step_number" value="Etapa" />
                    <TextInput id="step_number" v-model.number="form.step_number" type="number" min="1" max="10" class="mt-1 w-32" />
                </div>
                <label class="mt-4 flex items-center gap-2 text-sm">
                    <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-talents-600 focus:ring-talents-500" />
                    Template ativo
                </label>
            </div>

            <div v-for="(section, si) in state.sections" :key="si" class="surface-card p-6">
                <h3 class="font-semibold text-talents-700">Seção {{ si + 1 }}</h3>
                <div class="mt-3">
                    <InputLabel :for="'sec-title-' + si" value="Título" />
                    <TextInput :id="'sec-title-' + si" v-model="section.title" class="mt-1 block w-full" required />
                </div>
                <div class="mt-3">
                    <InputLabel :for="'sec-desc-' + si" value="Descrição (opcional)" />
                    <textarea
                        :id="'sec-desc-' + si"
                        v-model="section.description"
                        rows="2"
                        class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    />
                </div>
                <div class="mt-4 space-y-3">
                    <div v-for="(q, qi) in section.questions" :key="qi" class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                        <InputLabel :for="'q-' + si + '-' + qi" :value="'Pergunta ' + (qi + 1)" />
                        <TextInput :id="'q-' + si + '-' + qi" v-model="q.body" class="mt-1 block w-full" required />
                        <div class="mt-2 flex flex-wrap items-end gap-4">
                            <div>
                                <label class="text-xs text-gray-600">Tipo</label>
                                <select
                                    v-model="q.type"
                                    class="mt-1 block rounded-md border-gray-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                >
                                    <option value="scale">Escala numérica</option>
                                    <option value="text">Texto aberto</option>
                                </select>
                            </div>
                            <template v-if="q.type === 'scale'">
                                <div>
                                    <label class="text-xs text-gray-600">Mín</label>
                                    <TextInput v-model.number="q.scale_min" type="number" min="0" max="10" class="mt-1 w-20" />
                                </div>
                                <div>
                                    <label class="text-xs text-gray-600">Máx</label>
                                    <TextInput v-model.number="q.scale_max" type="number" min="0" max="10" class="mt-1 w-20" />
                                </div>
                            </template>
                            <label class="flex items-center gap-2 text-xs text-gray-600">
                                <input v-model="q.is_required" type="checkbox" class="rounded border-gray-300 text-talents-600 focus:ring-talents-500" />
                                Obrigatória
                            </label>
                        </div>
                    </div>
                    <button type="button" class="text-sm font-medium text-talents-700 hover:underline" @click="addQuestion(section)">+ Pergunta</button>
                </div>
            </div>

            <button type="button" class="text-sm font-medium text-talents-700 hover:underline" @click="addSection">+ Seção</button>

            <PrimaryButton :disabled="form.processing">Salvar alterações</PrimaryButton>
        </form>
    </AdminLayout>
</template>
