<script setup>
import {
    AcademicCapIcon,
    BuildingOffice2Icon,
    ChartBarIcon,
    HeartIcon,
    ShieldCheckIcon,
    SparklesIcon,
    UserGroupIcon,
} from '@heroicons/vue/24/outline';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import LandingCardGrid from '@/Components/Landing/LandingCardGrid.vue';
import LandingLayout from '@/Components/Landing/LandingLayout.vue';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
});

const title = 'Talents — Consultoria Estratégica em Gestão de Pessoas';
const description =
    'A Talents é uma consultoria estratégica em gestão de pessoas. Transformamos pessoas em estratégias de crescimento através de comportamento, liderança e desenvolvimento humano.';

const pillars = [
    {
        title: 'Comportamento',
        description: 'Compreensão de perfis, comunicação e tomada de decisão.',
        icon: SparklesIcon,
    },
    {
        title: 'Liderança',
        description: 'Fortalecimento de líderes e desenvolvimento de equipes.',
        icon: UserGroupIcon,
    },
    {
        title: 'Cultura Organizacional',
        description: 'Estruturação de ambientes mais conscientes e estratégicos.',
        icon: BuildingOffice2Icon,
    },
    {
        title: 'Desenvolvimento Humano',
        description: 'Clareza, direcionamento e evolução profissional.',
        icon: HeartIcon,
    },
    {
        title: 'Gestão Estratégica de Pessoas',
        description: 'Pessoas alinhadas ao crescimento da empresa.',
        icon: ChartBarIcon,
    },
    {
        title: 'NR-1 e Riscos Psicossociais',
        description: 'Apoio estratégico na saúde organizacional e conformidade.',
        icon: ShieldCheckIcon,
    },
];

const anchorPhrases = [
    'Fortalecemos empresas através das pessoas.',
    'Transformamos pessoas em estratégias de crescimento.',
    'Mais clareza. Mais consciência. Mais resultado.',
    'Toda transformação começa pelo autoconhecimento.',
    'Comportamento também é estratégia.',
];

const organizationJsonLd = computed(() =>
    JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'ProfessionalService',
        name: 'Talents Gestão de Pessoas',
        description,
        url: typeof window !== 'undefined' ? window.location.origin : '',
        logo: typeof window !== 'undefined' ? `${window.location.origin}/images/logo.png` : '/images/logo.png',
        areaServed: 'BR',
        serviceType: [
            'Consultoria estratégica em gestão de pessoas',
            'Desenvolvimento humano',
            'Liderança',
            'Cultura organizacional',
        ],
        email: 'contato@talentsgestao.com',
        address: {
            '@type': 'PostalAddress',
            addressLocality: 'Várzea Paulista',
            addressRegion: 'SP',
            addressCountry: 'BR',
        },
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
            <component :is="'script'" type="application/ld+json" head-key="jsonld-org">
                {{ organizationJsonLd }}
            </component>
        </template>

        <section class="relative overflow-hidden lg:min-h-[560px]">
            <div class="relative z-10 mx-auto max-w-6xl px-4 py-16 md:py-24 lg:py-28">
                <div class="lg:max-w-[46%]">
                    <p class="text-sm font-semibold uppercase tracking-widest text-talents-600">
                        Consultoria estratégica em gestão de pessoas
                    </p>
                    <h1 class="mt-4 text-4xl font-bold leading-tight text-slate-900 md:text-5xl">
                        Transformamos pessoas em estratégias de crescimento.
                    </h1>
                    <p class="mt-6 text-lg leading-relaxed text-slate-600">
                        A Talents é uma consultoria estratégica em gestão de pessoas que ajuda empresas e profissionais
                        através de comportamento, desenvolvimento humano e direcionamento estratégico.
                    </p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <Link :href="route('landing.empresas')" class="btn-primary"> Para Empresas </Link>
                        <Link :href="route('landing.pessoas')" class="btn-secondary"> Para Pessoas </Link>
                        <Link :href="route('landing.contato')" class="btn-ghost"> Falar com Especialista </Link>
                    </div>
                </div>
            </div>

            <img
                src="/images/hero-home.png"
                alt="Profissional apresentando perfis comportamentais: Executor, Comunicador, Planejador e Analista"
                class="mt-10 h-auto w-full rounded-l-[3rem] object-cover object-[72%_center] md:mt-12 md:max-h-[480px] lg:absolute lg:inset-y-0 lg:right-0 lg:mt-0 lg:h-full lg:max-h-none lg:w-1/2 lg:rounded-none lg:object-[78%_center]"
                fetchpriority="high"
            />
        </section>

        <section class="py-16">
            <div class="mx-auto max-w-6xl px-4">
                <div class="max-w-3xl">
                    <h2 class="text-2xl font-bold text-slate-900 md:text-3xl">
                        Desenvolvimento estratégico para pessoas e empresas que buscam transformação.
                    </h2>
                    <p class="mt-4 text-slate-600 leading-relaxed">
                        A Talents é uma consultoria estratégica em gestão de pessoas que ajuda pessoas e empresas a
                        compreender comportamentos, fortalecer lideranças e transformar relações através do
                        desenvolvimento e direcionamento estratégico.
                    </p>
                    <p class="mt-4 text-slate-600 leading-relaxed">
                        Acreditamos que as empresas se fortalecem quando compreendem melhor as pessoas que fazem parte
                        delas.
                    </p>
                </div>

                <div class="mt-12">
                    <LandingCardGrid :items="pillars" columns="md:grid-cols-2 lg:grid-cols-3" />
                </div>
            </div>
        </section>

        <section class="py-16">
            <div class="mx-auto max-w-6xl px-4">
                <div class="surface-card-dark p-8 md:p-12">
                    <div class="grid gap-8 md:grid-cols-2 md:items-center">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-widest text-talents-200">
                                Nossa visão
                            </p>
                            <h2 class="mt-3 text-2xl font-bold md:text-3xl">
                                Comportamento, liderança e estratégia a serviço da transformação.
                            </h2>
                            <p class="mt-4 text-talents-100/90 leading-relaxed">
                                Não somos RH operacional, recrutamento isolado ou treinamentos genéricos. Somos
                                consultoria estratégica em gestão de pessoas — com foco em cultura organizacional,
                                desenvolvimento humano e transformação consciente.
                            </p>
                        </div>
                        <div class="space-y-3">
                            <p
                                v-for="phrase in anchorPhrases"
                                :key="phrase"
                                class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3 text-sm font-medium text-talents-50"
                            >
                                {{ phrase }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="py-16">
            <div class="mx-auto max-w-6xl px-4">
                <h2 class="text-center text-2xl font-bold text-slate-900 md:text-3xl">Como ajudamos</h2>
                <p class="mx-auto mt-3 max-w-2xl text-center text-slate-600">
                    Metodologias próprias para empresas e profissionais em diferentes fases de transformação.
                </p>
                <div class="mt-10 grid gap-6 md:grid-cols-2">
                    <article class="surface-card-soft p-8">
                        <AcademicCapIcon class="h-8 w-8 text-talents-600" />
                        <h3 class="mt-4 text-xl font-semibold text-slate-900">Metamorfose Empresarial™</h3>
                        <p class="mt-3 text-sm leading-relaxed text-slate-600">
                            Transformação de empresas através das pessoas — comportamento, liderança, cultura e
                            desenvolvimento humano de forma consciente e estratégica.
                        </p>
                        <Link
                            :href="route('landing.empresas')"
                            class="mt-4 inline-block text-sm font-semibold text-talents-700 hover:underline"
                        >
                            Conhecer para empresas →
                        </Link>
                    </article>
                    <article class="surface-card-soft p-8">
                        <SparklesIcon class="h-8 w-8 text-talents-600" />
                        <h3 class="mt-4 text-xl font-semibold text-slate-900">Metamorfose Pessoal™</h3>
                        <p class="mt-3 text-sm leading-relaxed text-slate-600">
                            Autoconhecimento, clareza e direcionamento para diferentes fases da vida pessoal e
                            profissional através do comportamento humano.
                        </p>
                        <Link
                            :href="route('landing.pessoas')"
                            class="mt-4 inline-block text-sm font-semibold text-talents-700 hover:underline"
                        >
                            Conhecer para pessoas →
                        </Link>
                    </article>
                </div>
            </div>
        </section>

        <section class="border-t border-white/30 bg-white/30 py-16 backdrop-blur-sm">
            <div class="mx-auto max-w-3xl px-4 text-center">
                <h2 class="text-2xl font-bold text-slate-900 md:text-3xl">
                    Vamos fortalecer sua empresa através das pessoas?
                </h2>
                <p class="mx-auto mt-3 max-w-xl text-slate-600">
                    Converse com um especialista Talents e descubra como nossa consultoria pode apoiar sua
                    transformação.
                </p>
                <Link :href="route('landing.contato')" class="btn-primary mt-8"> Falar com Especialista </Link>
            </div>
        </section>
    </LandingLayout>
</template>
