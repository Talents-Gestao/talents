<script setup>
import { ArrowRightIcon, CalendarDaysIcon, ChevronDownIcon } from '@heroicons/vue/24/outline';
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import StatusChip from './StatusChip.vue';
import { campaignsLabel, highlightParts, initials, monogramColors } from './nr1CompanyPicker';

/**
 * Cartão da escolha de empresa: o cartão inteiro é o link (abre a campanha mais recente); «Outras campanhas» é um
 * disclosure separado (não fica dentro do link). Só dados de identificação da campanha, nenhum resultado.
 */
const props = defineProps({
    company: { type: Object, required: true },
    term: { type: String, default: '' },
});

const open = ref(false);
const parts = computed(() => highlightParts(props.company.name, props.term));
const monogram = computed(() => monogramColors(props.company.brandColor));
const latest = computed(() => props.company.latest);
const methodLabel = (method) => (method === 'v11' ? 'V11' : 'Modelo anterior');
const menuId = computed(() => `nr1-outras-${props.company.id}`);
</script>

<template>
    <article
        class="group relative flex h-full flex-col rounded-3xl border border-slate-200/80 bg-white p-5 shadow-sm transition-[transform,box-shadow,border-color] duration-200 ease-out hover:-translate-y-0.5 hover:border-slate-300/80 hover:shadow-md focus-within:border-slate-300 motion-reduce:transition-none motion-reduce:hover:translate-y-0"
    >
        <!-- Detalhe de acento pelo método (lilás V11, dourado modelo anterior). -->
        <span
            class="pointer-events-none absolute inset-x-6 top-0 h-[3px] rounded-b-full"
            :class="latest?.method === 'v11' ? 'bg-[#c9a3df]' : 'bg-[#ebc27a]'"
            aria-hidden="true"
        />

        <div class="flex items-start gap-3.5">
            <span
                class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl text-sm font-bold tracking-wide shadow-sm"
                :style="monogram"
                aria-hidden="true"
            >
                {{ initials(company.name) }}
            </span>
            <div class="min-w-0 flex-1">
                <h3 class="text-[15px] font-semibold leading-snug text-slate-900">
                    <Link
                        :href="company.href"
                        class="rounded-md outline-none after:absolute after:inset-0 after:rounded-3xl after:content-[''] focus-visible:after:ring-2 focus-visible:after:ring-primary-ring focus-visible:after:ring-offset-2"
                        :aria-label="`Abrir o painel NR-1 de ${company.name}`"
                    >
                        <template v-for="(part, index) in parts" :key="index">
                            <mark v-if="part.match" class="rounded bg-[#fcf0db] px-0.5 text-slate-900">{{ part.text }}</mark>
                            <template v-else>{{ part.text }}</template>
                        </template>
                    </Link>
                </h3>
                <p class="mt-0.5 text-xs text-slate-600">{{ campaignsLabel(company.campaigns) }}</p>
            </div>
            <ArrowRightIcon
                class="mt-1 h-5 w-5 shrink-0 text-slate-400 transition-transform duration-200 group-hover:translate-x-1 group-hover:text-primary-text motion-reduce:transition-none motion-reduce:group-hover:translate-x-0"
                aria-hidden="true"
            />
        </div>

        <div v-if="latest" class="nr1-shell mt-4 flex flex-wrap items-center gap-2">
            <StatusChip :label="latest.status.label" :tone="latest.status.tone" />
            <span
                class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-bold ring-1"
                :class="latest.method === 'v11' ? 'bg-[#f3eaf9] text-[#5b2a73] ring-[#e3d3ee]' : 'bg-[#fcf0db] text-[#7a4a08] ring-[#f0d9a8]'"
            >
                {{ methodLabel(latest.method) }}
            </span>
        </div>

        <div v-if="latest" class="mt-3 border-t border-slate-100 pt-3 text-xs text-slate-600">
            <p class="truncate font-medium text-slate-800" :title="latest.title">{{ latest.title }}</p>
            <p v-if="latest.period" class="mt-0.5 flex items-center gap-1.5">
                <CalendarDaysIcon class="h-3.5 w-3.5 shrink-0 text-slate-400" aria-hidden="true" />
                {{ latest.period }}
            </p>
        </div>

        <div v-if="company.others?.length" class="relative z-10 mt-auto pt-3">
            <button
                type="button"
                class="inline-flex items-center gap-1 rounded-lg px-1.5 py-1 text-xs font-semibold text-primary-text hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-ring"
                :aria-expanded="open"
                :aria-controls="menuId"
                @click="open = !open"
                @keydown.esc="open = false"
            >
                Outras campanhas ({{ company.others.length }})
                <ChevronDownIcon class="h-3.5 w-3.5 transition-transform motion-reduce:transition-none" :class="open ? 'rotate-180' : ''" aria-hidden="true" />
            </button>
            <ul v-if="open" :id="menuId" class="mt-1.5 space-y-0.5" @keydown.esc="open = false">
                <li v-for="campaign in company.others" :key="campaign.id">
                    <Link
                        :href="campaign.href"
                        class="flex items-center justify-between gap-2 rounded-lg px-2 py-1.5 text-xs text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-ring"
                    >
                        <span class="truncate">{{ campaign.title }}</span>
                        <span class="shrink-0 text-[11px] text-slate-500">{{ methodLabel(campaign.method) }}</span>
                    </Link>
                </li>
            </ul>
        </div>
    </article>
</template>
