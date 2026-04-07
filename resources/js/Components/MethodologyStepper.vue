<script setup>
/**
 * Stepper horizontal — Metodologia Talents (5 etapas).
 * Icones estilo Heroicons (outline), cores palette talents.
 */
defineProps({
    modelValue: { type: Number, default: 2 },
    interactive: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue']);

const steps = [
    {
        id: 1,
        label: 'Diagnóstico',
        available: false,
        title: 'Em desenvolvimento',
    },
    {
        id: 2,
        label: 'Pesquisa Satisfação',
        available: true,
        title: 'Acesse as pesquisas de satisfação',
    },
    {
        id: 3,
        label: 'Mapeamento Psicológico',
        available: false,
        title: 'Em desenvolvimento',
    },
    {
        id: 4,
        label: 'Mapeamento COP',
        available: false,
        title: 'Em desenvolvimento',
    },
    {
        id: 5,
        label: 'Direcionamento Estratégico',
        available: false,
        title: 'Em desenvolvimento',
    },
];

const iconPaths = {
    1: 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z',
    2: 'M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z',
    3: 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z',
    4: 'M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z',
    5: 'M15.59 14.37a6 6 0 01-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 006.16-12.12A14.98 14.98 0 009.631 8.41m5.96 5.96a14.926 14.926 0 01-5.856 2.65m.119-3.44a48.39 48.39 0 00-8.803-4.107 48.005 48.005 0 00-8.734 4.842m18.063-2.61a48.29 48.29 0 00-8.09 5.34M6.31 6.31A48.29 48.29 0 018.13 4.031m-4.01 9.349a48.005 48.005 0 004.108 8.41',
};

const onStepClick = (step, interactive) => {
    if (!interactive) {
        return;
    }
    emit('update:modelValue', step.id);
};
</script>

<template>
    <!-- padding vertical amplo: overflow-x-auto força overflow-y a cortar sombras/anel sem espaço extra -->
    <div class="w-full overflow-x-auto px-2 py-5 sm:py-6 sm:px-3">
        <div class="flex min-w-[640px] items-start justify-center gap-1 sm:min-w-0 sm:gap-2">
            <template v-for="(step, index) in steps" :key="step.id">
                <div class="flex flex-1 flex-col items-center">
                    <button
                        type="button"
                        :disabled="!interactive"
                        class="group flex flex-col items-center rounded-full px-1 pb-1 pt-2 focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500 focus-visible:ring-offset-2"
                        :class="interactive ? 'cursor-pointer' : 'cursor-default'"
                        :title="!step.available ? 'Em desenvolvimento' : step.label"
                        @click="onStepClick(step, interactive)"
                    >
                        <!-- ring sem ring-offset: evita desenhar fora da caixa e ser cortado pelo overflow -->
                        <div
                            class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full transition-all duration-200"
                            :class="[
                                modelValue === step.id && step.available ? 'ring-2 ring-white/90' : '',
                                step.available
                                    ? 'bg-talents-600 text-white shadow-lg shadow-talents-300/40 group-hover:scale-105 group-hover:shadow-xl group-hover:shadow-talents-400/35'
                                    : 'border-2 border-dashed border-talents-300 bg-talents-100 text-talents-400',
                            ]"
                        >
                            <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" :d="iconPaths[step.id]" />
                            </svg>
                        </div>
                        <span
                            class="mt-2 max-w-[100px] text-center text-xs font-medium leading-tight sm:max-w-none sm:text-sm"
                            :class="step.available ? 'text-talents-900' : 'text-gray-400'"
                        >
                            {{ String(step.id).padStart(2, '0') }} {{ step.label }}
                        </span>
                        <span
                            v-if="!step.available"
                            class="mt-1 rounded bg-amber-100 px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide text-amber-900 sm:text-[10px]"
                        >
                            Em breve
                        </span>
                    </button>
                </div>
                <div
                    v-if="index < steps.length - 1"
                    class="flex shrink-0 items-center self-center pb-8 text-talents-300 sm:pb-10"
                    aria-hidden="true"
                >
                    <span class="text-lg font-light sm:text-xl">→</span>
                </div>
            </template>
        </div>
    </div>
</template>
