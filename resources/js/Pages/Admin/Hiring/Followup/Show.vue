<script setup>
import FollowupSendModal from '@/Components/Hiring/FollowupSendModal.vue';
import FormPageHeader from '@/Components/FormPageHeader.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { confirmDialog, promptDialog } from '@/composables/useConfirmDialog';
import { formatDateTime } from '@/utils/dateOnly';
import { followupStatusClass, invitationStateClass } from '@/utils/hiringFollowup';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    cycle: { type: Object, required: true },
    milestones: { type: Array, default: () => [] },
    events: { type: Array, default: () => [] },
    previous_cycles: { type: Array, default: () => [] },
    manager_options: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
    emails_remaining_today: { type: Number, default: 0 },
});

const fmtDate = (d) => (d ? new Date(`${d}T12:00:00`).toLocaleDateString('pt-BR') : '—');
const base = () => ({ cycle: props.cycle.id });
const post = (name, data = {}) => router.post(route(name, base()), data, { preserveScroll: true });

/* ── Admissão, pausa, desligamento, readmissão ── */
const admissionForm = useForm({ admission_date: '' });
const confirmAdmission = () => admissionForm.post(route('admin.contratacao.pos.confirm-admission', base()), { preserveScroll: true });

const pause = async () => {
    const reason = await promptDialog({ title: 'Pausar o acompanhamento?', message: 'Informe o motivo. Os marcos ficam pausados até a retomada.', confirmLabel: 'Pausar' });
    if (reason !== null && reason.trim() !== '') {
        post('admin.contratacao.pos.pause', { reason });
    }
};
const resume = () => post('admin.contratacao.pos.resume');

const showTerminate = ref(false);
const terminateForm = useForm({ terminated_on: '', note: '' });
const terminate = async () => {
    if (!(await confirmDialog({ title: 'Registrar o desligamento?', message: 'O ciclo é encerrado e os convites em aberto são revogados. O histórico fica.', confirmLabel: 'Registrar desligamento' }))) {
        return;
    }
    terminateForm.post(route('admin.contratacao.pos.terminate', base()), { preserveScroll: true, onSuccess: () => (showTerminate.value = false) });
};

const showReadmit = ref(false);
const readmitForm = useForm({ admission_date: '' });
const readmit = async () => {
    if (!(await confirmDialog({ title: 'Registrar a readmissão?', message: 'Um ciclo novo é criado com a data informada; este ciclo fica preservado.', confirmLabel: 'Readmitir', variant: 'primary' }))) {
        return;
    }
    readmitForm.post(route('admin.contratacao.pos.readmit', base()), { preserveScroll: true });
};

/* ── Dados do vínculo ── */
const showEdit = ref(false);
const editForm = useForm({
    role_title: props.cycle.role_title ?? '',
    manager_contact_id: props.cycle.manager?.id ? String(props.cycle.manager.id) : '',
    collaborator_email: props.cycle.collaborator_email ?? '',
    collaborator_phone: props.cycle.collaborator_phone ?? '',
    collaborator_email_opt_in: props.cycle.collaborator_email_opt_in,
});
const saveEdit = () =>
    editForm
        .transform((d) => ({ ...d, manager_contact_id: d.manager_contact_id || null }))
        .patch(route('admin.contratacao.pos.update', base()), { preserveScroll: true, onSuccess: () => (showEdit.value = false) });

/* ── Marcos ── */
const sending = ref(null);
const skip = async (m) => {
    const reason = await promptDialog({ title: `Pular o marco ${m.moment_label}?`, message: 'Informe o motivo. O marco conta como concluído.', confirmLabel: 'Pular' });
    if (reason !== null && reason.trim() !== '') {
        router.post(m.skip_url, { skip_reason: reason }, { preserveScroll: true });
    }
};
const copyInvitation = async (inv) => {
    try {
        await navigator.clipboard.writeText(inv.link);
        await window.axios.post(inv.copy_url);
        router.reload({ preserveScroll: true });
    } catch {
        // falha silenciosa: o link continua visível para cópia manual
    }
};

/* ── Registro de acompanhamento ── */
const noteForm = useForm({ note: '' });
const saveNote = () => noteForm.post(route('admin.contratacao.pos.notes.store', base()), { preserveScroll: true, onSuccess: () => noteForm.reset() });

const fieldClass =
    'mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/70';
</script>

<template>
    <Head :title="`Pós-contratação · ${cycle.employee.name}`" />

    <AdminLayout>
        <template #header>
            <FormPageHeader
                :back-href="route('admin.contratacao.pos.index')"
                back-label="Pós-contratação"
                :title="cycle.employee.name"
                :subtitle="cycle.company.name"
            />
        </template>

        <div v-if="$page.props.flash?.success" class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status">
            {{ $page.props.flash.success }}
        </div>
        <div
            v-for="key in ['cycle', 'admission', 'admission_date', 'followup', 'skip_reason', 'manager_contact_id']"
            v-show="$page.props.errors?.[key]"
            :key="key"
            class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
            role="alert"
        >
            {{ $page.props.errors?.[key] }}
        </div>

        <!-- Cargo e gestor em destaque -->
        <section class="surface-card grid gap-4 p-5 sm:grid-cols-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cargo</p>
                <p class="mt-1 text-lg font-semibold text-talents-900">{{ cycle.role_title || 'Não informado' }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Gestor</p>
                <p v-if="cycle.manager" class="mt-1 text-lg font-semibold text-talents-900">{{ cycle.manager.name }}</p>
                <p v-if="cycle.manager" class="text-xs text-slate-500">{{ [cycle.manager.role, cycle.manager.email, cycle.manager.phone].filter(Boolean).join(' · ') }}</p>
                <p v-else class="mt-1 text-lg font-semibold text-amber-800">Não definido</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Situação</p>
                <p class="mt-1 flex flex-wrap items-center gap-2">
                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="followupStatusClass(cycle.followup_status)">{{ cycle.followup_status_label }}</span>
                    <span class="text-sm text-slate-600">Vínculo {{ cycle.status_label.toLowerCase() }}</span>
                </p>
                <p class="mt-1 text-sm text-slate-600">Admissão: {{ fmtDate(cycle.admission_date) }}</p>
                <p v-if="cycle.paused_at" class="text-xs text-slate-500">Pausado: {{ cycle.pause_reason }}</p>
                <p v-if="cycle.status === 'terminated'" class="text-xs text-slate-500">
                    Desligamento: {{ cycle.terminated_on ? fmtDate(cycle.terminated_on) : 'sem data informada' }}
                </p>
            </div>
            <div class="sm:col-span-3 flex flex-wrap items-center gap-3 border-t border-slate-100 pt-3 text-sm text-slate-600">
                <span>Colaborador: {{ cycle.collaborator_email || 'sem e-mail' }} · {{ cycle.collaborator_phone || 'sem telefone' }}</span>
                <span :class="cycle.collaborator_email_opt_in ? 'text-emerald-700' : 'text-amber-800'">
                    {{ cycle.collaborator_email_opt_in ? 'Autoriza convites por e-mail' : 'Sem autorização de e-mail' }}
                </span>
                <Link v-if="cycle.vacancy?.url" :href="cycle.vacancy.url" class="font-semibold text-talents-700 hover:underline">Vaga {{ cycle.vacancy.title }}</Link>
                <Link v-if="cycle.candidate_url" :href="cycle.candidate_url" class="font-semibold text-talents-700 hover:underline">Ficha do candidato</Link>
            </div>
            <div class="sm:col-span-3 flex flex-wrap gap-2">
                <SecondaryButton v-if="can.edit && cycle.status !== 'terminated'" type="button" @click="showEdit = true">Editar cargo, gestor e contato</SecondaryButton>
                <SecondaryButton v-if="can.edit && cycle.status === 'active'" type="button" @click="pause">Pausar</SecondaryButton>
                <PrimaryButton v-if="can.edit && cycle.status === 'paused'" type="button" @click="resume">Retomar</PrimaryButton>
                <SecondaryButton v-if="can.delete && cycle.status !== 'terminated'" type="button" class="!text-red-700" @click="showTerminate = true">Desligar</SecondaryButton>
                <PrimaryButton v-if="can.delete && cycle.status === 'terminated'" type="button" @click="showReadmit = true">Readmitir</PrimaryButton>
            </div>
        </section>

        <!-- Admissão pendente -->
        <section v-if="cycle.status === 'pending_admission'" class="surface-card mt-5 p-5">
            <h3 class="text-base font-semibold text-talents-900">Admissão pendente</h3>
            <p class="mt-1 text-sm text-slate-600">Sem a data de admissão confirmada, o acompanhamento não tem marcos.</p>
            <form v-if="can.edit" class="mt-3 flex flex-wrap items-end gap-3" @submit.prevent="confirmAdmission">
                <div>
                    <InputLabel for="admission_date" value="Data de admissão" />
                    <input id="admission_date" v-model="admissionForm.admission_date" type="date" required :class="fieldClass" />
                    <InputError class="mt-1" :message="admissionForm.errors.admission_date" />
                </div>
                <PrimaryButton :disabled="admissionForm.processing">Confirmar admissão</PrimaryButton>
            </form>
        </section>

        <!-- Marcos -->
        <section v-if="milestones.length" class="mt-5 space-y-3" aria-label="Marcos do acompanhamento">
            <article v-for="m in milestones" :key="m.id" class="surface-card p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-talents-900">{{ m.moment_label }} · {{ fmtDate(m.due_on) }}</h3>
                        <span class="mt-1 inline-block rounded-full px-2 py-0.5 text-xs font-semibold" :class="followupStatusClass(m.status)">{{ m.status_label }}</span>
                        <p v-if="m.skipped_at" class="mt-1 text-xs text-slate-500">Pulado: {{ m.skip_reason }}</p>
                        <p v-if="m.status === 'overdue'" class="mt-1 text-xs text-red-700">Vencido: revise e envie se ainda fizer sentido. Nada é enviado sozinho.</p>
                    </div>
                    <div v-if="can.edit && cycle.status === 'active' && !m.skipped_at" class="flex flex-wrap gap-2">
                        <PrimaryButton type="button" @click="sending = m">Enviar acompanhamento</PrimaryButton>
                        <SecondaryButton v-if="!m.completed_at" type="button" @click="skip(m)">Pular</SecondaryButton>
                    </div>
                </div>

                <ul v-if="m.invitations.length" class="mt-3 space-y-2">
                    <li v-for="inv in m.invitations" :key="inv.id" class="rounded-xl border border-slate-200 p-3 text-sm">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p>
                                <span class="font-semibold">{{ inv.audience_label }}</span>
                                <span class="text-slate-500"> · {{ inv.recipient_name }} · {{ inv.recipient_email || inv.recipient_phone || 'sem contato' }}</span>
                            </p>
                            <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="invitationStateClass(inv.state)">{{ inv.state_label }}</span>
                        </div>
                        <!-- Fatos separados: gerar, copiar, aceitar no servidor e responder. -->
                        <dl class="mt-2 grid gap-x-4 gap-y-1 text-xs text-slate-600 sm:grid-cols-3">
                            <div><dt class="inline font-semibold">Gerado:</dt> <dd class="inline">{{ formatDateTime(inv.generated_at) }}</dd></div>
                            <div><dt class="inline font-semibold">Link copiado:</dt> <dd class="inline">{{ inv.copied_at ? formatDateTime(inv.copied_at) : '—' }}</dd></div>
                            <div><dt class="inline font-semibold">Enviado ao servidor de e-mail:</dt> <dd class="inline">{{ inv.emailed_at ? formatDateTime(inv.emailed_at) : '—' }}</dd></div>
                            <div><dt class="inline font-semibold">Respondido:</dt> <dd class="inline">{{ inv.answered_at ? formatDateTime(inv.answered_at) : '—' }}</dd></div>
                            <div><dt class="inline font-semibold">Válido até:</dt> <dd class="inline">{{ formatDateTime(inv.expires_at) }}</dd></div>
                            <div v-if="inv.revoked_at"><dt class="inline font-semibold">Revogado:</dt> <dd class="inline">{{ inv.revoke_reason }}</dd></div>
                        </dl>
                        <p v-if="inv.email_failure_reason && !inv.emailed_at" class="mt-1 rounded-lg bg-red-50 px-2 py-1 text-xs text-red-800">{{ inv.email_failure_reason }}</p>
                        <div v-if="inv.response" class="mt-2 rounded-lg bg-talents-50/60 px-3 py-2 text-xs text-slate-700">
                            <p class="font-semibold">Resposta: {{ '★'.repeat(inv.response.rating) }}{{ '☆'.repeat(5 - inv.response.rating) }} ({{ inv.response.rating }}/5)</p>
                            <p v-if="inv.response.comment" class="mt-1 whitespace-pre-wrap">{{ inv.response.comment }}</p>
                            <p v-if="inv.response.support_requested" class="mt-1 font-semibold text-red-700">Pediu apoio<template v-if="inv.response.support_note">: {{ inv.response.support_note }}</template></p>
                            <p class="mt-1 text-slate-500">Uso interno da Talents: a resposta não vai ao gestor nem à empresa.</p>
                        </div>
                        <div v-if="can.edit && inv.copy_url" class="mt-2">
                            <button type="button" class="text-xs font-semibold text-talents-700 hover:underline" @click="copyInvitation(inv)">Copiar link</button>
                        </div>
                    </li>
                </ul>
                <p v-else class="mt-3 text-sm text-slate-500">Nenhum convite gerado.</p>
            </article>
        </section>

        <div class="mt-5 grid gap-5 lg:grid-cols-2">
            <section class="surface-card p-5" aria-labelledby="registro-title">
                <h3 id="registro-title" class="text-base font-semibold text-talents-900">Registro de acompanhamento</h3>
                <form v-if="can.edit" class="mt-3" @submit.prevent="saveNote">
                    <label for="note" class="sr-only">Nova anotação</label>
                    <textarea id="note" v-model="noteForm.note" rows="3" maxlength="2000" :class="fieldClass" placeholder="Registre um contato, combinado ou observação…" />
                    <InputError class="mt-1" :message="noteForm.errors.note" />
                    <div class="mt-2 flex justify-end"><PrimaryButton :disabled="noteForm.processing">Registrar</PrimaryButton></div>
                </form>
                <ol class="mt-3 space-y-2">
                    <li v-for="e in events" :key="e.id" class="rounded-xl bg-slate-50 px-3 py-2 text-xs">
                        <span class="font-semibold text-slate-800">{{ e.label }}</span>
                        <span class="text-slate-500"><template v-if="e.user_name"> · {{ e.user_name }}</template> · {{ formatDateTime(e.created_at) }}</span>
                        <p v-if="e.note" class="mt-0.5 whitespace-pre-wrap text-slate-700">{{ e.note }}</p>
                        <p v-if="e.fields.length" class="mt-0.5 text-slate-600">Campos: {{ e.fields.join(', ') }}</p>
                    </li>
                </ol>
            </section>

            <section class="surface-card p-5" aria-labelledby="ciclos-title">
                <h3 id="ciclos-title" class="text-base font-semibold text-talents-900">Ciclos anteriores</h3>
                <ul v-if="previous_cycles.length" class="mt-3 space-y-2">
                    <li v-for="p in previous_cycles" :key="p.id" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                        <Link :href="p.url" class="font-semibold text-talents-800 hover:underline">{{ p.role_title || 'Ciclo' }}</Link>
                        <span class="text-slate-500"> · {{ p.status_label }} · admissão {{ fmtDate(p.admission_date) }}<template v-if="p.terminated_on"> · desligamento {{ fmtDate(p.terminated_on) }}</template></span>
                    </li>
                </ul>
                <p v-else class="mt-3 text-sm text-slate-500">Nenhum outro ciclo deste colaborador nesta empresa.</p>
            </section>
        </div>

        <FollowupSendModal
            :show="sending !== null"
            :milestone="sending"
            :employee-name="cycle.employee.name"
            :emails-remaining="emails_remaining_today"
            @close="sending = null"
        />

        <Modal :show="showEdit" max-width="lg" @close="showEdit = false">
            <form class="space-y-4 p-6" @submit.prevent="saveEdit">
                <h3 class="text-lg font-semibold text-talents-900">Cargo, gestor e contato do colaborador</h3>
                <div>
                    <InputLabel for="role_title" value="Cargo" />
                    <input id="role_title" v-model="editForm.role_title" type="text" maxlength="255" :class="fieldClass" />
                </div>
                <div>
                    <InputLabel for="manager" value="Gestor (contato da empresa com o tópico Pós-contratação)" />
                    <select id="manager" v-model="editForm.manager_contact_id" :class="fieldClass">
                        <option value="">Não definido</option>
                        <option v-for="o in manager_options" :key="o.id" :value="String(o.id)">
                            {{ o.name }}{{ o.role ? ` · ${o.role}` : '' }}{{ o.reviewed ? '' : ' (não conferido)' }}
                        </option>
                    </select>
                    <p class="mt-1 text-xs text-slate-500">Trocar o gestor revoga o convite em aberto dele no próximo envio e gera um novo.</p>
                    <InputError class="mt-1" :message="editForm.errors.manager_contact_id" />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="c_email" value="E-mail do colaborador" />
                        <input id="c_email" v-model="editForm.collaborator_email" type="email" :class="fieldClass" />
                        <InputError class="mt-1" :message="editForm.errors.collaborator_email" />
                    </div>
                    <div>
                        <InputLabel for="c_phone" value="Telefone do colaborador" />
                        <input id="c_phone" v-model="editForm.collaborator_phone" type="tel" :class="fieldClass" />
                    </div>
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <input v-model="editForm.collaborator_email_opt_in" type="checkbox" class="rounded border-slate-300 text-talents-600" />
                    O colaborador autoriza receber os convites de acompanhamento
                </label>
                <div class="flex justify-end gap-2">
                    <SecondaryButton type="button" @click="showEdit = false">Cancelar</SecondaryButton>
                    <PrimaryButton :disabled="editForm.processing">Salvar</PrimaryButton>
                </div>
            </form>
        </Modal>

        <Modal :show="showTerminate" max-width="md" @close="showTerminate = false">
            <form class="space-y-4 p-6" @submit.prevent="terminate">
                <h3 class="text-lg font-semibold text-talents-900">Registrar desligamento</h3>
                <div>
                    <InputLabel for="terminated_on" value="Data de desligamento (opcional)" />
                    <input id="terminated_on" v-model="terminateForm.terminated_on" type="date" :class="fieldClass" />
                    <InputError class="mt-1" :message="terminateForm.errors.terminated_on" />
                </div>
                <div>
                    <InputLabel for="termination_note" value="Observação" />
                    <textarea id="termination_note" v-model="terminateForm.note" rows="3" maxlength="2000" :class="fieldClass" />
                </div>
                <div class="flex justify-end gap-2">
                    <SecondaryButton type="button" @click="showTerminate = false">Cancelar</SecondaryButton>
                    <PrimaryButton :disabled="terminateForm.processing">Registrar desligamento</PrimaryButton>
                </div>
            </form>
        </Modal>

        <Modal :show="showReadmit" max-width="md" @close="showReadmit = false">
            <form class="space-y-4 p-6" @submit.prevent="readmit">
                <h3 class="text-lg font-semibold text-talents-900">Readmitir</h3>
                <div>
                    <InputLabel for="readmit_date" value="Nova data de admissão" />
                    <input id="readmit_date" v-model="readmitForm.admission_date" type="date" required :class="fieldClass" />
                    <InputError class="mt-1" :message="readmitForm.errors.admission_date || readmitForm.errors.admission" />
                </div>
                <div class="flex justify-end gap-2">
                    <SecondaryButton type="button" @click="showReadmit = false">Cancelar</SecondaryButton>
                    <PrimaryButton :disabled="readmitForm.processing">Readmitir</PrimaryButton>
                </div>
            </form>
        </Modal>
    </AdminLayout>
</template>
