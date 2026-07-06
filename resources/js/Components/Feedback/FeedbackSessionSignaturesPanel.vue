<script setup>
import FeedbackStatusBadge from '@/Components/Feedback/FeedbackStatusBadge.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { feedbackRoute } from '@/composables/useFeedbackRoutes';
import { router } from '@inertiajs/vue3';
import { CheckCircleIcon, ClipboardDocumentIcon, EnvelopeIcon } from '@heroicons/vue/24/outline';
import { computed, ref } from 'vue';

const props = defineProps({
    session: { type: Object, required: true },
});

const sending = ref(false);
const copiedToken = ref(null);

const steps = [
    { key: 'fill', label: 'Preenchimento' },
    { key: 'invite', label: 'Convites' },
    { key: 'sign', label: 'Assinaturas' },
    { key: 'done', label: 'Concluído' },
];

const currentStep = computed(() => {
    if (props.session.status === 'completed') return 3;
    if (props.session.status === 'awaiting_signatures') return 2;
    if (props.session.signatures?.length) return 2;
    if (props.session.status === 'in_progress' || props.session.status === 'draft') return 0;
    return 0;
});

const canSendInvites = computed(
    () => !['completed', 'cancelled'].includes(props.session.status),
);

const inviteLabel = computed(() =>
    props.session.status === 'awaiting_signatures' || props.session.signatures?.length
        ? 'Reenviar convites por e-mail'
        : 'Enviar para assinatura',
);

const signedCount = computed(
    () => props.session.signatures?.filter((s) => s.signed_at).length ?? 0,
);

const sendInvites = () => {
    if (!canSendInvites.value || sending.value) return;
    sending.value = true;
    router.post(feedbackRoute('sessions.signatures', props.session.id), {}, {
        preserveScroll: true,
        onFinish: () => {
            sending.value = false;
        },
    });
};

const formatDate = (iso) => (iso ? new Date(iso).toLocaleString('pt-BR') : null);

const copyLink = async (sig) => {
    if (!sig.sign_url) return;
    try {
        await navigator.clipboard.writeText(sig.sign_url);
        copiedToken.value = sig.id;
        setTimeout(() => {
            copiedToken.value = null;
        }, 2000);
    } catch {
        window.prompt('Copie o link de assinatura:', sig.sign_url);
    }
};
</script>

<template>
    <section class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
        <div class="border-b border-slate-100 bg-gradient-to-r from-talents-50/80 to-white px-5 py-4">
            <h3 class="font-semibold text-talents-900">Fluxo de finalização</h3>
            <p class="mt-1 text-sm text-slate-600">
                Após o preenchimento, envie os convites. O feedback conclui quando colaborador e líder assinam.
            </p>
        </div>

        <ol class="grid gap-2 border-b border-slate-100 px-5 py-4 sm:grid-cols-4">
            <li
                v-for="(step, index) in steps"
                :key="step.key"
                class="flex items-center gap-2 text-sm"
                :class="index <= currentStep ? 'text-talents-800' : 'text-slate-400'"
            >
                <span
                    class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                    :class="
                        index < currentStep
                            ? 'bg-talents-700 text-white'
                            : index === currentStep
                              ? 'bg-talents-100 text-talents-800 ring-2 ring-talents-300'
                              : 'bg-slate-100 text-slate-500'
                    "
                >
                    <CheckCircleIcon v-if="index < currentStep" class="h-4 w-4" />
                    <span v-else>{{ index + 1 }}</span>
                </span>
                <span class="font-medium">{{ step.label }}</span>
            </li>
        </ol>

        <div
            v-if="session.status === 'completed'"
            class="flex items-start gap-3 border-b border-emerald-100 bg-emerald-50/80 px-5 py-4 text-sm text-emerald-900"
        >
            <CheckCircleIcon class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
            <div>
                <p class="font-semibold">Feedback concluído</p>
                <p v-if="session.completed_at" class="mt-0.5 text-emerald-800/90">
                    Finalizado em {{ formatDate(session.completed_at) }}.
                </p>
            </div>
        </div>

        <div v-else-if="canSendInvites" class="border-b border-slate-100 px-5 py-4">
            <PrimaryButton type="button" :disabled="sending" @click="sendInvites">
                <EnvelopeIcon class="mr-2 inline h-4 w-4" />
                {{ inviteLabel }}
            </PrimaryButton>
            <p class="mt-2 text-xs text-slate-500">
                E-mails serão enviados para o colaborador e o líder com link seguro para assinatura digital.
            </p>
        </div>

        <ul class="divide-y divide-slate-100">
            <li v-for="sig in session.signatures" :key="sig.id" class="px-5 py-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-medium text-slate-800">{{ sig.signer_name }}</p>
                        <p class="text-xs text-slate-500">
                            {{ sig.role_label ?? sig.role }} · {{ sig.signer_email }}
                        </p>
                        <p v-if="sig.signed_at" class="mt-1 text-xs text-emerald-700">
                            Assinado em {{ formatDate(sig.signed_at) }}
                        </p>
                        <p v-else-if="sig.sent_at" class="mt-1 text-xs text-slate-500">
                            Convite enviado em {{ formatDate(sig.sent_at) }}
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <FeedbackStatusBadge v-if="sig.signed_at" status="completed" label="Assinado" />
                        <FeedbackStatusBadge v-else status="awaiting_signatures" label="Pendente" />
                        <SecondaryButton
                            v-if="!sig.signed_at && sig.sign_url"
                            type="button"
                            class="!px-2 !py-1.5 text-xs"
                            @click="copyLink(sig)"
                        >
                            <ClipboardDocumentIcon class="h-4 w-4" />
                            {{ copiedToken === sig.id ? 'Copiado' : 'Copiar link' }}
                        </SecondaryButton>
                    </div>
                </div>
            </li>
            <li v-if="!session.signatures?.length" class="px-5 py-6 text-center text-sm text-slate-500">
                Nenhuma assinatura solicitada ainda. Use o botão acima após concluir o preenchimento.
            </li>
        </ul>
    </section>
</template>
