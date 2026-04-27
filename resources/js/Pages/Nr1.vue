<script setup>
import {
    ArrowTrendingUpIcon,
    ChartBarSquareIcon,
    CheckIcon,
    ChevronRightIcon,
    Cog6ToothIcon,
    MagnifyingGlassIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
});

const showContactModal = ref(false);

const form = useForm({
    name: '',
    email: '',
    phone: '',
    company: '',
    message: '',
});

const submitInterest = () => {
    form.post(route('landing.interest'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('name', 'email', 'phone', 'company', 'message');
            showContactModal.value = false;
        },
    });
};

const onKeydown = (e) => {
    if (e.key === 'Escape') {
        showContactModal.value = false;
    }
};

onMounted(() => window.addEventListener('keydown', onKeydown));
onUnmounted(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Head title="Talents — NR-1 e riscos psicossociais" />

    <div class="app-shell min-h-screen scroll-smooth text-slate-900">
        <header class="sticky top-0 z-20 border-b border-white/40 bg-white/70 shadow-sm backdrop-blur-md">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-4">
                <Link href="/" class="inline-flex items-center gap-3">
                    <img src="/images/logo.png" alt="Talents" class="h-16 w-auto md:h-20" />
                </Link>
                <nav class="flex flex-wrap items-center justify-end gap-2 sm:gap-4">
                    <Link href="/" class="text-sm font-semibold text-talents-700 hover:underline"> Início </Link>
                    <Link
                        v-if="canRegister && !$page.props.auth.user"
                        :href="route('register')"
                        class="text-sm font-semibold text-talents-700 hover:underline"
                    >
                        Criar conta
                    </Link>
                    <Link
                        v-if="canLogin && !$page.props.auth.user"
                        :href="route('login')"
                        class="rounded-full bg-talents-600 px-4 py-2 text-sm font-semibold text-white shadow-md transition hover:bg-talents-700"
                    >
                        Entrar
                    </Link>
                    <Link
                        v-if="$page.props.auth.user"
                        :href="route('dashboard')"
                        class="text-sm font-semibold text-talents-700 hover:underline"
                    >
                        Painel
                    </Link>
                </nav>
            </div>
        </header>

        <main>
            <section class="mx-auto max-w-6xl px-4 py-16 md:py-20">
                <div class="max-w-3xl">
                    <p class="text-sm font-medium uppercase tracking-wide text-talents-600">
                        NR-1 · Para quem leva pessoas a sério
                    </p>
                    <h1
                        class="mt-5 text-4xl font-bold leading-snug tracking-tight text-slate-900 md:text-5xl md:leading-[1.1]"
                    >
                        A Talents ajuda empresas a caminhar rumo à
                        <span
                            class="bg-gradient-to-r from-talents-700 to-talents-600 bg-clip-text text-transparent"
                            >conformidade com a NR-1</span
                        >
                        na gestão de riscos psicossociais
                    </h1>
                    <p class="mt-6 text-base leading-relaxed text-slate-600 md:text-lg">
                        Com <strong>método</strong>, <strong>ciência</strong> e <strong>estratégia</strong> — da identificação ao
                        monitoramento, com rastreabilidade para o PGR.
                    </p>

                    <ol
                        class="mt-8 flex list-none flex-nowrap items-center gap-1.5 overflow-x-auto scroll-smooth pb-1 pl-0 [-ms-overflow-style:none] [scrollbar-width:none] snap-x md:flex-wrap md:gap-3 md:overflow-visible md:snap-none [&::-webkit-scrollbar]:hidden"
                        aria-label="Ciclo de gestão: identificar, avaliar, implementar e monitorar riscos psicossociais"
                    >
                        <li class="flex shrink-0 snap-start list-none">
                            <span
                                class="inline-flex items-center gap-2 rounded-full border border-slate-200/90 bg-white/80 px-3.5 py-2 text-xs font-medium uppercase tracking-wide text-talents-800 shadow-sm ring-1 ring-slate-200/50 backdrop-blur-sm transition hover:border-talents-200/80 hover:bg-white"
                            >
                                <MagnifyingGlassIcon class="h-4 w-4 shrink-0 text-talents-600" aria-hidden="true" />
                                Identificar
                            </span>
                        </li>
                        <li class="flex shrink-0 list-none items-center text-slate-300" aria-hidden="true">
                            <ChevronRightIcon class="h-4 w-4" />
                        </li>
                        <li class="flex shrink-0 snap-start list-none">
                            <span
                                class="inline-flex items-center gap-2 rounded-full border border-slate-200/90 bg-white/80 px-3.5 py-2 text-xs font-medium uppercase tracking-wide text-talents-800 shadow-sm ring-1 ring-slate-200/50 backdrop-blur-sm transition hover:border-talents-200/80 hover:bg-white"
                            >
                                <ChartBarSquareIcon class="h-4 w-4 shrink-0 text-talents-600" aria-hidden="true" />
                                Avaliar
                            </span>
                        </li>
                        <li class="flex shrink-0 list-none items-center text-slate-300" aria-hidden="true">
                            <ChevronRightIcon class="h-4 w-4" />
                        </li>
                        <li class="flex shrink-0 snap-start list-none">
                            <span
                                class="inline-flex items-center gap-2 rounded-full border border-slate-200/90 bg-white/80 px-3.5 py-2 text-xs font-medium uppercase tracking-wide text-talents-800 shadow-sm ring-1 ring-slate-200/50 backdrop-blur-sm transition hover:border-talents-200/80 hover:bg-white"
                            >
                                <Cog6ToothIcon class="h-4 w-4 shrink-0 text-talents-600" aria-hidden="true" />
                                Implementar
                            </span>
                        </li>
                        <li class="flex shrink-0 list-none items-center text-slate-300" aria-hidden="true">
                            <ChevronRightIcon class="h-4 w-4" />
                        </li>
                        <li class="flex shrink-0 snap-start list-none">
                            <span
                                class="inline-flex items-center gap-2 rounded-full border border-slate-200/90 bg-white/80 px-3.5 py-2 text-xs font-medium uppercase tracking-wide text-talents-800 shadow-sm ring-1 ring-slate-200/50 backdrop-blur-sm transition hover:border-talents-200/80 hover:bg-white"
                            >
                                <ArrowTrendingUpIcon class="h-4 w-4 shrink-0 text-talents-600" aria-hidden="true" />
                                Monitorar
                            </span>
                        </li>
                    </ol>

                    <blockquote class="mt-6 border-l-4 border-talents-600 pl-4 text-lg italic text-slate-700">
                        Gestão de risco psicossocial não é evento.
                        <span class="font-semibold not-italic">É processo contínuo.</span>
                    </blockquote>

                    <nav
                        class="mt-8 flex flex-wrap gap-x-4 gap-y-2 border-t border-slate-200/80 pt-6 text-sm text-slate-600"
                        aria-label="Seções desta página"
                    >
                        <a href="#metodologia" class="font-medium text-talents-700 hover:underline">Metodologia</a>
                        <span class="text-slate-300" aria-hidden="true">·</span>
                        <a href="#pesquisa" class="font-medium text-talents-700 hover:underline">A pesquisa</a>
                        <span class="text-slate-300" aria-hidden="true">·</span>
                        <a href="#relatorios" class="font-medium text-talents-700 hover:underline">Relatórios e parecer</a>
                        <span class="text-slate-300" aria-hidden="true">·</span>
                        <a href="#plano-acao" class="font-medium text-talents-700 hover:underline">Plano de ação</a>
                        <span class="text-slate-300" aria-hidden="true">·</span>
                        <a href="#ciclo-nr1" class="font-medium text-talents-700 hover:underline">Ciclo em 5 passos</a>
                    </nav>

                    <div class="mt-8 flex flex-wrap gap-4">
                        <button
                            type="button"
                            class="rounded-full bg-talents-600 px-6 py-3 text-sm font-bold text-white shadow-lg transition hover:bg-talents-700"
                            @click="showContactModal = true"
                        >
                            Ver na prática
                        </button>
                    </div>
                    <p class="mt-6 text-sm text-slate-500">
                        Pesquisas com anonimato protegido · Resultados prontos para decisão · Adequação às exigências de saúde e
                        segurança do trabalho
                    </p>
                </div>
            </section>

            <section id="metodologia" class="scroll-mt-24 border-y border-white/30 bg-white/50 py-16 backdrop-blur-sm">
                <div class="mx-auto max-w-6xl px-4">
                    <h2 class="text-2xl font-bold text-slate-900 md:text-3xl">Metodologia: método repetível, não achismo</h2>
                    <p class="mt-4 max-w-3xl text-lg text-slate-600">
                        A NR-1 pede identificação, avaliação e controle de riscos psicossociais com base em critérios técnicos. Na
                        Talents isso vira um <strong>ciclo guiado</strong>: você define o escopo, aplica instrumentos com respaldo
                        metodológico, interpreta com clareza e deixa rastro documental para o PGR — em vez de “uma pesquisa solta”
                        que ninguém sabe repetir no ano seguinte.
                    </p>
                    <div class="mt-10 grid gap-6 md:grid-cols-2">
                        <div class="surface-card border-l-4 border-talents-600 p-6 shadow-md">
                            <h3 class="text-lg font-semibold text-slate-900">Base técnica e consistência</h3>
                            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                                Modelos de questionário alinhados a boas práticas internacionais e ao que a organização precisa
                                demonstrar: fatores de risco psicossocial mensuráveis, comparáveis entre áreas e ao longo do tempo.
                            </p>
                        </div>
                        <div class="surface-card border-l-4 border-talents-600 p-6 shadow-md">
                            <h3 class="text-lg font-semibold text-slate-900">Governança do processo</h3>
                            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                                Campanhas com período de resposta, acompanhamento de participação e regras de privacidade — para que
                                o dado seja útil à decisão e defensável em auditoria, sem expor quem respondeu.
                            </p>
                        </div>
                        <div class="surface-card border-l-4 border-talents-600 p-6 shadow-md">
                            <h3 class="text-lg font-semibold text-slate-900">Da leitura à priorização</h3>
                            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                                Resultados agregados por corte (área, unidade etc., conforme configurado) mostram onde o risco se
                                concentra — não só uma média global que esconde pontos críticos.
                            </p>
                        </div>
                        <div class="surface-card border-l-4 border-talents-600 p-6 shadow-md">
                            <h3 class="text-lg font-semibold text-slate-900">Conteúdo de capacitação e metodologia no produto</h3>
                            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                                Materiais e trilhas de metodologia ajudam RH e SESMT a alinhar linguagem, responsabilidades e
                                rituais de revisão — para o ciclo não depender de uma única pessoa.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <section id="pesquisa" class="scroll-mt-24 mx-auto max-w-6xl px-4 py-16">
                <h2 class="text-2xl font-bold text-slate-900 md:text-3xl">Como a pesquisa é feita, na prática</h2>
                <p class="mt-4 max-w-3xl text-slate-600">
                    A coleta é o momento em que o colaborador registra a percepção sobre fatores psicossociais. O sistema cuida do
                    fluxo para que isso seja <strong>seguro, simples e mensurável</strong>.
                </p>
                <ol class="mt-10 space-y-8">
                    <li class="flex gap-5">
                        <span
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-talents-600 text-sm font-bold text-white"
                            >1</span
                        >
                        <div>
                            <h3 class="font-semibold text-slate-900">Configuração da campanha</h3>
                            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                                Você define período de abertura e encerramento, vincula à estrutura da empresa (áreas/unidades) e
                                escolhe o instrumento adequado. O objetivo é que a amostra reflita a realidade operacional — e que
                                os cortes de análise façam sentido para liderança e SESMT.
                            </p>
                        </div>
                    </li>
                    <li class="flex gap-5">
                        <span
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-talents-600 text-sm font-bold text-white"
                            >2</span
                        >
                        <div>
                            <h3 class="font-semibold text-slate-900">Divulgação e acesso</h3>
                            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                                Os colaboradores acessam por link seguro, no próprio ritmo. A plataforma acompanha taxa de
                                participação para você saber se o recado chegou — e onde pode precisar reforçar comunicação.
                            </p>
                        </div>
                    </li>
                    <li class="flex gap-5">
                        <span
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-talents-600 text-sm font-bold text-white"
                            >3</span
                        >
                        <div>
                            <h3 class="font-semibold text-slate-900">Anonimato e agregação</h3>
                            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                                Respostas são tratadas com regras de agregação: só entram em relatório grupos com tamanho mínimo,
                                reduzindo risco de identificação indevida. Isso aumenta a confiança no instrumento e a qualidade do
                                dado para gestão de risco.
                            </p>
                        </div>
                    </li>
                    <li class="flex gap-5">
                        <span
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-talents-600 text-sm font-bold text-white"
                            >4</span
                        >
                        <div>
                            <h3 class="font-semibold text-slate-900">Processamento e indicadores</h3>
                            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                                Ao encerrar o período, os resultados são consolidados em indicadores e visualizações por corte.
                                Quando aplicável, é possível recalcular ou aprofundar análises para sustentar a discussão técnica
                                com o time de SST.
                            </p>
                        </div>
                    </li>
                    <li class="flex gap-5">
                        <span
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-talents-600 text-sm font-bold text-white"
                            >5</span
                        >
                        <div>
                            <h3 class="font-semibold text-slate-900">Exportação e continuidade</h3>
                            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                                Os dados podem ser exportados para cruzamento externo ou arquivo de evidências. Assim a pesquisa
                                não morre no gráfico: vira insumo para relatório, comitê e próxima rodada de monitoramento.
                            </p>
                        </div>
                    </li>
                </ol>
            </section>

            <section id="relatorios" class="scroll-mt-24 border-y border-white/30 bg-white/40 py-16 backdrop-blur-sm">
                <div class="mx-auto max-w-6xl px-4">
                    <h2 class="text-2xl font-bold text-slate-900 md:text-3xl">Relatórios e parecer técnico</h2>
                    <p class="mt-4 max-w-3xl text-slate-600">
                        Depois da coleta, a organização precisa <strong>interpretar</strong> e <strong>formalizar</strong> o resultado
                        para integrar ao PGR e às instâncias de decisão. A Talents oferece saídas em formatos distintos para cada
                        público — sem misturar “slide para diretoria” com “detalhe para SST”.
                    </p>
                    <div class="mt-10 grid gap-6 lg:grid-cols-2">
                        <div class="surface-card p-6 shadow-md">
                            <p class="text-sm font-bold uppercase tracking-wide text-talents-600">Relatório executivo</p>
                            <h3 class="mt-2 text-lg font-semibold text-slate-900">Leitura para gestão e comitês</h3>
                            <p class="mt-3 text-sm leading-relaxed text-slate-600">
                                Síntese dos principais achados, tendências e prioridades visíveis nos cortes configurados. Serve para
                                alinhar patrocínio, orçamento e cronologia de medidas — em linguagem acessível, sem abrir mão da
                                mensagem central sobre onde está o risco.
                            </p>
                        </div>
                        <div class="surface-card p-6 shadow-md">
                            <p class="text-sm font-bold uppercase tracking-wide text-talents-600">Relatório técnico</p>
                            <h3 class="mt-2 text-lg font-semibold text-slate-900">Subsídio ao parecer e ao PGR</h3>
                            <p class="mt-3 text-sm leading-relaxed text-slate-600">
                                Detalhamento metodológico e resultados segmentados para sustentar o <strong>parecer técnico</strong> e
                                o registro no Programa de Gerenciamento de Riscos: o “porquê” e o “como foi medido”, com base no
                                que a plataforma consolidou a partir da pesquisa.
                            </p>
                            <p class="mt-3 text-xs text-slate-500">
                                O profissional legalmente habilitado mantém a responsabilidade técnica pelo parecer; a Talents
                                fornece a estrutura de dados, documentação do ciclo e relatórios para embasar essa etapa.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <section id="plano-acao" class="scroll-mt-24 mx-auto max-w-6xl px-4 py-16">
                <h2 class="text-2xl font-bold text-slate-900 md:text-3xl">Plano de ação: do diagnóstico à medida</h2>
                <p class="mt-4 max-w-3xl text-slate-600">
                    Identificar risco psicossocial sem agir é o mesmo que documentar um problema e empurrá-lo para frente. O
                    <strong>plano de ação</strong> na Talents liga o resultado da pesquisa a medidas concretas — com rastreio do
                    que foi combinado e do que foi feito.
                </p>
                <ul class="mt-8 grid gap-4 sm:grid-cols-2">
                    <li class="surface-card flex gap-3 p-5 shadow-md">
                        <CheckIcon class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true" />
                        <div>
                            <p class="font-semibold text-slate-900">Priorização a partir dos resultados</p>
                            <p class="mt-1 text-sm text-slate-600">
                                Itens ligados aos fatores e cortes mais críticos — para que esforço e orçamento sigam o risco, não o
                                ruído.
                            </p>
                        </div>
                    </li>
                    <li class="surface-card flex gap-3 p-5 shadow-md">
                        <CheckIcon class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true" />
                        <div>
                            <p class="font-semibold text-slate-900">Responsáveis e prazos</p>
                            <p class="mt-1 text-sm text-slate-600">
                                Cada medida com dono visível reduz o “empurra-empurra” entre RH, SESMT e liderança.
                            </p>
                        </div>
                    </li>
                    <li class="surface-card flex gap-3 p-5 shadow-md">
                        <CheckIcon class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true" />
                        <div>
                            <p class="font-semibold text-slate-900">Acompanhamento e revisão</p>
                            <p class="mt-1 text-sm text-slate-600">
                                Status e histórico mostram evolução — essencial para monitorar o ciclo NR-1 e provar melhoria
                                contínua.
                            </p>
                        </div>
                    </li>
                    <li class="surface-card flex gap-3 p-5 shadow-md">
                        <CheckIcon class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true" />
                        <div>
                            <p class="font-semibold text-slate-900">Conexão com o PGR</p>
                            <p class="mt-1 text-sm text-slate-600">
                                O que foi combinado pode ser referenciado na formalização das medidas de prevenção integradas ao
                                gerenciamento de riscos da organização.
                            </p>
                        </div>
                    </li>
                </ul>
            </section>

            <section id="ciclo-nr1" class="scroll-mt-24 border-y border-white/30 bg-white/40 py-16 backdrop-blur-sm">
                <div class="mx-auto max-w-6xl px-4">
                    <h2 class="text-center text-2xl font-bold text-slate-900 md:text-3xl">O ciclo NR-1 em cinco passos</h2>
                    <p class="mx-auto mt-3 max-w-2xl text-center text-slate-600">
                        Visão resumida do que a norma espera em riscos psicossociais — e como a Talents apoia cada etapa.
                    </p>
                </div>
                <div class="mx-auto mt-10 max-w-[1600px] px-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 xl:gap-5">
                        <article class="surface-card flex h-full flex-col border-t-4 border-talents-600 p-5 shadow-md sm:p-6">
                            <div class="flex items-start gap-3">
                                <span
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-talents-600 text-sm font-bold text-white"
                                    >1</span
                                >
                                <h3 class="text-base font-bold leading-snug text-slate-900 md:text-lg">
                                    Buscar orientação técnica especializada
                                </h3>
                            </div>
                            <p class="mt-3 flex-1 text-sm leading-relaxed text-slate-600">
                                A NR-1 exige método e estrutura. Ter apoio especializado evita improviso e exposição jurídica.
                            </p>
                        </article>

                        <article class="surface-card flex h-full flex-col border-t-4 border-red-500 p-5 shadow-md sm:p-6">
                            <div class="flex items-start gap-3">
                                <span
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-red-500 text-sm font-bold text-white"
                                    >2</span
                                >
                                <h3 class="text-base font-bold leading-snug text-slate-900 md:text-lg">Identificar</h3>
                            </div>
                            <ul class="mt-3 list-inside list-disc space-y-1.5 text-sm text-slate-600">
                                <li>Sobrecarga e pressão excessiva</li>
                                <li>Conflitos recorrentes</li>
                                <li>Liderança despreparada</li>
                                <li>Lacunas no PGR</li>
                            </ul>
                        </article>

                        <article class="surface-card flex h-full flex-col border-t-4 border-amber-500 p-5 shadow-md sm:p-6">
                            <div class="flex items-start gap-3">
                                <span
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-amber-500 text-sm font-bold text-white"
                                    >3</span
                                >
                                <h3 class="text-base font-bold leading-snug text-slate-900 md:text-lg">Avaliar</h3>
                            </div>
                            <ul class="mt-3 list-inside list-disc space-y-1.5 text-sm text-slate-600">
                                <li>Classificar nível de exposição (baixo, médio, alto)</li>
                                <li>Definir prioridades</li>
                                <li>Avaliar impacto organizacional</li>
                            </ul>
                        </article>

                        <article class="surface-card flex h-full flex-col border-t-4 border-emerald-600 p-5 shadow-md sm:p-6">
                            <div class="flex items-start gap-3">
                                <span
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-sm font-bold text-white"
                                    >4</span
                                >
                                <h3 class="text-base font-bold leading-snug text-slate-900 md:text-lg">Implementar</h3>
                            </div>
                            <ul class="mt-3 list-inside list-disc space-y-1.5 text-sm text-slate-600">
                                <li>Estruturar plano de ação</li>
                                <li>Treinamentos e ajustes organizacionais</li>
                                <li>Formalização no PGR</li>
                            </ul>
                        </article>

                        <article class="surface-card flex h-full flex-col border-t-4 border-blue-600 p-5 shadow-md sm:p-6">
                            <div class="flex items-start gap-3">
                                <span
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-blue-600 text-sm font-bold text-white"
                                    >5</span
                                >
                                <h3 class="text-base font-bold leading-snug text-slate-900 md:text-lg">Monitorar</h3>
                            </div>
                            <ul class="mt-3 list-inside list-disc space-y-1.5 text-sm text-slate-600">
                                <li>Acompanhar indicadores humanos</li>
                                <li>Reavaliar periodicamente</li>
                                <li>Atualizar plano de ação</li>
                            </ul>
                        </article>
                    </div>
                </div>
            </section>

            <section class="scroll-mt-24 border-y border-white/30 bg-white/40 py-16 backdrop-blur-sm">
                <div class="mx-auto max-w-6xl px-4">
                    <h2 class="text-center text-2xl font-bold text-slate-900 md:text-3xl">Por que contratar, benefícios e resultados</h2>
                    <p class="mx-auto mt-3 max-w-3xl text-center text-slate-600">
                        A Talents conecta metodologia, governança e acompanhamento contínuo para transformar riscos psicossociais em
                        decisões práticas para RH, SESMT e liderança.
                    </p>
                    <div class="mt-10 grid gap-6 md:grid-cols-3">
                        <article class="surface-card p-6 shadow-md">
                            <p class="text-sm font-bold uppercase tracking-wide text-talents-600">Por que contratar</p>
                            <h3 class="mt-2 text-lg font-semibold text-slate-900">Condução técnica com clareza</h3>
                            <p class="mt-3 text-sm leading-relaxed text-slate-600">
                                Estrutura para cumprir o ciclo da NR-1 com método replicável, documentação consistente e linguagem que
                                facilita o alinhamento entre áreas técnicas e gestão.
                            </p>
                        </article>
                        <article class="surface-card p-6 shadow-md">
                            <p class="text-sm font-bold uppercase tracking-wide text-talents-600">Benefícios</p>
                            <h3 class="mt-2 text-lg font-semibold text-slate-900">Rotina de gestão mais organizada</h3>
                            <p class="mt-3 text-sm leading-relaxed text-slate-600">
                                Campanhas estruturadas, leituras por recorte e priorização objetiva reduzem ruído operacional e
                                aceleram a tomada de decisão sobre as medidas mais críticas.
                            </p>
                        </article>
                        <article class="surface-card p-6 shadow-md">
                            <p class="text-sm font-bold uppercase tracking-wide text-talents-600">Resultados</p>
                            <h3 class="mt-2 text-lg font-semibold text-slate-900">Evidências para ação contínua</h3>
                            <p class="mt-3 text-sm leading-relaxed text-slate-600">
                                Relatórios e trilha de acompanhamento deixam o progresso visível no tempo, sustentando auditorias e
                                reforçando a cultura de prevenção com foco em melhoria contínua.
                            </p>
                        </article>
                    </div>
                </div>
            </section>

            <section id="contato" class="border-t border-white/30 bg-white/30 py-16 backdrop-blur-sm">
                <div class="mx-auto max-w-2xl px-4">
                    <h2 class="text-center text-2xl font-bold text-slate-900 md:text-3xl">Quer conhecer mais a Talents?</h2>
                    <p class="mx-auto mt-3 max-w-xl text-center text-slate-600">
                        Deixe seus dados (telefone/WhatsApp ajuda no retorno) e, se quiser, uma mensagem.
                    </p>

                    <div
                        v-if="$page.props.flash?.success"
                        class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-center text-sm text-emerald-800"
                    >
                        {{ $page.props.flash.success }}
                    </div>
                    <div
                        v-if="$page.props.flash?.error"
                        class="mt-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-center text-sm text-red-800"
                    >
                        {{ $page.props.flash.error }}
                    </div>

                    <form class="surface-card mt-8 space-y-5 p-6 shadow-md sm:p-8" @submit.prevent="submitInterest">
                        <div>
                            <label class="block text-sm font-medium text-slate-700" for="nr1-interest-name">Nome</label>
                            <input
                                id="nr1-interest-name"
                                v-model="form.name"
                                type="text"
                                required
                                autocomplete="name"
                                class="mt-1 w-full rounded-lg border-slate-200 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            />
                            <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700" for="nr1-interest-email">E-mail</label>
                            <input
                                id="nr1-interest-email"
                                v-model="form.email"
                                type="email"
                                required
                                autocomplete="email"
                                class="mt-1 w-full rounded-lg border-slate-200 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            />
                            <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700" for="nr1-interest-phone"
                                >Telefone / WhatsApp <span class="font-normal text-slate-500">(opcional)</span></label
                            >
                            <input
                                id="nr1-interest-phone"
                                v-model="form.phone"
                                type="tel"
                                autocomplete="tel"
                                placeholder="DDD + número"
                                class="mt-1 w-full rounded-lg border-slate-200 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            />
                            <p v-if="form.errors.phone" class="mt-1 text-sm text-red-600">{{ form.errors.phone }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700" for="nr1-interest-company"
                                >Empresa <span class="font-normal text-slate-500">(opcional)</span></label
                            >
                            <input
                                id="nr1-interest-company"
                                v-model="form.company"
                                type="text"
                                autocomplete="organization"
                                class="mt-1 w-full rounded-lg border-slate-200 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            />
                            <p v-if="form.errors.company" class="mt-1 text-sm text-red-600">{{ form.errors.company }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700" for="nr1-interest-message"
                                >Mensagem <span class="font-normal text-slate-500">(opcional)</span></label
                            >
                            <textarea
                                id="nr1-interest-message"
                                v-model="form.message"
                                rows="4"
                                class="mt-1 w-full rounded-lg border-slate-200 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                placeholder="Conte um pouco do que você busca ou deixe em branco."
                            />
                            <p v-if="form.errors.message" class="mt-1 text-sm text-red-600">{{ form.errors.message }}</p>
                        </div>
                        <div class="pt-2">
                            <button
                                type="submit"
                                class="w-full rounded-full bg-talents-600 px-6 py-3 text-sm font-bold text-white shadow-md transition hover:bg-talents-700 disabled:opacity-60 sm:w-auto"
                                :disabled="form.processing"
                            >
                                {{ form.processing ? 'Enviando…' : 'Enviar interesse' }}
                            </button>
                        </div>
                    </form>
                </div>
            </section>
        </main>

        <footer class="border-t border-white/30 py-8 text-center text-xs text-slate-500 backdrop-blur-sm">
            <Link href="/" class="text-talents-700 hover:underline">Voltar à página inicial</Link>
            <span class="mx-2 text-slate-300">·</span>
            Talents &mdash; Gestão de Pessoas
        </footer>

        <Teleport to="body">
            <div
                v-if="showContactModal"
                class="fixed inset-0 z-[100] flex items-end justify-center bg-black/60 p-4 sm:items-center"
                role="dialog"
                aria-modal="true"
                @click.self="showContactModal = false"
            >
                <div
                    class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-t-2xl bg-white shadow-2xl sm:rounded-2xl"
                    @click.stop
                >
                    <div class="sticky top-0 flex items-center justify-between border-b border-slate-200 bg-white px-5 py-4">
                        <div>
                            <h3 class="text-lg font-bold text-slate-900">Ver na prática</h3>
                            <p class="text-sm text-slate-600">Fale com um especialista Talents</p>
                        </div>
                        <button
                            type="button"
                            class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-800"
                            aria-label="Fechar"
                            @click="showContactModal = false"
                        >
                            <XMarkIcon class="h-5 w-5" />
                        </button>
                    </div>
                    <form class="space-y-4 px-5 py-5" @submit.prevent="submitInterest">
                        <div>
                            <label class="block text-sm font-medium text-slate-700" for="nr1-modal-name">Nome</label>
                            <input
                                id="nr1-modal-name"
                                v-model="form.name"
                                type="text"
                                required
                                autocomplete="name"
                                class="mt-1 w-full rounded-lg border-slate-200 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            />
                            <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700" for="nr1-modal-email">E-mail</label>
                            <input
                                id="nr1-modal-email"
                                v-model="form.email"
                                type="email"
                                required
                                autocomplete="email"
                                class="mt-1 w-full rounded-lg border-slate-200 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            />
                            <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700" for="nr1-modal-phone"
                                >Telefone / WhatsApp <span class="font-normal text-slate-500">(opcional)</span></label
                            >
                            <input
                                id="nr1-modal-phone"
                                v-model="form.phone"
                                type="tel"
                                autocomplete="tel"
                                class="mt-1 w-full rounded-lg border-slate-200 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            />
                            <p v-if="form.errors.phone" class="mt-1 text-sm text-red-600">{{ form.errors.phone }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700" for="nr1-modal-company">Empresa (opcional)</label>
                            <input
                                id="nr1-modal-company"
                                v-model="form.company"
                                type="text"
                                class="mt-1 w-full rounded-lg border-slate-200 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700" for="nr1-modal-message">Mensagem (opcional)</label>
                            <textarea
                                id="nr1-modal-message"
                                v-model="form.message"
                                rows="3"
                                class="mt-1 w-full rounded-lg border-slate-200 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            />
                        </div>
                        <button
                            type="submit"
                            class="w-full rounded-full bg-talents-600 py-3 text-sm font-bold text-white shadow hover:bg-talents-700 disabled:opacity-60"
                            :disabled="form.processing"
                        >
                            {{ form.processing ? 'Enviando…' : 'Enviar' }}
                        </button>
                    </form>
                </div>
            </div>
        </Teleport>
    </div>
</template>
