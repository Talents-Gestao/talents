<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    portal: {
        type: Object,
        default: null,
    },
});
</script>

<template>
    <div class="app-shell flex min-h-screen flex-col items-center px-4 pb-10 pt-8 sm:justify-center sm:px-6 sm:pb-12 sm:pt-6">
        <div>
            <Link v-if="!portal" href="/">
                <ApplicationLogo class="h-20 max-w-[280px] sm:h-24 sm:max-w-[320px]" />
            </Link>
            <div v-else class="text-center">
                <img
                    v-if="portal.logo_url"
                    :src="portal.logo_url"
                    :alt="portal.name"
                    class="mx-auto h-36 w-auto max-w-[min(100%,28rem)] object-contain sm:h-44 sm:max-w-[32rem]"
                />
                <ApplicationLogo v-else class="mx-auto h-20 max-w-[280px] sm:h-24 sm:max-w-[320px]" />
                <p
                    class="mt-3 text-lg font-semibold text-slate-800"
                    :style="portal.primary_color ? { color: portal.primary_color } : undefined"
                >
                    {{ portal.name }}
                </p>
            </div>
        </div>

        <div class="surface-glass mt-8 w-full max-w-md overflow-hidden px-6 py-8 shadow-shell sm:mt-10 sm:px-8 sm:py-10">
            <slot />
        </div>
    </div>
</template>
