<script setup>
/**
 * "Frase do dia" da Home do admin. Variante com mais presença do DailyQuoteCard, que continua
 * intacto porque o ClientLayout também o usa.
 */
defineProps({
    quote: { type: Object, required: true },
    dateLabel: { type: String, default: '' },
});
</script>

<template>
    <article class="home-quote" aria-label="Frase do dia">
        <span class="home-quote__aurora" aria-hidden="true" />

        <div class="home-quote__body">
            <span class="home-quote__mark" aria-hidden="true">
                <img src="/images/metamorfose-butterfly.png" alt="" class="home-quote__butterfly" />
            </span>
            <div class="min-w-0">
                <p class="home-quote__eyebrow">Frase do dia</p>
                <h3 class="home-quote__word">{{ quote.word }}</h3>
                <blockquote class="home-quote__phrase">“{{ quote.phrase }}”</blockquote>
            </div>
        </div>

        <p v-if="dateLabel" class="home-quote__date">
            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M6.75 3v2.25M17.25 3v2.25M3.75 8.25h16.5M5.25 5.25h13.5A1.5 1.5 0 0120.25 6.75v12.75A1.5 1.5 0 0118.75 21H5.25a1.5 1.5 0 01-1.5-1.5V6.75a1.5 1.5 0 011.5-1.5z"
                />
            </svg>
            {{ dateLabel }}
        </p>
    </article>
</template>
