<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    href: {
        type: String,
        required: true,
    },
    active: {
        type: Boolean,
    },
});

const classes = computed(() =>
    props.active
        ? 'block w-full border-l-4 border-talents-600 bg-talents-50/90 py-2 pe-4 ps-3 text-start text-base font-medium text-talents-900 transition duration-150 ease-in-out focus:bg-talents-50 focus:outline-none focus:text-talents-950'
        : 'block w-full border-l-4 border-transparent py-2 pe-4 ps-3 text-start text-base font-medium text-zinc-600 transition duration-150 ease-in-out hover:border-zinc-300 hover:bg-zinc-50 hover:text-zinc-900 focus:bg-zinc-50 focus:outline-none focus:text-zinc-900',
);
</script>

<template>
    <Link :href="href" :class="classes">
        <slot />
    </Link>
</template>
