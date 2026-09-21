<script setup>
import ApexChart from '@/Components/Charts/ApexChart.vue';
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    campaign: { type: Object, required: true },
    publicUrl: { type: String, required: true },
    sectorFilter: { type: String, default: 'all' },
    dashboard: { type: Object, required: true },
    themesPending: { type: Boolean, default: false },
    /** URLs (show / analyze) — sem depender do Ziggy. */
    actionUrls: {
        type: Object,
        required: true,
        validator: (v) => v?.show && v?.analyze,
    },
});

const activeTab = ref('dashboard');
const exploreQuestion = ref('all');
const copied = ref(false);

const setSector = (sector) => {
    router.get(props.actionUrls.show, { sector }, { preserveState: true, preserveScroll: true });
};

const copyLink = async () => {
    try {
        await navigator.clipboard.writeText(props.publicUrl);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch {
        copied.value = false;
    }
};

const runAnalysis = () => {
    router.post(props.actionUrls.analyze, {}, { preserveScroll: true });
};

const barOptions = computed(() => ({
    chart: { type: 'bar', toolbar: { show: false }, fontFamily: 'inherit' },
    plotOptions: { bar: { horizontal: true, borderRadius: 4 } },
    dataLabels: { enabled: true },
    xaxis: {
        categories: props.dashboard.participation_by_sector.map((r) => r.sector),
    },
    colors: ['#632a7e'],
    grid: { strokeDashArray: 4 },
}));

const barSeries = computed(() => [
    {
        name: 'Respostas',
        data: props.dashboard.participation_by_sector.map((r) => r.count),
    },
]);

const filteredResponses = computed(() => {
    const rows = props.dashboard.responses ?? [];
    if (exploreQuestion.value === 'all') {
        return rows;
    }
    return rows;
});

const questionEntries = computed(() => Object.values(props.dashboard.questions ?? {}));
</script>

<template>
    <div>
        <div
            v-if="$page.props.flash?.success"
            class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-900"
        >
            {{ $page.props.flash.success }}
        </div>

        <div class="mb-6 flex flex-wrap items-end gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="min-w-0 flex-1">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Link anónimo</p>
                <p class="mt-1 break-all font-mono text-xs text-talents-800">{{ publicUrl }}</p>
            </div>
            <button
                type="button"
                class="rounded-md bg-talents-100 px-3 py-2 text-sm font-medium text-talents-800 hover:bg-talents-200"
                @click="copyLink"
            >
                {{ copied ? 'Copiado!' : 'Copiar link' }}
            </button>
        </div>

        <div class="mb-4 flex flex-wrap gap-2">
            <button
                type="button"
                class="rounded-full px-3 py-1.5 text-xs font-semibold"
                :class="activeTab === 'dashboard' ? 'bg-talents-700 text-white' : 'bg-slate-100 text-slate-700'"
                @click="activeTab = 'dashboard'"
            >
                Dashboard
            </button>
            <button
                type="button"
                class="rounded-full px-3 py-1.5 text-xs font-semibold"
                :class="activeTab === 'explore' ? 'bg-talents-700 text-white' : 'bg-slate-100 text-slate-700'"
                @click="activeTab = 'explore'"
            >
                Explorar respostas
            </button>
        </div>

        <div class="mb-6 flex flex-wrap items-center gap-2">
            <label class="text-sm font-medium text-slate-700">Setor</label>
            <select
                class="rounded-md border-gray-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                :value="sectorFilter"
                @change="setSector($event.target.value)"
            >
                <option value="all">Todos os setores</option>
                <option v-for="s in dashboard.catalog_sectors" :key="s" :value="s">{{ s }}</option>
            </select>
            <button
                type="button"
                class="ml-auto rounded-md bg-talents-700 px-3 py-2 text-sm font-semibold text-white hover:bg-talents-800 disabled:opacity-60"
                :disabled="themesPending"
                @click="runAnalysis"
            >
                {{ themesPending ? 'Analisando temas…' : 'Gerar / atualizar temas' }}
            </button>
        </div>

        <div v-show="activeTab === 'dashboard'" class="space-y-6">
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Respostas</p>
                    <p class="mt-2 text-3xl font-bold tabular-nums text-talents-900">
                        {{ dashboard.kpis.total_responses }}
                    </p>
                </article>
                <article class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Setores cobertos</p>
                    <p class="mt-2 text-3xl font-bold tabular-nums text-talents-900">
                        {{ dashboard.kpis.sectors_covered }}
                    </p>
                </article>
                <article class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Sem resposta</p>
                    <p class="mt-2 text-3xl font-bold tabular-nums text-amber-800">
                        {{ dashboard.kpis.sectors_without_response }}
                    </p>
                </article>
                <article class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Dias abertos</p>
                    <p class="mt-2 text-3xl font-bold tabular-nums text-talents-900">
                        {{ dashboard.kpis.days_open ?? '—' }}
                    </p>
                </article>
            </div>

            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-talents-900">Participação por setor</h3>
                <div class="mt-4 min-h-[22rem]">
                    <ApexChart
                        v-if="dashboard.participation_by_sector?.length"
                        height="360"
                        type="bar"
                        :options="barOptions"
                        :series="barSeries"
                    />
                    <p v-else class="text-sm text-slate-500">Ainda sem respostas.</p>
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-talents-900">Temas por pergunta</h3>
                <p class="mt-1 text-sm text-slate-500">
                    Gere a análise para agrupar narrativas (IA quando configurada; senão frequência de palavras).
                </p>
                <div class="mt-4 grid gap-4 lg:grid-cols-2">
                    <div
                        v-for="q in questionEntries"
                        :key="q.key"
                        class="rounded-lg border border-slate-100 bg-slate-50/80 p-4"
                    >
                        <h4 class="text-sm font-semibold text-talents-800">{{ q.dashboard_label }}</h4>
                        <ul v-if="dashboard.themes_by_question[q.key]?.length" class="mt-3 space-y-2">
                            <li
                                v-for="t in dashboard.themes_by_question[q.key]"
                                :key="t.theme"
                                class="flex items-center justify-between gap-2 text-sm"
                            >
                                <span class="rounded-full bg-white px-2.5 py-1 ring-1 ring-slate-200">{{ t.theme }}</span>
                                <span class="tabular-nums text-slate-600">{{ t.count }}</span>
                            </li>
                        </ul>
                        <p v-else class="mt-3 text-xs text-slate-500">Sem temas ainda.</p>
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h3 class="text-lg font-semibold text-talents-900">Alinhamento de dores</h3>
                    <p v-if="dashboard.pain_alignment.score != null" class="text-sm text-slate-600">
                        Indicador de sobreposição:
                        <span class="font-semibold tabular-nums text-talents-800">{{ dashboard.pain_alignment.score }}%</span>
                    </p>
                </div>
                <div class="mt-4 grid gap-4 md:grid-cols-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Eu resolvo</p>
                        <ul class="mt-2 space-y-1 text-sm">
                            <li v-for="t in dashboard.pain_alignment.self" :key="'s-' + t.theme">
                                {{ t.theme }}
                                <span class="text-slate-400">({{ t.count }})</span>
                            </li>
                            <li v-if="!dashboard.pain_alignment.self.length" class="text-slate-400">—</li>
                        </ul>
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Setor resolve</p>
                        <ul class="mt-2 space-y-1 text-sm">
                            <li v-for="t in dashboard.pain_alignment.sector" :key="'sec-' + t.theme">
                                {{ t.theme }}
                                <span class="text-slate-400">({{ t.count }})</span>
                            </li>
                            <li v-if="!dashboard.pain_alignment.sector.length" class="text-slate-400">—</li>
                        </ul>
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Empresa resolve</p>
                        <ul class="mt-2 space-y-1 text-sm">
                            <li v-for="t in dashboard.pain_alignment.company" :key="'c-' + t.theme">
                                {{ t.theme }}
                                <span class="text-slate-400">({{ t.count }})</span>
                            </li>
                            <li v-if="!dashboard.pain_alignment.company.length" class="text-slate-400">—</li>
                        </ul>
                    </div>
                </div>
                <p v-if="dashboard.pain_alignment.overlap_themes?.length" class="mt-4 text-sm text-slate-700">
                    Temas em comum:
                    <span
                        v-for="o in dashboard.pain_alignment.overlap_themes"
                        :key="o"
                        class="mr-1 inline-block rounded-full bg-talents-50 px-2 py-0.5 text-talents-900 ring-1 ring-talents-200"
                    >
                        {{ o }}
                    </span>
                </p>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-talents-900">Sonhos e motivações</h3>
                <div class="mt-4 grid gap-6 lg:grid-cols-2">
                    <div>
                        <p class="text-xs font-bold uppercase text-slate-500">Motivações</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <span
                                v-for="t in dashboard.motivation_and_dreams.why_work"
                                :key="'w-' + t.theme"
                                class="rounded-full bg-emerald-50 px-3 py-1 text-sm text-emerald-900 ring-1 ring-emerald-200"
                            >
                                {{ t.theme }} · {{ t.count }}
                            </span>
                            <span v-if="!dashboard.motivation_and_dreams.why_work?.length" class="text-sm text-slate-400"
                                >—</span
                            >
                        </div>
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase text-slate-500">Sonhos</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <span
                                v-for="t in dashboard.motivation_and_dreams.dream"
                                :key="'d-' + t.theme"
                                class="rounded-full bg-sky-50 px-3 py-1 text-sm text-sky-900 ring-1 ring-sky-200"
                            >
                                {{ t.theme }} · {{ t.count }}
                            </span>
                            <span v-if="!dashboard.motivation_and_dreams.dream?.length" class="text-sm text-slate-400"
                                >—</span
                            >
                        </div>
                    </div>
                </div>
                <ul class="mt-6 space-y-3">
                    <li
                        v-for="(q, idx) in dashboard.motivation_and_dreams.quotes"
                        :key="idx"
                        class="rounded-lg border border-slate-100 bg-slate-50 px-4 py-3 text-sm text-slate-700"
                    >
                        <p class="text-[10px] font-semibold uppercase text-slate-500">
                            {{ q.question }} · {{ q.sector }}
                        </p>
                        <p class="mt-1 italic">“{{ q.text }}”</p>
                    </li>
                </ul>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-talents-900">Insights</h3>
                <ul class="mt-4 list-disc space-y-2 pl-5 text-sm text-slate-700">
                    <li v-for="i in dashboard.insights" :key="i.id">{{ i.message }}</li>
                    <li v-if="!dashboard.insights?.length">Nenhum insight ainda — execute a análise de temas.</li>
                </ul>
            </section>
        </div>

        <div v-show="activeTab === 'explore'" class="space-y-4">
            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    class="rounded-full px-3 py-1 text-xs font-semibold"
                    :class="exploreQuestion === 'all' ? 'bg-talents-700 text-white' : 'bg-slate-100'"
                    @click="exploreQuestion = 'all'"
                >
                    Todas as perguntas
                </button>
                <button
                    v-for="q in questionEntries"
                    :key="q.key"
                    type="button"
                    class="rounded-full px-3 py-1 text-xs font-semibold"
                    :class="exploreQuestion === q.key ? 'bg-talents-700 text-white' : 'bg-slate-100'"
                    @click="exploreQuestion = q.key"
                >
                    {{ q.dashboard_label }}
                </button>
            </div>

            <article
                v-for="r in filteredResponses"
                :key="r.id"
                class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm"
            >
                <p class="text-xs font-semibold uppercase tracking-wide text-talents-700">{{ r.sector }}</p>
                <template v-if="exploreQuestion === 'all'">
                    <p v-for="q in questionEntries" :key="q.key" class="mt-3 text-sm text-slate-700">
                        <span class="font-medium text-slate-900">{{ q.label }}</span>
                        <br />
                        {{ r[q.key] }}
                    </p>
                </template>
                <p v-else class="mt-3 text-sm text-slate-700">{{ r[exploreQuestion] }}</p>
            </article>
            <p v-if="!filteredResponses.length" class="text-sm text-slate-500">Nenhuma resposta neste filtro.</p>
        </div>
    </div>
</template>
