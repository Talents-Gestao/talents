<script setup>
import Nr1AttentionList from '@/Components/Nr1Dashboard/Nr1AttentionList.vue';
import Nr1DashboardHeader from '@/Components/Nr1Dashboard/Nr1DashboardHeader.vue';
import Nr1DimensionBars from '@/Components/Nr1Dashboard/Nr1DimensionBars.vue';
import Nr1GroupParticipation from '@/Components/Nr1Dashboard/Nr1GroupParticipation.vue';
import Nr1ItemCards from '@/Components/Nr1Dashboard/Nr1ItemCards.vue';
import Nr1StrengthsList from '@/Components/Nr1Dashboard/Nr1StrengthsList.vue';
import Nr1SummaryCards from '@/Components/Nr1Dashboard/Nr1SummaryCards.vue';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    dashboard: { type: Object, required: true },
    executiveBrief: { type: Object, default: null },
    psychosocialDocuments: { type: Array, default: () => [] },
    canValidate: { type: Boolean, default: false },
    validationDeclaration: { type: String, default: '' },
});

const activeTab = ref('overview');

const validationForm = useForm({
    declaration_accepted: false,
});

const downloadableDocuments = () => props.psychosocialDocuments.filter((doc) => doc.pdf_status === 'ready');
const validatableDocuments = () => props.psychosocialDocuments.filter((doc) => doc.validatable);

const submitValidation = (type) => {
    validationForm.post(route('client.surveys.psychosocial-documents.validate', [props.dashboard.survey.id, type]), {
        preserveScroll: true,
        onSuccess: () => validationForm.reset(),
    });
};

const bandClasses = {
    green: 'bg-emerald-50 text-emerald-800 border-emerald-200',
    yellow: 'bg-amber-50 text-amber-800 border-amber-200',
    red: 'bg-red-50 text-red-800 border-red-200',
};

const bandClass = (riskLevel) => bandClasses[riskLevel] ?? 'bg-gray-50 text-gray-700 border-gray-200';
</script>

<template>
    <Head :title="`Painel gerencial - ${dashboard.survey.title}`" />

    <ClientLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-talents-900">Painel gerencial</h2>
                    <p class="text-sm text-gray-600">{{ dashboard.survey.title }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a
                        v-for="doc in downloadableDocuments()"
                        :key="doc.type"
                        :href="route('client.surveys.psychosocial-documents.download', [dashboard.survey.id, doc.type])"
                        class="rounded-md bg-talents-700 px-3 py-1 text-sm font-semibold text-white"
                    >
                        Baixar {{ doc.label }}
                    </a>
                    <a
                        v-if="executiveBrief?.pdf_status === 'ready' && !psychosocialDocuments.some((doc) => doc.type === 'executive_brief' && doc.pdf_status === 'ready')"
                        :href="route('client.surveys.executive-brief.download', dashboard.survey.id)"
                        class="rounded-md bg-talents-700 px-3 py-1 text-sm font-semibold text-white"
                    >
                        Baixar resumo executivo
                    </a>
                    <a
                        :href="route('client.surveys.psychosocial-dashboard.print', dashboard.survey.id)"
                        target="_blank"
                        rel="noopener"
                        class="rounded-md bg-talents-700 px-3 py-1 text-sm font-semibold text-white"
                    >
                        Imprimir panorama
                    </a>
                    <a
                        :href="route('client.surveys.psychosocial-dashboard.export.items', dashboard.survey.id)"
                        class="rounded-md border border-gray-300 px-3 py-1 text-sm font-semibold text-gray-800"
                    >
                        Exportar perguntas (CSV)
                    </a>
                    <a
                        :href="route('client.surveys.psychosocial-dashboard.export.csv', dashboard.survey.id)"
                        class="rounded-md border border-gray-300 px-3 py-1 text-sm font-semibold text-gray-800"
                    >
                        Exportar CSV agregado
                    </a>
                    <a
                        :href="route('client.surveys.psychosocial-dashboard.export.json', dashboard.survey.id)"
                        class="rounded-md border border-gray-300 px-3 py-1 text-sm font-semibold text-gray-800"
                        target="_blank"
                    >
                        Exportar JSON agregado
                    </a>
                    <Link :href="route('client.surveys.results', dashboard.survey.id)" class="text-sm text-talents-700 hover:underline">
                        Voltar aos resultados
                    </Link>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <div
                v-if="$page.props.flash?.success"
                class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
            >
                {{ $page.props.flash.success }}
            </div>
            <div
                v-if="$page.props.flash?.error"
                class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900"
            >
                {{ $page.props.flash.error }}
            </div>

            <section v-if="canValidate && validatableDocuments().length" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h3 class="text-base font-semibold text-talents-900">Validar documento</h3>
                <p class="mt-1 text-sm text-gray-600">A validação se refere ao conteúdo congelado da versão atual. O PDF original não é refeito; nasce uma versão validada.</p>
                <div v-for="doc in validatableDocuments()" :key="doc.type" class="mt-4 rounded-lg border border-gray-100 p-4">
                    <p class="text-sm font-medium text-gray-900">{{ doc.label }} · versão {{ doc.version_number }}</p>
                    <label class="mt-2 flex items-start gap-2 text-sm text-gray-700">
                        <input v-model="validationForm.declaration_accepted" type="checkbox" class="mt-1 h-4 w-4 rounded border-gray-300" />
                        <span>{{ validationDeclaration }}</span>
                    </label>
                    <p v-if="validationForm.errors.declaration_accepted" class="mt-1 text-xs text-red-600">
                        {{ validationForm.errors.declaration_accepted }}
                    </p>
                    <button
                        type="button"
                        class="mt-3 rounded-md bg-talents-700 px-3 py-1.5 text-sm font-semibold text-white disabled:opacity-60"
                        :disabled="validationForm.processing"
                        @click="submitValidation(doc.type)"
                    >
                        Validar {{ doc.label }}
                    </button>
                </div>
            </section>

            <div
                v-if="dashboard.polarity_warning"
                class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-900"
            >
                {{ dashboard.polarity_warning }}
            </div>

            <div
                v-if="dashboard.eligible.alert"
                class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900"
            >
                {{ dashboard.eligible.alert }}
            </div>

            <div v-if="!dashboard.has_results" class="rounded-lg border border-gray-200 bg-white px-4 py-6 text-sm text-gray-600">
                Os resultados desta campanha ainda não foram calculados.
            </div>

            <Nr1DashboardHeader :header="dashboard.header" />

            <div role="tablist" aria-label="Seções do painel" class="flex gap-2 border-b border-gray-200">
                <button
                    id="tab-overview"
                    type="button"
                    role="tab"
                    :aria-selected="activeTab === 'overview'"
                    aria-controls="panel-overview"
                    class="-mb-px border-b-2 px-4 py-2 text-sm font-semibold"
                    :class="activeTab === 'overview' ? 'border-talents-700 text-talents-900' : 'border-transparent text-gray-600 hover:text-gray-900'"
                    @click="activeTab = 'overview'"
                >
                    Visão geral
                </button>
                <button
                    id="tab-results"
                    type="button"
                    role="tab"
                    :aria-selected="activeTab === 'results'"
                    aria-controls="panel-results"
                    class="-mb-px border-b-2 px-4 py-2 text-sm font-semibold"
                    :class="activeTab === 'results' ? 'border-talents-700 text-talents-900' : 'border-transparent text-gray-600 hover:text-gray-900'"
                    @click="activeTab = 'results'"
                >
                    Resultados da pesquisa
                </button>
            </div>

            <div v-show="activeTab === 'overview'" id="panel-overview" role="tabpanel" aria-labelledby="tab-overview" class="space-y-6">
                <Nr1SummaryCards :cards="dashboard.cards" :band-disclaimer="dashboard.band_disclaimer" />

                <div class="grid gap-6 lg:grid-cols-2">
                    <Nr1StrengthsList :strengths="dashboard.strengths" />
                    <Nr1DimensionBars :dimensions="dashboard.dimensions" :legend="dashboard.method.legend" />
                </div>

                <Nr1AttentionList :attention="dashboard.attention" :has-results="dashboard.has_results" />
            </div>

            <div v-show="activeTab === 'results'" id="panel-results" role="tabpanel" aria-labelledby="tab-results" class="space-y-6">
                <Nr1GroupParticipation :groups="dashboard.groups" :respondents="dashboard.respondents" />

                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <h3 class="text-base font-semibold text-talents-900">Setores</h3>
                    <p class="mt-1 text-xs text-gray-500">
                        Setores com menos de {{ dashboard.min_group_size }} respondentes não são exibidos, e outros setores pequenos podem ser
                        ocultados para impedir a identificação por subtração.
                    </p>
                    <p v-if="dashboard.sectors.length === 0" class="mt-3 text-sm text-gray-600">Nenhum setor pode ser exibido nesta campanha.</p>
                    <div v-for="sector in dashboard.sectors" :key="sector.department_id" class="mt-4 rounded-lg border border-gray-100 p-4">
                        <div class="flex flex-wrap items-center gap-3">
                            <p class="font-semibold text-talents-900">{{ sector.department_name }}</p>
                            <span class="text-xs text-gray-500">n = {{ sector.n }}</span>
                            <span class="font-semibold">{{ sector.score_label }}</span>
                            <span class="inline-flex rounded-md border px-2 py-0.5 text-xs font-semibold" :class="bandClass(sector.risk_level)">
                                {{ sector.band_label }}
                            </span>
                            <span class="text-xs text-gray-500">{{ dashboard.band_disclaimer }}</span>
                        </div>
                        <ul class="mt-2 space-y-1 text-sm">
                            <li v-for="dimension in sector.dimensions" :key="dimension.section_id" class="flex flex-wrap items-center gap-2">
                                <span class="text-gray-700">{{ dimension.title }}</span>
                                <span class="font-semibold">{{ dimension.score_label }}</span>
                                <span class="inline-flex rounded-md border px-2 py-0.5 text-xs font-semibold" :class="bandClass(dimension.risk_level)">
                                    {{ dimension.band_label }}
                                </span>
                                <span class="text-xs text-gray-500">{{ dashboard.band_disclaimer }}</span>
                            </li>
                        </ul>
                    </div>
                </section>

                <Nr1ItemCards :items="dashboard.items" :respondents="dashboard.respondents" />
            </div>
        </div>
    </ClientLayout>
</template>
