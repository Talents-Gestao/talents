<script setup>
import MiaNr1AdminPanel from '@/Components/MiaNr1AdminPanel.vue';
import Nr1SurveyResultsPanel from '@/Components/Nr1SurveyResultsPanel.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    company: Object,
    survey: Object,
    overall: { type: Object, default: null },
    bySection: { type: Array, default: () => [] },
    deptOveralls: { type: Array, default: () => [] },
    deptSectionsByDepartment: { type: Array, default: () => [] },
    insights: { type: Array, default: () => [] },
    questionDistributions: { type: Array, default: () => [] },
    departmentParticipation: { type: Array, default: () => [] },
    questionDistributionsByDepartment: { type: Array, default: () => [] },
    plan: { type: Object, default: null },
    items: { type: Array, default: () => [] },
    aiEnabled: { type: Boolean, default: false },
    aiAnalysis: { type: Object, default: null },
    aiAnalysisPending: { type: Boolean, default: false },
    aiGeneratePostUrl: { type: String, required: true },
});

const form = useForm({
    items:
        props.items.length > 0
            ? props.items.map((i) => ({ title: i.title, description: i.description ?? '' }))
            : [{ title: '', description: '' }],
});

const addRow = () => {
    form.items.push({ title: '', description: '' });
};

const removeRow = (index) => {
    form.items.splice(index, 1);
    if (form.items.length === 0) {
        form.items.push({ title: '', description: '' });
    }
};

const submit = () => {
    form
        .transform((data) => ({
            items: data.items.filter((row) => String(row.title).trim() !== ''),
        }))
        .put(route('admin.companies.surveys.action-plan.update', [props.company.id, props.survey.id]));
};
</script>

<template>
    <Head :title="`Plano de ação — ${survey.title}`" />

    <AdminLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-900">Plano de ação (NR-1)</h2>
                    <p class="mt-1 text-sm text-gray-600">
                        {{ company.name }} — {{ survey.title }}
                    </p>
                </div>
                <Link
                    :href="route('admin.companies.show', company.id)"
                    class="text-sm font-medium text-talents-700 hover:underline"
                >
                    Voltar à empresa
                </Link>
            </div>
        </template>

        <div
            v-if="$page.props.flash?.success"
            class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
        >
            {{ $page.props.flash.success }}
        </div>
        <div
            v-if="$page.props.flash?.error"
            class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900"
        >
            {{ $page.props.flash.error }}
        </div>
        <div
            v-if="$page.props.flash?.info"
            class="mb-4 rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900"
        >
            {{ $page.props.flash.info }}
        </div>

        <div class="mb-6 surface-card p-5">
            <h3 class="text-sm font-semibold text-gray-900">Pesquisa</h3>
            <dl class="mt-3 grid gap-2 text-sm text-gray-700 sm:grid-cols-2">
                <div><span class="text-gray-500">Status:</span> {{ survey.status }}</div>
                <div>
                    <span class="text-gray-500">Período:</span>
                    {{ survey.starts_at }} — {{ survey.ends_at }}
                </div>
                <div class="sm:col-span-2">
                    <span class="text-gray-500">Mín. respondentes por setor (quebra):</span>
                    {{ survey.min_responses_for_breakdown }}
                </div>
            </dl>
        </div>

        <div class="mb-4">
            <h3 class="text-lg font-semibold text-talents-900">Detalhamento dos resultados</h3>
            <p class="mt-1 text-sm text-gray-600">
                Use os gráficos e a tabela abaixo para embasar os itens do plano de ação. Os dados são os mesmos vistos pela empresa em
                Resultados (agregados e anônimos).
            </p>
        </div>

        <Nr1SurveyResultsPanel
            :survey="survey"
            :overall="overall"
            :by-section="bySection"
            :dept-overalls="deptOveralls"
            :dept-sections-by-department="deptSectionsByDepartment"
            :insights="insights"
            :question-distributions="questionDistributions"
            :department-participation="departmentParticipation"
            :question-distributions-by-department="questionDistributionsByDepartment"
        />

        <div v-if="!overall" class="mb-8 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
            Ainda não há resultados agregados para esta pesquisa. Verifique se há respostas concluídas e se o recálculo foi feito no portal da
            empresa.
        </div>

        <MiaNr1AdminPanel
            v-if="aiEnabled"
            :generate-post-url="aiGeneratePostUrl"
            :ai-enabled="aiEnabled"
            :ai-analysis="aiAnalysis"
            :ai-analysis-pending="aiAnalysisPending"
        />
        <div v-else class="mb-8 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
            A Mia não está disponível (API desativada ou sem chave). Configure em
            <Link :href="route('admin.settings.edit')" class="font-medium text-talents-800 underline">Configurações</Link>
            para gerar análise automática e apoiar o preenchimento do plano.
        </div>

        <div class="mb-6 rounded-lg border border-sky-100 bg-sky-50/80 p-4 text-sm text-sky-950">
            <p>
                Preencha os itens abaixo. Ao salvar com pelo menos um título preenchido, o plano fica
                <strong>visível para a empresa</strong> na página de Plano de ação da pesquisa. Itens vazios são ignorados. Para ocultar o
                plano do cliente, remova todos os itens e salve.
            </p>
            <p v-if="plan?.admin_published_at" class="mt-2 text-xs text-sky-900/80">
                Última publicação: {{ plan.admin_published_at }}
            </p>
        </div>

        <form class="space-y-6 surface-card p-6 text-slate-900" @submit.prevent="submit">
            <div class="flex items-center justify-between">
                <h3 class="font-semibold text-talents-800">Itens do plano de ação</h3>
                <button type="button" class="text-sm font-medium text-talents-700 hover:underline" @click="addRow">+ Adicionar item</button>
            </div>

            <div v-for="(row, index) in form.items" :key="index" class="rounded-lg border border-gray-200 p-4">
                <div class="flex items-start justify-between gap-2">
                    <div class="grid flex-1 gap-3 sm:grid-cols-1">
                        <div>
                            <InputLabel :for="'title-' + index" value="Título" />
                            <TextInput
                                :id="'title-' + index"
                                v-model="row.title"
                                class="mt-1 block w-full"
                                placeholder="Ex.: Revisar dimensão demanda psicológica"
                            />
                        </div>
                        <div>
                            <InputLabel :for="'desc-' + index" value="Descrição" />
                            <textarea
                                :id="'desc-' + index"
                                v-model="row.description"
                                rows="3"
                                class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                placeholder="Orientações e próximos passos para a empresa..."
                            />
                        </div>
                    </div>
                    <button
                        type="button"
                        class="shrink-0 rounded-md border border-red-200 bg-white px-3 py-1.5 text-sm text-red-700 hover:bg-red-50"
                        @click="removeRow(index)"
                    >
                        Remover
                    </button>
                </div>
            </div>

            <div v-if="form.errors.items" class="text-sm text-red-600">{{ form.errors.items }}</div>

            <div class="flex gap-3">
                <PrimaryButton :disabled="form.processing">Salvar e publicar para a empresa</PrimaryButton>
            </div>
        </form>
    </AdminLayout>
</template>
