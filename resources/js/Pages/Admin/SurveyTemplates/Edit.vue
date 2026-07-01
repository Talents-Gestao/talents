<script setup>
import FormPageHeader from '@/Components/FormPageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    template: Object,
    surveysWithCompletedResponses: { type: Number, default: 0 },
});

const state = reactive({
    sections: JSON.parse(JSON.stringify(props.template.sections || [])).map((s) => ({
        id: s.id ?? null,
        title: s.title,
        description: s.description || '',
        questions: (s.questions || []).map((q) => ({
            id: q.id ?? null,
            body: q.body,
            reverse_score: !!q.reverse_score,
            weight: q.weight != null ? Number(q.weight) : 1,
            response_scale: q.response_scale === 'agreement' ? 'agreement' : 'frequency',
        })),
    })),
});

if (!state.sections.length) {
    state.sections.push({
        title: 'Dimensão',
        description: '',
        questions: [{ body: 'Pergunta', reverse_score: false, weight: 1, response_scale: 'frequency' }],
    });
}

const form = useForm({
    title: props.template.title,
    description: props.template.description || '',
    is_active: props.template.is_active,
    sections: [],
});

const addSection = () => {
    state.sections.push({
        title: 'Dimensão',
        description: '',
        questions: [{ body: 'Pergunta', reverse_score: false, weight: 1, response_scale: 'frequency' }],
    });
};

const addQuestion = (section) => {
    section.questions.push({ body: '', reverse_score: false, weight: 1, response_scale: 'frequency' });
};

const removeSection = (si) => {
    if (state.sections.length <= 1) {
        return;
    }
    state.sections.splice(si, 1);
};

const removeQuestion = (section, qi) => {
    if (section.questions.length <= 1) {
        return;
    }
    section.questions.splice(qi, 1);
};

const submit = () => {
    form.transform(() => ({
        title: form.title,
        description: form.description,
        is_active: form.is_active,
        sections: state.sections,
    })).put(route('admin.survey-templates.update', props.template.id));
};
</script>

<template>
    <Head title="Editar mapeamento" />

    <AdminLayout>
        <template #header>
            <FormPageHeader :back-href="route('admin.survey-templates.index')" title="Editar mapeamento" />
        </template>

        <div
            v-if="$page.props.flash?.success"
            class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
        >
            {{ $page.props.flash.success }}
        </div>

        <form class="space-y-6 text-gray-900" @submit.prevent="submit">
            <div
                v-if="template.forked_from_id"
                class="rounded-lg border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900"
            >
                Nova versão derivada do mapeamento #{{ template.forked_from_id }}.
            </div>
            <div
                v-if="surveysWithCompletedResponses > 0"
                class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900"
                role="alert"
            >
                <p class="font-semibold">Este mapeamento já tem pesquisas com respostas.</p>
                <p class="mt-1">
                    Ao salvar, será criada automaticamente uma <strong>nova versão</strong> deste mapeamento.
                    O mapeamento original e todas as respostas já coletadas permanecem intactos.
                    Use a nova versão apenas em pesquisas futuras.
                </p>
            </div>

            <div class="surface-card p-6">
                <div>
                    <InputLabel for="title" value="Título" />
                    <TextInput id="title" v-model="form.title" class="mt-1 block w-full" required />
                </div>
                <div class="mt-4">
                    <InputLabel for="description" value="Descrição" />
                    <textarea id="description" v-model="form.description" rows="3" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-talents-500 focus:ring-talents-500" />
                </div>
                <label class="mt-4 flex items-center gap-2 text-sm">
                    <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-talents-600 focus:ring-talents-500" />
                    Ativo
                </label>
            </div>

            <div v-for="(section, si) in state.sections" :key="si" class="surface-card p-6">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="font-semibold text-talents-700">Dimensão {{ si + 1 }}</h3>
                    <button
                        v-if="state.sections.length > 1"
                        type="button"
                        class="text-sm font-medium text-red-600 hover:text-red-800 hover:underline"
                        @click="removeSection(si)"
                    >
                        Excluir dimensão
                    </button>
                </div>
                <div class="mt-3">
                    <InputLabel :for="'sec-title-' + si" value="Título" />
                    <TextInput :id="'sec-title-' + si" v-model="section.title" class="mt-1 block w-full" required />
                </div>
                <div class="mt-3">
                    <InputLabel :for="'sec-desc-' + si" value="Descrição" />
                    <textarea :id="'sec-desc-' + si" v-model="section.description" rows="2" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-talents-500 focus:ring-talents-500" />
                </div>
                <div class="mt-4 space-y-3">
                    <div v-for="(q, qi) in section.questions" :key="qi" class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                        <div class="flex items-start justify-between gap-2">
                            <InputLabel :for="'q-' + si + '-' + qi" :value="'Pergunta ' + (qi + 1)" class="flex-1" />
                            <button
                                v-if="section.questions.length > 1"
                                type="button"
                                class="shrink-0 text-xs font-medium text-red-600 hover:text-red-800 hover:underline"
                                @click="removeQuestion(section, qi)"
                            >
                                Excluir
                            </button>
                        </div>
                        <TextInput :id="'q-' + si + '-' + qi" v-model="q.body" class="mt-1 block w-full" required />
                        <div class="mt-2 flex flex-wrap items-end gap-4">
                            <div>
                                <span class="text-xs text-gray-600">Peso</span>
                                <TextInput
                                    type="number"
                                    step="0.1"
                                    min="0.1"
                                    v-model.number="q.weight"
                                    class="mt-1 w-24"
                                />
                            </div>
                            <label class="flex items-center gap-2 text-xs text-gray-600">
                                <input v-model="q.reverse_score" type="checkbox" class="rounded border-gray-300 text-talents-600 focus:ring-talents-500" />
                                Inverter pontuação
                            </label>
                            <div>
                                <label class="text-xs text-gray-600">Escala de resposta</label>
                                <select
                                    v-model="q.response_scale"
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                >
                                    <option value="frequency">Frequência (Nunca…Sempre)</option>
                                    <option value="agreement">Concordância (Discordo…Concordo)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="text-sm font-medium text-talents-700 hover:underline" @click="addQuestion(section)">+ Pergunta</button>
                </div>
            </div>

            <button type="button" class="text-sm font-medium text-talents-700 hover:underline" @click="addSection">+ Dimensão</button>

            <PrimaryButton :disabled="form.processing">Atualizar</PrimaryButton>
        </form>
    </AdminLayout>
</template>
