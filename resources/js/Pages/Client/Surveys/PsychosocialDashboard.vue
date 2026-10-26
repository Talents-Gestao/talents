<script setup>
import Nr1DashboardHeader from '@/Components/Nr1Dashboard/Nr1DashboardHeader.vue';
import Nr1PanelTabs from '@/Components/Nr1Dashboard/Nr1PanelTabs.vue';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    dashboard: { type: Object, required: true },
    nextActions: { type: Array, default: () => [] },
    campaigns: { type: Array, default: () => [] },
    executiveBrief: { type: Object, default: null },
    psychosocialDocuments: { type: Array, default: () => [] },
    canValidate: { type: Boolean, default: false },
    validationDeclaration: { type: String, default: '' },
    documentsUrl: { type: String, default: null },
});

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
                    <Link
                        v-if="documentsUrl"
                        :href="documentsUrl"
                        class="rounded-md border border-gray-300 px-3 py-1 text-sm font-semibold text-gray-800"
                    >
                        Documentos
                    </Link>
                    <Link
                        :href="route('client.surveys.measures.index', dashboard.survey.id)"
                        class="rounded-md border border-gray-300 px-3 py-1 text-sm font-semibold text-gray-800"
                    >
                        Plano de ação
                    </Link>
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

            <Nr1PanelTabs
                :dashboard="dashboard"
                :next-actions="nextActions"
                :campaigns="campaigns"
                :plan-url="route('client.surveys.measures.index', dashboard.survey.id)"
                :print-url="route('client.surveys.psychosocial-dashboard.print', dashboard.survey.id)"
            />
        </div>
    </ClientLayout>
</template>
