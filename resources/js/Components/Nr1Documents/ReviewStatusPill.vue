<script setup>
defineProps({
    status: { type: String, required: true },
    label: { type: String, required: true },
});

const CLASSES = {
    draft: 'bg-gray-100 text-gray-800 border-gray-300',
    in_review: 'bg-amber-50 text-amber-800 border-amber-200',
    changes_requested: 'bg-red-50 text-red-800 border-red-200',
    approved: 'bg-sky-50 text-sky-800 border-sky-200',
    published: 'bg-emerald-50 text-emerald-800 border-emerald-200',
};
</script>

<template>
    <span class="inline-flex rounded-md border px-2.5 py-1 text-xs font-semibold" :class="CLASSES[status] ?? CLASSES.draft">{{ label }}</span>
</template>
