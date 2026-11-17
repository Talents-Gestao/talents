<script setup>
/** Estado vazio de Feedbacks: ícone, o que falta e, no slot, a ação que resolve. */
defineProps({
    icon: { type: [Object, Function], default: null },
    title: { type: String, required: true },
    description: { type: String, default: '' },
});
</script>

<template>
    <div class="rounded-2xl border border-dashed border-talents-200 bg-talents-50/40 px-6 py-10 text-center">
        <component :is="icon" v-if="icon" class="mx-auto h-10 w-10 text-talents-300" aria-hidden="true" />
        <p class="mt-3 text-sm font-semibold text-talents-900">{{ title }}</p>
        <p v-if="description" class="mx-auto mt-1 max-w-md text-pretty text-sm text-slate-600">{{ description }}</p>
        <div v-if="$slots.default" class="mt-5 flex flex-wrap justify-center gap-2">
            <slot />
        </div>
    </div>
</template>
