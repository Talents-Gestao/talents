<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center bg-zinc-50 px-4 pt-8 sm:justify-center sm:px-6 sm:pt-0"
    >
        <div>
            <Link href="/">
                <ApplicationLogo class="h-16 w-16 fill-current text-zinc-400 sm:h-20 sm:w-20" />
            </Link>
        </div>

        <div
            class="mt-8 w-full max-w-md overflow-hidden rounded-2xl border border-zinc-200/90 bg-white px-6 py-8 sm:mt-10"
        >
            <slot />
        </div>
    </div>
</template>
