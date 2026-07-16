<script setup>
import HiringProcessStepper from '@/Components/HiringProcessStepper.vue';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { BriefcaseIcon, CalendarDaysIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    stages: { type: Array, required: true },
    active_stage: { type: String, required: true },
    stage_counts: { type: Object, required: true },
    processes: { type: Array, required: true },
    all_processes: { type: Array, required: true },
    company_name: { type: String, required: true },
});

const currentStage = ref(props.active_stage);
watch(
    () => props.active_stage,
    (v) => {
        currentStage.value = v;
    },
);

const activeStageLabel = computed(() => {
    const s = props.stages.find((x) => x.value === currentStage.value);
    return s?.label ?? '';
});

const totalCount = computed(() =>
    Object.values(props.stage_counts || {}).reduce((sum, n) => sum + (Number(n) || 0), 0),
);

const onStageChange = (stage) => {
    currentStage.value = stage;
    router.get(
        route('client.acompanhamento.index'),
        { stage },
        { preserveState: true, replace: true },
    );
};

const formatDate = (iso) => {
    if (!iso) {
        return '';
    }
    try {
        return new Date(iso).toLocaleDateString('pt-BR', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        });
    } catch {
        return '';
    }
};
</script>

<template>
    <Head title="Acompanhamento" />

    <ClientLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold leading-tight text-talents-900">Acompanhamento</h2>
                <p class="mt-1 text-sm text-slate-600">
                    Acompanhe em que fase está cada processo de contratação de {{ company_name }}.
                </p>
            </div>
        </template>

        <div class="space-y-5">
            <section
                class="overflow-hidden rounded-2xl border border-talents-200/80 bg-gradient-to-b from-talents-50/90 via-white to-white shadow-sm"
            >
                <div class="border-b border-talents-100/80 px-4 py-4 sm:px-6 sm:py-5">
                    <div class="mb-4">
                        <p class="text-xs font-semibold uppercase tracking-wider text-talents-600">Funil de contratação</p>
                        <p class="mt-0.5 text-sm text-slate-600">
                            {{ totalCount }} processo(s) ·
                            <span class="font-semibold text-talents-800">{{ activeStageLabel }}</span>
                        </p>
                    </div>
                    <HiringProcessStepper
                        :stages="stages"
                        :current-stage="currentStage"
                        :stage-counts="stage_counts"
                        @update:current-stage="onStageChange"
                    />
                </div>

                <div class="px-4 py-5 sm:px-6">
                    <div class="mb-4 flex items-baseline justify-between gap-2">
                        <h3 class="text-base font-semibold text-talents-900">Processos nesta fase</h3>
                        <span class="text-xs font-medium tabular-nums text-slate-500">
                            {{ processes.length }} de {{ totalCount }}
                        </span>
                    </div>

                    <ul v-if="processes.length" class="grid gap-3 sm:grid-cols-2">
                        <li
                            v-for="p in processes"
                            :key="p.id"
                            class="rounded-2xl border border-slate-200/90 bg-white p-4 shadow-sm transition hover:border-talents-200 hover:shadow-md"
                        >
                            <div class="flex items-start gap-3">
                                <span
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-talents-100 text-talents-700"
                                >
                                    <BriefcaseIcon class="h-5 w-5" />
                                </span>
                                <div class="min-w-0">
                                    <p class="font-semibold text-slate-900">{{ p.title }}</p>
                                    <p class="mt-1 text-sm text-talents-700">{{ p.current_stage_label }}</p>
                                    <p v-if="p.updated_at" class="mt-2 flex items-center gap-1 text-xs text-slate-500">
                                        <CalendarDaysIcon class="h-3.5 w-3.5" />
                                        Atualizado em {{ formatDate(p.updated_at) }}
                                    </p>
                                </div>
                            </div>
                        </li>
                    </ul>

                    <div
                        v-else
                        class="rounded-2xl border border-dashed border-talents-200 bg-gradient-to-b from-talents-50/40 to-white px-6 py-12 text-center"
                    >
                        <div
                            class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-talents-100 text-talents-700"
                        >
                            <BriefcaseIcon class="h-6 w-6" />
                        </div>
                        <p class="mt-4 font-semibold text-talents-900">Nenhum processo nesta fase</p>
                        <p class="mx-auto mt-1 max-w-sm text-sm text-slate-600">
                            Quando a Talents avançar um processo, ele aparecerá aqui.
                        </p>
                    </div>
                </div>
            </section>

            <section
                v-if="all_processes.length"
                class="rounded-2xl border border-talents-200/80 bg-white p-4 shadow-sm sm:p-6"
            >
                <div class="mb-4">
                    <h3 class="text-base font-semibold text-talents-900">Visão geral</h3>
                    <p class="mt-0.5 text-sm text-slate-600">Todos os processos e a fase atual de cada um.</p>
                </div>
                <ul class="space-y-3">
                    <li
                        v-for="p in all_processes"
                        :key="'all-' + p.id"
                        class="rounded-2xl border border-slate-100 bg-gradient-to-br from-slate-50/90 to-white p-4"
                    >
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <p class="font-semibold text-slate-900">{{ p.title }}</p>
                            <span
                                class="inline-flex rounded-full bg-talents-100 px-2.5 py-1 text-xs font-semibold text-talents-800"
                            >
                                {{ p.current_stage_label }}
                            </span>
                        </div>
                        <HiringProcessStepper
                            :stages="stages"
                            :current-stage="p.current_stage"
                            :stage-counts="{}"
                            :progress-stage="p.current_stage"
                            :interactive="false"
                            compact
                        />
                    </li>
                </ul>
            </section>
        </div>
    </ClientLayout>
</template>
