<script setup>
import InfoTooltip from '@/Components/InfoTooltip.vue';
import { formatDateTime } from '@/utils/dateOnly';
import { PlusIcon, TrashIcon } from '@heroicons/vue/24/outline';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    companyId: { type: Number, required: true },
    surveyId: { type: Number, required: true },
    documents: { type: Array, default: () => [] },
});

const sealLabels = {
    pending_validation: 'Aguardando validação',
    validated: 'Validado',
};

const pdfLabels = {
    queued: 'PDF em geração',
    ready: 'PDF pronto',
    failed: 'PDF falhou',
};

const openFormType = ref(null);

const form = useForm({
    name: '',
    role: '',
    registry: '',
    date: '',
    proof: null,
});

const toggleForm = (type) => {
    openFormType.value = openFormType.value === type ? null : type;
    form.reset();
    form.clearErrors();
};

const onProofChange = (event) => {
    form.proof = event.target.files?.[0] ?? null;
};

const submitInformed = (type) => {
    form.post(route('admin.companies.surveys.psychosocial-documents.validate-informed', [props.companyId, props.surveyId, type]), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            openFormType.value = null;
            form.reset();
        },
    });
};

const dossierForm = useForm({
    attachments: [],
});

const addDossierRow = () => {
    dossierForm.attachments.push({ kind: 'aep', file: null });
};

const removeDossierRow = (index) => {
    dossierForm.attachments.splice(index, 1);
};

const onDossierFile = (index, event) => {
    dossierForm.attachments[index].file = event.target.files?.[0] ?? null;
};

const submitDossier = () => {
    dossierForm.post(route('admin.companies.surveys.evidence-dossier.release', [props.companyId, props.surveyId]), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => dossierForm.reset(),
    });
};

const unpublishedHint = (type) =>
    ({
        executive_brief: 'Libere no botão do topo. Vira uma página congelada para o cliente baixar.',
        pgr_subsidy: 'Libere no botão do topo. Reúne o achado da pesquisa para a AEP e deixa o inventário do PGR em branco.',
        evidence_dossier: 'Libere no formulário abaixo. Pode ir sem anexo.',
    })[type] ?? '';
</script>

<template>
    <section class="mb-6 rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm sm:p-6">
        <h2 class="text-base font-semibold text-slate-900">Documentos liberados</h2>
        <ul class="mt-2 space-y-1 text-sm text-slate-600">
            <li>Esta lista é a versão atual de cada documento. Liberar de novo cria outra versão; a anterior continua guardada.</li>
            <li>«Baixar PDF» entrega o arquivo já gravado. O cliente vê o mesmo arquivo no Painel gerencial quando o PDF está pronto.</li>
            <li>«Registrar validação informada» é o Admin lançando a validação feita fora, com comprovante. A validação dentro do sistema é o cliente, no Painel gerencial, com a declaração.</li>
        </ul>

        <ul class="mt-4 divide-y divide-slate-100">
            <li v-for="doc in documents" :key="doc.type" class="py-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-slate-900">{{ doc.label }}</p>
                        <p v-if="doc.version_number" class="text-xs text-slate-500">
                            Versão {{ doc.version_number }} · {{ sealLabels[doc.seal] ?? doc.seal }} · {{ pdfLabels[doc.pdf_status] ?? doc.pdf_status }}
                            · registrada em {{ formatDateTime(doc.released_at) }}
                        </p>
                        <p v-else class="text-xs text-slate-500">
                            Ainda não liberado.
                            <span v-if="unpublishedHint(doc.type)" class="mt-0.5 block">{{ unpublishedHint(doc.type) }}</span>
                        </p>
                        <ul v-if="doc.attachments?.length" class="mt-1 space-y-1">
                            <li v-for="attachment in doc.attachments" :key="attachment.id" class="text-xs text-slate-600">
                                {{ attachment.kind_label }} —
                                <a
                                    :href="route('admin.companies.surveys.evidence-dossier.attachments.download', [companyId, surveyId, attachment.id])"
                                    class="font-medium text-talents-700 hover:underline"
                                >
                                    {{ attachment.file_name }}
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a
                            v-if="doc.pdf_status === 'ready'"
                            :href="route('admin.companies.surveys.psychosocial-documents.download', [companyId, surveyId, doc.type])"
                            class="inline-flex items-center rounded-full border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                        >
                            Baixar PDF
                        </a>
                        <button
                            v-if="doc.validatable"
                            type="button"
                            class="inline-flex items-center rounded-full border border-talents-300 bg-white px-3 py-1.5 text-xs font-medium text-talents-900 hover:bg-talents-50"
                            @click="toggleForm(doc.type)"
                        >
                            Registrar validação informada
                        </button>
                    </div>
                </div>

                <form
                    v-if="openFormType === doc.type"
                    class="mt-3 grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 sm:grid-cols-2"
                    @submit.prevent="submitInformed(doc.type)"
                >
                    <label class="text-sm text-slate-700">
                        Nome do responsável
                        <input v-model="form.name" type="text" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
                        <span v-if="form.errors.name" class="text-xs text-red-600">{{ form.errors.name }}</span>
                    </label>
                    <label class="text-sm text-slate-700">
                        Função
                        <input v-model="form.role" type="text" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
                        <span v-if="form.errors.role" class="text-xs text-red-600">{{ form.errors.role }}</span>
                    </label>
                    <label class="text-sm text-slate-700">
                        Registro profissional (opcional)
                        <input v-model="form.registry" type="text" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
                        <span v-if="form.errors.registry" class="text-xs text-red-600">{{ form.errors.registry }}</span>
                    </label>
                    <label class="text-sm text-slate-700">
                        Data da validação
                        <input v-model="form.date" type="date" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
                        <span v-if="form.errors.date" class="text-xs text-red-600">{{ form.errors.date }}</span>
                    </label>
                    <label class="text-sm text-slate-700 sm:col-span-2">
                        Comprovante (PDF, JPG ou PNG)
                        <input type="file" accept=".pdf,.jpg,.jpeg,.png" required class="mt-1 block w-full text-sm" @change="onProofChange" />
                        <span v-if="form.errors.proof" class="text-xs text-red-600">{{ form.errors.proof }}</span>
                    </label>
                    <div class="flex justify-end gap-2 sm:col-span-2">
                        <button type="button" class="rounded-full px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-100" @click="toggleForm(doc.type)">
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-full bg-talents-700 px-4 py-1.5 text-sm font-medium text-white hover:bg-talents-800 disabled:opacity-60"
                        >
                            Registrar validação
                        </button>
                    </div>
                </form>
            </li>
        </ul>

        <form
            class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4 sm:p-5"
            @submit.prevent="submitDossier"
        >
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">Liberar dossiê de evidências</h3>
                    <ul class="mt-1 max-w-2xl space-y-1 text-sm text-slate-600">
                        <li>Sem anexo, o PDF registra «Nenhum anexo registrado».</li>
                        <li>Com anexo, o PDF lista tipo e nome. O arquivo em si baixa por este parecer.</li>
                        <li>O dossiê também aparece para o cliente no Painel gerencial, no botão «Baixar Dossiê de evidências».</li>
                    </ul>
                </div>
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-full border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50"
                    @click="addDossierRow"
                >
                    <PlusIcon class="h-4 w-4" aria-hidden="true" />
                    Adicionar anexo
                </button>
            </div>

            <p v-if="dossierForm.attachments.length === 0" class="mt-4 rounded-lg border border-dashed border-slate-300 bg-white px-4 py-3 text-sm text-slate-500">
                Nenhum anexo nesta liberação.
            </p>

            <ul v-else class="mt-4 space-y-3">
                <li
                    v-for="(row, index) in dossierForm.attachments"
                    :key="index"
                    class="grid items-end gap-3 rounded-xl border border-slate-200 bg-white p-3 shadow-sm sm:grid-cols-[11rem_minmax(0,1fr)_auto] sm:p-4"
                >
                    <div>
                        <span class="flex items-center gap-1 text-xs font-medium text-slate-500">
                            Tipo
                            <InfoTooltip text="AEP é o arquivo da avaliação ergonômica. Observação é o registro de campo. Inventário é o documento do PGR. Outro cobre a evidência restante." />
                        </span>
                    <label class="block">
                        <select v-model="row.kind" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm">
                            <option value="aep">AEP</option>
                            <option value="observation">Observação</option>
                            <option value="inventory">Inventário</option>
                            <option value="other">Outro</option>
                        </select>
                    </label>
                    </div>

                    <label class="block min-w-0 cursor-pointer">
                        <span class="text-xs font-medium text-slate-500">Arquivo</span>
                        <span class="mt-1 flex min-h-10 items-center gap-3 rounded-lg border border-slate-300 bg-white px-2 py-1.5 shadow-sm transition hover:border-slate-400">
                            <span class="shrink-0 rounded-md bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-700">Escolher arquivo</span>
                            <span class="truncate text-sm" :class="row.file ? 'font-medium text-slate-900' : 'text-slate-400'">
                                {{ row.file?.name ?? 'Nenhum arquivo escolhido' }}
                            </span>
                            <input type="file" class="sr-only" @change="onDossierFile(index, $event)" />
                        </span>
                        <span v-if="dossierForm.errors[`attachments.${index}.file`]" class="mt-1 block text-xs text-red-600">
                            {{ dossierForm.errors[`attachments.${index}.file`] }}
                        </span>
                    </label>

                    <button
                        type="button"
                        class="inline-flex h-10 w-10 items-center justify-center justify-self-end rounded-lg text-slate-400 hover:bg-red-50 hover:text-red-700"
                        aria-label="Remover anexo"
                        @click="removeDossierRow(index)"
                    >
                        <TrashIcon class="h-4 w-4" aria-hidden="true" />
                    </button>
                </li>
            </ul>

            <div class="mt-4 flex justify-end border-t border-slate-200/80 pt-4">
                <button
                    type="submit"
                    :disabled="dossierForm.processing"
                    class="rounded-full bg-talents-700 px-4 py-2 text-sm font-medium text-white hover:bg-talents-800 disabled:opacity-60"
                >
                    Liberar dossiê
                </button>
            </div>
            <p v-if="dossierForm.errors.attachments" class="mt-2 text-xs text-red-600">{{ dossierForm.errors.attachments }}</p>
        </form>
    </section>
</template>
