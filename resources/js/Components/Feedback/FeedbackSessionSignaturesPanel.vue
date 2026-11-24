<script setup>
import FeedbackStatusBadge from '@/Components/Feedback/FeedbackStatusBadge.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { promptDialog } from '@/composables/useConfirmDialog';
import { feedbackRoute } from '@/composables/useFeedbackRoutes';
import { formatDateTime } from '@/utils/dateOnly';
import { router } from '@inertiajs/vue3';
import { CheckCircleIcon, ClipboardDocumentIcon, EnvelopeIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';
import { computed, ref } from 'vue';

/**
 * Aba «Assinaturas» do detalhe do feedback: envio (ou reenvio) dos convites com o texto de apoio junto da ação,
 * o estado de cada assinatura e o link de cada convite. A jornada (Preenchimento › Assinaturas › Concluído) fica
 * no topo da página. Regra de envio inalterada: qualquer status, exceto concluído ou cancelado.
 */
const props = defineProps({
    session: { type: Object, required: true },
    /** Perguntas ainda sem resposta (só para avisar; não bloqueia o envio). */
    pendingAnswers: { type: Number, default: 0 },
});

/** Depois do envio (o servidor volta ao detalhe sem ?aba), quem usa reabre a aba «Assinaturas». */
const emit = defineEmits(['sent']);

const sending = ref(false);
const copiedToken = ref(null);

const canSendInvites = computed(
    () => !['completed', 'cancelled'].includes(props.session.status),
);

const inviteLabel = computed(() =>
    props.session.status === 'awaiting_signatures' || props.session.signatures?.length
        ? 'Reenviar convites por e-mail'
        : 'Enviar para assinatura',
);

const sendInvites = () => {
    if (!canSendInvites.value || sending.value) return;
    sending.value = true;
    router.post(feedbackRoute('sessions.signatures', props.session.id), {}, {
        preserveScroll: true,
        onSuccess: () => emit('sent'),
        onFinish: () => {
            sending.value = false;
        },
    });
};

const formatDate = (iso) => (iso ? formatDateTime(iso) : null);

const copyLink = async (sig) => {
    if (!sig.sign_url) return;
    try {
        await navigator.clipboard.writeText(sig.sign_url);
        copiedToken.value = sig.id;
        setTimeout(() => {
            copiedToken.value = null;
        }, 2000);
    } catch {
        await promptDialog({
            title: 'Copie o link de assinatura',
            message: 'A área de transferência não está disponível. Selecione o endereço abaixo.',
            defaultValue: sig.sign_url,
            confirmLabel: 'Fechar',
        });
    }
};
</script>

<template>
    <section class="space-y-4" aria-labelledby="feedback_signatures_title" data-feedback-signatures>
        <h3 id="feedback_signatures_title" class="sr-only">Assinaturas</h3>

        <div
            v-if="session.status === 'completed'"
            class="flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-950"
            role="status"
        >
            <CheckCircleIcon class="mt-0.5 h-5 w-5 shrink-0 text-emerald-700" aria-hidden="true" />
            <div>
                <p class="font-semibold">Feedback concluído</p>
                <p v-if="session.completed_at" class="mt-0.5">Finalizado em {{ formatDate(session.completed_at) }}.</p>
            </div>
        </div>

        <ul v-if="session.signatures?.length" class="divide-y divide-slate-100 rounded-2xl border border-slate-200/80 bg-white shadow-sm">
            <li v-for="sig in session.signatures" :key="sig.id" class="px-4 py-4 sm:px-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-medium text-slate-900">{{ sig.signer_name }}</p>
                        <p class="break-words text-[13px] text-slate-700">{{ sig.role_label ?? sig.role }} · {{ sig.signer_email }}</p>
                        <p v-if="sig.signed_at" class="mt-1 text-[13px] text-emerald-800">Assinado em {{ formatDate(sig.signed_at) }}</p>
                        <p v-else-if="sig.sent_at" class="mt-1 text-[13px] text-slate-700">Convite enviado em {{ formatDate(sig.sent_at) }}</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <FeedbackStatusBadge v-if="sig.signed_at" status="completed" label="Assinado" />
                        <FeedbackStatusBadge v-else status="awaiting_signatures" label="Pendente" />
                        <SecondaryButton
                            v-if="!sig.signed_at && sig.sign_url"
                            type="button"
                            class="min-h-[44px] gap-1.5 !px-3 text-xs hover:border-talents-300 hover:text-talents-800 active:bg-talents-50"
                            @click="copyLink(sig)"
                        >
                            <ClipboardDocumentIcon class="h-4 w-4" aria-hidden="true" />
                            {{ copiedToken === sig.id ? 'Copiado' : 'Copiar link' }}
                        </SecondaryButton>
                    </div>
                </div>
            </li>
        </ul>

        <!-- Estado e envio num só card: situação no topo, aviso de pendência e, no rodapé, a ação com o texto de apoio -->
        <div
            v-if="!session.signatures?.length || canSendInvites"
            class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm"
        >
            <div v-if="!session.signatures?.length" class="flex items-start gap-4 px-4 py-5 sm:px-6" data-feedback-signatures-empty>
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-talents-50 ring-1 ring-talents-100" aria-hidden="true">
                    <EnvelopeIcon class="h-5 w-5 text-talents-700" />
                </span>
                <div class="min-w-0">
                    <p class="font-semibold text-slate-900">Nenhuma assinatura solicitada</p>
                    <p class="mt-1 max-w-[60ch] text-sm text-slate-700">Colaborador e líder recebem um link por e-mail para assinar.</p>
                </div>
            </div>

            <div
                v-if="canSendInvites"
                class="space-y-3 px-4 py-4 sm:px-6"
                :class="!session.signatures?.length ? 'border-t border-slate-100 bg-slate-50/60' : ''"
                data-feedback-signatures-send
            >
                <p
                    v-if="pendingAnswers > 0"
                    class="flex items-start gap-2 rounded-xl bg-amber-50 px-3 py-2 text-sm font-medium text-amber-950 ring-1 ring-amber-200"
                    data-feedback-signatures-pending
                >
                    <ExclamationTriangleIcon class="mt-0.5 h-4 w-4 shrink-0 text-amber-700" aria-hidden="true" />
                    Ainda há {{ pendingAnswers }} {{ pendingAnswers === 1 ? 'pergunta sem resposta' : 'perguntas sem resposta' }}.
                </p>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-4">
                    <!-- Secundário: a ação principal da página fica no cabeçalho (uma por estado) -->
                    <SecondaryButton
                        type="button"
                        class="min-h-[44px] shrink-0 justify-center hover:border-talents-300 hover:text-talents-800 active:bg-talents-50 disabled:cursor-not-allowed"
                        :disabled="sending"
                        data-feedback-signatures-send-button
                        @click="sendInvites"
                    >
                        <EnvelopeIcon class="mr-2 inline h-4 w-4" aria-hidden="true" />
                        {{ sending ? 'Enviando…' : inviteLabel }}
                    </SecondaryButton>
                    <p class="max-w-[60ch] text-sm text-slate-700">E-mails com link seguro para assinatura digital vão para o colaborador e o líder.</p>
                </div>
            </div>
        </div>
    </section>
</template>
