<script setup>
import ClientLayout from '@/Layouts/ClientLayout.vue';
import FormPageHeader from '@/Components/FormPageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { confirmDialog } from '@/composables/useConfirmDialog';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    evaluation: { type: Object, required: true },
});

const copied = ref(false);
const emailForm = useForm({});

const formatDate = (iso) => {
    if (!iso) {
        return '—';
    }
    try {
        return new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
    } catch {
        return '—';
    }
};

const statusClass = (value) => {
    if (value === 'answered') {
        return 'bg-emerald-50 text-emerald-800 ring-emerald-200';
    }
    if (value === 'pending') {
        return 'bg-amber-50 text-amber-800 ring-amber-200';
    }
    return 'bg-slate-100 text-slate-600 ring-slate-200';
};

const copyLink = async () => {
    try {
        await navigator.clipboard.writeText(props.evaluation.public_url);
        copied.value = true;
        window.setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch {
        copied.value = false;
    }
};

const sendEmail = async () => {
    const confirmed = await confirmDialog(
        `Enviar a avaliação por e-mail para ${props.evaluation.respondent_email}?`,
    );
    if (!confirmed) {
        return;
    }
    emailForm.post(route('client.avaliacoes.email', props.evaluation.id));
};

const scores = computed(() => [
    { label: 'Atendimento', value: props.evaluation.service_score },
    { label: 'Qualidade da entrega', value: props.evaluation.delivery_score },
    { label: 'Comunicação', value: props.evaluation.communication_score },
]);
</script>

<template>
    <Head :title="`Avaliação · ${evaluation.cycle_label}`" />

    <ClientLayout>
        <template #header>
            <FormPageHeader
                :back-href="route('client.avaliacoes.index')"
                back-label="Avaliações"
                :title="evaluation.cycle_label"
                :subtitle="evaluation.company_name"
            >
                <template #trailing>
                    <span
                        class="inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1"
                        :class="statusClass(evaluation.status)"
                    >
                        {{ evaluation.status_label }}
                    </span>
                </template>
            </FormPageHeader>
        </template>

        <div
            v-if="$page.props.flash?.success"
            class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
        >
            {{ $page.props.flash.success }}
        </div>
        <div
            v-if="$page.props.flash?.error"
            class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
        >
            {{ $page.props.flash.error }}
        </div>

        <div class="grid gap-4 lg:grid-cols-5">
            <section class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-3">
                <h3 class="text-sm font-semibold text-slate-900">Avaliação</h3>
                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-slate-500">Contato</dt>
                        <dd class="font-medium text-slate-900">{{ evaluation.respondent_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">E-mail</dt>
                        <dd class="font-medium text-slate-900">{{ evaluation.respondent_email }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Celular</dt>
                        <dd class="font-medium text-slate-900">{{ evaluation.respondent_phone || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Validade</dt>
                        <dd class="font-medium text-slate-900">{{ formatDate(evaluation.expires_at) }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">E-mail enviado</dt>
                        <dd class="font-medium text-slate-900">{{ formatDate(evaluation.sent_at) }}</dd>
                    </div>
                </dl>

                <div v-if="evaluation.can_share" class="space-y-3 border-t border-slate-100 pt-4">
                    <p class="text-sm text-slate-600">
                        Envie este link pelo canal que preferir. Cada avaliação aceita uma resposta.
                    </p>
                    <label class="block text-xs font-medium uppercase tracking-wide text-slate-500" for="public-url">
                        Link público
                    </label>
                    <input
                        id="public-url"
                        :value="evaluation.public_url"
                        readonly
                        class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-800"
                    />
                    <div class="flex flex-wrap gap-2">
                        <PrimaryButton type="button" @click="copyLink">
                            {{ copied ? 'Link copiado' : 'Copiar link' }}
                        </PrimaryButton>
                        <a
                            v-if="evaluation.whatsapp_url"
                            :href="evaluation.whatsapp_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700"
                        >
                            Abrir WhatsApp
                        </a>
                        <SecondaryButton v-if="evaluation.can_email" type="button" :disabled="emailForm.processing" @click="sendEmail">
                            Enviar e-mail
                        </SecondaryButton>
                    </div>
                </div>

                <div v-else class="border-t border-slate-100 pt-4 text-sm text-slate-600">
                    Este link não aceita mais respostas.
                </div>

                <div v-if="evaluation.can_replace" class="border-t border-slate-100 pt-4">
                    <Link
                        :href="route('client.avaliacoes.create', {
                            cycle_year: evaluation.cycle_year,
                            cycle_quarter: evaluation.cycle_quarter,
                        })"
                        class="text-sm font-semibold text-talents-700 hover:underline"
                    >
                        Gerar um novo link para este ciclo
                    </Link>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2">
                <h3 class="text-sm font-semibold text-slate-900">Resposta</h3>
                <div v-if="evaluation.status === 'answered'" class="mt-4 space-y-4 text-sm">
                    <div>
                        <p class="text-slate-500">Recomendação</p>
                        <p class="mt-1 text-2xl font-semibold text-slate-900">
                            {{ evaluation.nps_score }}<span class="text-base font-medium text-slate-400"> / 10</span>
                        </p>
                        <p class="text-talents-700">{{ evaluation.nps_band }}</p>
                    </div>
                    <dl class="space-y-2">
                        <div v-for="score in scores" :key="score.label" class="flex items-center justify-between gap-3">
                            <dt class="text-slate-600">{{ score.label }}</dt>
                            <dd class="font-semibold text-slate-900">{{ score.value }} / 5</dd>
                        </div>
                    </dl>
                    <div v-if="evaluation.comment">
                        <p class="text-slate-500">Comentário</p>
                        <p class="mt-1 whitespace-pre-wrap text-slate-800">{{ evaluation.comment }}</p>
                    </div>
                    <p class="text-xs text-slate-500">
                        {{ evaluation.answered_name }}
                        <span v-if="evaluation.answered_role"> · {{ evaluation.answered_role }}</span>
                        · {{ formatDate(evaluation.answered_at) }}
                    </p>
                </div>
                <p v-else class="mt-4 text-sm text-slate-500">Ainda sem resposta.</p>
            </section>
        </div>
    </ClientLayout>
</template>
