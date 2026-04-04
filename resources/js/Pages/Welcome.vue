<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
});
</script>

<template>
    <Head title="Talents — Gestão de Pessoas e NR-1" />

    <div class="min-h-screen bg-white text-gray-900">
        <header class="border-b border-gray-200 bg-white shadow-sm">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
                <img src="/images/logo.png" alt="Talents" class="h-10 w-auto" />
                <nav class="flex items-center gap-3 sm:gap-4">
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
                        class="rounded-md bg-talents-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-talents-700"
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
                    <p class="text-sm font-semibold uppercase tracking-widest text-talents-600">
                        Para quem leva pessoas a sério
                    </p>
                    <h1 class="mt-4 text-4xl font-bold leading-tight text-gray-900 md:text-5xl">
                        Menos improviso. Mais clareza sobre o clima e os riscos na sua empresa.
                    </h1>
                    <p class="mt-6 text-lg text-gray-600">
                        A Talents ajuda você a ouvir o time com segurança, enxergar onde investir em bem-estar e
                        documentar o que a lei exige — sem planilhas intermináveis nem relatórios que ninguém usa.
                    </p>
                    <div class="mt-8 flex flex-wrap gap-4">
                        <Link
                            v-if="canLogin && !$page.props.auth.user"
                            :href="route('login')"
                            class="rounded-xl bg-talents-600 px-6 py-3 text-sm font-bold text-white shadow-lg hover:bg-talents-700"
                        >
                            Ver na prática
                        </Link>
                        <Link
                            v-if="canRegister && !$page.props.auth.user"
                            :href="route('register')"
                            class="rounded-xl border-2 border-talents-600 px-6 py-3 text-sm font-bold text-talents-700 hover:bg-talents-50"
                        >
                            Começar agora
                        </Link>
                    </div>
                    <p class="mt-6 text-sm text-gray-500">
                        Pesquisas com anonimato protegido · Resultados prontos para decisão · Adequação às exigências de
                        saúde e segurança do trabalho
                    </p>
                </div>
            </section>

            <section class="border-y border-gray-100 bg-gray-50 py-16">
                <div class="mx-auto max-w-6xl px-4">
                    <h2 class="text-center text-2xl font-bold text-gray-900 md:text-3xl">
                        O que você ganha no dia a dia
                    </h2>
                    <p class="mx-auto mt-3 max-w-2xl text-center text-gray-600">
                        Ferramentas pensadas para RH, SESMT e liderança — linguagem humana, números que importam.
                    </p>
                    <div class="mt-12 grid gap-8 md:grid-cols-3">
                        <div class="rounded-2xl border border-gray-200 bg-white p-8 shadow-sm">
                            <p class="text-sm font-bold uppercase tracking-wide text-talents-600">Decisões</p>
                            <h3 class="mt-2 text-xl font-semibold text-gray-900">Saiba onde agir primeiro</h3>
                            <p class="mt-3 text-sm leading-relaxed text-gray-600">
                                Painéis e cortes por área mostram prioridades reais — não só “nota média” genérica.
                                Você apresenta números para diretoria e comitê com confiança.
                            </p>
                        </div>
                        <div class="rounded-2xl border border-gray-200 bg-white p-8 shadow-sm">
                            <p class="text-sm font-bold uppercase tracking-wide text-talents-600">Confiança</p>
                            <h3 class="mt-2 text-xl font-semibold text-gray-900">Colaboradores respondem com segurança</h3>
                            <p class="mt-3 text-sm leading-relaxed text-gray-600">
                                Fluxo anônimo e regras de agregação que protegem quem fala. Mais participação, feedback
                                mais honesto, menos medo de retaliação.
                            </p>
                        </div>
                        <div class="rounded-2xl border border-gray-200 bg-white p-8 shadow-sm">
                            <p class="text-sm font-bold uppercase tracking-wide text-talents-600">Tranquilidade</p>
                            <h3 class="mt-2 text-xl font-semibold text-gray-900">Organização que sustenta auditoria</h3>
                            <p class="mt-3 text-sm leading-relaxed text-gray-600">
                                Campanhas, histórico e relatórios em um só lugar. Menos corre atrás na hora de mostrar
                                evolução ou atender fiscalização.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mx-auto max-w-6xl px-4 py-16">
                <div class="grid gap-12 md:grid-cols-2 md:items-center">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900 md:text-3xl">Simples de implantar</h2>
                        <ol class="mt-8 space-y-6">
                            <li class="flex gap-4">
                                <span
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-talents-600 text-sm font-bold text-white"
                                    >1</span
                                >
                                <div>
                                    <p class="font-semibold text-gray-900">Configure a pesquisa</p>
                                    <p class="mt-1 text-sm text-gray-600">
                                        Modelos prontos alinhados a boas práticas internacionais — você adapta ao seu
                                        contexto.
                                    </p>
                                </div>
                            </li>
                            <li class="flex gap-4">
                                <span
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-talents-600 text-sm font-bold text-white"
                                    >2</span
                                >
                                <div>
                                    <p class="font-semibold text-gray-900">Divulgue o link</p>
                                    <p class="mt-1 text-sm text-gray-600">
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
                                    <p class="font-semibold text-gray-900">Aja com dados</p>
                                    <p class="mt-1 text-sm text-gray-600">
                                        Exporte, compartilhe com o PGR e transforme achados em plano de ação mensurável.
                                    </p>
                                </div>
                            </li>
                        </ol>
                    </div>
                    <div class="rounded-2xl border border-talents-200 bg-gradient-to-br from-talents-50 to-white p-8 md:p-10">
                        <p class="text-lg font-semibold text-gray-900">Pronto para reduzir o ruído entre RH e operação?</p>
                        <p class="mt-4 text-sm leading-relaxed text-gray-600">
                            Se sua equipe já perde horas montando formulários e cruzando planilhas, o Talents centraliza o
                            ciclo inteiro — da coleta ao relatório que sustenta decisões e conformidade.
                        </p>
                        <div class="mt-8 flex flex-wrap gap-3">
                            <Link
                                v-if="canLogin && !$page.props.auth.user"
                                :href="route('login')"
                                class="rounded-lg bg-talents-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-talents-700"
                            >
                                Acessar a plataforma
                            </Link>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-gray-200 py-8 text-center text-xs text-gray-500">
            Talents &mdash; Gestão de Pessoas
        </footer>
    </div>
</template>
