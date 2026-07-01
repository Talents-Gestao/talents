<script setup>
defineProps({
    message: { type: String, required: true },
});
</script>

<template>
    <li class="px-4 py-10 text-center text-sm text-slate-500">
        {{ message }}
    </li>
</template>
