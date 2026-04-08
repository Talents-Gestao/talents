<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
});
</script>

<template>
    <Head title="Talents — Gestão de Pessoas e NR-1" />

    <div class="min-h-screen bg-zinc-50 text-zinc-900">
        <header class="border-b border-zinc-200/90 bg-white/90 backdrop-blur-sm">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-5 sm:px-6">
                <img src="/images/logo.png" alt="Talents" class="h-9 w-auto" />
                <nav class="flex items-center gap-4 sm:gap-6">
                    <Link
                        v-if="canRegister && !$page.props.auth.user"
                        :href="route('register')"
                        class="text-sm font-medium text-zinc-600 transition hover:text-talents-700"
                    >
                        Criar conta
                    </Link>
                    <Link
                        v-if="canLogin && !$page.props.auth.user"
                        :href="route('login')"
                        class="rounded-lg bg-talents-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-talents-700 focus:outline-none focus:ring-2 focus:ring-talents-500/50 focus:ring-offset-2"
                    >
                        Entrar
                    </Link>
                    <Link
                        v-if="$page.props.auth.user"
                        :href="route('dashboard')"
                        class="text-sm font-medium text-talents-700 transition hover:text-talents-800"
                    >
                        Painel
                    </Link>
                </nav>
            </div>
        </header>

        <main>
            <section class="mx-auto max-w-6xl px-4 py-20 md:py-28">
                <div class="max-w-3xl">
                    <p class="text-xs font-medium tracking-wide text-talents-700">
                        Para quem leva pessoas a sério
                    </p>
                    <h1 class="mt-4 text-4xl font-semibold leading-[1.15] tracking-tight text-zinc-900 md:text-5xl">
                        Menos improviso. Mais clareza sobre o clima e os riscos na sua empresa.
                    </h1>
                    <p class="mt-6 text-lg leading-relaxed text-zinc-600">
                        A Talents ajuda você a ouvir o time com segurança, enxergar onde investir em bem-estar e
                        documentar o que a lei exige — sem planilhas intermináveis nem relatórios que ninguém usa.
                    </p>
                    <div class="mt-10 flex flex-wrap gap-3">
                        <Link
                            v-if="canLogin && !$page.props.auth.user"
                            :href="route('login')"
                            class="rounded-lg bg-talents-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-talents-700 focus:outline-none focus:ring-2 focus:ring-talents-500/45 focus:ring-offset-2"
                        >
                            Ver na prática
                        </Link>
                        <Link
                            v-if="canRegister && !$page.props.auth.user"
                            :href="route('register')"
                            class="rounded-lg border border-zinc-300 bg-white px-6 py-3 text-sm font-semibold text-zinc-800 transition hover:border-zinc-400 hover:bg-zinc-50"
                        >
                            Começar agora
                        </Link>
                    </div>
                    <p class="mt-8 text-sm text-zinc-500">
                        Pesquisas com anonimato protegido · Resultados prontos para decisão · Adequação às exigências de
                        saúde e segurança do trabalho
                    </p>
                </div>
            </section>

            <section class="border-y border-zinc-200/80 bg-white py-20">
                <div class="mx-auto max-w-6xl px-4 sm:px-6">
                    <h2 class="text-center text-2xl font-semibold tracking-tight text-zinc-900 md:text-3xl">
                        O que você ganha no dia a dia
                    </h2>
                    <p class="mx-auto mt-3 max-w-2xl text-center text-zinc-600">
                        Ferramentas pensadas para RH, SESMT e liderança — linguagem humana, números que importam.
                    </p>
                    <div class="mt-14 grid gap-6 md:grid-cols-3">
                        <div class="surface-card p-8">
                            <p class="text-xs font-semibold uppercase tracking-wide text-talents-700">Decisões</p>
                            <h3 class="mt-3 text-lg font-semibold text-zinc-900">Saiba onde agir primeiro</h3>
                            <p class="mt-3 text-sm leading-relaxed text-zinc-600">
                                Painéis e cortes por área mostram prioridades reais — não só “nota média” genérica.
                                Você apresenta números para diretoria e comitê com confiança.
                            </p>
                        </div>
                        <div class="surface-card p-8">
                            <p class="text-xs font-semibold uppercase tracking-wide text-talents-700">Confiança</p>
                            <h3 class="mt-3 text-lg font-semibold text-zinc-900">Colaboradores respondem com segurança</h3>
                            <p class="mt-3 text-sm leading-relaxed text-zinc-600">
                                Fluxo anônimo e regras de agregação que protegem quem fala. Mais participação, feedback
                                mais honesto, menos medo de retaliação.
                            </p>
                        </div>
                        <div class="surface-card p-8">
                            <p class="text-xs font-semibold uppercase tracking-wide text-talents-700">Tranquilidade</p>
                            <h3 class="mt-3 text-lg font-semibold text-zinc-900">Organização que sustenta auditoria</h3>
                            <p class="mt-3 text-sm leading-relaxed text-zinc-600">
                                Campanhas, histórico e relatórios em um só lugar. Menos corre atrás na hora de mostrar
                                evolução ou atender fiscalização.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mx-auto max-w-6xl px-4 py-20 sm:px-6">
                <div class="grid gap-14 md:grid-cols-2 md:items-center">
                    <div>
                        <h2 class="text-2xl font-semibold tracking-tight text-zinc-900 md:text-3xl">Simples de implantar</h2>
                        <ol class="mt-10 space-y-8">
                            <li class="flex gap-4">
                                <span
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-talents-600 text-sm font-semibold text-white"
                                    >1</span
                                >
                                <div>
                                    <p class="font-medium text-zinc-900">Configure a pesquisa</p>
                                    <p class="mt-1 text-sm text-zinc-600">
                                        Modelos prontos alinhados a boas práticas internacionais — você adapta ao seu
                                        contexto.
                                    </p>
                                </div>
                            </li>
                            <li class="flex gap-4">
                                <span
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-talents-600 text-sm font-semibold text-white"
                                    >2</span
                                >
                                <div>
                                    <p class="font-medium text-zinc-900">Divulgue o link</p>
                                    <p class="mt-1 text-sm text-zinc-600">
                                        Os colaboradores respondem no tempo deles; você acompanha taxa de participação.
                                    </p>
                                </div>
                            </li>
                            <li class="flex gap-4">
                                <span
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-talents-600 text-sm font-semibold text-white"
                                    >3</span
                                >
                                <div>
                                    <p class="font-medium text-zinc-900">Aja com dados</p>
                                    <p class="mt-1 text-sm text-zinc-600">
                                        Exporte, compartilhe com o PGR e transforme achados em plano de ação mensurável.
                                    </p>
                                </div>
                            </li>
                        </ol>
                    </div>
                    <div class="surface-card bg-gradient-to-br from-talents-50/80 to-white p-8 md:p-10">
                        <p class="text-lg font-semibold text-zinc-900">Pronto para reduzir o ruído entre RH e operação?</p>
                        <p class="mt-4 text-sm leading-relaxed text-zinc-600">
                            Se sua equipe já perde horas montando formulários e cruzando planilhas, o Talents centraliza o
                            ciclo inteiro — da coleta ao relatório que sustenta decisões e conformidade.
                        </p>
                        <div class="mt-8 flex flex-wrap gap-3">
                            <Link
                                v-if="canLogin && !$page.props.auth.user"
                                :href="route('login')"
                                class="rounded-lg bg-talents-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-talents-700"
                            >
                                Acessar a plataforma
                            </Link>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-zinc-200/90 py-10 text-center text-xs text-zinc-500">
            Talents &mdash; Gestão de Pessoas
        </footer>
    </div>
</template>
