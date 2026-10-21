<script setup>
import FormPageHeader from '@/Components/FormPageHeader.vue';
import InfoTooltip from '@/Components/InfoTooltip.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatDateTime } from '@/utils/dateOnly';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    company: { type: Object, required: true },
    survey: { type: Object, required: true },
    registration: { type: Object, required: true },
    lastUpdate: { type: Object, default: null },
});

const form = useForm({
    unit_name: props.registration.unit_name ?? '',
    process_name: props.registration.process_name ?? '',
    legal_name_snapshot: props.registration.legal_name_snapshot ?? '',
    cnpj_snapshot: props.registration.cnpj_snapshot ?? '',
    eligible_count: props.registration.eligible_count ?? '',
    eligible_source: props.registration.eligible_source ?? '',
    exposure_groups: [...(props.registration.exposure_groups ?? [])],
    aep_summary: props.registration.aep_summary ?? '',
    observation_summary: props.registration.observation_summary ?? '',
    existing_controls: props.registration.existing_controls ?? '',
    technical_responsible_name: props.registration.technical_responsible_name ?? '',
    technical_responsible_role: props.registration.technical_responsible_role ?? '',
    technical_responsible_registration: props.registration.technical_responsible_registration ?? '',
    approving_organization_name: props.registration.approving_organization_name ?? '',
});

const useCurrentCompanyData = () => {
    form.legal_name_snapshot = props.company.legal_name ?? '';
    form.cnpj_snapshot = props.company.cnpj ?? '';
};

const addExposureGroup = () => {
    form.exposure_groups.push('');
};

const removeExposureGroup = (index) => {
    form.exposure_groups.splice(index, 1);
};

const submit = () => {
    form.transform((data) => ({
        ...data,
        eligible_count: data.eligible_count === '' || data.eligible_count === null ? null : data.eligible_count,
    })).put(route('admin.companies.surveys.registration.update', [props.company.id, props.survey.id]), {
        preserveScroll: true,
    });
};

const inputClass = 'mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500';
const labelClass = 'block text-sm font-medium text-slate-700';
</script>

<template>
    <Head :title="`Cadastro técnico — ${survey.title}`" />

    <AdminLayout>
        <template #header>
            <FormPageHeader
                :back-href="route('admin.companies.surveys.action-plan.edit', [company.id, survey.id])"
                back-label="Voltar ao parecer e plano de ação"
                title="Cadastro técnico da campanha"
                :subtitle="`${company.name} — ${survey.title}`"
            />
        </template>

        <div
            v-if="$page.props.flash?.success"
            class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
        >
            {{ $page.props.flash.success }}
        </div>

        <p class="mb-4 text-sm text-slate-600">
            Todos os campos são opcionais. Campo deixado em branco fica registrado como não informado.
            <span v-if="lastUpdate?.at">
                Última gravação em {{ formatDateTime(lastUpdate.at) }}<span v-if="lastUpdate.by"> por {{ lastUpdate.by }}</span>.
            </span>
        </p>

        <form class="space-y-6" @submit.prevent="submit">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <h3 class="text-base font-semibold text-slate-900">Identificação</h3>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <div class="flex items-center gap-1">
                            <label :class="labelClass" for="unit_name">Unidade</label>
                            <InfoTooltip text="Estabelecimento ou local em que a campanha foi aplicada. Esse nome sai na identificação do subsídio AEP/PGR e do dossiê." />
                        </div>
                        <input id="unit_name" v-model="form.unit_name" type="text" :class="inputClass" />
                        <InputError :message="form.errors.unit_name" class="mt-1" />
                    </div>
                    <div>
                        <div class="flex items-center gap-1">
                            <label :class="labelClass" for="process_name">Processo</label>
                            <InfoTooltip text="Atividade ou processo de trabalho coberto por esta campanha. Situa a avaliação ergonômica e o inventário do PGR." />
                        </div>
                        <input id="process_name" v-model="form.process_name" type="text" :class="inputClass" />
                        <InputError :message="form.errors.process_name" class="mt-1" />
                    </div>
                    <div>
                        <div class="flex items-center gap-1">
                            <label :class="labelClass" for="legal_name_snapshot">Razão social (cópia)</label>
                            <InfoTooltip text="Nome legal impresso no documento. O botão abaixo só preenche este formulário e não altera o cadastro da empresa." />
                        </div>
                        <input id="legal_name_snapshot" v-model="form.legal_name_snapshot" type="text" :class="inputClass" />
                        <InputError :message="form.errors.legal_name_snapshot" class="mt-1" />
                    </div>
                    <div>
                        <div class="flex items-center gap-1">
                            <label :class="labelClass" for="cnpj_snapshot">CNPJ (cópia)</label>
                            <InfoTooltip text="CNPJ impresso no subsídio e no dossiê. É uma cópia desta campanha, não uma atualização do cadastro da empresa." />
                        </div>
                        <input id="cnpj_snapshot" v-model="form.cnpj_snapshot" type="text" :class="inputClass" />
                        <InputError :message="form.errors.cnpj_snapshot" class="mt-1" />
                    </div>
                </div>
                <button
                    type="button"
                    class="mt-3 text-sm font-medium text-talents-700 hover:underline"
                    @click="useCurrentCompanyData"
                >
                    Usar dados atuais da empresa
                </button>
                <p class="mt-1 text-xs text-slate-500">Preenche só razão social e CNPJ neste formulário. O cadastro da empresa não é alterado.</p>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <h3 class="text-base font-semibold text-slate-900">Elegíveis e grupos de exposição</h3>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <div class="flex items-center gap-1">
                            <label :class="labelClass" for="eligible_count">Total de elegíveis</label>
                            <InfoTooltip text="Quantas pessoas podiam responder. Em branco, o documento não calcula taxa de adesão. Use zero só quando o total for realmente zero." />
                        </div>
                        <input id="eligible_count" v-model="form.eligible_count" type="number" min="0" step="1" :class="inputClass" />
                        <p class="mt-1 text-xs text-slate-500">Deixe em branco se ainda não houver o total. Zero só quando for realmente zero.</p>
                        <InputError :message="form.errors.eligible_count" class="mt-1" />
                    </div>
                    <div>
                        <div class="flex items-center gap-1">
                            <label :class="labelClass" for="eligible_source">Origem dos elegíveis</label>
                            <InfoTooltip text="De onde veio o total, por exemplo folha de pagamento ou quadro de lotação. Essa origem é impressa no subsídio AEP/PGR." />
                        </div>
                        <textarea id="eligible_source" v-model="form.eligible_source" rows="2" :class="inputClass" />
                        <InputError :message="form.errors.eligible_source" class="mt-1" />
                    </div>
                </div>

                <div class="mt-5">
                    <div class="flex items-center gap-1">
                        <p :class="labelClass">Grupos de exposição</p>
                        <InfoTooltip text="Grupos de pessoas expostas aos mesmos fatores. O PGR organiza o inventário por esses grupos. Escreva um nome em cada linha." />
                    </div>
                    <p v-if="form.exposure_groups.length === 0" class="mt-1 text-sm text-slate-500">Nenhum grupo informado.</p>
                    <div v-for="(group, index) in form.exposure_groups" :key="index" class="mt-2 flex items-center gap-2">
                        <input v-model="form.exposure_groups[index]" type="text" :class="inputClass" :aria-label="`Grupo de exposição ${index + 1}`" />
                        <button type="button" class="text-sm text-red-700 hover:underline" @click="removeExposureGroup(index)">Remover</button>
                    </div>
                    <InputError :message="form.errors.exposure_groups" class="mt-1" />
                    <button type="button" class="mt-2 text-sm font-medium text-talents-700 hover:underline" @click="addExposureGroup">
                        Adicionar grupo
                    </button>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <h3 class="text-base font-semibold text-slate-900">Avaliação e controles</h3>
                <div class="mt-4 space-y-4">
                    <div>
                        <div class="flex items-center gap-1">
                            <label :class="labelClass" for="aep_summary">Resumo da AEP</label>
                            <InfoTooltip text="Síntese da avaliação ergonômica preliminar. A pesquisa é um insumo dessa avaliação; este texto não substitui a AEP oficial nem classifica o risco do PGR." />
                        </div>
                        <textarea id="aep_summary" v-model="form.aep_summary" rows="4" :class="inputClass" />
                        <InputError :message="form.errors.aep_summary" class="mt-1" />
                    </div>
                    <div>
                        <div class="flex items-center gap-1">
                            <label :class="labelClass" for="observation_summary">Resumo da observação</label>
                            <InfoTooltip text="O que foi visto em visita, entrevista ou posto de trabalho. Complementa a AEP e entra no dossiê como contexto da evidência." />
                        </div>
                        <textarea id="observation_summary" v-model="form.observation_summary" rows="4" :class="inputClass" />
                        <InputError :message="form.errors.observation_summary" class="mt-1" />
                    </div>
                    <div>
                        <div class="flex items-center gap-1">
                            <label :class="labelClass" for="existing_controls">Controles existentes</label>
                            <InfoTooltip text="O que a empresa já faz hoje sobre os fatores psicossociais. No PGR, isso registra o controle existente antes de uma medida nova." />
                        </div>
                        <textarea id="existing_controls" v-model="form.existing_controls" rows="4" :class="inputClass" />
                        <InputError :message="form.errors.existing_controls" class="mt-1" />
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <h3 class="text-base font-semibold text-slate-900">Responsável técnico e aprovação</h3>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <div class="flex items-center gap-1">
                            <label :class="labelClass" for="technical_responsible_name">Nome do responsável técnico</label>
                            <InfoTooltip text="Profissional que responde pela AEP e pelo inventário do PGR. O sistema registra o nome e não assina o documento no lugar dessa pessoa." />
                        </div>
                        <input id="technical_responsible_name" v-model="form.technical_responsible_name" type="text" :class="inputClass" />
                        <InputError :message="form.errors.technical_responsible_name" class="mt-1" />
                    </div>
                    <div>
                        <div class="flex items-center gap-1">
                            <label :class="labelClass" for="technical_responsible_role">Função</label>
                            <InfoTooltip text="Função desse profissional no documento, por exemplo ergonomista ou engenheiro de segurança do trabalho." />
                        </div>
                        <input id="technical_responsible_role" v-model="form.technical_responsible_role" type="text" :class="inputClass" />
                        <InputError :message="form.errors.technical_responsible_role" class="mt-1" />
                    </div>
                    <div>
                        <div class="flex items-center gap-1">
                            <label :class="labelClass" for="technical_responsible_registration">Registro profissional</label>
                            <InfoTooltip text="Número de conselho ou registro profissional, quando houver. Fica impresso junto do responsável técnico." />
                        </div>
                        <input
                            id="technical_responsible_registration"
                            v-model="form.technical_responsible_registration"
                            type="text"
                            :class="inputClass"
                        />
                        <InputError :message="form.errors.technical_responsible_registration" class="mt-1" />
                    </div>
                    <div>
                        <div class="flex items-center gap-1">
                            <label :class="labelClass" for="approving_organization_name">Organização que aprova</label>
                            <InfoTooltip text="Empresa ou órgão que formaliza a aprovação da AEP ou do PGR, quando essa aprovação existir fora do sistema." />
                        </div>
                        <input id="approving_organization_name" v-model="form.approving_organization_name" type="text" :class="inputClass" />
                        <InputError :message="form.errors.approving_organization_name" class="mt-1" />
                    </div>
                </div>
            </section>

            <div class="flex justify-end">
                <PrimaryButton :disabled="form.processing">Salvar cadastro técnico</PrimaryButton>
            </div>
        </form>
    </AdminLayout>
</template>
