<script setup>
import CommunicationHistory from '@/Components/Hiring/CommunicationHistory.vue';
import FormPageHeader from '@/Components/FormPageHeader.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { confirmDialog } from '@/composables/useConfirmDialog';
import { formatDateTime } from '@/utils/dateOnly';
import { emptyCriterion, ratingClass } from '@/utils/hiringAssessment';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { PlusIcon, TrashIcon } from '@heroicons/vue/24/outline';
import { computed } from 'vue';

const props = defineProps({
    vacancy: { type: Object, required: true },
    application: { type: Object, required: true },
    assessment: { type: Object, required: true },
    interviews: { type: Array, default: () => [] },
    decisions: { type: Array, required: true },
    ratings: { type: Array, required: true },
    methodology_note: { type: String, required: true },
    can: { type: Object, default: () => ({}) },
    dossier_send: { type: Object, default: null },
    dossier_history: { type: Array, default: () => [] },
});

/* ── Enviar dossiê ao cliente (contratacao_vagas,validate) ── */
const sendForm = useForm({
    contact_ids: [],
    subject: props.dossier_send?.template?.subject ?? '',
    body: props.dossier_send?.template?.body ?? '',
    resend: false,
});
const sendDossier = async () => {
    const ok = await confirmDialog({
        title: 'Enviar o dossiê aprovado?',
        message: 'O PDF é gerado agora, a partir do conteúdo aprovado, e enviado por e-mail aos contatos escolhidos.',
        confirmLabel: 'Enviar dossiê',
        variant: 'primary',
    });
    if (!ok) {
        return;
    }
    sendForm.post(route('admin.contratacao.vagas.applications.assessment.send', routeParams.value), {
        preserveScroll: true,
        onSuccess: () => {
            sendForm.contact_ids = [];
            sendForm.resend = false;
        },
    });
};

const form = useForm({
    success_profile: props.assessment.success_profile ?? '',
    case_description: props.assessment.case_description ?? '',
    case_result: props.assessment.case_result ?? '',
    criteria: (props.assessment.criteria ?? []).map((c) => ({ ...emptyCriterion(), ...c })),
    interviewer_summary: props.assessment.interviewer_summary ?? '',
    limitations: props.assessment.limitations ?? '',
    decision: props.assessment.decision ?? 'pending',
});

const routeParams = computed(() => ({ vacancy: props.vacancy.id, application: props.application.id }));
const approved = computed(() => Boolean(props.assessment.approved_at));

const addCriterion = () => form.criteria.push(emptyCriterion());
const removeCriterion = (index) => form.criteria.splice(index, 1);

const save = () => {
    form.put(route('admin.contratacao.vagas.applications.assessment.update', routeParams.value), { preserveScroll: true });
};

const approve = async () => {
    if (form.isDirty) {
        await confirmDialog({
            title: 'Há alterações não salvas',
            message: 'Salve a avaliação antes de aprovar o dossiê.',
            confirmLabel: 'Entendi',
            variant: 'primary',
        });
        return;
    }
    const ok = await confirmDialog({
        title: 'Aprovar o dossiê?',
        message: 'A decisão registrada é humana. Depois de aprovado, o dossiê em PDF fica disponível; alterar a avaliação desfaz a aprovação.',
        confirmLabel: 'Aprovar dossiê',
        variant: 'primary',
    });
    if (ok) {
        router.post(route('admin.contratacao.vagas.applications.assessment.approve', routeParams.value), {}, { preserveScroll: true });
    }
};

const fieldClass =
    'mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/70 disabled:bg-slate-50';
</script>

<template>
    <Head :title="`Avaliação · ${application.candidate.name}`" />

    <AdminLayout>
        <template #header>
            <FormPageHeader
                :back-href="route('admin.contratacao.vagas.show', vacancy.id)"
                back-label="Vaga"
                :title="`Avaliação · ${application.candidate.name}`"
                :subtitle="[vacancy.title, vacancy.company_name, application.stage_name ? `etapa ${application.stage_name}` : null].filter(Boolean).join(' · ')"
            >
                <template #trailing>
                    <a
                        v-if="approved"
                        :href="route('admin.contratacao.vagas.applications.assessment.dossier', routeParams)"
                        class="inline-flex items-center rounded-md bg-talents-600 px-3 py-2 text-sm font-medium text-white hover:bg-talents-700"
                    >
                        Dossiê em PDF
                    </a>
                </template>
            </FormPageHeader>
        </template>

        <div
            v-if="$page.props.flash?.success"
            class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
            role="status"
        >
            {{ $page.props.flash.success }}
        </div>
        <div
            v-if="$page.props.flash?.error"
            class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
            role="alert"
        >
            {{ $page.props.flash.error }}
        </div>
        <div
            v-if="$page.props.errors?.approve"
            class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
            role="alert"
        >
            {{ $page.props.errors.approve }}
        </div>

        <div class="grid gap-5 xl:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
            <form class="surface-card space-y-6 p-5 sm:p-6" @submit.prevent="save">
                <fieldset :disabled="!can.edit" class="space-y-6">
                    <div>
                        <InputLabel for="success_profile" value="Perfil de sucesso" />
                        <textarea id="success_profile" v-model="form.success_profile" rows="4" :class="fieldClass" />
                        <InputError class="mt-1" :message="form.errors.success_profile" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="case_description" value="Case · descrição" />
                            <textarea id="case_description" v-model="form.case_description" rows="4" :class="fieldClass" />
                            <InputError class="mt-1" :message="form.errors.case_description" />
                        </div>
                        <div>
                            <InputLabel for="case_result" value="Case · resultado" />
                            <textarea id="case_result" v-model="form.case_result" rows="4" :class="fieldClass" />
                            <InputError class="mt-1" :message="form.errors.case_result" />
                        </div>
                    </div>

                    <section aria-labelledby="criteria-title">
                        <div class="flex items-center justify-between gap-2">
                            <h3 id="criteria-title" class="text-sm font-semibold text-talents-900">Critérios e evidências</h3>
                            <button
                                v-if="can.edit"
                                type="button"
                                class="inline-flex items-center gap-1 text-sm font-semibold text-talents-700 hover:underline"
                                @click="addCriterion"
                            >
                                <PlusIcon class="h-4 w-4" aria-hidden="true" />
                                Critério
                            </button>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">Sem evidência registrada, o critério fica como «Sem evidência».</p>
                        <InputError class="mt-1" :message="form.errors.criteria" />
                        <ol class="mt-3 space-y-3">
                            <li v-for="(c, index) in form.criteria" :key="index" class="rounded-xl border border-slate-200 p-3">
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <InputLabel :for="`crit-${index}`" value="Critério" />
                                        <input :id="`crit-${index}`" v-model="c.criterio" type="text" maxlength="255" :class="fieldClass" />
                                    </div>
                                    <div>
                                        <InputLabel :for="`rating-${index}`" value="Avaliação" />
                                        <select :id="`rating-${index}`" v-model="c.avaliacao" :class="fieldClass">
                                            <option v-for="r in ratings" :key="r.value" :value="r.value">{{ r.label }}</option>
                                        </select>
                                    </div>
                                    <div class="sm:col-span-2">
                                        <InputLabel :for="`evidence-${index}`" value="Evidência" />
                                        <textarea :id="`evidence-${index}`" v-model="c.evidencia" rows="2" maxlength="3000" :class="fieldClass" />
                                    </div>
                                    <div>
                                        <InputLabel :for="`origin-${index}`" value="Origem (entrevista, case, referência…)" />
                                        <input :id="`origin-${index}`" v-model="c.origem" type="text" maxlength="255" :class="fieldClass" />
                                    </div>
                                    <div class="flex items-end justify-between gap-2">
                                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="ratingClass(c.avaliacao)">
                                            {{ ratings.find((r) => r.value === c.avaliacao)?.label }}
                                        </span>
                                        <button
                                            v-if="can.edit"
                                            type="button"
                                            class="rounded-lg p-1.5 text-red-600 hover:bg-red-50"
                                            :aria-label="`Remover critério ${c.criterio || index + 1}`"
                                            @click="removeCriterion(index)"
                                        >
                                            <TrashIcon class="h-4 w-4" aria-hidden="true" />
                                        </button>
                                    </div>
                                </div>
                            </li>
                        </ol>
                        <p v-if="!form.criteria.length" class="mt-3 text-sm text-slate-500">Nenhum critério registrado.</p>
                    </section>

                    <div>
                        <InputLabel for="interviewer_summary" value="Síntese da entrevistadora" />
                        <textarea id="interviewer_summary" v-model="form.interviewer_summary" rows="5" :class="fieldClass" />
                        <InputError class="mt-1" :message="form.errors.interviewer_summary" />
                    </div>

                    <div>
                        <InputLabel for="limitations" value="Limites da avaliação" />
                        <textarea id="limitations" v-model="form.limitations" rows="3" :class="fieldClass" />
                        <InputError class="mt-1" :message="form.errors.limitations" />
                    </div>

                    <div>
                        <InputLabel for="decision" value="Decisão humana" />
                        <select id="decision" v-model="form.decision" :class="fieldClass">
                            <option v-for="d in decisions" :key="d.value" :value="d.value">{{ d.label }}</option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.decision" />
                    </div>
                </fieldset>

                <div v-if="can.edit || can.validate" class="flex flex-wrap items-center justify-end gap-3">
                    <span v-if="form.isDirty" class="text-sm text-amber-800">Há alterações não salvas.</span>
                    <PrimaryButton v-if="can.edit" :disabled="form.processing">Salvar avaliação</PrimaryButton>
                    <SecondaryButton v-if="can.validate && assessment.exists && !approved" type="button" @click="approve">
                        Aprovar dossiê
                    </SecondaryButton>
                </div>
            </form>

            <aside class="space-y-5">
                <section class="surface-card p-5" aria-labelledby="status-title">
                    <h3 id="status-title" class="text-sm font-semibold text-talents-900">Situação</h3>
                    <p class="mt-2 text-sm">
                        <span v-if="approved" class="font-semibold text-emerald-700">
                            Dossiê aprovado em {{ formatDateTime(assessment.approved_at) }}<template v-if="assessment.approved_by"> por {{ assessment.approved_by }}</template>
                        </span>
                        <span v-else-if="assessment.exists" class="font-semibold text-amber-800">Dossiê ainda não aprovado</span>
                        <span v-else class="text-slate-500">Avaliação ainda não iniciada</span>
                    </p>
                    <p v-if="assessment.updated_at" class="mt-1 text-xs text-slate-500">
                        Atualizada em {{ formatDateTime(assessment.updated_at) }}<template v-if="assessment.updated_by"> por {{ assessment.updated_by }}</template>
                    </p>
                    <p class="mt-3 text-xs text-slate-500">Uso interno da Talents: nada desta avaliação vai para o portal da empresa.</p>
                </section>

                <section class="surface-card p-5" aria-labelledby="interviews-title">
                    <div class="flex items-center justify-between gap-2">
                        <h3 id="interviews-title" class="text-sm font-semibold text-talents-900">Entrevistas da candidatura</h3>
                        <Link
                            v-if="can.create_interview"
                            :href="route('admin.entrevistas.create', { hiring_application_id: application.id })"
                            class="text-xs font-semibold text-talents-700 hover:underline"
                        >
                            Nova entrevista
                        </Link>
                    </div>
                    <ul v-if="interviews.length" class="mt-3 space-y-2">
                        <li v-for="i in interviews" :key="i.id" class="rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-2 text-sm">
                            <Link v-if="i.url" :href="i.url" class="font-semibold text-talents-800 hover:underline">
                                {{ i.questionnaire ?? 'Entrevista' }}
                            </Link>
                            <span v-else class="font-semibold text-slate-800">{{ i.questionnaire ?? 'Entrevista' }}</span>
                            <p class="text-xs text-slate-500">
                                {{ i.status_label }} · {{ i.is_reviewed ? 'revisada' : 'revisão pendente' }} · {{ formatDateTime(i.created_at) }}
                            </p>
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-sm text-slate-500">Nenhuma entrevista vinculada a esta candidatura.</p>
                </section>

                <section v-if="dossier_send && approved" class="surface-card p-5" aria-labelledby="send-title">
                    <h3 id="send-title" class="text-sm font-semibold text-talents-900">Enviar dossiê ao cliente</h3>
                    <p class="mt-1 text-xs text-slate-500">
                        Só contatos habilitados, conferidos, com o tópico «Vagas» e o canal e-mail. O portal da empresa não mostra o dossiê.
                    </p>
                    <div v-if="sendForm.hasErrors" class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900" role="alert">
                        <p v-for="(msg, key) in sendForm.errors" :key="key">{{ msg }}</p>
                    </div>
                    <p v-if="!dossier_send.smtp_configured" class="mt-3 text-sm text-amber-800">O SMTP do painel não está ligado; o envio por e-mail fica indisponível.</p>
                    <form v-else class="mt-3 space-y-3" @submit.prevent="sendDossier">
                        <fieldset>
                            <legend class="text-xs font-semibold uppercase tracking-wide text-slate-500">Contatos</legend>
                            <ul v-if="dossier_send.contacts.length" class="mt-1 space-y-1">
                                <li v-for="c in dossier_send.contacts" :key="c.id">
                                    <label class="flex items-center gap-2 text-sm">
                                        <input v-model="sendForm.contact_ids" type="checkbox" :value="c.id" class="rounded border-slate-300 text-talents-600" />
                                        <span class="font-semibold">{{ c.name }}</span>
                                        <span class="text-xs text-slate-500">{{ c.email }}</span>
                                    </label>
                                </li>
                            </ul>
                            <p v-else class="mt-1 text-sm text-slate-500">
                                Nenhum contato elegível.
                                <Link v-if="dossier_send.contacts_url" :href="dossier_send.contacts_url" class="font-semibold text-talents-700 underline">Revisar contatos</Link>
                            </p>
                        </fieldset>
                        <div>
                            <InputLabel for="dossier-subject" value="Assunto" />
                            <input id="dossier-subject" v-model="sendForm.subject" type="text" maxlength="200" :class="fieldClass" />
                        </div>
                        <div>
                            <InputLabel for="dossier-body" value="Texto" />
                            <textarea id="dossier-body" v-model="sendForm.body" rows="5" maxlength="5000" :class="fieldClass" />
                        </div>
                        <label class="flex items-start gap-2 text-xs text-slate-700">
                            <input v-model="sendForm.resend" type="checkbox" class="mt-0.5 rounded border-slate-300 text-talents-600" />
                            Enviar novamente (só se este mesmo texto já tiver sido enviado e precisar sair de novo)
                        </label>
                        <p class="text-xs text-slate-500">Restam {{ dossier_send.emails_remaining_today }} e-mails de comunicação hoje.</p>
                        <div class="flex justify-end">
                            <PrimaryButton :disabled="sendForm.processing || !sendForm.contact_ids.length">Enviar dossiê</PrimaryButton>
                        </div>
                    </form>
                </section>

                <section v-if="dossier_history.length" class="surface-card p-5" aria-labelledby="dossier-history-title">
                    <h3 id="dossier-history-title" class="text-sm font-semibold text-talents-900">Envios do dossiê</h3>
                    <div class="mt-3">
                        <CommunicationHistory :items="dossier_history" />
                    </div>
                </section>

                <section class="surface-card p-5" aria-labelledby="note-title">
                    <h3 id="note-title" class="text-sm font-semibold text-talents-900">Nota metodológica do dossiê</h3>
                    <p class="mt-2 text-xs text-slate-600">{{ methodology_note }}</p>
                </section>
            </aside>
        </div>
    </AdminLayout>
</template>
