<script setup>
defineProps({
    quote: {
        type: Object,
        required: true,
    },
    dateLabel: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <article
        class="dashboard-accent-dark relative overflow-hidden !px-6 !py-4 text-white shadow-sm !shadow-md sm:!px-7 sm:!py-4.5"
    >
        <div class="dashboard-hero-blob -left-8 -top-10 h-24 w-24 bg-talents-300/20" />
        <div class="dashboard-hero-blob -right-6 -bottom-8 h-20 w-20 bg-violet-300/15" />

        <div class="relative flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-6">
            <div class="flex min-w-0 items-center gap-3">
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/20"
                    aria-hidden="true"
                >
                    <img
                        src="/images/metamorfose-butterfly.png"
                        alt=""
                        class="h-6 w-6 object-contain mix-blend-screen"
                    />
                </div>
                <div class="min-w-0">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-talents-100/75">
                        Frase do dia
                    </p>
                    <h3 class="mt-0.5 text-base font-bold uppercase tracking-wide text-white sm:text-lg">
                        {{ quote.word }}
                    </h3>
                    <blockquote class="mt-0.5 text-[13px] leading-snug text-talents-50/90 sm:text-sm">
                        “{{ quote.phrase }}”
                    </blockquote>
                </div>
            </div>

            <p
                v-if="dateLabel"
                class="flex shrink-0 items-center gap-2 text-xs font-medium text-talents-100/80 sm:text-sm"
            >
                <svg class="h-4 w-4 shrink-0 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M6.75 3v2.25M17.25 3v2.25M3.75 8.25h16.5M5.25 5.25h13.5A1.5 1.5 0 0120.25 6.75v12.75A1.5 1.5 0 0118.75 21H5.25a1.5 1.5 0 01-1.5-1.5V6.75a1.5 1.5 0 011.5-1.5z"
                    />
                </svg>
                {{ dateLabel }}
            </p>
        </div>
    </article>
</template>
