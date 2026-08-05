<script setup>
import {
    AcademicCapIcon,
    BuildingOffice2Icon,
    ChartBarIcon,
    ClipboardDocumentCheckIcon,
    CursorArrowRaysIcon,
    HeartIcon,
    MagnifyingGlassIcon,
    ShieldCheckIcon,
    SparklesIcon,
    UserGroupIcon,
    UserPlusIcon,
} from '@heroicons/vue/24/outline';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import LandingButterflyPillars from '@/Components/Landing/LandingButterflyPillars.vue';
import LandingCardGrid from '@/Components/Landing/LandingCardGrid.vue';
import LandingHeroTypewriter from '@/Components/Landing/LandingHeroTypewriter.vue';
import LandingLayout from '@/Components/Landing/LandingLayout.vue';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
});

const title = 'Talents — Consultoria Estratégica em Gestão de Pessoas';
const description =
    'A Talents é uma consultoria estratégica em gestão de pessoas. Transformamos pessoas em estratégias de crescimento através de comportamento, liderança e desenvolvimento humano.';

const methodologyPillars = [
    {
        title: 'Metamorfose Comportamental',
        items: ['Mapeamento', 'Análise', 'Imersão', 'Direcionamento'],
        objective: 'Compreender o potencial humano.',
        icon: SparklesIcon,
        accent: 'from-violet-500/15 via-white to-white',
        iconWrap: 'bg-violet-100 text-violet-700 ring-violet-200/70',
        bullet: 'bg-violet-500',
        bar: 'bg-violet-500',
        chip: 'bg-violet-50 text-violet-800 ring-violet-100',
    },
    {
        title: 'Contratação de Talentos',
        items: [
            'Análise Salarial',
            'Engenharia de Cargos',
            'Recrutamento e Seleção',
            'Mapeamento Comportamental',
        ],
        objective: 'Contratar com método e aderência.',
        icon: CursorArrowRaysIcon,
        accent: 'from-fuchsia-500/15 via-white to-white',
        iconWrap: 'bg-fuchsia-100 text-fuchsia-700 ring-fuchsia-200/70',
        bullet: 'bg-fuchsia-500',
        bar: 'bg-fuchsia-500',
        chip: 'bg-fuchsia-50 text-fuchsia-800 ring-fuchsia-100',
    },
    {
        title: 'Direcionamento Estratégico',
        items: [
            'Diagnóstico Empresarial',
            'Voz do Time',
            'Desenvolvimento de Líderes e Time',
            'Estruturação de RH',
            'Cultura Organizacional',
        ],
        objective: 'Desenvolver pessoas e negócios.',
        icon: ChartBarIcon,
        accent: 'from-talents-500/15 via-white to-white',
        iconWrap: 'bg-talents-100 text-talents-800 ring-talents-200/70',
        bullet: 'bg-talents-600',
        bar: 'bg-talents-600',
        chip: 'bg-talents-50 text-talents-800 ring-talents-100',
    },
    {
        title: 'Gestão de Riscos Psicossociais',
        items: [
            'Mapeamento Psicossocial',
            'Plano de Ação',
            'Treinamentos de Líderes e Time',
            'Monitoramento Contínuo',
        ],
        objective: 'Promover ambientes saudáveis e seguros.',
        icon: ShieldCheckIcon,
        accent: 'from-indigo-500/15 via-white to-white',
        iconWrap: 'bg-indigo-100 text-indigo-700 ring-indigo-200/70',
        bullet: 'bg-indigo-500',
        bar: 'bg-indigo-500',
        chip: 'bg-indigo-50 text-indigo-800 ring-indigo-100',
    },
];

const recruitmentPhases = (() => {
    const phases = [
        {
            title: 'Preparar',
            description: 'Base salarial, cargos e atração de candidatos.',
            icon: ClipboardDocumentCheckIcon,
            steps: [
                'Análise salarial',
                'Engenharia de cargos',
                'Divulgação de vagas',
            ],
        },
        {
            title: 'Selecionar',
            description: 'Triagem, entrevistas e análise técnica e comportamental.',
            icon: MagnifyingGlassIcon,
            steps: [
                'Triagem de candidatos',
                'Análise comportamental',
                'Agendamento de entrevistas presenciais e online',
                'Entrevistas presenciais e online',
                'Análise técnica',
                'Segunda fase de entrevistas com o responsável do cargo, se desejarem',
            ],
        },
        {
            title: 'Contratar e acompanhar',
            description: 'Admissão, relatórios e acompanhamento pós-contratação.',
            icon: UserPlusIcon,
            steps: [
                'Visita do pré-candidato à empresa (opcional)',
                'Envio do candidato para admissão',
                'Relatórios (Profiler + Matcher)',
                'Acompanhamento pós-contratação (3 meses) com direito a 1 recontratação',
                'Análise comportamental semestral para acompanhar o desenvolvimento (mediante a empresa)',
            ],
        },
    ];

    let stepNumber = 1;
    return phases.map((phase) => ({
        ...phase,
        steps: phase.steps.map((label) => {
            const numbered = { number: stepNumber, label };
            stepNumber += 1;
            return numbered;
        }),
    }));
})();

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

const heroTypewriterPhrases = [
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
            <div class="relative z-10 mx-auto max-w-6xl px-4 py-10 md:py-24 lg:py-28">
                <div class="lg:max-w-[46%]">
                    <p class="text-xs font-semibold uppercase tracking-widest text-talents-600 md:text-sm">
                        Consultoria estratégica em gestão de pessoas
                    </p>
                    <LandingHeroTypewriter />
                    <div class="mt-6 flex flex-wrap gap-3 md:mt-8">
                        <Link :href="route('landing.empresas')" class="btn-primary"> Para Empresas </Link>
                        <Link :href="route('landing.pessoas')" class="btn-secondary"> Para Pessoas </Link>
                        <Link :href="route('landing.contato')" class="btn-ghost"> Falar com Especialista </Link>
                    </div>
                </div>
            </div>

            <img
                src="/images/hero-home.png"
                alt="Profissional apresentando perfis comportamentais: Executor, Comunicador, Planejador e Analista"
                class="hidden h-auto w-full object-cover lg:absolute lg:inset-y-0 lg:right-0 lg:block lg:h-full lg:w-1/2 lg:object-[78%_center]"
                fetchpriority="high"
            />
        </section>

        <section class="border-t border-white/20 bg-white/40 py-12 backdrop-blur-sm md:py-16">
            <div class="mx-auto max-w-6xl px-4">
                <div class="max-w-3xl">
                    <p class="text-xs font-semibold uppercase tracking-widest text-talents-600 md:text-sm">
                        Metodologia Talents
                    </p>
                    <h2 class="mt-3 text-2xl font-bold text-slate-900 md:text-3xl">
                        Pilares da metodologia
                    </h2>
                    <p class="mt-3 text-base font-medium text-talents-800 md:text-lg">
                        A metamorfose empresarial começa com bons talentos no time!
                    </p>
                    <p class="mt-3 text-sm leading-relaxed text-slate-600 md:text-base">
                        Quatro pilares para transformar pessoas, fortalecer empresas e gerar resultados sustentáveis —
                        com clareza de método em cada etapa.
                    </p>
                </div>

                <div class="mt-10">
                    <LandingButterflyPillars :pillars="methodologyPillars" />
                </div>
            </div>
        </section>

        <section
            id="recrutamento-estrategico"
            class="scroll-mt-24 border-t border-white/20 py-12 md:py-16"
        >
            <div class="mx-auto max-w-6xl px-4">
                <div class="max-w-3xl">
                    <p class="text-xs font-semibold uppercase tracking-widest text-talents-600 md:text-sm">
                        Contratação de talentos
                    </p>
                    <h2 class="mt-3 text-2xl font-bold text-slate-900 md:text-3xl">
                        Recrutamento estratégico
                    </h2>
                    <p class="mt-3 text-base leading-relaxed text-slate-600 md:text-lg">
                        Processo completo — do diagnóstico da vaga ao acompanhamento pós-contratação —
                        para contratar com método, aderência e menor risco de turnover.
                    </p>
                </div>

                <div class="mt-10 grid gap-5 lg:grid-cols-3">
                    <article
                        v-for="(phase, phaseIndex) in recruitmentPhases"
                        :key="phase.title"
                        class="flex h-full flex-col rounded-3xl border border-slate-200/80 bg-white/90 p-5 shadow-sm md:p-6"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div
                                class="inline-flex rounded-2xl bg-talents-100 p-2.5 text-talents-800 ring-1 ring-talents-200/70"
                            >
                                <component :is="phase.icon" class="h-5 w-5" aria-hidden="true" />
                            </div>
                            <span class="text-xs font-bold tabular-nums text-slate-300">
                                {{ String(phaseIndex + 1).padStart(2, '0') }}
                            </span>
                        </div>
                        <h3 class="mt-4 text-lg font-bold text-slate-900">{{ phase.title }}</h3>
                        <p class="mt-1.5 text-sm leading-relaxed text-slate-500">
                            {{ phase.description }}
                        </p>
                        <ol class="mt-5 flex-1 space-y-3">
                            <li
                                v-for="step in phase.steps"
                                :key="step.number"
                                class="flex items-start gap-3 text-sm leading-snug text-slate-700"
                            >
                                <span
                                    class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-talents-50 text-[0.7rem] font-bold tabular-nums text-talents-700 ring-1 ring-talents-100"
                                >
                                    {{ step.number }}
                                </span>
                                <span>{{ step.label }}</span>
                            </li>
                        </ol>
                    </article>
                </div>

                <div class="mt-8 flex flex-wrap items-center gap-3">
                    <Link :href="route('landing.contato')" class="btn-secondary">
                        Falar com especialista
                    </Link>
                </div>
            </div>
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
                                v-for="phrase in heroTypewriterPhrases"
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
