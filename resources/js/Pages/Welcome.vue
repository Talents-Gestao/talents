<script setup>
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
    <Head title="Talents — Gestão de Pessoas" />

    <div class="app-shell min-h-screen scroll-smooth text-slate-900">
        <header class="landing-header">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-4">
                <img src="/images/logo.png" alt="Talents" class="h-16 w-auto md:h-20" />
                <nav class="flex flex-wrap items-center justify-end gap-2 sm:gap-4">
                    <Link
                        :href="route('landing.diagnostico')"
                        class="landing-nav-link"
                    >
                        Diagnóstico Comportamental
                    </Link>
                    <Link
                        :href="route('landing.contratacao')"
                        class="landing-nav-link"
                    >
                        Contratação de Talentos
                    </Link>
                    <Link
                        :href="route('landing.direcionamento')"
                        class="landing-nav-link"
                    >
                        Direcionamento Estratégico
                    </Link>
                    <Link
                        :href="route('landing.nr1')"
                        class="landing-nav-link"
                    >
                        NR-1
                    </Link>
                    <Link
                        v-if="canRegister && !$page.props.auth.user"
                        :href="route('register')"
                        class="landing-nav-link"
                    >
                        Criar conta
                    </Link>
                    <Link
                        v-if="canLogin && !$page.props.auth.user"
                        :href="route('login')"
                        class="btn-primary px-4 py-2"
                    >
                        Entrar
                    </Link>
                    <Link
                        v-if="$page.props.auth.user"
                        :href="route('dashboard')"
                        class="landing-nav-link"
                    >
                        Painel
                    </Link>
                </nav>
            </div>
        </header>

        <main>
            <section class="mx-auto max-w-6xl px-4 py-16 md:py-20">
                <div class="max-w-3xl">
                    <p class="text-sm font-semibold uppercase tracking-widest text-talents-600">
                        Para quem leva pessoas a sério
                    </p>
                    <h1 class="mt-4 text-4xl font-bold leading-tight text-slate-900 md:text-5xl">
                        Menos improviso. Mais clareza sobre o clima e os riscos na sua empresa.
                    </h1>
                    <p class="mt-6 text-lg text-slate-600">
                        A Talents ajuda você a ouvir o time com segurança, enxergar onde investir em bem-estar e documentar o que a
                        lei exige — sem planilhas intermináveis nem relatórios que ninguém usa.
                    </p>

                    <div class="mt-8 flex flex-wrap gap-3">
                        <button
                            type="button"
                            class="btn-primary"
                            @click="showContactModal = true"
                        >
                            Ver na prática
                        </button>
                        <Link
                            v-if="canRegister && !$page.props.auth.user"
                            :href="route('register')"
                            class="btn-secondary"
                        >
                            Criar conta
                        </Link>
                        <Link
                            :href="route('landing.nr1')"
                            class="btn-ghost"
                        >
                            NR-1 e riscos psicossociais
                        </Link>
                    </div>
                    <p class="mt-6 text-sm text-slate-500">
                        Diagnóstico Comportamental · Contratação de Talentos · Direcionamento Estratégico · NR-1 e riscos
                        psicossociais.
                    </p>
                </div>
            </section>

            <section class="py-16">
                <div class="mx-auto max-w-6xl px-4">
                    <h2 class="text-center text-2xl font-bold text-slate-900 md:text-3xl">Conheça nossos pilares</h2>
                    <p class="mx-auto mt-3 max-w-3xl text-center text-slate-600">
                        Pesquisas, resultados, plano de ação, denúncias e conformidade — em um só lugar, com quatro frentes que
                        conectam gestão de pessoas e estratégia.
                    </p>
                    <div class="mt-10 grid gap-6 md:grid-cols-2">
                        <article class="surface-card-soft p-6">
                            <p class="text-sm font-bold uppercase tracking-wide text-talents-600">Pilar 01</p>
                            <h3 class="mt-2 text-lg font-semibold text-slate-900">Diagnóstico Comportamental</h3>
                            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                                Mapeie padrões do time para orientar liderança, comunicação e desenvolvimento com mais precisão.
                            </p>
                            <Link :href="route('landing.diagnostico')" class="mt-4 inline-block text-sm font-semibold text-talents-700 hover:underline">
                                Saiba mais →
                            </Link>
                        </article>
                        <article class="surface-card-soft p-6">
                            <p class="text-sm font-bold uppercase tracking-wide text-talents-600">Pilar 02</p>
                            <h3 class="mt-2 text-lg font-semibold text-slate-900">Contratação de Talentos</h3>
                            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                                Estruture processos seletivos para atrair pessoas com aderência ao negócio e acelerar resultados.
                            </p>
                            <Link :href="route('landing.contratacao')" class="mt-4 inline-block text-sm font-semibold text-talents-700 hover:underline">
                                Saiba mais →
                            </Link>
                        </article>
                        <article class="surface-card-soft p-6">
                            <p class="text-sm font-bold uppercase tracking-wide text-talents-600">Pilar 03</p>
                            <h3 class="mt-2 text-lg font-semibold text-slate-900">Direcionamento Estratégico</h3>
                            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                                Converta dados de pessoas em prioridades claras para evolução de cultura e performance.
                            </p>
                            <Link :href="route('landing.direcionamento')" class="mt-4 inline-block text-sm font-semibold text-talents-700 hover:underline">
                                Saiba mais →
                            </Link>
                        </article>
                        <article class="surface-card-soft p-6">
                            <p class="text-sm font-bold uppercase tracking-wide text-talents-600">Pilar 04</p>
                            <h3 class="mt-2 text-lg font-semibold text-slate-900">NR-1 e riscos psicossociais</h3>
                            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                                Organize o ciclo de gestão de risco psicossocial com método, rastreabilidade e continuidade.
                            </p>
                            <Link :href="route('landing.nr1')" class="mt-4 inline-block text-sm font-semibold text-talents-700 hover:underline">
                                Saiba mais →
                            </Link>
                        </article>
                    </div>
                </div>
            </section>

            <section class="py-16">
                <div class="mx-auto max-w-6xl px-4">
                    <h2 class="text-center text-2xl font-bold text-slate-900 md:text-3xl">O que você encontra na plataforma</h2>
                    <p class="mx-auto mt-3 max-w-2xl text-center text-slate-600">
                        Funcionalidades pensadas para RH, SESMT e liderança — da coleta à decisão e ao registro para auditoria.
                    </p>
                    <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="surface-card-soft p-6">
                            <h3 class="text-lg font-semibold text-slate-900">Pesquisas e campanhas</h3>
                            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                                Modelos alinhados a boas práticas, participação acompanhada e anonimato protegido por regras de
                                agregação.
                            </p>
                        </div>
                        <div class="surface-card-soft p-6">
                            <h3 class="text-lg font-semibold text-slate-900">Resultados e prioridades</h3>
                            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                                Painéis e cortes por área para saber onde agir primeiro — não só média genérica. Exportação para
                                análise e relatórios executivo e técnico.
                            </p>
                        </div>
                        <div class="surface-card-soft p-6">
                            <h3 class="text-lg font-semibold text-slate-900">Plano de ação</h3>
                            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                                Estruture medidas, responsáveis e prazos a partir dos resultados e acompanhe evolução no tempo.
                            </p>
                        </div>
                        <div class="surface-card-soft p-6">
                            <h3 class="text-lg font-semibold text-slate-900">NR-1 e PGR</h3>
                            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                                Apoio ao ciclo de riscos psicossociais com rastreabilidade para integrar ao Programa de Gerenciamento
                                de Riscos.
                            </p>
                            <Link
                                :href="route('landing.nr1')"
                                class="mt-3 inline-block text-sm font-semibold text-talents-700 hover:underline"
                            >
                                Ver página NR-1 →
                            </Link>
                        </div>
                        <div class="surface-card-soft p-6">
                            <h3 class="text-lg font-semibold text-slate-900">Canal de denúncias</h3>
                            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                                Conforme a Lei nº 14.457/2022, quando habilitado para a empresa — fluxo estruturado e rastreável para
                                o comitê.
                            </p>
                        </div>
                        <div class="surface-card-soft p-6">
                            <h3 class="text-lg font-semibold text-slate-900">Metodologia e calendário</h3>
                            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                                Conteúdo de capacitação, metodologia aplicável por empresa e calendário estratégico para organizar o
                                ciclo ao longo do ano.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Bloco original: o que você ganha -->
            <section class="py-16">
                <div class="mx-auto max-w-6xl px-4">
                    <h2 class="text-center text-2xl font-bold text-slate-900 md:text-3xl">O que você ganha no dia a dia</h2>
                    <p class="mx-auto mt-3 max-w-2xl text-center text-slate-600">
                        Ferramentas pensadas para RH, SESMT e liderança — linguagem humana, números que importam.
                    </p>
                    <div class="mt-12 grid gap-8 md:grid-cols-3">
                        <div class="surface-card-soft p-8">
                            <p class="text-sm font-bold uppercase tracking-wide text-talents-600">Decisões</p>
                            <h3 class="mt-2 text-xl font-semibold text-slate-900">Saiba onde agir primeiro</h3>
                            <p class="mt-3 text-sm leading-relaxed text-slate-600">
                                Painéis e cortes por área mostram prioridades reais — não só “nota média” genérica. Você apresenta
                                números para diretoria e comitê com confiança.
                            </p>
                        </div>
                        <div class="surface-card-soft p-8">
                            <p class="text-sm font-bold uppercase tracking-wide text-talents-600">Confiança</p>
                            <h3 class="mt-2 text-xl font-semibold text-slate-900">Colaboradores respondem com segurança</h3>
                            <p class="mt-3 text-sm leading-relaxed text-slate-600">
                                Fluxo anônimo e regras de agregação que protegem quem fala. Mais participação, feedback mais honesto,
                                menos medo de retaliação.
                            </p>
                        </div>
                        <div class="surface-card-soft p-8">
                            <p class="text-sm font-bold uppercase tracking-wide text-talents-600">Tranquilidade</p>
                            <h3 class="mt-2 text-xl font-semibold text-slate-900">Organização que sustenta auditoria</h3>
                            <p class="mt-3 text-sm leading-relaxed text-slate-600">
                                Campanhas, histórico e relatórios em um só lugar. Menos corre atrás na hora de mostrar evolução ou
                                atender fiscalização.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="py-16">
                <div class="mx-auto max-w-6xl px-4">
                    <h2 class="text-center text-2xl font-bold text-slate-900 md:text-3xl">O que fazemos</h2>
                    <p class="mx-auto mt-3 max-w-3xl text-center text-slate-600">
                        Encontramos, conectamos e desenvolvemos talentos para gerar resultados sustentáveis nas empresas.
                    </p>
                    <div class="mt-10 grid gap-6 md:grid-cols-2">
                        <article class="surface-card-soft p-6">
                            <h3 class="text-lg font-semibold text-slate-900">Consultoria Talents</h3>
                            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                                Entrevistas com líderes e equipes, diagnóstico estratégico, engenharia de cargos, avaliação
                                comportamental e acompanhamento contínuo para fortalecer cultura e performance.
                            </p>
                        </article>
                        <article class="surface-card-soft p-6">
                            <h3 class="text-lg font-semibold text-slate-900">Produtos e entregáveis</h3>
                            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                                Soluções práticas para diagnóstico, contratação, direcionamento estratégico e gestão de riscos
                                psicossociais em um mesmo ecossistema.
                            </p>
                        </article>
                    </div>
                </div>
            </section>

            <section class="py-16">
                <div class="mx-auto max-w-6xl px-4">
                    <h2 class="text-center text-2xl font-bold text-slate-900 md:text-3xl">Como recrutamos</h2>
                    <p class="mx-auto mt-3 max-w-2xl text-center text-slate-600">
                        Um processo estruturado, humano e orientado a dados.
                    </p>
                    <div class="mt-10 grid gap-6 md:grid-cols-3">
                        <article class="surface-card-soft p-6">
                            <p class="text-sm font-bold uppercase tracking-wide text-talents-600">Etapa 01</p>
                            <h3 class="mt-2 text-lg font-semibold text-slate-900">Mapeamento da vaga</h3>
                            <p class="mt-2 text-sm text-slate-600">
                                Alinhamos perfil técnico, comportamental e contexto da operação com a liderança.
                            </p>
                        </article>
                        <article class="surface-card-soft p-6">
                            <p class="text-sm font-bold uppercase tracking-wide text-talents-600">Etapa 02</p>
                            <h3 class="mt-2 text-lg font-semibold text-slate-900">Seleção qualificada</h3>
                            <p class="mt-2 text-sm text-slate-600">
                                Avaliação estruturada para priorizar aderência, potencial e consistência na decisão.
                            </p>
                        </article>
                        <article class="surface-card-soft p-6">
                            <p class="text-sm font-bold uppercase tracking-wide text-talents-600">Etapa 03</p>
                            <h3 class="mt-2 text-lg font-semibold text-slate-900">Acompanhamento de integração</h3>
                            <p class="mt-2 text-sm text-slate-600">
                                Suporte na entrada para acelerar adaptação e reduzir risco de retrabalho.
                            </p>
                        </article>
                    </div>
                </div>
            </section>

            <section class="py-16">
                <div class="mx-auto max-w-6xl px-4">
                    <h2 class="text-center text-2xl font-bold text-slate-900 md:text-3xl">Nossos diferenciais</h2>
                    <div class="mt-10 grid gap-6 md:grid-cols-3">
                        <article class="surface-card-soft p-6">
                            <h3 class="text-lg font-semibold text-slate-900">Rede de talentos qualificados</h3>
                            <p class="mt-2 text-sm text-slate-600">
                                Acesso a perfis com aderência real ao contexto do cliente.
                            </p>
                        </article>
                        <article class="surface-card-soft p-6">
                            <h3 class="text-lg font-semibold text-slate-900">Processo personalizado</h3>
                            <p class="mt-2 text-sm text-slate-600">
                                Metodologia flexível para diferentes fases de negócio e maturidade de gestão.
                            </p>
                        </article>
                        <article class="surface-card-soft p-6">
                            <h3 class="text-lg font-semibold text-slate-900">Agilidade com precisão</h3>
                            <p class="mt-2 text-sm text-slate-600">
                                Decisões mais rápidas sem abrir mão de qualidade técnica e humana.
                            </p>
                        </article>
                    </div>
                </div>
            </section>

            <section class="py-16">
                <div class="mx-auto max-w-6xl px-4">
                    <div class="surface-card-soft p-8 text-center md:p-10">
                        <p class="text-sm font-bold uppercase tracking-wide text-talents-600">Nossos clientes</p>
                        <h2 class="mt-2 text-2xl font-bold text-slate-900 md:text-3xl">
                            Empresas que confiam na Talents para transformar sua gestão de pessoas
                        </h2>
                        <p class="mx-auto mt-4 max-w-2xl text-sm text-slate-600">
                            Faça parte dessa rede e descubra como alinhar cultura, estratégia e performance de forma prática.
                        </p>
                        <a
                            href="https://wa.me/5511975703032"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="btn-primary mt-6"
                        >
                            Falar com a Talents
                        </a>
                    </div>

                    <div class="mx-auto mt-8 grid max-w-4xl grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                        <div
                            class="group flex h-20 items-center justify-center rounded-2xl border border-slate-200/80 bg-white/90 px-3 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-talents-300 hover:shadow-md"
                        >
                            <img
                                src="/images/clientes/fortex.png"
                                alt="Fortex"
                                class="max-h-10 w-auto object-contain transition duration-200 group-hover:scale-105"
                            />
                        </div>
                        <div
                            class="group flex h-20 items-center justify-center rounded-2xl border border-slate-200/80 bg-white/90 px-3 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-talents-300 hover:shadow-md"
                        >
                            <img
                                src="/images/clientes/maismais.png"
                                alt="Mais Mais"
                                class="max-h-10 w-auto object-contain transition duration-200 group-hover:scale-105"
                            />
                        </div>
                        <div
                            class="group flex h-20 items-center justify-center rounded-2xl border border-slate-200/80 bg-white/90 px-3 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-talents-300 hover:shadow-md"
                        >
                            <img
                                src="/images/clientes/multibor.png"
                                alt="Multibor"
                                class="max-h-10 w-auto object-contain transition duration-200 group-hover:scale-105"
                            />
                        </div>
                        <div
                            class="group flex h-20 items-center justify-center rounded-2xl border border-slate-200/80 bg-white/90 px-3 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-talents-300 hover:shadow-md"
                        >
                            <img
                                src="/images/clientes/pasqualino.png"
                                alt="Pasqualino"
                                class="max-h-10 w-auto object-contain transition duration-200 group-hover:scale-105"
                            />
                        </div>
                        <div
                            class="group flex h-20 items-center justify-center rounded-2xl border border-slate-200/80 bg-white/90 px-3 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-talents-300 hover:shadow-md"
                        >
                            <img
                                src="/images/clientes/passeg.png"
                                alt="PASSEG"
                                class="max-h-10 w-auto object-contain transition duration-200 group-hover:scale-105"
                            />
                        </div>
                        <div
                            class="group flex h-20 items-center justify-center rounded-2xl border border-slate-200/80 bg-white/90 px-3 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-talents-300 hover:shadow-md"
                        >
                            <img
                                src="/images/clientes/ramep.jpg"
                                alt="Ramep"
                                class="max-h-10 w-auto object-contain transition duration-200 group-hover:scale-105"
                            />
                        </div>
                        <div
                            class="group flex h-20 items-center justify-center rounded-2xl border border-slate-200/80 bg-white/90 px-3 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-talents-300 hover:shadow-md"
                        >
                            <img
                                src="/images/clientes/soem.png"
                                alt="SOEM"
                                class="max-h-10 w-auto object-contain transition duration-200 group-hover:scale-105"
                            />
                        </div>
                        <div
                            class="group flex h-20 items-center justify-center rounded-2xl border border-slate-200/80 bg-white/90 px-3 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-talents-300 hover:shadow-md"
                        >
                            <img
                                src="/images/clientes/wizzard.png"
                                alt="Wizzard"
                                class="max-h-10 w-auto object-contain transition duration-200 group-hover:scale-105"
                            />
                        </div>
                    </div>
                </div>
            </section>

            <section class="py-16">
                <div class="mx-auto grid max-w-6xl gap-8 px-4 lg:grid-cols-2 lg:items-center">
                    <div class="surface-card-soft p-8">
                        <p class="text-sm font-bold uppercase tracking-wide text-talents-600">Prazer, Suzane Pasqualino</p>
                        <h2 class="mt-2 text-2xl font-bold text-slate-900 md:text-3xl">Consultoria com ciência e estratégia</h2>
                        <p class="mt-4 text-sm leading-relaxed text-slate-600">
                            Psicóloga (2013), especialista em comportamento humano, CEO da Talents e Diretora de RH no Pasqualino.
                            A trajetória é guiada por escuta ativa, acolhimento e construção de ambientes mais conscientes e
                            estratégicos.
                        </p>
                        <p class="mt-4 text-sm leading-relaxed text-slate-600">
                            Missão: Conectar talentos e transformar negócios. Visão: ser referência no desenvolvimento de talentos.
                            Valores: família, honestidade, confiança e excelência.
                        </p>
                    </div>
                    <div class="space-y-6">
                        <div class="surface-card-soft p-6">
                            <p class="text-sm font-bold uppercase tracking-wide text-talents-600">Banco de Vagas</p>
                            <h3 class="mt-2 text-lg font-semibold text-slate-900">Conectamos talentos às melhores oportunidades</h3>
                            <p class="mt-3 text-sm leading-relaxed text-slate-600">
                                Explore oportunidades por área, localização e nível de experiência na plataforma de vagas da Talents.
                            </p>
                            <a
                                href="https://talents.vagas.solides.com.br/"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="mt-4 inline-block text-sm font-semibold text-talents-700 hover:underline"
                            >
                                Acessar banco de vagas →
                            </a>
                        </div>
                        <div class="surface-card-soft p-6">
                            <p class="text-sm font-bold uppercase tracking-wide text-talents-600">Canais de contato</p>
                            <p class="mt-2 text-sm text-slate-600">contato@talentsgestao.com</p>
                            <p class="mt-1 text-sm text-slate-600">Várzea Paulista • SP</p>
                            <a
                                href="https://wa.me/5511975703032"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="btn-primary mt-4 px-5 py-2.5"
                            >
                                Falar com a Talents no WhatsApp
                            </a>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Formulário (estilo original da seção de contato) -->
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

                    <form class="surface-card-soft mt-8 space-y-5 p-6 sm:p-8" @submit.prevent="submitInterest">
                        <div>
                            <label class="block text-sm font-medium text-slate-700" for="interest-name">Nome</label>
                            <input
                                id="interest-name"
                                v-model="form.name"
                                type="text"
                                required
                                autocomplete="name"
                                class="field-input"
                            />
                            <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700" for="interest-email">E-mail</label>
                            <input
                                id="interest-email"
                                v-model="form.email"
                                type="email"
                                required
                                autocomplete="email"
                                class="field-input"
                            />
                            <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700" for="interest-phone"
                                >Telefone / WhatsApp <span class="font-normal text-slate-500">(opcional)</span></label
                            >
                            <input
                                id="interest-phone"
                                v-model="form.phone"
                                type="tel"
                                autocomplete="tel"
                                placeholder="DDD + número"
                                class="field-input"
                            />
                            <p v-if="form.errors.phone" class="mt-1 text-sm text-red-600">{{ form.errors.phone }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700" for="interest-company"
                                >Empresa <span class="font-normal text-slate-500">(opcional)</span></label
                            >
                            <input
                                id="interest-company"
                                v-model="form.company"
                                type="text"
                                autocomplete="organization"
                                class="field-input"
                            />
                            <p v-if="form.errors.company" class="mt-1 text-sm text-red-600">{{ form.errors.company }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700" for="interest-message"
                                >Mensagem <span class="font-normal text-slate-500">(opcional)</span></label
                            >
                            <textarea
                                id="interest-message"
                                v-model="form.message"
                                rows="4"
                                class="field-input"
                                placeholder="Conte um pouco do que você busca ou deixe em branco."
                            />
                            <p v-if="form.errors.message" class="mt-1 text-sm text-red-600">{{ form.errors.message }}</p>
                        </div>
                        <div class="pt-2">
                            <button
                                type="submit"
                                class="btn-primary w-full disabled:opacity-60 sm:w-auto"
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
                    class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-t-2xl bg-white shadow-xl sm:rounded-2xl"
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
                            ✕
                        </button>
                    </div>
                    <form class="space-y-4 px-5 py-5" @submit.prevent="submitInterest">
                        <div>
                            <label class="block text-sm font-medium text-slate-700" for="modal-name">Nome</label>
                            <input
                                id="modal-name"
                                v-model="form.name"
                                type="text"
                                required
                                autocomplete="name"
                                class="field-input"
                            />
                            <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700" for="modal-email">E-mail</label>
                            <input
                                id="modal-email"
                                v-model="form.email"
                                type="email"
                                required
                                autocomplete="email"
                                class="field-input"
                            />
                            <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700" for="modal-phone"
                                >Telefone / WhatsApp <span class="font-normal text-slate-500">(opcional)</span></label
                            >
                            <input
                                id="modal-phone"
                                v-model="form.phone"
                                type="tel"
                                autocomplete="tel"
                                class="field-input"
                            />
                            <p v-if="form.errors.phone" class="mt-1 text-sm text-red-600">{{ form.errors.phone }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700" for="modal-company">Empresa (opcional)</label>
                            <input
                                id="modal-company"
                                v-model="form.company"
                                type="text"
                                class="field-input"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700" for="modal-message">Mensagem (opcional)</label>
                            <textarea
                                id="modal-message"
                                v-model="form.message"
                                rows="3"
                                class="field-input"
                            />
                        </div>
                        <button
                            type="submit"
                            class="btn-primary w-full disabled:opacity-60"
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
