<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    pillars: {
        type: Array,
        required: true,
    },
});

const selectedIndex = ref(null);

const selectedPillar = computed(() => {
    if (selectedIndex.value === null) {
        return null;
    }

    return props.pillars[selectedIndex.value] ?? null;
});

const isOpen = computed(() => selectedPillar.value !== null);

function toggleWing(index) {
    selectedIndex.value = selectedIndex.value === index ? null : index;
}

function closePanel() {
    selectedIndex.value = null;
}
</script>

<template>
    <div
        class="flex flex-col gap-6 lg:flex-row lg:items-start lg:gap-6"
        :class="isOpen ? 'lg:justify-start' : 'lg:justify-center'"
    >
        <div
            class="mx-auto w-full transition-[max-width] duration-300 ease-out"
            :class="isOpen ? 'max-w-full lg:max-w-[56%]' : 'max-w-4xl'"
        >
            <figure class="butterfly-split relative">
                <img
                    src="/images/pilares-borboleta.png?v=20260731d"
                    alt="Infográfico dos 4 pilares Talents em formato de borboleta — clique numa asa para detalhar"
                    width="1920"
                    height="1080"
                    class="block h-auto w-full object-contain"
                    loading="lazy"
                    decoding="async"
                />

                <div
                    class="absolute inset-0 grid grid-cols-2 grid-rows-2"
                    role="group"
                    aria-label="Asas dos pilares da metodologia"
                >
                    <button
                        v-for="(pillar, index) in pillars"
                        :key="pillar.title"
                        type="button"
                        class="wing-hotspot cursor-pointer border-0 bg-transparent p-0 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-talents-500"
                        :aria-pressed="selectedIndex === index"
                        :aria-label="`Abrir pilar: ${pillar.title}`"
                        @click="toggleWing(index)"
                    />
                </div>
            </figure>

            <p class="mt-3 text-center text-sm text-slate-500">
                Clique numa asa para ver o detalhe do pilar. Clique de novo para fechar.
            </p>
        </div>

        <aside
            class="w-full overflow-hidden transition-all duration-300 ease-out lg:self-stretch"
            :class="
                isOpen
                    ? 'max-h-[48rem] opacity-100 lg:max-w-[44%] lg:flex-1'
                    : 'pointer-events-none max-h-0 opacity-0 lg:max-h-none lg:max-w-0 lg:flex-none lg:opacity-0'
            "
            :aria-hidden="!isOpen"
        >
            <article
                v-if="selectedPillar"
                class="relative flex h-full min-h-[16rem] flex-col overflow-hidden rounded-3xl border border-slate-200/80 bg-gradient-to-br p-5 shadow-sm md:p-6"
                :class="selectedPillar.accent"
            >
                <span
                    class="absolute inset-x-0 top-0 h-1 opacity-90"
                    :class="selectedPillar.bar"
                    aria-hidden="true"
                />

                <div class="flex items-start justify-between gap-3">
                    <div
                        class="inline-flex rounded-2xl p-2.5 ring-1"
                        :class="selectedPillar.iconWrap"
                    >
                        <component :is="selectedPillar.icon" class="h-5 w-5" aria-hidden="true" />
                    </div>
                    <button
                        type="button"
                        class="rounded-lg px-2 py-1 text-xs font-semibold text-slate-500 transition hover:bg-white/70 hover:text-slate-800"
                        @click="closePanel"
                    >
                        Fechar
                    </button>
                </div>

                <h3 class="mt-4 text-base font-bold leading-snug text-slate-900 md:text-lg">
                    {{ selectedPillar.title }}
                </h3>

                <ul class="mt-4 flex-1 space-y-2.5 text-sm leading-snug text-slate-600">
                    <li
                        v-for="item in selectedPillar.items"
                        :key="item"
                        class="flex items-start gap-2.5"
                    >
                        <span
                            class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full"
                            :class="selectedPillar.bullet"
                            aria-hidden="true"
                        />
                        <span>{{ item }}</span>
                    </li>
                </ul>

                <div
                    class="mt-5 rounded-2xl px-3 py-2.5 text-xs leading-relaxed ring-1"
                    :class="selectedPillar.chip"
                >
                    <span class="font-bold uppercase tracking-wide">Objetivo</span>
                    <p class="mt-1 font-medium normal-case tracking-normal">
                        {{ selectedPillar.objective }}
                    </p>
                </div>
            </article>
        </aside>
    </div>
</template>

<style scoped>
/* Paredes invisíveis nas 4 asas — só para clique. */
.butterfly-split::before,
.butterfly-split::after {
    content: '';
    position: absolute;
    z-index: 1;
    pointer-events: none;
    background: transparent;
}

.butterfly-split::before {
    top: 0;
    bottom: 0;
    left: 50%;
    width: 0;
}

.butterfly-split::after {
    left: 0;
    right: 0;
    top: 50%;
    height: 0;
}

.wing-hotspot {
    appearance: none;
    background: transparent;
}

.wing-hotspot:hover,
.wing-hotspot:focus,
.wing-hotspot:active,
.wing-hotspot[aria-pressed='true'] {
    background: transparent;
    box-shadow: none;
}
</style>
