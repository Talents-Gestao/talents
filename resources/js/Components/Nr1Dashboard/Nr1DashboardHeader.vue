<script setup>
defineProps({
    header: { type: Object, required: true },
});

const statusClasses = {
    collecting: 'bg-emerald-50 text-emerald-800 border-emerald-200',
    scheduled: 'bg-sky-50 text-sky-800 border-sky-200',
    closed: 'bg-gray-100 text-gray-800 border-gray-300',
    inactive: 'bg-gray-100 text-gray-700 border-gray-300',
};
</script>

<template>
    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="nr1-header-title">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Pesquisa psicossocial · NR-1</p>
                <h1 id="nr1-header-title" class="mt-1 text-2xl font-bold text-talents-900">
                    {{ header.company_name ?? 'Empresa' }}
                </h1>
                <p v-if="header.unit_name" class="text-sm text-gray-600">Estabelecimento: {{ header.unit_name }}</p>
                <p class="mt-1 text-sm text-gray-700">
                    {{ header.campaign_title }}
                    <span v-if="header.period_label" class="text-gray-500"> · {{ header.period_label }}</span>
                </p>
            </div>
            <span
                class="inline-flex shrink-0 rounded-md border px-2.5 py-1 text-xs font-semibold"
                :class="statusClasses[header.status.code] ?? statusClasses.inactive"
            >
                {{ header.status.label }}
            </span>
        </div>
    </section>
</template>
