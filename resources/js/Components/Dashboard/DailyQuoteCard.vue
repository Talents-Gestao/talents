<script setup>
defineProps({
    quote: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <article class="dashboard-accent-dark relative overflow-hidden text-white">
        <div class="dashboard-hero-blob -left-8 -top-8 h-36 w-36 bg-talents-300/25" />
        <div class="dashboard-hero-blob right-0 top-0 h-28 w-28 translate-x-1/4 -translate-y-1/4 bg-violet-300/20" />

        <div class="relative flex gap-4 p-5 sm:gap-5 sm:p-6">
            <div
                class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-white/8 ring-1 ring-white/15 sm:h-16 sm:w-16"
                aria-hidden="true"
            >
                <img
                    src="/images/metamorfose-butterfly.png"
                    alt=""
                    class="h-10 w-10 object-contain mix-blend-screen sm:h-11 sm:w-11"
                />
            </div>

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-talents-100/80">Frase do dia</p>
                    <span class="text-[11px] text-talents-100/60">{{ quote.deck_title }}</span>
                </div>

                <h3 class="mt-2 text-lg font-bold uppercase tracking-wide text-white sm:text-xl">
                    {{ quote.word }}
                </h3>

                <blockquote class="mt-3 text-sm leading-relaxed text-talents-50/95 sm:text-base">
                    “{{ quote.phrase }}”
                </blockquote>

                <p class="mt-3 text-[11px] font-medium text-talents-100/65">
                    {{ quote.card_label }}
                </p>
            </div>
        </div>
    </article>
</template>
