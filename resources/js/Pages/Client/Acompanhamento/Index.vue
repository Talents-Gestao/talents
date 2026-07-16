<script setup>
import HiringProcessStepper from '@/Components/HiringProcessStepper.vue';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

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
                <p class="mt-1 text-sm text-gray-600">
                    Acompanhe em que fase está cada processo de contratação de {{ company_name }}.
                </p>
            </div>
        </template>

        <div class="space-y-6">
            <div class="rounded-2xl border border-talents-200 bg-white p-4 shadow-sm sm:p-6">
                <HiringProcessStepper
                    :stages="stages"
                    :current-stage="currentStage"
                    :stage-counts="stage_counts"
                    @update:current-stage="onStageChange"
                />

                <div class="mt-6">
                    <h3 class="text-lg font-semibold text-talents-900">Processos nesta fase</h3>
                    <p class="text-sm text-gray-600">
                        {{ activeStageLabel }} — {{ processes.length }} de {{ totalCount }} processo(s)
                    </p>
                </div>

                <ul v-if="processes.length" class="mt-4 divide-y divide-slate-100 rounded-xl border border-slate-200">
                    <li v-for="p in processes" :key="p.id" class="px-4 py-4">
                        <p class="font-semibold text-slate-900">{{ p.title }}</p>
                        <p class="text-sm text-slate-500">
                            {{ p.current_stage_label }}
                            <span v-if="p.updated_at"> · atualizado em {{ formatDate(p.updated_at) }}</span>
                        </p>
                    </li>
                </ul>
                <div
                    v-else
                    class="mt-4 rounded-xl border border-dashed border-talents-200 bg-talents-50/40 px-6 py-10 text-center"
                >
                    <p class="font-medium text-talents-900">Nenhum processo nesta fase</p>
                    <p class="mt-1 text-sm text-gray-600">
                        Quando a Talents avançar um processo, ele aparecerá aqui.
                    </p>
                </div>
            </div>

            <div v-if="all_processes.length" class="rounded-2xl border border-talents-200 bg-white p-4 shadow-sm sm:p-6">
                <h3 class="text-lg font-semibold text-talents-900">Visão geral</h3>
                <p class="mt-1 text-sm text-gray-600">Todos os processos e a fase atual de cada um.</p>
                <ul class="mt-4 space-y-4">
                    <li
                        v-for="p in all_processes"
                        :key="'all-' + p.id"
                        class="rounded-xl border border-slate-100 bg-slate-50/80 p-4"
                    >
                        <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                            <p class="font-semibold text-slate-900">{{ p.title }}</p>
                            <p class="text-sm text-talents-700">{{ p.current_stage_label }}</p>
                        </div>
                        <HiringProcessStepper
                            :stages="stages"
                            :current-stage="p.current_stage"
                            :stage-counts="{}"
                            :progress-stage="p.current_stage"
                            :interactive="false"
                        />
                    </li>
                </ul>
            </div>
        </div>
    </ClientLayout>
</template>
