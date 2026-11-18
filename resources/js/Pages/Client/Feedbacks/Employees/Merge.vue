<script setup>
import FeedbackNotice from '@/Components/Feedback/FeedbackNotice.vue';
import FeedbacksLayout from '@/Components/Feedback/FeedbacksLayout.vue';
import FormPageHeader from '@/Components/FormPageHeader.vue';
import { feedbackRoute } from '@/composables/useFeedbackRoutes';
import { formatDateTime } from '@/utils/dateOnly';
import { ArrowsRightLeftIcon, ArrowRightIcon } from '@heroicons/vue/24/outline';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Pré-visualização e confirmação da união de dois cadastros. Mostra o que será movido para o cadastro principal
 * e os avisos; só une com a confirmação explícita, e o servidor confere de novo as contagens.
 */
const props = defineProps({
    primary: { type: Object, required: true },
    absorbed: { type: Object, required: true },
    preview: { type: Object, required: true },
    tables: { type: Object, required: true },
});

const form = useForm({
    principal: props.primary.id,
    absorvido: props.absorbed.id,
    counts: props.preview.counts,
    confirmed: false,
});

const rows = computed(() => [
    ...Object.entries(props.tables).map(([table, label]) => ({ label, count: props.preview.counts[table] ?? 0 })),
    { label: 'Gravações dessas sessões', count: props.preview.recordings },
    { label: 'Relatórios dessas sessões', count: props.preview.reports },
    { label: 'Convites e assinaturas dessas sessões', count: props.preview.signatures },
]);
const nothingToMove = computed(() => rows.value.every((r) => r.count === 0));

const submit = () => form.post(feedbackRoute('employees.merge'), { preserveScroll: true });
const swapHref = computed(() => feedbackRoute('employees.merge.preview', { principal: props.absorbed.id, absorvido: props.primary.id }));
</script>

<template>
    <Head title="Unir cadastros" />

    <FeedbacksLayout>
        <template #header>
            <FormPageHeader :back-href="feedbackRoute('employees.duplicates')" back-label="Cadastros duplicados" title="Unir cadastros" subtitle="Confira o que será movido antes de confirmar" />
        </template>

        <div class="space-y-5">
            <div class="grid items-stretch gap-3 md:grid-cols-[1fr_auto_1fr]">
                <section class="rounded-2xl border border-slate-300 bg-white p-4" aria-labelledby="merge_absorbed">
                    <p id="merge_absorbed" class="text-[13px] font-semibold uppercase tracking-wide text-slate-600">Será absorvido (fica inativo)</p>
                    <p class="mt-1 font-semibold text-slate-900">{{ absorbed.name }}</p>
                    <p class="text-sm text-slate-700">{{ absorbed.email || 'Sem e-mail' }}</p>
                    <p class="mt-1 text-[13px] text-slate-700">{{ absorbed.sessions_count }} feedbacks · cadastrado em {{ formatDateTime(absorbed.created_at) }}</p>
                </section>
                <div class="flex items-center justify-center text-slate-500" aria-hidden="true">
                    <ArrowRightIcon class="h-6 w-6 rotate-90 md:rotate-0" />
                </div>
                <section class="rounded-2xl border-2 border-talents-300 bg-talents-50/50 p-4" aria-labelledby="merge_primary">
                    <p id="merge_primary" class="text-[13px] font-semibold uppercase tracking-wide text-talents-900">Fica (cadastro principal)</p>
                    <p class="mt-1 font-semibold text-slate-900">{{ primary.name }}</p>
                    <p class="text-sm text-slate-700">{{ primary.email || 'Sem e-mail' }}</p>
                    <p class="mt-1 text-[13px] text-slate-700">{{ primary.sessions_count }} feedbacks · cadastrado em {{ formatDateTime(primary.created_at) }}</p>
                </section>
            </div>
            <Link
                :href="swapHref"
                class="inline-flex min-h-[44px] items-center gap-2 rounded-xl px-2 text-sm font-semibold text-talents-800 underline underline-offset-2 focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500"
            >
                <ArrowsRightLeftIcon class="h-4 w-4" aria-hidden="true" />
                Inverter: manter «{{ absorbed.name }}»
            </Link>

            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5" aria-labelledby="merge_moves">
                <h3 id="merge_moves" class="font-semibold text-slate-900">O que passa para «{{ primary.name }}»</h3>
                <dl class="mt-3 divide-y divide-slate-100">
                    <div v-for="r in rows" :key="r.label" class="flex items-center justify-between gap-4 py-2 text-sm">
                        <dt class="text-slate-800">{{ r.label }}</dt>
                        <dd class="font-semibold tabular-nums text-slate-900">{{ r.count }}</dd>
                    </div>
                </dl>
                <p class="mt-3 text-sm text-slate-700">
                    Só o vínculo muda: os feedbacks continuam com o nome registrado na época, as respostas e as assinaturas ficam como estão
                    <span v-if="preview.signed_signatures">({{ preview.signed_signatures }} já assinadas)</span>.
                </p>
                <p v-if="nothingToMove" class="mt-2 text-sm text-slate-700">Nada a mover: o cadastro absorvido só fica inativo.</p>
            </section>

            <div v-if="preview.conflicts.length" class="space-y-2">
                <FeedbackNotice v-for="(c, i) in preview.conflicts" :key="i" tone="warning">{{ c.message }}</FeedbackNotice>
            </div>

            <form class="space-y-4 rounded-2xl border border-rose-200 bg-white p-4 shadow-sm sm:p-5" @submit.prevent="submit">
                <label class="flex min-h-[44px] cursor-pointer items-start gap-3 rounded-xl border px-4 py-3 text-sm text-slate-900" :class="form.confirmed ? 'border-rose-300 bg-rose-50/60' : 'border-slate-300'">
                    <input v-model="form.confirmed" type="checkbox" class="mt-0.5 h-5 w-5 shrink-0 rounded border-slate-400 text-rose-600 focus:ring-rose-500" />
                    <span class="font-medium">Entendo que a união não pode ser desfeita pela interface.</span>
                </label>
                <FeedbackNotice v-for="(message, key) in form.errors" :key="key" tone="danger">{{ message }}</FeedbackNotice>
                <div class="flex flex-wrap items-center gap-3">
                    <button
                        type="submit"
                        class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-xl bg-rose-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-700"
                        :disabled="!form.confirmed || form.processing"
                        :aria-describedby="!form.confirmed ? 'merge_reason' : undefined"
                    >
                        {{ form.processing ? 'Unindo…' : 'Unir cadastros' }}
                    </button>
                    <span v-if="!form.confirmed" id="merge_reason" class="text-sm text-slate-700">Marque a confirmação para liberar.</span>
                    <Link :href="feedbackRoute('employees.duplicates')" class="inline-flex min-h-[44px] items-center rounded-xl px-3 text-sm font-semibold text-slate-700 underline underline-offset-2">Cancelar</Link>
                </div>
            </form>
        </div>
    </FeedbacksLayout>
</template>
