<script setup>
import FeedbackEvolutionPanel from '@/Components/Feedback/FeedbackEvolutionPanel.vue';
import FeedbackFlash from '@/Components/Feedback/FeedbackFlash.vue';
import FeedbackStatusBadge from '@/Components/Feedback/FeedbackStatusBadge.vue';
import FeedbacksLayout from '@/Components/Feedback/FeedbacksLayout.vue';
import FormPageHeader from '@/Components/FormPageHeader.vue';
import { feedbackRoute } from '@/composables/useFeedbackRoutes';
import { CheckCircleIcon, UsersIcon } from '@heroicons/vue/24/outline';
import { Head, Link } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';

/**
 * Pessoa em Feedbacks: histórico das sessões que o usuário vê e a Evolução entre períodos.
 */
const props = defineProps({
    employee: { type: Object, required: true },
    sessions: { type: Array, default: () => [] },
    evolution: { type: Object, required: true },
    narrative: { type: Object, default: null },
    ai: { type: Object, required: true },
    canManageEmployees: { type: Boolean, default: false },
});

const tabs = [
    { key: 'evolution', label: 'Evolução' },
    { key: 'history', label: 'Histórico' },
];
const tab = ref(props.evolution.ready ? 'evolution' : 'history');
const tabRefs = ref({});
async function onKeydown(event, index) {
    const next = { ArrowRight: index + 1, ArrowLeft: index - 1, Home: 0, End: tabs.length - 1 }[event.key];
    if (next === undefined) return;
    event.preventDefault();
    tab.value = tabs[(next + tabs.length) % tabs.length].key;
    await nextTick();
    tabRefs.value[tab.value]?.focus();
}
</script>

<template>
    <Head :title="employee.name" />

    <FeedbacksLayout>
        <template #header>
            <FormPageHeader :back-href="feedbackRoute('sessions.index')" back-label="Sessões" :title="employee.name" :subtitle="employee.email ?? undefined">
                <template v-if="canManageEmployees" #trailing>
                    <Link
                        :href="feedbackRoute('employees.duplicates')"
                        class="inline-flex min-h-[40px] items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500 focus-visible:ring-offset-2"
                    >
                        <UsersIcon class="h-4 w-4" aria-hidden="true" />
                        Cadastros duplicados
                    </Link>
                </template>
            </FormPageHeader>
        </template>

        <FeedbackFlash class="mb-6" />

        <div role="tablist" aria-label="Pessoa" class="mb-5 grid max-w-md grid-cols-2 gap-1 rounded-xl bg-slate-100 p-1">
            <button
                v-for="(t, i) in tabs"
                :id="`employee-tab-${t.key}`"
                :key="t.key"
                :ref="(el) => (tabRefs[t.key] = el)"
                type="button"
                role="tab"
                :aria-selected="tab === t.key"
                :aria-controls="`employee-panel-${t.key}`"
                :tabindex="tab === t.key ? 0 : -1"
                class="min-h-[44px] rounded-lg px-3 py-2 text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500"
                :class="tab === t.key ? 'bg-white text-talents-800 shadow-sm ring-1 ring-slate-200' : 'text-slate-700 hover:bg-white/60'"
                @click="tab = t.key"
                @keydown="onKeydown($event, i)"
            >
                {{ t.label }}
            </button>
        </div>

        <div v-if="tab === 'evolution'" id="employee-panel-evolution" role="tabpanel" aria-labelledby="employee-tab-evolution" tabindex="0" class="focus:outline-none">
            <FeedbackEvolutionPanel :employee-id="employee.id" :evolution="evolution" :narrative="narrative" :ai="ai" />
        </div>

        <div v-else id="employee-panel-history" role="tabpanel" aria-labelledby="employee-tab-history" tabindex="0" class="focus:outline-none">
            <p v-if="!sessions.length" class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center text-sm text-slate-700">
                Nenhum feedback desta pessoa visível para você.
            </p>
            <ol v-else class="space-y-3">
                <li v-for="s in sessions" :key="s.id" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-[13px] font-semibold uppercase tracking-wide text-slate-600">{{ s.period_label }}</p>
                            <Link
                                :href="feedbackRoute('sessions.show', s.id)"
                                class="mt-0.5 inline-block rounded font-semibold text-talents-800 underline decoration-talents-300 underline-offset-2 hover:decoration-talents-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500"
                            >
                                {{ s.title }}
                            </Link>
                            <p class="mt-0.5 text-[13px] text-slate-700">
                                <span v-if="s.leader">Líder: {{ s.leader }}</span><span v-if="s.template"> · {{ s.template }}</span>
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span v-if="s.has_report" class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-[13px] font-semibold text-emerald-900 ring-1 ring-emerald-300">
                                <CheckCircleIcon class="h-4 w-4" aria-hidden="true" /> Relatório pronto
                            </span>
                            <FeedbackStatusBadge :status="s.status" :label="s.status_label" />
                        </div>
                    </div>
                </li>
            </ol>
        </div>
    </FeedbacksLayout>
</template>
