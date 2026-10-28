<script setup>
import FormPageHeader from '@/Components/FormPageHeader.vue';
import InfoTooltip from '@/Components/InfoTooltip.vue';
import LaudoBody from '@/Components/Nr1Legacy/LaudoBody.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowDownTrayIcon } from '@heroicons/vue/24/outline';
import { ref } from 'vue';

/**
 * Página antiga «Parecer e plano»: layout do Admin + cabeçalho com as ações; o corpo (Diagnóstico, Parecer, Publicar,
 * Documentos liberados) vive em Components/Nr1Legacy/LaudoBody.vue, o mesmo que o shell NR-1 usa.
 */
defineOptions({ inheritAttrs: false });

const props = defineProps({
    company: Object,
    survey: Object,
    executiveBrief: { type: Object, default: null },
});

const body = ref(null);
</script>

<template>
    <Head :title="`Parecer e plano — ${survey.title}`" />

    <AdminLayout>
        <template #header>
            <FormPageHeader
                :back-href="route('admin.companies.show', company.id)"
                back-label="Voltar à empresa"
                title="Parecer técnico e plano de ação (NR-1)"
                :subtitle="`${company.name} — ${survey.title}`"
                stack-actions
            >
                <template #trailing>
                    <span class="inline-flex items-center gap-1">
                        <Link
                            :href="route('admin.companies.surveys.registration.edit', [company.id, survey.id])"
                            class="inline-flex items-center rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50"
                        >
                            Cadastro técnico
                        </Link>
                        <InfoTooltip text="Abre os dados que entram no PDF: unidade, elegíveis, resumo da AEP, controles e responsável. Campo vazio sai como «Não informado». Não altera a empresa." />
                    </span>
                    <span class="inline-flex items-center gap-1">
                        <Link
                            :href="route('admin.companies.surveys.measures.edit', [company.id, survey.id])"
                            class="inline-flex items-center rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50"
                        >
                            Medidas
                        </Link>
                        <InfoTooltip text="A medida nasce «A aprovar». Ela entra no próximo documento liberado. Um PDF já emitido não muda. O plano de ação antigo continua separado." />
                    </span>
                    <span class="inline-flex items-center gap-1">
                        <button
                            type="button"
                            class="inline-flex items-center rounded-full border border-talents-300 bg-white px-4 py-2 text-sm font-medium text-talents-900 shadow-sm hover:bg-talents-50 disabled:opacity-60"
                            :disabled="body?.releasingExecutiveBrief"
                            @click="body?.releaseExecutiveBrief()"
                        >
                            Liberar resumo executivo
                        </button>
                        <InfoTooltip text="Congela uma página com o painel, o cadastro e as medidas. O cliente baixa em Resultados da campanha → Painel gerencial, no botão «Baixar Resumo executivo». Campanha HSE com polaridade pendente ainda pode sair, com o aviso." />
                    </span>
                    <a
                        v-if="executiveBrief?.pdf_status === 'ready'"
                        :href="route('admin.companies.surveys.executive-brief.download', [company.id, survey.id])"
                        class="inline-flex items-center gap-1.5 rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50"
                    >
                        <ArrowDownTrayIcon class="h-4 w-4" aria-hidden="true" />
                        Baixar resumo executivo (v{{ executiveBrief.version_number }})
                    </a>
                    <span class="inline-flex items-center gap-1">
                        <button
                            type="button"
                            class="inline-flex items-center rounded-full border border-talents-300 bg-white px-4 py-2 text-sm font-medium text-talents-900 shadow-sm hover:bg-talents-50 disabled:opacity-60"
                            :disabled="body?.releasingPgrSubsidy"
                            @click="body?.releasePgrSubsidy()"
                        >
                            Liberar subsídio AEP/PGR
                        </button>
                        <InfoTooltip text="O mesmo congelamento do resumo, mais a tabela do inventário em «Não informado». Não substitui a AEP oficial nem o PGR. O cliente baixa no mesmo Painel gerencial. HSE com polaridade pendente não libera." />
                    </span>
                    <span class="inline-flex items-center gap-1">
                        <button
                            type="button"
                            class="inline-flex items-center rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50"
                            @click="body?.openExportPdfModal()"
                        >
                            Exportar resultados
                        </button>
                        <InfoTooltip text="É o PDF antigo do parecer, com indicadores e gráficos. É outro arquivo, diferente do resumo, do subsídio e do dossiê." />
                    </span>
                </template>
            </FormPageHeader>
        </template>

        <LaudoBody ref="body" v-bind="{ ...$props, ...$attrs }" />
    </AdminLayout>
</template>
