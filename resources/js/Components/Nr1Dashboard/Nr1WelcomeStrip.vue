<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    planUrl: { type: String, default: null },
    printUrl: { type: String, default: null },
});
</script>

<template>
    <section
        class="relative overflow-hidden rounded-xl bg-gradient-to-r from-[#51256b] via-[#7d3b94] to-[#9c599f] px-6 py-7 text-white shadow-sm"
        aria-labelledby="nr1-welcome-title"
    >
        <p class="text-xs font-semibold uppercase tracking-wide text-[#e4c7ef]">Inteligência de pessoas • NR-1</p>
        <h2 id="nr1-welcome-title" class="mt-2 text-3xl font-bold leading-tight">
            Enxergue o que importa.<br />Cuide do que faz diferença.
        </h2>
        <p class="mt-2 max-w-2xl text-sm leading-relaxed text-[#f2e1fa]">
            Resultados objetivos, prioridades claras e um plano de ação conectado às necessidades da empresa.
        </p>
        <div class="mt-5 flex flex-wrap gap-3 print:hidden">
            <Link v-if="planUrl" :href="planUrl" class="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-[#66347b] focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-[#7d3b94]">
                Acompanhar ações →
            </Link>
            <a
                v-if="printUrl"
                :href="printUrl"
                target="_blank"
                rel="noopener"
                class="rounded-lg border border-white/40 bg-white/15 px-4 py-2 text-sm font-semibold text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
            >
                Imprimir panorama ↓
            </a>
        </div>
    </section>
</template>
