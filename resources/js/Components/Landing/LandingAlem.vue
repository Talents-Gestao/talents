<script setup>
import { ArrowRightIcon } from '@heroicons/vue/24/outline';
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { ALEM_INTRO, ALEM_ROUTES } from './landingContent.js';
import { useReveal } from './useLandingMotion.js';
import './landingGlass.css';

/**
 * Além de contratar: bento assimétrico dos 6 cartões existentes. NR-1 em destaque; cada cartão inteiro é um
 * link (stretched link no título) para a página correspondente. Ícones e textos são os atuais.
 */
const props = defineProps({
    items: { type: Array, required: true },
});

const root = ref(null);
useReveal(root);

const routeOf = (item) => ALEM_ROUTES[item.title] ?? 'landing.empresas';
const isFeatured = (item) => item.title === ALEM_INTRO.featured;
const featured = props.items.find(isFeatured);
const others = props.items.filter((i) => !isFeatured(i));
</script>

<template>
    <section ref="root" class="lg-stage scroll-mt-24 py-16 md:py-24" aria-labelledby="alem-title">
        <div class="mx-auto max-w-6xl px-4">
            <div class="lg-reveal grid gap-4 lg:grid-cols-12 lg:items-end">
                <div class="lg:col-span-7">
                    <p class="text-sm font-semibold text-talents-600">{{ ALEM_INTRO.label }}</p>
                    <h2
                        id="alem-title"
                        class="mt-2 text-3xl font-bold leading-tight tracking-tight text-talents-900 md:text-5xl"
                    >
                        {{ ALEM_INTRO.title }}
                    </h2>
                </div>
                <p class="text-base leading-relaxed text-slate-700 md:text-lg lg:col-span-5">{{ ALEM_INTRO.text }}</p>
            </div>

            <ul class="mt-10 grid gap-4 md:grid-cols-2 lg:grid-cols-12 lg:gap-5">
                <li v-if="featured" class="lg-card lg-card--featured lg-reveal lg:col-span-6 lg:row-span-2">
                    <span class="lg-card__icon lg-card__icon--on">
                        <component :is="featured.icon" class="h-7 w-7" aria-hidden="true" />
                    </span>
                    <h3 class="mt-8 text-3xl font-bold leading-tight tracking-tight lg:text-4xl">
                        <Link :href="route(routeOf(featured))" class="lg-stretch">{{ featured.title }}</Link>
                    </h3>
                    <p class="mt-4 max-w-md text-lg leading-relaxed text-talents-50">{{ featured.description }}</p>
                    <span class="mt-auto inline-flex items-center gap-2 pt-8 text-base font-semibold" aria-hidden="true">
                        Conhecer solução NR-1
                        <ArrowRightIcon class="h-5 w-5" />
                    </span>
                </li>

                <li
                    v-for="(item, i) in others"
                    :key="item.title"
                    class="lg-card lg-glass lg-reveal lg-lift"
                    :class="i === others.length - 1 ? 'md:col-span-2 lg:col-span-12' : 'lg:col-span-3'"
                >
                    <span class="lg-card__icon">
                        <component :is="item.icon" class="h-6 w-6" aria-hidden="true" />
                    </span>
                    <h3 class="mt-5 text-xl font-semibold text-talents-900">
                        <Link :href="route(routeOf(item))" class="lg-stretch">{{ item.title }}</Link>
                    </h3>
                    <p class="mt-2 text-base leading-relaxed text-slate-700">{{ item.description }}</p>
                </li>
            </ul>
        </div>
    </section>
</template>

<style scoped>
.lg-card {
    position: relative;
    display: flex;
    flex-direction: column;
    padding: 1.6rem;
}

.lg-card--featured {
    border-radius: 1.5rem;
    padding: 2rem;
    color: #fff;
    background:
        radial-gradient(70% 60% at 90% 0%, rgba(179, 136, 217, 0.5), transparent 70%),
        linear-gradient(160deg, #4a2070 0%, #2a1042 100%);
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.25),
        0 30px 60px -30px rgba(42, 16, 66, 0.7);
    transition: transform 0.25s ease;
}

@media (hover: hover) {
    .lg-card--featured:hover {
        transform: translateY(-3px);
    }
}

.lg-card__icon {
    display: inline-flex;
    height: 3rem;
    width: 3rem;
    align-items: center;
    justify-content: center;
    border-radius: 1rem;
    background: rgba(179, 136, 217, 0.22);
    color: #632a7e;
}

.lg-card__icon--on {
    height: 3.5rem;
    width: 3.5rem;
    background: rgba(255, 255, 255, 0.16);
    color: #fff;
}

/* O link do título cobre o cartão inteiro (área de clique única, nome acessível = título). */
.lg-stretch::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 1.5rem;
}

.lg-stretch:focus-visible {
    outline: none;
}

.lg-stretch:focus-visible::after {
    outline: 3px solid #7b4fa2;
    outline-offset: 3px;
}

.lg-card--featured .lg-stretch:focus-visible::after {
    outline-color: #d4b8e4;
}
</style>
