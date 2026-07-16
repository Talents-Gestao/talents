<script setup>
import DesligamentoLayout from '@/Components/Desligamento/DesligamentoLayout.vue';
import ExitInterviewAccordions from '@/Components/Desligamento/ExitInterviewAccordions.vue';
import FormPageHeader from '@/Components/FormPageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { desligamentoRoute, isDesligamentoAdminContext } from '@/composables/useDesligamentoRoutes';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowDownTrayIcon, ClipboardDocumentIcon, LinkIcon, NoSymbolIcon } from '@heroicons/vue/24/outline';
import { computed, ref } from 'vue';

const props = defineProps({
    interview: Object,
    sections: { type: Array, default: () => [] },
    consultantNoteFields: { type: Array, default: () => [] },
});

const formatDate = (iso) => (iso ? new Date(`${iso}T12:00:00`).toLocaleDateString('pt-BR') : '—');

const linkCopied = ref(false);
const linkBusy = ref(false);

const backHref = computed(() =>
    isDesligamentoAdminContext() ? route('admin.survey-templates.index') : desligamentoRoute('index'),
);

const backLabel = computed(() => (isDesligamentoAdminContext() ? 'Mapeamentos' : 'Desligamento'));

const canGenerateLink = computed(
    () => props.interview.status === 'draft' && !props.interview.employee_submitted_at,
);

const copyPublicLink = async () => {
    if (!props.interview.public_url) {
        return;
    }
    try {
        await navigator.clipboard.writeText(props.interview.public_url);
        linkCopied.value = true;
        setTimeout(() => {
            linkCopied.value = false;
        }, 2000);
    } catch {
        window.prompt('Copie o link:', props.interview.public_url);
    }
};

const generateLink = () => {
    linkBusy.value = true;
    router.post(
        desligamentoRoute('link.store', props.interview.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                linkBusy.value = false;
            },
        },
    );
};

const revokeLink = () => {
    if (!confirm('Desativar o link público? O colaborador não poderá mais responder por ele.')) {
        return;
    }
    linkBusy.value = true;
    router.delete(desligamentoRoute('link.destroy', props.interview.id), {
        preserveScroll: true,
        onFinish: () => {
            linkBusy.value = false;
        },
    });
};
</script>

<template>
    <Head :title="`Desligamento — ${interview.employee?.name ?? ''}`" />

    <DesligamentoLayout>
        <template #header>
            <FormPageHeader
                :back-href="backHref"
                :back-label="backLabel"
                :title="interview.employee?.name ?? 'Pesquisa de desligamento'"
                :subtitle="`Entrevista em ${formatDate(interview.interview_date)} · ${interview.status_label}`"
            >
                <template #trailing>
                    <a :href="desligamentoRoute('pdf', interview.id)">
                        <SecondaryButton type="button">
                            <span class="inline-flex items-center gap-1.5">
                                <ArrowDownTrayIcon class="h-4 w-4" />
                                Baixar PDF
                            </span>
                        </SecondaryButton>
                    </a>
                    <Link :href="desligamentoRoute('edit', interview.id)">
                        <PrimaryButton type="button">Editar</PrimaryButton>
                    </Link>
                </template>
            </FormPageHeader>
        </template>

        <div class="space-y-3">
            <div
                v-if="$page.props.flash?.success"
                class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
            >
                {{ $page.props.flash.success }}
            </div>

            <section class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-talents-900">Link para o colaborador</h3>
                        <p class="mt-1 max-w-xl text-sm text-slate-600">
                            Se a entrevista não for presencial, envie o link para a pessoa responder sozinha.
                            As anotações da consultora não aparecem nesse formulário.
                        </p>
                    </div>
                    <button
                        v-if="canGenerateLink && !interview.has_public_link"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-talents-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-talents-700 disabled:opacity-50"
                        :disabled="linkBusy"
                        @click="generateLink"
                    >
                        <LinkIcon class="h-4 w-4" />
                        Gerar link
                    </button>
                </div>

                <div v-if="interview.has_public_link && interview.public_url" class="mt-4 space-y-3">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <input
                            type="text"
                            readonly
                            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700"
                            :value="interview.public_url"
                        />
                        <div class="flex shrink-0 gap-2">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                @click="copyPublicLink"
                            >
                                <ClipboardDocumentIcon class="h-4 w-4" />
                                {{ linkCopied ? 'Copiado!' : 'Copiar' }}
                            </button>
                            <button
                                v-if="canGenerateLink"
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-xl border border-rose-100 bg-white px-3 py-2 text-xs font-semibold text-rose-600 hover:bg-rose-50 disabled:opacity-50"
                                :disabled="linkBusy"
                                @click="revokeLink"
                            >
                                <NoSymbolIcon class="h-4 w-4" />
                                Desativar
                            </button>
                        </div>
                    </div>
                    <p v-if="interview.accepts_employee_responses" class="text-xs text-emerald-700">
                        Link ativo — aguardando resposta do colaborador.
                    </p>
                    <p v-else-if="interview.employee_submitted_at" class="text-xs text-slate-600">
                        Respondida pelo colaborador em
                        {{ new Date(interview.employee_submitted_at).toLocaleString('pt-BR') }}.
                    </p>
                </div>

                <p v-else-if="!canGenerateLink" class="mt-3 text-xs text-slate-500">
                    Para gerar um link, a pesquisa precisa estar em rascunho.
                </p>
            </section>

            <ExitInterviewAccordions
                mode="show"
                :sections="sections"
                :consultant-note-fields="consultantNoteFields"
                :answers="interview.answers ?? {}"
                :consultant-notes="interview.consultant_notes ?? {}"
            />

            <div class="flex justify-end pt-3">
                <Link :href="backHref">
                    <SecondaryButton type="button">Voltar</SecondaryButton>
                </Link>
            </div>
        </div>
    </DesligamentoLayout>
</template>
