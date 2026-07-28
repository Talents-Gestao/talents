<script setup>
import {
    ArrowTrendingUpIcon,
    ChatBubbleLeftRightIcon,
    ClipboardDocumentCheckIcon,
    HeartIcon,
    LightBulbIcon,
    MegaphoneIcon,
    ShieldCheckIcon,
    UserGroupIcon,
    UserPlusIcon,
} from '@heroicons/vue/24/outline';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import LandingCardGrid from '@/Components/Landing/LandingCardGrid.vue';
import LandingLayout from '@/Components/Landing/LandingLayout.vue';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
});

const title = 'Para Empresas — Metamorfose Empresarial™ | Talents';
const description =
    'Metamorfose Empresarial™: consultoria estratégica em gestão de pessoas para transformar empresas através de comportamento, liderança, cultura organizacional e NR-1.';

const pillars = [
    { title: 'Diagnóstico Estratégico', description: 'Leitura profunda do contexto organizacional e das pessoas.', icon: ClipboardDocumentCheckIcon },
    { title: 'Voz do Time', description: 'Escuta estruturada para compreender a realidade interna.', icon: MegaphoneIcon },
    { title: 'Mapeamento Comportamental', description: 'Compreensão dos perfis e impactos na rotina.', icon: UserGroupIcon },
    { title: 'Imersão de Perfis', description: 'Aprofundamento prático para alinhar equipes e lideranças.', icon: LightBulbIcon },
];

const benefits = [
    {
        title: 'Fortalecimento de Liderança',
        description: 'Desenvolvimento de líderes mais conscientes, estratégicos e preparados para conduzir equipes.',
        icon: LightBulbIcon,
    },
    {
        title: 'Clareza Comportamental da Equipe',
        description: 'Compreensão dos diferentes perfis comportamentais e seus impactos na rotina organizacional.',
        icon: UserGroupIcon,
    },
    {
        title: 'Redução de Conflitos Internos',
        description: 'Melhoria das relações interpessoais, alinhamento de comunicação e diminuição de desgastes internos.',
        icon: ChatBubbleLeftRightIcon,
    },
    {
        title: 'Comunicação Mais Assertiva',
        description: 'Equipes mais alinhadas, com maior clareza na troca de informações e relacionamento profissional.',
        icon: MegaphoneIcon,
    },
    {
        title: 'Desenvolvimento de Equipes',
        description: 'Fortalecimento do desempenho coletivo através do entendimento dos perfis e potencialidades.',
        icon: ArrowTrendingUpIcon,
    },
    {
        title: 'Cultura Organizacional Mais Saudável',
        description: 'Construção de ambientes mais conscientes, colaborativos e alinhados à realidade da empresa.',
        icon: HeartIcon,
    },
    {
        title: 'Apoio Estratégico à NR-1',
        description: 'Fortalecimento da gestão de riscos psicossociais e ações preventivas relacionadas à saúde emocional e organizacional.',
        icon: ShieldCheckIcon,
    },
    {
        title: 'Contratações Mais Estratégicas',
        description: 'Maior assertividade na identificação de perfis alinhados à cultura e necessidades da empresa.',
        icon: UserPlusIcon,
    },
    {
        title: 'Aumento de Engajamento e Retenção',
        description: 'Colaboradores mais conectados ao ambiente, propósito e desenvolvimento profissional.',
        icon: ArrowTrendingUpIcon,
    },
];

const breadcrumbJsonLd = computed(() =>
    JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'BreadcrumbList',
        itemListElement: [
            { '@type': 'ListItem', position: 1, name: 'Home', item: typeof window !== 'undefined' ? window.location.origin : '' },
            { '@type': 'ListItem', position: 2, name: 'Para Empresas' },
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

        <section class="mx-auto max-w-6xl px-4 py-16 md:py-20">
            <p class="text-sm font-semibold uppercase tracking-widest text-talents-600">Para Empresas</p>
            <h1 class="mt-4 text-4xl font-bold leading-tight text-slate-900 md:text-5xl">Metamorfose Empresarial™</h1>
            <p class="mt-6 max-w-3xl text-lg leading-relaxed text-slate-600">
                A Metamorfose Empresarial™ foi criada para transformar empresas através das pessoas. Por meio de
                comportamento, liderança, cultura organizacional e desenvolvimento humano, ajudamos equipes e empresas a
                evoluírem de forma mais consciente, estratégica e sustentável.
            </p>
        </section>

        <section class="py-12">
            <div class="mx-auto max-w-6xl px-4">
                <h2 class="text-center text-2xl font-bold text-slate-900 md:text-3xl">Pilares da metodologia</h2>
                <div class="mt-10">
                    <LandingCardGrid :items="pillars" columns="md:grid-cols-2 lg:grid-cols-4" />
                </div>
                <div class="mt-10 text-center">
                    <a
                        href="https://wa.me/message/PCQFKYWHCPIBF1"
                        class="btn-primary"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        Contrate seu talento aqui
                    </a>
                </div>
            </div>
        </section>

        <section class="py-16">
            <div class="mx-auto max-w-6xl px-4">
                <h2 class="text-center text-2xl font-bold text-slate-900 md:text-3xl">Benefícios para sua empresa</h2>
                <p class="mx-auto mt-3 max-w-2xl text-center text-slate-600">
                    Resultados práticos em liderança, cultura, comunicação e gestão estratégica de pessoas.
                </p>
                <div class="mt-10">
                    <LandingCardGrid :items="benefits" columns="md:grid-cols-2 lg:grid-cols-3" />
                </div>
            </div>
        </section>

        <section class="py-12">
            <div class="mx-auto max-w-6xl px-4">
                <article class="surface-card-soft p-8 md:p-10">
                    <h2 class="text-xl font-semibold text-slate-900">Apoio à NR-1 e riscos psicossociais</h2>
                    <p class="mt-3 max-w-3xl text-sm leading-relaxed text-slate-600">
                        Além da transformação cultural, apoiamos empresas na gestão estratégica de riscos
                        psicossociais e saúde organizacional — com método, clareza e direcionamento prático.
                    </p>
                    <Link
                        :href="route('landing.nr1')"
                        class="mt-4 inline-block text-sm font-semibold text-talents-700 hover:underline"
                    >
                        Conhecer solução NR-1 →
                    </Link>
                </article>
            </div>
        </section>

        <section class="border-t border-white/30 bg-white/30 py-16 backdrop-blur-sm">
            <div class="mx-auto max-w-3xl px-4 text-center">
                <h2 class="text-2xl font-bold text-slate-900 md:text-3xl">
                    Vamos fortalecer sua empresa através das pessoas?
                </h2>
                <Link :href="route('landing.contato')" class="btn-primary mt-8"> Falar com Especialista </Link>
            </div>
        </section>
    </LandingLayout>
</template>
