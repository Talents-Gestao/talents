<script setup>
defineProps({
    orientation: { type: Object, required: true },
});
</script>

<template>
    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="orientation-title">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 id="orientation-title" class="text-base font-semibold text-talents-900">{{ orientation.title }}</h2>
            <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">{{ orientation.availability }}</span>
        </div>
        <p v-for="(paragraph, index) in orientation.paragraphs" :key="index" class="mt-2 text-sm text-gray-700">{{ paragraph }}</p>
    </section>
</template>
