<script setup>
import FeedbackFlash from '@/Components/Feedback/FeedbackFlash.vue';
import FeedbackNotice from '@/Components/Feedback/FeedbackNotice.vue';
import FeedbacksLayout from '@/Components/Feedback/FeedbacksLayout.vue';
import FormPageHeader from '@/Components/FormPageHeader.vue';
import { feedbackRoute } from '@/composables/useFeedbackRoutes';
import { formatDateTime } from '@/utils/dateOnly';
import { ArrowsRightLeftIcon, UsersIcon } from '@heroicons/vue/24/outline';
import { Head, Link } from '@inertiajs/vue3';

/** Sugestões de cadastros que podem ser a mesma pessoa. Só sugere: a união sempre passa pela pré-visualização. */
defineProps({
    pairs: { type: Array, default: () => [] },
});

const mergeHref = (keep, absorb) => feedbackRoute('employees.merge.preview', { principal: keep.id, absorvido: absorb.id });
const btn =
    'inline-flex min-h-[44px] items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 shadow-sm transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500 focus-visible:ring-offset-2';
</script>

<template>
    <Head title="Cadastros duplicados" />

    <FeedbacksLayout>
        <template #header>
            <FormPageHeader
                :back-href="feedbackRoute('sessions.index')"
                back-label="Sessões"
                title="Cadastros duplicados"
                subtitle="Pessoas que podem ter sido registradas mais de uma vez nesta empresa"
            />
        </template>

        <FeedbackFlash class="mb-6" />

        <FeedbackNotice tone="info" class="mb-5">
            Estas são só sugestões, por e-mail igual ou nome muito parecido. Nada é unido sem você conferir a pré-visualização e confirmar.
        </FeedbackNotice>

        <div v-if="!pairs.length" class="flex flex-col items-center gap-3 rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center">
            <UsersIcon class="h-8 w-8 text-talents-600" aria-hidden="true" />
            <p class="font-semibold text-slate-900">Nenhum cadastro duplicado encontrado</p>
            <p class="max-w-[60ch] text-sm text-slate-700">Quando o mesmo colaborador for registrado com e-mail igual ou nome muito parecido, a sugestão aparece aqui.</p>
        </div>

        <ul v-else class="space-y-4">
            <li v-for="(pair, i) in pairs" :key="`${pair.a.id}-${pair.b.id}`" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5" :aria-label="`Sugestão ${i + 1}: ${pair.reason_label}`">
                <p class="inline-flex rounded-full bg-talents-50 px-2.5 py-1 text-[13px] font-semibold text-talents-900 ring-1 ring-talents-200">{{ pair.reason_label }}</p>
                <div class="mt-3 grid gap-3 md:grid-cols-2">
                    <div v-for="e in [pair.a, pair.b]" :key="e.id" class="rounded-xl border border-slate-200 bg-slate-50/60 p-3">
                        <p class="font-semibold text-slate-900">{{ e.name }}</p>
                        <p class="text-sm text-slate-700">{{ e.email || 'Sem e-mail' }}</p>
                        <p class="mt-1 text-[13px] text-slate-700">
                            {{ e.sessions_count }} {{ e.sessions_count === 1 ? 'feedback' : 'feedbacks' }} · cadastrado em {{ formatDateTime(e.created_at) }}
                        </p>
                    </div>
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    <Link :href="mergeHref(pair.a, pair.b)" :class="btn">
                        <ArrowsRightLeftIcon class="h-4 w-4" aria-hidden="true" />
                        Unir mantendo «{{ pair.a.name }}»
                    </Link>
                    <Link :href="mergeHref(pair.b, pair.a)" :class="btn">
                        <ArrowsRightLeftIcon class="h-4 w-4" aria-hidden="true" />
                        Unir mantendo «{{ pair.b.name }}»
                    </Link>
                </div>
            </li>
        </ul>
    </FeedbacksLayout>
</template>
