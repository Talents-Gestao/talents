<script setup>
import {
    ArrowTrendingUpIcon,
    BuildingOffice2Icon,
    ChartBarIcon,
    HeartIcon,
    ShieldCheckIcon,
    SparklesIcon,
    UserGroupIcon,
} from '@heroicons/vue/24/outline';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import LandingLayout from '@/Components/Landing/LandingLayout.vue';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
});

const title = 'Nossos Clientes — Talents Gestão de Pessoas';
const description =
    'Empresas que confiam na Talents para transformar sua gestão de pessoas. Conheça cases e resultados de consultoria estratégica.';

/** scale ajusta o peso visual de logos muito largos, quadrados ou com fundo embutido */
const clients = [
    { name: 'Fortex', logo: '/images/clientes/fortex.png', scale: 0.88 },
    { name: 'Giraffas', logo: '/images/clientes/Giraffas.png', scale: 1.22 },
    { name: 'KMR Embalagens', logo: '/images/clientes/kmr.png', scale: 1 },
    { name: 'Mais Mais', logo: '/images/clientes/maismais.png', scale: 1.05 },
    { name: 'Multibor', logo: '/images/clientes/multibor.png', scale: 1.18 },
    { name: 'Pasqualino', logo: '/images/clientes/pasqualino.png', scale: 0.92 },
    { name: 'PASSEG', logo: '/images/clientes/passeg.png', scale: 1 },
    { name: 'Pontix', logo: '/images/clientes/pontix.png', scale: 1.28 },
    { name: 'Ramep', logo: '/images/clientes/ramep.png', scale: 0.88 },
    { name: 'SOEM', logo: '/images/clientes/soem.png', scale: 1 },
    { name: 'Wizzard', logo: '/images/clientes/wizzard.png', scale: 0.92 },
];

const highlights = [
    {
        title: 'Liderança e times',
        description: 'Clareza comportamental para líderes e equipes alinhadas.',
        icon: UserGroupIcon,
    },
    {
        title: 'Cultura organizacional',
        description: 'Ambientes mais conscientes, estratégicos e humanos.',
        icon: BuildingOffice2Icon,
    },
    {
        title: 'Resultado sustentável',
        description: 'Pessoas conectadas à estratégia e ao crescimento.',
        icon: ChartBarIcon,
    },
];

const trustPoints = [
    { label: 'Consultoria estratégica', icon: SparklesIcon },
    { label: 'Desenvolvimento humano', icon: HeartIcon },
    { label: 'Método próprio Talents', icon: ChartBarIcon },
];

const cases = [
    {
        title: 'Fortalecimento de liderança',
        description:
            'Programa de desenvolvimento comportamental para líderes, com foco em comunicação assertiva e gestão de equipes.',
        result: 'Maior clareza relacional e redução de conflitos internos.',
        icon: UserGroupIcon,
        accent: 'from-violet-500/15 via-white to-white',
        bar: 'bg-violet-500',
        iconWrap: 'bg-violet-100 text-violet-700 ring-violet-200/70',
        chip: 'bg-violet-50 text-violet-800 ring-violet-100',
    },
    {
        title: 'Cultura e engajamento',
        description:
            'Diagnóstico estratégico e mapeamento comportamental para alinhar cultura organizacional à estratégia da empresa.',
        result: 'Equipes mais conectadas ao propósito e ao desenvolvimento.',
        icon: SparklesIcon,
        accent: 'from-fuchsia-500/15 via-white to-white',
        bar: 'bg-fuchsia-500',
        iconWrap: 'bg-fuchsia-100 text-fuchsia-700 ring-fuchsia-200/70',
        chip: 'bg-fuchsia-50 text-fuchsia-800 ring-fuchsia-100',
    },
    {
        title: 'Saúde organizacional e NR-1',
        description:
            'Apoio na gestão de riscos psicossociais com método, escuta do time e plano de ação estruturado.',
        result: 'Mais conformidade e ambiente emocionalmente mais saudável.',
        icon: ShieldCheckIcon,
        accent: 'from-indigo-500/15 via-white to-white',
        bar: 'bg-indigo-500',
        iconWrap: 'bg-indigo-100 text-indigo-700 ring-indigo-200/70',
        chip: 'bg-indigo-50 text-indigo-800 ring-indigo-100',
    },
];

const breadcrumbJsonLd = computed(() =>
    JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'BreadcrumbList',
        itemListElement: [
            { '@type': 'ListItem', position: 1, name: 'Home', item: typeof window !== 'undefined' ? window.location.origin : '' },
            { '@type': 'ListItem', position: 2, name: 'Nossos Clientes' },
        ],
    }),
);
</script>

<template>
    <LandingLayout
        :title="title"
        :description="description"
        :can-login="canLogin"
        :can-register="canRegister"
    >
        <template #head>
            <component :is="'script'" type="application/ld+json" head-key="jsonld-breadcrumb">
                {{ breadcrumbJsonLd }}
            </component>
        </template>

        <section class="relative overflow-hidden border-b border-white/30">
            <div
                class="pointer-events-none absolute -right-24 -top-24 h-72 w-72 rounded-full bg-talents-400/20 blur-3xl"
                aria-hidden="true"
            />
            <div
                class="pointer-events-none absolute -bottom-20 left-10 h-56 w-56 rounded-full bg-violet-300/20 blur-3xl"
                aria-hidden="true"
            />

            <div class="relative mx-auto max-w-6xl px-4 py-14 md:py-20">
                <div class="grid items-center gap-10 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)] lg:gap-10">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-widest text-talents-600">
                            Prova social
                        </p>
                        <h1 class="mt-4 text-3xl font-bold leading-tight text-slate-900 md:text-4xl">
                            Empresas que transformam pessoas em estratégia
                        </h1>
                        <p class="mt-5 max-w-xl text-base leading-relaxed text-slate-600 md:text-lg">
                            Da indústria ao varejo e serviços: organizações que confiam na Talents para
                            fortalecer liderança, cultura e desenvolvimento humano com método.
                        </p>

                        <div class="mt-6 flex flex-wrap gap-2">
                            <span
                                v-for="point in trustPoints"
                                :key="point.label"
                                class="inline-flex items-center gap-2 rounded-full border border-talents-200/80 bg-white/80 px-3 py-1.5 text-xs font-semibold text-talents-800 shadow-sm"
                            >
                                <component :is="point.icon" class="h-3.5 w-3.5 text-talents-600" aria-hidden="true" />
                                {{ point.label }}
                            </span>
                        </div>

                        <div class="mt-8 flex flex-wrap gap-3">
                            <a href="#empresas-atendidas" class="btn-primary"> Ver empresas atendidas </a>
                            <Link :href="route('landing.contato')" class="btn-secondary">
                                Quero ser o próximo case
                            </Link>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-5">
                        <div
                            class="rounded-3xl border border-talents-200/70 bg-gradient-to-br from-talents-600 to-talents-800 p-6 text-white shadow-lg sm:p-7"
                        >
                            <p class="text-xs font-semibold uppercase tracking-widest text-talents-100">
                                Rede Talents
                            </p>
                            <p class="mt-3 text-4xl font-bold tabular-nums md:text-5xl">
                                {{ clients.length }}+
                            </p>
                            <p class="mt-2 text-sm leading-relaxed text-talents-100/90">
                                empresas acompanhadas com consultoria estratégica em gestão de pessoas.
                            </p>
                        </div>

                        <article
                            v-for="item in highlights"
                            :key="item.title"
                            class="rounded-2xl border border-slate-200/80 bg-white/90 p-5 shadow-sm sm:p-6"
                        >
                            <div
                                class="inline-flex rounded-xl bg-talents-50 p-2.5 text-talents-700 ring-1 ring-talents-100"
                            >
                                <component :is="item.icon" class="h-5 w-5" aria-hidden="true" />
                            </div>
                            <h2 class="mt-3 text-base font-bold text-slate-900">{{ item.title }}</h2>
                            <p class="mt-1.5 text-sm leading-relaxed text-slate-600">
                                {{ item.description }}
                            </p>
                        </article>
                    </div>
                </div>
            </div>
        </section>

        <section id="empresas-atendidas" class="scroll-mt-24 py-14 md:py-16">
            <div class="mx-auto max-w-6xl px-4">
                <div class="mx-auto max-w-2xl text-center">
                    <p class="text-xs font-semibold uppercase tracking-widest text-talents-600 md:text-sm">
                        Parcerias
                    </p>
                    <h2 class="mt-3 text-2xl font-bold text-slate-900 md:text-3xl">Empresas atendidas</h2>
                    <p class="mt-3 text-sm leading-relaxed text-slate-600 md:text-base">
                        Marcas de diferentes setores que escolheram a Talents para evoluir pessoas e resultados.
                    </p>
                </div>
                <div class="mx-auto mt-10 grid max-w-5xl grid-cols-2 gap-5 sm:grid-cols-3 lg:grid-cols-4">
                    <div
                        v-for="client in clients"
                        :key="client.name"
                        class="group flex h-32 items-center justify-center rounded-2xl border border-slate-200/80 bg-white/90 px-6 py-5 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-talents-300 hover:shadow-md sm:h-36"
                    >
                        <span
                            class="flex h-12 w-[9.5rem] shrink-0 items-center justify-center sm:h-14 sm:w-44"
                        >
                            <img
                                :src="client.logo"
                                :alt="`Logo ${client.name} — cliente Talents`"
                                class="h-full w-full object-contain"
                                :style="{
                                    transform: `scale(${client.scale})`,
                                    transformOrigin: 'center center',
                                }"
                            />
                        </span>
                    </div>
                </div>
            </div>
        </section>

        <section class="border-t border-white/20 py-14 md:py-16">
            <div class="mx-auto max-w-6xl px-4">
                <div class="mx-auto max-w-2xl text-center">
                    <p class="text-xs font-semibold uppercase tracking-widest text-talents-600 md:text-sm">
                        Resultados
                    </p>
                    <h2 class="mt-3 text-2xl font-bold text-slate-900 md:text-3xl">
                        Cases de transformação
                    </h2>
                    <p class="mt-3 text-sm leading-relaxed text-slate-600 md:text-base">
                        Frentes em que a metodologia Talents gera impacto concreto nas organizações.
                    </p>
                </div>
                <div class="mt-10 grid gap-6 md:grid-cols-3">
                    <article
                        v-for="(item, index) in cases"
                        :key="item.title"
                        class="group relative flex h-full flex-col overflow-hidden rounded-[1.75rem] border border-slate-200/90 bg-white shadow-[0_12px_40px_-18px_rgba(88,28,135,0.35)] transition duration-300 hover:-translate-y-1.5 hover:border-talents-300 hover:shadow-[0_20px_50px_-20px_rgba(88,28,135,0.45)]"
                    >
                        <div
                            class="relative border-b border-slate-100 bg-gradient-to-br px-6 pb-5 pt-6"
                            :class="item.accent"
                        >
                            <span
                                class="absolute inset-x-0 top-0 h-1.5"
                                :class="item.bar"
                                aria-hidden="true"
                            />
                            <div class="flex items-start justify-between gap-3">
                                <div
                                    class="inline-flex rounded-2xl p-3 shadow-sm ring-1 transition duration-300 group-hover:scale-105"
                                    :class="item.iconWrap"
                                >
                                    <component :is="item.icon" class="h-6 w-6" aria-hidden="true" />
                                </div>
                                <span
                                    class="rounded-full bg-white/80 px-2.5 py-1 text-[0.65rem] font-bold tabular-nums text-slate-500 ring-1 ring-slate-200/80"
                                >
                                    Case {{ String(index + 1).padStart(2, '0') }}
                                </span>
                            </div>
                            <h3 class="mt-4 text-lg font-bold leading-snug text-slate-900">
                                {{ item.title }}
                            </h3>
                        </div>

                        <div class="flex flex-1 flex-col px-6 py-5">
                            <p class="flex-1 text-sm leading-relaxed text-slate-600">
                                {{ item.description }}
                            </p>
                            <div
                                class="mt-5 rounded-2xl px-3.5 py-3 text-xs leading-relaxed ring-1"
                                :class="item.chip"
                            >
                                <span class="inline-flex items-center gap-1.5 font-bold uppercase tracking-wide">
                                    <ArrowTrendingUpIcon class="h-3.5 w-3.5" aria-hidden="true" />
                                    Resultado
                                </span>
                                <p class="mt-1.5 text-[0.8rem] font-medium normal-case tracking-normal">
                                    {{ item.result }}
                                </p>
                            </div>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <section class="border-t border-white/30 bg-white/30 py-16 backdrop-blur-sm">
            <div class="mx-auto max-w-3xl px-4 text-center">
                <h2 class="text-2xl font-bold text-slate-900 md:text-3xl">
                    Faça parte dessa rede de empresas que transformam pessoas em estratégia.
                </h2>
                <Link :href="route('landing.contato')" class="btn-primary mt-8"> Falar com Especialista </Link>
            </div>
        </section>
    </LandingLayout>
</template>
