<script setup>
import {
    BuildingOffice2Icon,
    ChartBarIcon,
    CheckBadgeIcon,
    ClipboardDocumentCheckIcon,
    ClockIcon,
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

const title = 'Talents — Contratação de Talentos e Consultoria em Gestão de Pessoas';
const description =
    'Contratação de talentos com método: recrutamento e seleção estratégica, análise comportamental e acompanhamento pós-contratação. Consultoria em gestão de pessoas em Várzea Paulista/SP.';

const methodologyPillars = [
    {
        title: 'Metamorfose Comportamental',
        items: ['Mapeamento', 'Análise', 'Imersão', 'Direcionamento'],
        objective: 'Depois da contratação: compreender perfis para desenvolver o potencial de quem entrou no time.',
        icon: SparklesIcon,
        accent: 'from-violet-500/15 via-white to-white',
        iconWrap: 'bg-violet-100 text-violet-700 ring-violet-200/70',
        bullet: 'bg-violet-500',
        bar: 'bg-violet-500',
        chip: 'bg-violet-50 text-violet-800 ring-violet-100',
    },
    {
        title: 'Contratação de Talentos',
        featured: true,
        items: [
            'Análise Salarial',
            'Engenharia de Cargos',
            'Recrutamento e Seleção',
            'Mapeamento Comportamental',
            'Acompanhamento pós-contratação',
        ],
        objective:
            'Contratar com método, aderência cultural e acompanhamento pós-admissão — o ponto de partida da metamorfose empresarial.',
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
        objective: 'Desenvolver líderes e cultura para reter e evoluir quem você contratou.',
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
        objective: 'Proteger o clima e a saúde do time que você montou.',
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
            description: 'Definir o cargo certo antes de abrir a vaga.',
            proof: 'Base clara antes de publicar',
            icon: ClipboardDocumentCheckIcon,
            steps: [
                'Análise salarial do mercado e da vaga',
                'Engenharia de cargos',
                'Alinhamento cultural do perfil',
                'Divulgação com critérios definidos',
            ],
        },
        {
            title: 'Selecionar',
            description: 'Escolher com evidência — não só por currículo.',
            proof: 'Comportamental + técnico + entrevistas',
            icon: MagnifyingGlassIcon,
            steps: [
                'Triagem de candidatos',
                'Análise comportamental',
                'Entrevistas presenciais e online',
                'Análise técnica e alinhamento com o gestor',
            ],
        },
        {
            title: 'Contratar',
            description: 'Admitir com evidência e clareza na integração inicial.',
            proof: 'Admissão com Profiler + Matcher',
            icon: UserPlusIcon,
            steps: [
                'Envio do candidato para admissão',
                'Relatórios Profiler + Matcher',
            ],
        },
        {
            title: 'Acompanhar',
            description: 'Pós-contratação com continuidade e garantia real.',
            proof: '90 dias + 1 recontratação',
            icon: ClockIcon,
            steps: [
                'Acompanhamento pós-contratação por 3 meses, com direito a 1 recontratação',
                'Análise comportamental semestral (mediante a empresa)',
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
        description: 'Lê perfis para contratar com aderência e desenvolver quem já entrou.',
        icon: SparklesIcon,
    },
    {
        title: 'Liderança',
        description: 'Líderes que integram, acompanham e desenvolvem o novo talento.',
        icon: UserGroupIcon,
    },
    {
        title: 'Cultura Organizacional',
        description: 'Ambiente claro para o contratado se encaixar e render.',
        icon: BuildingOffice2Icon,
    },
    {
        title: 'Desenvolvimento Humano',
        description: 'Direcionamento para o profissional evoluir após a contratação.',
        icon: HeartIcon,
    },
    {
        title: 'Gestão Estratégica de Pessoas',
        description: 'Pessoas alinhadas ao crescimento — do hire ao resultado.',
        icon: ChartBarIcon,
    },
    {
        title: 'NR-1 e Riscos Psicossociais',
        description: 'Protege o clima do time que você montou e a conformidade.',
        icon: ShieldCheckIcon,
    },
];

const visionPoints = [
    'Contratação com método: preparar, selecionar, contratar e acompanhar.',
    'Pós-contratação de 90 dias, com direito a 1 recontratação.',
    'Ciência comportamental a serviço da aderência ao cargo.',
    'Os outros pilares sustentam retenção, liderança e saúde do time.',
    'Fortalecemos empresas através das pessoas.',
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
            'Contratação de talentos',
            'Recrutamento e seleção estratégica',
            'Consultoria estratégica em gestão de pessoas',
            'Desenvolvimento humano',
            'Liderança',
            'Cultura organizacional',
            'Gestão de riscos psicossociais NR-1',
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
                        Metodologia em 4 pilares — com a contratação no centro
                    </h2>
                    <p class="mt-3 text-base font-medium text-talents-800 md:text-lg">
                        Contratar certo é o ponto de partida. Os outros pilares sustentam retenção, cultura e saúde do
                        time.
                    </p>
                    <p class="mt-3 text-sm leading-relaxed text-slate-600 md:text-base">
                        A metamorfose empresarial começa com bons talentos no time — e continua com comportamento,
                        direcionamento e proteção psicossocial.
                    </p>
                </div>

                <div class="mt-10">
                    <LandingButterflyPillars :pillars="methodologyPillars" />
                </div>
            </div>
        </section>

        <section
            id="recrutamento-estrategico"
            class="scroll-mt-24 border-t border-white/20 py-14 md:py-20"
        >
            <div class="mx-auto max-w-6xl px-4">
                <div class="max-w-2xl">
                    <p class="text-xs font-semibold uppercase tracking-widest text-talents-600 md:text-sm">
                        Contratação de talentos
                    </p>
                    <h2 class="mt-3 text-2xl font-bold tracking-tight text-slate-900 md:text-3xl">
                        Recrutamento estratégico
                    </h2>
                    <p class="mt-4 text-base leading-relaxed text-slate-600 md:text-lg">
                        Contratar errado custa tempo, clima e resultado. Em quatro fases — preparar, selecionar,
                        contratar e acompanhar — unimos análise comportamental, critério técnico e pós-contratação para
                        mais aderência e menos risco de turnover.
                    </p>
                </div>

                <div class="mt-12 grid gap-8 sm:grid-cols-2 lg:gap-6">
                    <div
                        v-for="(phase, phaseIndex) in recruitmentPhases"
                        :key="phase.title"
                        class="flex h-full flex-col gap-3"
                    >
                        <article
                            class="group flex flex-1 flex-col rounded-3xl border border-slate-200/70 bg-white p-5 shadow-[0_8px_28px_-14px_rgba(88,28,135,0.14),0_2px_6px_rgba(15,23,42,0.04)] ring-1 ring-transparent transition-[border-color,box-shadow,transform] duration-300 motion-safe:hover:-translate-y-1 motion-safe:hover:border-talents-200/80 motion-safe:hover:shadow-[0_18px_40px_-16px_rgba(88,28,135,0.22),0_4px_10px_rgba(15,23,42,0.05)] motion-safe:hover:ring-talents-100/80 motion-reduce:transition-none md:p-6"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div
                                    class="inline-flex rounded-2xl bg-gradient-to-br from-talents-50 to-violet-100/70 p-3 text-talents-800 ring-1 ring-talents-200/80 transition duration-300 motion-safe:group-hover:from-talents-100 motion-safe:group-hover:to-violet-100"
                                >
                                    <component :is="phase.icon" class="h-5 w-5" aria-hidden="true" />
                                </div>
                                <span
                                    class="select-none text-2xl font-light tabular-nums leading-none tracking-tight text-slate-200 md:text-3xl"
                                    aria-hidden="true"
                                >
                                    {{ String(phaseIndex + 1).padStart(2, '0') }}
                                </span>
                            </div>

                            <h3 class="mt-5 text-xl font-bold tracking-tight text-slate-900">
                                {{ phase.title }}
                            </h3>
                            <p class="mt-2 text-sm font-medium leading-relaxed text-talents-800/80">
                                {{ phase.description }}
                            </p>

                            <ol class="mt-6 flex-1 space-y-3.5 border-t border-slate-100 pt-5">
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
                                    <span class="pt-0.5">{{ step.label }}</span>
                                </li>
                            </ol>
                        </article>

                        <p class="flex items-center gap-2 px-1 text-sm font-semibold text-slate-700">
                            <CheckBadgeIcon
                                class="h-5 w-5 shrink-0 text-talents-600"
                                aria-hidden="true"
                            />
                            <span>{{ phase.proof }}</span>
                        </p>
                    </div>
                </div>

                <div class="mt-10 flex flex-wrap items-center gap-3">
                    <Link :href="route('landing.contato')" class="btn-primary">
                        Quero contratar com a Talents
                    </Link>
                </div>
            </div>
        </section>

        <section class="border-t border-white/20 py-12 md:py-14">
            <div class="mx-auto max-w-6xl px-4">
                <div class="max-w-3xl">
                    <p class="text-xs font-semibold uppercase tracking-widest text-talents-600 md:text-sm">
                        Amplitude da consultoria
                    </p>
                    <h2 class="mt-3 text-xl font-bold text-slate-900 md:text-2xl">
                        Além de contratar: o que sustenta o talento no time
                    </h2>
                    <p class="mt-3 text-sm leading-relaxed text-slate-600 md:text-base">
                        Contratar é o centro. As demais frentes da Talents existem para que o hire não fique órfão —
                        com liderança, cultura, desenvolvimento e proteção psicossocial.
                    </p>
                </div>

                <div class="mt-10">
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
                                Contratar certo. Desenvolver de verdade. Proteger o time.
                            </h2>
                            <p class="mt-4 text-talents-100/90 leading-relaxed">
                                Não somos RH operacional nem treinamentos genéricos. Também não fazemos recrutamento
                                isolado: contratamos com método, acompanhamos a integração e usamos a mesma ciência de
                                comportamento para sustentar liderança, cultura e NR-1.
                            </p>
                        </div>
                        <div class="space-y-3">
                            <p
                                v-for="point in visionPoints"
                                :key="point"
                                class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3 text-sm font-medium text-talents-50"
                            >
                                {{ point }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="border-t border-white/30 bg-white/30 py-16 backdrop-blur-sm">
            <div class="mx-auto max-w-3xl px-4 text-center">
                <h2 class="text-2xl font-bold text-slate-900 md:text-3xl">
                    Pronto para contratar com mais método e menos risco?
                </h2>
                <p class="mx-auto mt-3 max-w-xl text-slate-600">
                    Converse com um especialista Talents sobre o processo de Contratação de Talentos — e, se fizer
                    sentido, sobre os pilares que sustentam o time depois do hire.
                </p>
                <Link :href="route('landing.contato')" class="btn-primary mt-8"> Falar sobre contratação </Link>
            </div>
        </section>
    </LandingLayout>
</template>
