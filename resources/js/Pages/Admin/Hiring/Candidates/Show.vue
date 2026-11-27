<script setup>
import FormPageHeader from '@/Components/FormPageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { confirmDialog } from '@/composables/useConfirmDialog';
import { formatDateTime } from '@/utils/dateOnly';
import { describeApplicationEvent } from '@/utils/hiringVacancyBoard';
import { candidateAuditDescription, contactPreferenceLabel } from '@/utils/talentCandidate';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArchiveBoxArrowDownIcon,
    ArrowUturnLeftIcon,
    CheckCircleIcon,
    PaperClipIcon,
    PencilSquareIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    candidate: { type: Object, required: true },
    applications: { type: Array, default: () => [] },
    interviews: { type: Array, default: () => [] },
    employment: { type: Array, default: () => [] },
    audit: { type: Array, default: () => [] },
    contact_preferences: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
    attachments_count: { type: Number, default: null },
    attachments_url: { type: String, default: null },
});

const archive = async () => {
    const ok = await confirmDialog({
        title: `Arquivar o cadastro de ${props.candidate.name}?`,
        message: 'Candidaturas, entrevistas e anexos são preservados. Enquanto arquivado, o candidato não entra em vagas novas.',
        confirmLabel: 'Arquivar',
    });
    if (ok) {
        router.post(route('admin.contratacao.candidatos.archive', props.candidate.id), {}, { preserveScroll: true });
    }
};

const restore = () => {
    router.post(route('admin.contratacao.candidatos.restore', props.candidate.id), {}, { preserveScroll: true });
};

const fields = [
    ['E-mail', 'email'],
    ['Telefone', 'phone'],
    ['Origem', 'source'],
    ['Cargo atual', 'current_role'],
    ['Empresa atual', 'current_company'],
    ['ID na Sólides', 'solides_id'],
];
</script>

<template>
    <Head :title="candidate.name" />

    <AdminLayout>
        <template #header>
            <FormPageHeader
                :back-href="route('admin.contratacao.candidatos.index')"
                back-label="Candidatos"
                :title="candidate.name"
                :subtitle="candidate.archived ? `Cadastro arquivado em ${formatDateTime(candidate.archived_at)}` : 'Ficha do candidato'"
            >
                <template #trailing>
                    <div class="flex flex-wrap items-center gap-2">
                        <Link v-if="attachments_url" :href="attachments_url">
                            <SecondaryButton type="button" class="gap-1.5">
                                <PaperClipIcon class="h-4 w-4" aria-hidden="true" />
                                Anexos internos ({{ attachments_count }})
                            </SecondaryButton>
                        </Link>
                        <template v-if="can.archive">
                            <SecondaryButton v-if="!candidate.archived" type="button" class="gap-1.5" @click="archive">
                                <ArchiveBoxArrowDownIcon class="h-4 w-4" aria-hidden="true" />
                                Arquivar
                            </SecondaryButton>
                            <PrimaryButton v-else type="button" class="gap-1.5" @click="restore">
                                <ArrowUturnLeftIcon class="h-4 w-4" aria-hidden="true" />
                                Restaurar
                            </PrimaryButton>
                        </template>
                        <Link v-if="can.edit" :href="route('admin.contratacao.candidatos.edit', candidate.id)">
                            <PrimaryButton type="button" class="gap-1.5">
                                <PencilSquareIcon class="h-4 w-4" aria-hidden="true" />
                                Editar
                            </PrimaryButton>
                        </Link>
                    </div>
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
            v-if="$page.props.flash?.info"
            class="mb-4 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900"
            role="status"
        >
            {{ $page.props.flash.info }}
        </div>
        <div
            v-if="$page.props.errors?.candidate"
            class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
            role="alert"
        >
            {{ $page.props.errors.candidate }}
        </div>
        <div
            v-if="candidate.archived"
            class="mb-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700"
        >
            Este cadastro está arquivado e não pode entrar em vagas novas.
            <template v-if="can.archive">Use «Restaurar» para reativá-lo.</template>
        </div>

        <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
            <section class="surface-card p-5" aria-labelledby="ficha-title">
                <h3 id="ficha-title" class="text-base font-semibold text-talents-900">Ficha</h3>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div v-for="[label, key] in fields" :key="key">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ label }}</dt>
                        <dd class="mt-1 break-words text-sm text-slate-800">{{ candidate[key] || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Preferência de contato</dt>
                        <dd class="mt-1 text-sm text-slate-800">
                            {{ contactPreferenceLabel(candidate.contact_preference, contact_preferences) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Conferências</dt>
                        <dd class="mt-1 space-y-1 text-sm text-slate-800">
                            <p class="flex items-center gap-1.5">
                                <CheckCircleIcon
                                    class="h-4 w-4"
                                    :class="candidate.email_reviewed ? 'text-emerald-600' : 'text-slate-300'"
                                    aria-hidden="true"
                                />
                                E-mail {{ candidate.email_reviewed ? 'conferido' : 'não conferido' }}
                            </p>
                            <p class="flex items-center gap-1.5">
                                <CheckCircleIcon
                                    class="h-4 w-4"
                                    :class="candidate.phone_reviewed ? 'text-emerald-600' : 'text-slate-300'"
                                    aria-hidden="true"
                                />
                                Telefone {{ candidate.phone_reviewed ? 'conferido' : 'não conferido' }}
                            </p>
                            <p class="flex items-center gap-1.5">
                                <CheckCircleIcon
                                    class="h-4 w-4"
                                    :class="candidate.email_opt_in ? 'text-emerald-600' : 'text-slate-300'"
                                    aria-hidden="true"
                                />
                                {{ candidate.email_opt_in ? 'Autoriza convites por e-mail' : 'Não autorizou convites por e-mail' }}
                            </p>
                        </dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Observações</dt>
                        <dd class="mt-1 whitespace-pre-wrap text-sm text-slate-800">{{ candidate.notes || '—' }}</dd>
                    </div>
                </dl>
            </section>

            <section class="surface-card p-5" aria-labelledby="vagas-title">
                <h3 id="vagas-title" class="text-base font-semibold text-talents-900">Vagas</h3>
                <ul v-if="applications.length" class="mt-4 space-y-3">
                    <li v-for="a in applications" :key="a.id" class="rounded-xl border border-slate-200 p-3">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0">
                                <Link
                                    v-if="a.vacancy?.url"
                                    :href="a.vacancy.url"
                                    class="font-semibold text-talents-800 hover:text-talents-900 hover:underline"
                                >
                                    {{ a.vacancy.title }}
                                </Link>
                                <p v-else class="font-semibold text-slate-800">{{ a.vacancy?.title ?? '—' }}</p>
                                <p class="text-xs text-slate-500">
                                    {{ a.vacancy?.company_name ?? '—' }} · vaga {{ (a.vacancy?.status_label ?? '').toLowerCase() }}
                                </p>
                            </div>
                            <span class="rounded-full bg-talents-50 px-2 py-0.5 text-xs font-semibold text-talents-800">
                                {{ a.stage_name ?? '—' }}
                            </span>
                        </div>
                        <p v-if="a.hired_at" class="mt-1 text-xs font-medium text-emerald-700">
                            Contratado em {{ formatDateTime(a.hired_at) }}
                        </p>
                        <details v-if="a.events.length" class="mt-2">
                            <summary class="cursor-pointer text-xs font-semibold text-talents-700">
                                Histórico da candidatura ({{ a.events.length }})
                            </summary>
                            <ol class="mt-2 space-y-1.5">
                                <li v-for="e in a.events" :key="e.id" class="rounded-lg bg-slate-50 px-2.5 py-1.5 text-xs">
                                    <span class="font-semibold text-slate-800">{{ e.type_label }}</span>
                                    <span class="text-slate-500">
                                        <template v-if="e.user_name"> · {{ e.user_name }}</template>
                                        · {{ formatDateTime(e.created_at) }}
                                    </span>
                                    <p class="mt-0.5 whitespace-pre-wrap text-slate-700">{{ describeApplicationEvent(e) }}</p>
                                </li>
                            </ol>
                        </details>
                    </li>
                </ul>
                <p v-else class="mt-4 text-sm text-slate-500">O candidato ainda não está em nenhuma vaga.</p>
            </section>

            <section v-if="employment.length" class="surface-card p-5 xl:col-span-2" aria-labelledby="vinculo-title">
                <h3 id="vinculo-title" class="text-base font-semibold text-talents-900">Vínculo e pós-contratação</h3>
                <ul class="mt-3 space-y-2">
                    <li v-for="e in employment" :key="e.id" class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-emerald-200 bg-emerald-50/40 px-3 py-2 text-sm">
                        <div>
                            <p class="font-semibold text-slate-900">{{ e.role_title || 'Cargo não informado' }} · {{ e.company_name }}</p>
                            <p class="text-xs text-slate-600">Gestor: {{ e.manager_name || 'não definido' }} · {{ e.status_label }}</p>
                        </div>
                        <Link v-if="e.url" :href="e.url" class="text-xs font-semibold text-talents-700 hover:underline">Abrir o ciclo</Link>
                    </li>
                </ul>
            </section>

            <section class="surface-card p-5" aria-labelledby="entrevistas-title">
                <h3 id="entrevistas-title" class="text-base font-semibold text-talents-900">Entrevistas</h3>
                <ul v-if="interviews.length" class="mt-4 space-y-2">
                    <li v-for="i in interviews" :key="i.id" class="flex flex-wrap items-start justify-between gap-2 rounded-xl border border-slate-200 p-3">
                        <div class="min-w-0">
                            <Link v-if="i.url" :href="i.url" class="font-semibold text-talents-800 hover:text-talents-900 hover:underline">
                                {{ i.questionnaire ?? 'Entrevista' }}
                            </Link>
                            <p v-else class="font-semibold text-slate-800">{{ i.questionnaire ?? 'Entrevista' }}</p>
                            <p class="text-xs text-slate-500">
                                {{ i.vacancy_title ? `Vaga ${i.vacancy_title}` : 'Sem vaga vinculada' }} · {{ formatDateTime(i.created_at) }}
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-1 text-xs font-semibold">
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-slate-700">{{ i.status_label }}</span>
                            <span
                                class="rounded-full px-2 py-0.5"
                                :class="i.is_reviewed ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-800'"
                            >
                                {{ i.is_reviewed ? 'Revisada' : 'Revisão pendente' }}
                            </span>
                        </div>
                    </li>
                </ul>
                <p v-else class="mt-4 text-sm text-slate-500">Nenhuma entrevista vinculada a este candidato.</p>
            </section>

            <section class="surface-card p-5" aria-labelledby="auditoria-title">
                <h3 id="auditoria-title" class="text-base font-semibold text-talents-900">Histórico do cadastro</h3>
                <ol v-if="audit.length" class="mt-4 space-y-2">
                    <li v-for="log in audit" :key="log.id" class="rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-2">
                        <div class="flex flex-wrap items-center gap-x-2 text-xs text-slate-500">
                            <span class="font-semibold text-slate-800">{{ log.action_label }}</span>
                            <span v-if="log.user_name">· {{ log.user_name }}</span>
                            <span>· {{ formatDateTime(log.created_at) }}</span>
                        </div>
                        <p v-if="candidateAuditDescription(log)" class="mt-0.5 text-xs text-slate-700">
                            {{ candidateAuditDescription(log) }}
                        </p>
                    </li>
                </ol>
                <p v-else class="mt-4 text-sm text-slate-500">Sem registros.</p>
            </section>
        </div>
    </AdminLayout>
</template>
