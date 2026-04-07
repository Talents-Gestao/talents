<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';

const state = reactive({
    sections: [
        {
            title: 'Nova seção',
            description: '',
            questions: [
                {
                    body: 'Nova pergunta (escala 0–5)',
                    type: 'scale',
                    is_required: true,
                    scale_min: 0,
                    scale_max: 5,
                },
            ],
        },
    ],
});

const form = useForm({
    title: '',
    description: '',
    step_number: 2,
    sections: [],
});

const addSection = () => {
    state.sections.push({
        title: 'Seção',
        description: '',
        questions: [
            {
                body: 'Pergunta',
                type: 'scale',
                is_required: true,
                scale_min: 0,
                scale_max: 5,
            },
        ],
    });
};

const addQuestion = (section) => {
    section.questions.push({
        body: '',
        type: 'scale',
        is_required: true,
        scale_min: 0,
        scale_max: 5,
    });
};

const submit = () => {
    form.transform(() => ({
        title: form.title,
        description: form.description,
        step_number: form.step_number,
        sections: state.sections,
    })).post(route('admin.methodology-templates.store'));
};
</script>

<template>
    <Head title="Novo template Metodologia" />

    <AdminLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-900">Novo template — satisfação</h2>
        </template>

        <form class="space-y-6 text-gray-900" @submit.prevent="submit">
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
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
                    <InputLabel for="step_number" value="Número da etapa (padrão 2)" />
                    <TextInput id="step_number" v-model.number="form.step_number" type="number" min="1" max="10" class="mt-1 w-32" />
                </div>
            </div>

            <div v-for="(section, si) in state.sections" :key="si" class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
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

            <PrimaryButton :disabled="form.processing">Salvar template</PrimaryButton>
        </form>
    </AdminLayout>
</template>
