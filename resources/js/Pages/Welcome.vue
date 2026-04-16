<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

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

const maturity = ref({
    responsavel: false,
    formalizouPgr: false,
    classificouExposicao: false,
    planoAcao: false,
    treinouLiderancas: false,
    monitoraIndicadores: false,
});

const maturityCount = computed(() => Object.values(maturity.value).filter(Boolean).length);
const maturityVulnerable = computed(() => maturityCount.value < 6);

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
    <Head title="Talents — Gestão de Pessoas e NR-1" />

    <div class="app-shell min-h-screen scroll-smooth text-slate-900">
        <header class="sticky top-0 z-20 border-b border-white/40 bg-white/70 shadow-sm backdrop-blur-md">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-4">
                <img src="/images/logo.png" alt="Talents" class="h-10 w-auto" />
                <nav class="flex flex-wrap items-center justify-end gap-2 sm:gap-4">
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
            <!-- Hero (layout original: largura contida, fundo do app-shell) -->
            <section class="mx-auto max-w-6xl px-4 py-16 md:py-20">
                <div class="max-w-3xl">
                    <p class="text-sm font-semibold uppercase tracking-widest text-talents-600">
                        NR-1 · Para quem leva pessoas a sério
                    </p>
                    <h1 class="mt-4 text-4xl font-bold leading-tight text-slate-900 md:text-5xl">
                        A Talents ajuda empresas a caminhar rumo à
                        <span class="text-talents-700">conformidade com a NR-1</span> na gestão de riscos psicossociais
                    </h1>
                    <p class="mt-6 text-lg text-slate-600">
                        Com <strong>método</strong>, <strong>ciência</strong> e <strong>estratégia</strong> — da identificação ao
                        monitoramento, com rastreabilidade para o PGR.
                    </p>

                    <div class="mt-6 flex flex-wrap items-center gap-2 md:gap-3">
                        <span
                            class="inline-flex items-center rounded-full border-2 border-talents-600 bg-talents-50 px-4 py-2 text-sm font-bold text-talents-800 shadow-sm"
                            >IDENTIFICAR</span
                        >
                        <span class="text-talents-400">→</span>
                        <span
                            class="inline-flex items-center rounded-full border-2 border-talents-600 bg-talents-50 px-4 py-2 text-sm font-bold text-talents-800 shadow-sm"
                            >📊 AVALIAR</span
                        >
                        <span class="text-talents-400">→</span>
                        <span
                            class="inline-flex items-center rounded-full border-2 border-talents-600 bg-talents-50 px-4 py-2 text-sm font-bold text-talents-800 shadow-sm"
                            >⚙️ IMPLEMENTAR</span
                        >
                        <span class="text-talents-400">→</span>
                        <span
                            class="inline-flex items-center rounded-full border-2 border-talents-600 bg-talents-50 px-4 py-2 text-sm font-bold text-talents-800 shadow-sm"
                            >📈 MONITORAR</span
                        >
                    </div>

                    <blockquote class="mt-6 border-l-4 border-talents-600 pl-4 text-lg italic text-slate-700">
                        Gestão de risco psicossocial não é evento.
                        <span class="font-semibold not-italic">É processo.</span>
                    </blockquote>

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

            <!-- Bloco original: o que você ganha -->
            <section class="border-y border-white/30 bg-white/40 py-16 backdrop-blur-sm">
                <div class="mx-auto max-w-6xl px-4">
                    <h2 class="text-center text-2xl font-bold text-slate-900 md:text-3xl">O que você ganha no dia a dia</h2>
                    <p class="mx-auto mt-3 max-w-2xl text-center text-slate-600">
                        Ferramentas pensadas para RH, SESMT e liderança — linguagem humana, números que importam.
                    </p>
                    <div class="mt-12 grid gap-8 md:grid-cols-3">
                        <div class="surface-card p-8 shadow-md">
                            <p class="text-sm font-bold uppercase tracking-wide text-talents-600">Decisões</p>
                            <h3 class="mt-2 text-xl font-semibold text-slate-900">Saiba onde agir primeiro</h3>
                            <p class="mt-3 text-sm leading-relaxed text-slate-600">
                                Painéis e cortes por área mostram prioridades reais — não só “nota média” genérica. Você apresenta
                                números para diretoria e comitê com confiança.
                            </p>
                        </div>
                        <div class="surface-card p-8 shadow-md">
                            <p class="text-sm font-bold uppercase tracking-wide text-talents-600">Confiança</p>
                            <h3 class="mt-2 text-xl font-semibold text-slate-900">Colaboradores respondem com segurança</h3>
                            <p class="mt-3 text-sm leading-relaxed text-slate-600">
                                Fluxo anônimo e regras de agregação que protegem quem fala. Mais participação, feedback mais honesto,
                                menos medo de retaliação.
                            </p>
                        </div>
                        <div class="surface-card p-8 shadow-md">
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

            <!-- NR-1: cinco cards lado a lado (grade responsiva) -->
            <section class="border-y border-white/30 bg-white/40 py-16 backdrop-blur-sm">
                <div class="mx-auto max-w-[1600px] px-4">
                    <div
                        class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 xl:gap-5"
                    >
                        <article class="surface-card flex h-full flex-col border-t-4 border-talents-600 p-5 shadow-md sm:p-6">
                            <h3 class="text-base font-bold leading-snug text-slate-900 md:text-lg">
                                1️⃣ Buscar orientação técnica especializada
                            </h3>
                            <p class="mt-3 flex-1 text-sm leading-relaxed text-slate-600">
                                A NR-1 exige método e estrutura. Ter apoio especializado evita improviso e exposição jurídica.
                            </p>
                        </article>

                        <article class="surface-card flex h-full flex-col border-t-4 border-red-500 p-5 shadow-md sm:p-6">
                            <h3 class="text-base font-bold leading-snug text-slate-900 md:text-lg">2️⃣ IDENTIFICAR</h3>
                            <ul class="mt-3 list-inside list-disc space-y-1.5 text-sm text-slate-600">
                                <li>Sobrecarga e pressão excessiva</li>
                                <li>Conflitos recorrentes</li>
                                <li>Liderança despreparada</li>
                                <li>Lacunas no PGR</li>
                            </ul>
                        </article>

                        <article class="surface-card flex h-full flex-col border-t-4 border-amber-500 p-5 shadow-md sm:p-6">
                            <h3 class="text-base font-bold leading-snug text-slate-900 md:text-lg">3️⃣ AVALIAR</h3>
                            <ul class="mt-3 list-inside list-disc space-y-1.5 text-sm text-slate-600">
                                <li>Classificar nível de exposição (baixo, médio, alto)</li>
                                <li>Definir prioridades</li>
                                <li>Avaliar impacto organizacional</li>
                            </ul>
                        </article>

                        <article class="surface-card flex h-full flex-col border-t-4 border-emerald-600 p-5 shadow-md sm:p-6">
                            <h3 class="text-base font-bold leading-snug text-slate-900 md:text-lg">4️⃣ IMPLEMENTAR</h3>
                            <ul class="mt-3 list-inside list-disc space-y-1.5 text-sm text-slate-600">
                                <li>Estruturar plano de ação</li>
                                <li>Treinamentos e ajustes organizacionais</li>
                                <li>Formalização no PGR</li>
                            </ul>
                        </article>

                        <article class="surface-card flex h-full flex-col border-t-4 border-blue-600 p-5 shadow-md sm:p-6">
                            <h3 class="text-base font-bold leading-snug text-slate-900 md:text-lg">5️⃣ MONITORAR</h3>
                            <ul class="mt-3 list-inside list-disc space-y-1.5 text-sm text-slate-600">
                                <li>Acompanhar indicadores humanos</li>
                                <li>Reavaliar periodicamente</li>
                                <li>Atualizar plano de ação</li>
                            </ul>
                        </article>
                    </div>
                </div>
            </section>

            <!-- Simples de implantar (layout original) -->
            <section class="mx-auto max-w-6xl px-4 py-16">
                <div class="grid gap-12 md:grid-cols-2 md:items-center">
                    <div>
                        <h2 class="text-2xl font-bold text-slate-900 md:text-3xl">Simples de implantar</h2>
                        <ol class="mt-8 space-y-6">
                            <li class="flex gap-4">
                                <span
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-talents-600 text-sm font-bold text-white"
                                    >1</span
                                >
                                <div>
                                    <p class="font-semibold text-slate-900">Configure a pesquisa</p>
                                    <p class="mt-1 text-sm text-slate-600">
                                        Modelos prontos alinhados a boas práticas internacionais — você adapta ao seu contexto.
                                    </p>
                                </div>
                            </li>
                            <li class="flex gap-4">
                                <span
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-talents-600 text-sm font-bold text-white"
                                    >2</span
                                >
                                <div>
                                    <p class="font-semibold text-slate-900">Divulgue o link</p>
                                    <p class="mt-1 text-sm text-slate-600">
                                        Os colaboradores respondem no tempo deles; você acompanha taxa de participação.
                                    </p>
                                </div>
                            </li>
                            <li class="flex gap-4">
                                <span
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-talents-600 text-sm font-bold text-white"
                                    >3</span
                                >
                                <div>
                                    <p class="font-semibold text-slate-900">Aja com dados</p>
                                    <p class="mt-1 text-sm text-slate-600">
                                        Exporte, compartilhe com o PGR e transforme achados em plano de ação mensurável.
                                    </p>
                                </div>
                            </li>
                        </ol>
                    </div>
                    <div class="surface-glass border-talents-200/50 p-8 md:p-10">
                        <p class="text-lg font-semibold text-slate-900">Pronto para reduzir o ruído entre RH e operação?</p>
                        <p class="mt-4 text-sm leading-relaxed text-slate-600">
                            Se sua equipe já perde horas montando formulários e cruzando planilhas, o Talents centraliza o ciclo
                            inteiro — da coleta ao relatório que sustenta decisões e conformidade.
                        </p>
                        <div class="mt-8 flex flex-wrap gap-3">
                            <Link
                                v-if="canLogin && !$page.props.auth.user"
                                :href="route('login')"
                                class="rounded-full bg-talents-600 px-5 py-2.5 text-sm font-bold text-white shadow-md transition hover:bg-talents-700"
                            >
                                Acessar a plataforma
                            </Link>
                            <button
                                type="button"
                                class="rounded-full border-2 border-talents-600 bg-talents-50 px-5 py-2.5 text-sm font-bold text-talents-800 shadow-sm transition hover:bg-talents-100"
                                @click="showContactModal = true"
                            >
                                Fale com um especialista
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Checklist -->
            <section class="border-y border-white/30 bg-white/40 py-16 backdrop-blur-sm">
                <div class="mx-auto max-w-3xl px-4">
                    <h2 class="text-center text-2xl font-bold text-slate-900 md:text-3xl">CHECKLIST DE MATURIDADE</h2>
                    <p class="mx-auto mt-3 max-w-xl text-center text-sm text-slate-500">
                        Marque o que se aplica à sua organização.
                    </p>

                    <div class="surface-card mt-8 space-y-4 p-6 shadow-md sm:p-8">
                        <label class="flex cursor-pointer gap-3 text-slate-700">
                            <input v-model="maturity.responsavel" type="checkbox" class="mt-1 rounded border-slate-300 text-talents-600 focus:ring-talents-500" />
                            <span>Possui responsável pela gestão de riscos psicossociais?</span>
                        </label>
                        <label class="flex cursor-pointer gap-3 text-slate-700">
                            <input v-model="maturity.formalizouPgr" type="checkbox" class="mt-1 rounded border-slate-300 text-talents-600 focus:ring-talents-500" />
                            <span>Formalizou esses riscos no PGR?</span>
                        </label>
                        <label class="flex cursor-pointer gap-3 text-slate-700">
                            <input v-model="maturity.classificouExposicao" type="checkbox" class="mt-1 rounded border-slate-300 text-talents-600 focus:ring-talents-500" />
                            <span>Classificou nível de exposição?</span>
                        </label>
                        <label class="flex cursor-pointer gap-3 text-slate-700">
                            <input v-model="maturity.planoAcao" type="checkbox" class="mt-1 rounded border-slate-300 text-talents-600 focus:ring-talents-500" />
                            <span>Possui plano de ação estruturado?</span>
                        </label>
                        <label class="flex cursor-pointer gap-3 text-slate-700">
                            <input v-model="maturity.treinouLiderancas" type="checkbox" class="mt-1 rounded border-slate-300 text-talents-600 focus:ring-talents-500" />
                            <span>Treinou lideranças?</span>
                        </label>
                        <label class="flex cursor-pointer gap-3 text-slate-700">
                            <input v-model="maturity.monitoraIndicadores" type="checkbox" class="mt-1 rounded border-slate-300 text-talents-600 focus:ring-talents-500" />
                            <span>Monitora indicadores humanos?</span>
                        </label>

                        <p class="mt-6 border-t border-slate-200 pt-6 text-center text-sm font-medium text-slate-600">
                            Itens marcados: <strong class="text-slate-900">{{ maturityCount }}</strong> / 6
                        </p>
                        <p
                            v-if="maturityVulnerable"
                            class="rounded-lg bg-amber-50 px-4 py-3 text-center text-sm font-semibold text-amber-900"
                        >
                            Se marcou menos de 6 itens, sua empresa ainda está vulnerável.
                        </p>
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

                    <form class="surface-card mt-8 space-y-5 p-6 shadow-md sm:p-8" @submit.prevent="submitInterest">
                        <div>
                            <label class="block text-sm font-medium text-slate-700" for="interest-name">Nome</label>
                            <input
                                id="interest-name"
                                v-model="form.name"
                                type="text"
                                required
                                autocomplete="name"
                                class="mt-1 w-full rounded-lg border-slate-200 shadow-sm focus:border-talents-500 focus:ring-talents-500"
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
                                class="mt-1 w-full rounded-lg border-slate-200 shadow-sm focus:border-talents-500 focus:ring-talents-500"
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
                                class="mt-1 w-full rounded-lg border-slate-200 shadow-sm focus:border-talents-500 focus:ring-talents-500"
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
                                class="mt-1 w-full rounded-lg border-slate-200 shadow-sm focus:border-talents-500 focus:ring-talents-500"
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
                                class="mt-1 w-full rounded-lg border-slate-200 shadow-sm focus:border-talents-500 focus:ring-talents-500"
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
                                class="mt-1 w-full rounded-lg border-slate-200 shadow-sm focus:border-talents-500 focus:ring-talents-500"
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
                                class="mt-1 w-full rounded-lg border-slate-200 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            />
                            <p v-if="form.errors.phone" class="mt-1 text-sm text-red-600">{{ form.errors.phone }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700" for="modal-company">Empresa (opcional)</label>
                            <input
                                id="modal-company"
                                v-model="form.company"
                                type="text"
                                class="mt-1 w-full rounded-lg border-slate-200 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700" for="modal-message">Mensagem (opcional)</label>
                            <textarea
                                id="modal-message"
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
