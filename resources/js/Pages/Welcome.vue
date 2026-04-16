<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
});

const form = useForm({
    name: '',
    email: '',
    company: '',
    message: '',
});

const submitInterest = () => {
    form.post(route('landing.interest'), {
        preserveScroll: true,
        onSuccess: () => form.reset('name', 'email', 'company', 'message'),
    });
};
</script>

<template>
    <Head title="Talents — Gestão de Pessoas e NR-1" />

    <div class="app-shell min-h-screen text-slate-900">
        <header class="sticky top-0 z-20 border-b border-white/40 bg-white/70 shadow-sm backdrop-blur-md">
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
                    <p class="text-sm font-semibold uppercase tracking-widest text-talents-600">
                        Para quem leva pessoas a sério
                    </p>
                    <h1 class="mt-4 text-4xl font-bold leading-tight text-slate-900 md:text-5xl">
                        Menos improviso. Mais clareza sobre o clima e os riscos na sua empresa.
                    </h1>
                    <p class="mt-6 text-lg text-slate-600">
                        A Talents ajuda você a ouvir o time com segurança, enxergar onde investir em bem-estar e
                        documentar o que a lei exige — sem planilhas intermináveis nem relatórios que ninguém usa.
                    </p>
                    <div class="mt-8 flex flex-wrap gap-4">
                        <Link
                            v-if="canLogin && !$page.props.auth.user"
                            :href="route('login')"
                            class="rounded-full bg-talents-600 px-6 py-3 text-sm font-bold text-white shadow-lg transition hover:bg-talents-700"
                        >
                            Ver na prática
                        </Link>
                        <Link
                            v-if="canRegister && !$page.props.auth.user"
                            :href="route('register')"
                            class="rounded-full border-2 border-talents-600 px-6 py-3 text-sm font-bold text-talents-700 transition hover:bg-talents-50"
                        >
                            Começar agora
                        </Link>
                    </div>
                    <p class="mt-6 text-sm text-slate-500">
                        Pesquisas com anonimato protegido · Resultados prontos para decisão · Adequação às exigências de
                        saúde e segurança do trabalho
                    </p>
                </div>
            </section>

            <section class="border-y border-white/30 bg-white/40 py-16 backdrop-blur-sm">
                <div class="mx-auto max-w-6xl px-4">
                    <h2 class="text-center text-2xl font-bold text-slate-900 md:text-3xl">
                        O que você ganha no dia a dia
                    </h2>
                    <p class="mx-auto mt-3 max-w-2xl text-center text-slate-600">
                        Ferramentas pensadas para RH, SESMT e liderança — linguagem humana, números que importam.
                    </p>
                    <div class="mt-12 grid gap-8 md:grid-cols-3">
                        <div class="surface-card p-8 shadow-md">
                            <p class="text-sm font-bold uppercase tracking-wide text-talents-600">Decisões</p>
                            <h3 class="mt-2 text-xl font-semibold text-slate-900">Saiba onde agir primeiro</h3>
                            <p class="mt-3 text-sm leading-relaxed text-slate-600">
                                Painéis e cortes por área mostram prioridades reais — não só “nota média” genérica.
                                Você apresenta números para diretoria e comitê com confiança.
                            </p>
                        </div>
                        <div class="surface-card p-8 shadow-md">
                            <p class="text-sm font-bold uppercase tracking-wide text-talents-600">Confiança</p>
                            <h3 class="mt-2 text-xl font-semibold text-slate-900">Colaboradores respondem com segurança</h3>
                            <p class="mt-3 text-sm leading-relaxed text-slate-600">
                                Fluxo anônimo e regras de agregação que protegem quem fala. Mais participação, feedback
                                mais honesto, menos medo de retaliação.
                            </p>
                        </div>
                        <div class="surface-card p-8 shadow-md">
                            <p class="text-sm font-bold uppercase tracking-wide text-talents-600">Tranquilidade</p>
                            <h3 class="mt-2 text-xl font-semibold text-slate-900">Organização que sustenta auditoria</h3>
                            <p class="mt-3 text-sm leading-relaxed text-slate-600">
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
                            Se sua equipe já perde horas montando formulários e cruzando planilhas, o Talents centraliza o
                            ciclo inteiro — da coleta ao relatório que sustenta decisões e conformidade.
                        </p>
                        <div class="mt-8 flex flex-wrap gap-3">
                            <Link
                                v-if="canLogin && !$page.props.auth.user"
                                :href="route('login')"
                                class="rounded-full bg-talents-600 px-5 py-2.5 text-sm font-bold text-white shadow-md transition hover:bg-talents-700"
                            >
                                Acessar a plataforma
                            </Link>
                        </div>
                    </div>
                </div>
            </section>

            <section id="contato" class="border-t border-white/30 bg-white/30 py-16 backdrop-blur-sm">
                <div class="mx-auto max-w-2xl px-4">
                    <h2 class="text-center text-2xl font-bold text-slate-900 md:text-3xl">Quer conhecer mais a Talents?</h2>
                    <p class="mx-auto mt-3 max-w-xl text-center text-slate-600">
                        Deixe seus dados e, se quiser, uma mensagem. Entraremos em contato.
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
    </div>
</template>
