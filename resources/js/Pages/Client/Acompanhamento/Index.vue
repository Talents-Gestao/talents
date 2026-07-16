<script setup>
import HiringProcessStepper from '@/Components/HiringProcessStepper.vue';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { BriefcaseIcon, CalendarDaysIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    stages: { type: Array, required: true },
    active_stage: { type: String, required: true },
    stage_counts: { type: Object, required: true },
    columns: { type: Array, required: true },
    company_name: { type: String, required: true },
});

const currentStage = ref(props.active_stage);
watch(
    () => props.active_stage,
    (v) => {
        currentStage.value = v;
    },
);

const totalCount = computed(() =>
    Object.values(props.stage_counts || {}).reduce((sum, n) => sum + (Number(n) || 0), 0),
);

const onStageChange = (stage) => {
    currentStage.value = stage;
    document.getElementById(`stage-col-${stage}`)?.scrollIntoView({
        behavior: 'smooth',
        inline: 'center',
        block: 'nearest',
    });
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
                            {{ totalCount }} processo(s) · visão somente leitura
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
                    <div class="flex items-start gap-3 overflow-x-auto pb-2">
                        <div
                            v-for="col in columns"
                            :id="`stage-col-${col.value}`"
                            :key="col.value"
                            class="flex w-72 shrink-0 flex-col rounded-2xl bg-slate-100/80 p-2.5 ring-1 ring-slate-200/80"
                            :class="currentStage === col.value ? 'ring-2 ring-talents-300 bg-talents-50/50' : ''"
                        >
                            <header class="mb-2 flex items-start justify-between gap-2 px-1.5 pt-1">
                                <div class="min-w-0">
                                    <h3 class="truncate text-sm font-semibold text-slate-900" :title="col.label">
                                        {{ col.label }}
                                    </h3>
                                    <p class="mt-0.5 text-[11px] font-medium tabular-nums text-slate-500">
                                        {{ col.processes.length }} processo(s)
                                    </p>
                                </div>
                            </header>

                            <div class="flex min-h-[12rem] flex-1 flex-col gap-2">
                                <div
                                    v-for="p in col.processes"
                                    :key="p.id"
                                    class="rounded-xl bg-white p-3 shadow-sm ring-1 ring-slate-200"
                                >
                                    <div class="flex items-start gap-2">
                                        <span
                                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-talents-100 text-talents-700"
                                        >
                                            <BriefcaseIcon class="h-4 w-4" />
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-semibold leading-snug text-slate-900">{{ p.title }}</p>
                                            <p
                                                v-if="p.updated_at"
                                                class="mt-1.5 flex items-center gap-1 text-[11px] text-slate-500"
                                            >
                                                <CalendarDaysIcon class="h-3 w-3" />
                                                {{ formatDate(p.updated_at) }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <p
                                    v-if="!col.processes.length"
                                    class="rounded-xl border border-dashed border-slate-300 px-2 py-10 text-center text-xs text-slate-400"
                                >
                                    Nenhum processo
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </ClientLayout>
</template>
