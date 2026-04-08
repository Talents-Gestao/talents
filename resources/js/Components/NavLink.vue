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
        ? 'inline-flex items-center border-b-2 border-talents-600 px-1 pt-1 text-sm font-medium leading-5 text-zinc-900 focus:outline-none focus:border-talents-700 transition duration-150 ease-in-out'
        : 'inline-flex items-center border-b-2 border-transparent px-1 pt-1 text-sm font-medium leading-5 text-zinc-500 transition duration-150 ease-in-out hover:border-zinc-300 hover:text-zinc-800 focus:outline-none focus:border-zinc-300 focus:text-zinc-800',
);
</script>

<template>
    <Link :href="href" :class="classes">
        <slot />
    </Link>
</template>
