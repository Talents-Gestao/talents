<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    href: {
        type: String,
        required: true,
    },
    method: {
        type: String,
        default: undefined,
    },
    as: {
        type: String,
        default: undefined,
    },
});
</script>

<template>
    <Link
        :href="href"
        :method="method"
        :as="as"
        class="block w-full px-4 py-2 text-start text-sm leading-5 text-zinc-700 transition duration-150 ease-in-out hover:bg-zinc-50 focus:bg-zinc-50 focus:outline-none"
    >
        <slot />
    </Link>
</template>
