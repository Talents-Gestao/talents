<script setup>
import { computed, nextTick, ref } from 'vue';
import { PILARES_INTRO } from './landingContent.js';
import { onTablistKeydown, useReveal } from './useLandingMotion.js';
import './landingGlass.css';

/**
 * Pilares em bento: infográfico da borboleta (asas clicáveis para o mouse) + painel de detalhe.
 * O controle acessível é o tablist de pilares; as asas são um atalho visual (aria-hidden, fora da ordem de foco).
 */
const props = defineProps({
    pillars: { type: Array, required: true },
});

const root = ref(null);
const tabs = ref([]);
const featuredIndex = Math.max(0, props.pillars.findIndex((p) => p.featured));
const active = ref(featuredIndex);
const selected = computed(() => props.pillars[active.value]);

useReveal(root);

const select = async (i, focus = false) => {
    active.value = i;
    if (focus) {
        await nextTick();
        tabs.value[i]?.focus();
    }
};
</script>

<template>
    <section ref="root" class="lg-stage scroll-mt-24 py-14 md:py-20" aria-labelledby="pilares-title">
        <div class="mx-auto max-w-6xl px-4">
            <div class="lg-reveal max-w-3xl">
                <p class="text-sm font-semibold text-talents-600">{{ PILARES_INTRO.label }}</p>
                <h2
                    id="pilares-title"
                    class="mt-2 text-3xl font-bold leading-tight tracking-tight text-talents-900 md:text-5xl"
                >
                    {{ PILARES_INTRO.title }}
                </h2>
                <p class="mt-4 text-lg font-medium text-talents-800">{{ PILARES_INTRO.lead }}</p>
                <p class="mt-3 text-base leading-relaxed text-slate-700">{{ PILARES_INTRO.text }}</p>
            </div>

            <div class="mt-10 grid gap-6 lg:grid-cols-12">
                <figure class="lg-glass lg-reveal relative flex flex-col justify-center overflow-hidden p-3 md:p-5 lg:col-span-7">
                    <div class="crop relative">
                        <img
                            src="/images/pilares-borboleta.png?v=20260804g"
                            alt="Infográfico dos 4 pilares Talents em formato de borboleta"
                            width="1920"
                            height="1080"
                            loading="lazy"
                            decoding="async"
                        />
                        <div class="absolute inset-0 grid grid-cols-2 grid-rows-2" aria-hidden="true">
                            <button
                                v-for="(p, i) in pillars"
                                :key="p.title"
                                type="button"
                                tabindex="-1"
                                class="wing"
                                @click="select(i)"
                            />
                        </div>
                    </div>
                    <figcaption class="mt-2 px-2 text-center text-sm text-slate-600">
                        Escolha um pilar para ver o detalhe.
                    </figcaption>
                </figure>

                <div class="flex flex-col gap-4 lg:col-span-5">
                    <div
                        role="tablist"
                        aria-label="Pilares da metodologia"
                        class="lg-reveal grid grid-cols-2 gap-2"
                    >
                        <button
                            v-for="(p, i) in pillars"
                            :id="`pilar-tab-${i}`"
                            :key="p.title"
                            :ref="(el) => (tabs[i] = el)"
                            type="button"
                            role="tab"
                            class="lg-tab lg-glass lg-glass--soft lg-lift"
                            :aria-selected="active === i"
                            aria-controls="pilar-panel"
                            :tabindex="active === i ? 0 : -1"
                            @click="select(i)"
                            @keydown="onTablistKeydown($event, pillars.length, active, (n) => select(n, true))"
                        >
                            <component :is="p.icon" class="h-5 w-5 shrink-0" aria-hidden="true" />
                            <span>{{ p.title }}</span>
                        </button>
                    </div>

                    <article
                        id="pilar-panel"
                        role="tabpanel"
                        :aria-labelledby="`pilar-tab-${active}`"
                        tabindex="0"
                        class="lg-glass lg-reveal flex-1 p-6 md:p-8"
                    >
                        <div :key="active" class="pilar-detail">
                            <div class="flex flex-wrap items-center gap-3">
                                <h3 class="text-2xl font-bold leading-snug text-talents-900">{{ selected.title }}</h3>
                                <span
                                    v-if="selected.featured"
                                    class="rounded-full bg-talents-600 px-3 py-1 text-xs font-semibold text-white"
                                >
                                    Pilar central
                                </span>
                            </div>
                            <ul class="mt-5 grid gap-2.5 text-base text-slate-700">
                                <li v-for="item in selected.items" :key="item" class="flex items-start gap-3">
                                    <span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-talents-500" aria-hidden="true" />
                                    <span>{{ item }}</span>
                                </li>
                            </ul>
                            <div class="lg-glass lg-glass--soft mt-6 p-4">
                                <h4 class="text-sm font-semibold text-talents-700">Objetivo</h4>
                                <p class="mt-1 text-base font-medium leading-relaxed text-talents-900">
                                    {{ selected.objective }}
                                </p>
                            </div>
                        </div>
                    </article>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.lg-tab {
    display: flex;
    min-height: 3.25rem;
    align-items: center;
    gap: 0.6rem;
    padding: 0.6rem 0.9rem;
    text-align: left;
    font-size: 0.9rem;
    font-weight: 600;
    line-height: 1.25;
    color: #4a2070;
}

.lg-tab[aria-selected='true'] {
    background: #632a7e;
    color: #fff;
}

.crop {
    aspect-ratio: 1.2 / 1;
    overflow: hidden;
}

/* O PNG tem margem transparente nas laterais: recorte horizontal centralizado, altura total. */
.crop img {
    position: absolute;
    left: 50%;
    top: 0;
    height: 100%;
    width: auto;
    max-width: none;
    transform: translateX(-50%);
}

.wing {
    background: transparent;
    border: 0;
    cursor: pointer;
}

.pilar-detail {
    animation: pilar-in 0.4s ease both;
}

@keyframes pilar-in {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
}

@media (prefers-reduced-motion: reduce) {
    .pilar-detail {
        animation: none;
    }
}

#pilar-panel:focus-visible {
    outline: 2px solid #7b4fa2;
    outline-offset: 3px;
}

@media (min-width: 1024px) {
    #pilar-panel {
        min-height: 22rem;
    }
}
</style>
