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
    <Head title="Talents — Direcionamento Estratégico" />

    <div class="app-shell min-h-screen scroll-smooth text-slate-900">
        <header class="sticky top-0 z-20 border-b border-white/40 bg-white/70 shadow-sm backdrop-blur-md">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-4">
                <Link href="/" class="inline-flex items-center gap-3">
                    <img src="/images/logo.png" alt="Talents" class="h-16 w-auto md:h-20" />
                </Link>
                <nav class="flex flex-wrap items-center justify-end gap-2 sm:gap-4">
                    <Link href="/" class="text-sm font-semibold text-talents-700 hover:underline"> Início </Link>
                    <Link :href="route('landing.diagnostico')" class="text-sm font-semibold text-talents-700 hover:underline">
                        Diagnóstico Comportamental
                    </Link>
                    <Link :href="route('landing.contratacao')" class="text-sm font-semibold text-talents-700 hover:underline">
                        Contratação de Talentos
                    </Link>
                    <Link :href="route('landing.nr1')" class="text-sm font-semibold text-talents-700 hover:underline"> NR-1 </Link>
                    <Link
                        v-if="canLogin && !$page.props.auth.user"
                        :href="route('login')"
                        class="rounded-full bg-talents-600 px-4 py-2 text-sm font-semibold text-white shadow-md transition hover:bg-talents-700"
                    >
                        Entrar
                    </Link>
                </nav>
            </div>
        </header>

        <main>
            <section class="mx-auto max-w-6xl px-4 py-16 md:py-20">
                <div class="max-w-3xl">
                    <p class="text-sm font-medium uppercase tracking-wide text-talents-600">Pilar Talents</p>
                    <h1 class="mt-5 text-4xl font-bold leading-tight text-slate-900 md:text-5xl">Direcionamento Estratégico</h1>
                    <p class="mt-6 text-base leading-relaxed text-slate-600 md:text-lg">
                        Transforme dados de pessoas em decisões estruturadas para crescimento, produtividade e sustentabilidade do
                        negócio.
                    </p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <button
                            type="button"
                            class="rounded-full bg-talents-600 px-6 py-3 text-sm font-bold text-white shadow-lg transition hover:bg-talents-700"
                            @click="showContactModal = true"
                        >
                            Falar com especialista
                        </button>
                    </div>
                </div>
            </section>

            <section class="border-y border-white/30 bg-white/40 py-16 backdrop-blur-sm">
                <div class="mx-auto max-w-6xl px-4">
                    <h2 class="text-center text-2xl font-bold text-slate-900 md:text-3xl">Por que contratar</h2>
                    <div class="mx-auto mt-10 max-w-4xl rounded-xl border border-talents-200 bg-white p-8 shadow-md">
                        <p class="text-base leading-relaxed text-slate-600">
                            Estratégia de pessoas sem diagnóstico e governança vira ação isolada. O direcionamento estratégico conecta
                            metas do negócio, realidade dos times e priorização das decisões para ganho consistente de performance.
                        </p>
                    </div>
                </div>
            </section>

            <section class="mx-auto max-w-6xl px-4 py-16">
                <h2 class="text-center text-2xl font-bold text-slate-900 md:text-3xl">Benefícios</h2>
                <div class="mt-10 grid gap-6 md:grid-cols-3">
                    <article class="surface-card p-6 shadow-md">
                        <h3 class="text-lg font-semibold text-slate-900">Decisões priorizadas</h3>
                        <p class="mt-3 text-sm leading-relaxed text-slate-600">
                            Definição clara do que atacar primeiro com base em impacto organizacional.
                        </p>
                    </article>
                    <article class="surface-card p-6 shadow-md">
                        <h3 class="text-lg font-semibold text-slate-900">Mais alinhamento executivo</h3>
                        <p class="mt-3 text-sm leading-relaxed text-slate-600">
                            Diretrizes comuns entre liderança, RH e áreas operacionais para execução coordenada.
                        </p>
                    </article>
                    <article class="surface-card p-6 shadow-md">
                        <h3 class="text-lg font-semibold text-slate-900">Ritmo de evolução</h3>
                        <p class="mt-3 text-sm leading-relaxed text-slate-600">
                            Acompanhamento contínuo com indicadores e revisão de rota ao longo do ciclo.
                        </p>
                    </article>
                </div>
            </section>

            <section class="border-y border-white/30 bg-white/40 py-16 backdrop-blur-sm">
                <div class="mx-auto max-w-6xl px-4">
                    <h2 class="text-center text-2xl font-bold text-slate-900 md:text-3xl">Resultados</h2>
                    <div class="mt-10 grid gap-6 md:grid-cols-3">
                        <article class="surface-card p-6 text-center shadow-md">
                            <p class="text-sm font-bold uppercase tracking-wide text-talents-600">Mais foco</p>
                            <p class="mt-2 text-sm text-slate-600">Investimentos direcionados para ações com maior retorno.</p>
                        </article>
                        <article class="surface-card p-6 text-center shadow-md">
                            <p class="text-sm font-bold uppercase tracking-wide text-talents-600">Mais integração</p>
                            <p class="mt-2 text-sm text-slate-600">Planos que conectam operação, liderança e estratégia corporativa.</p>
                        </article>
                        <article class="surface-card p-6 text-center shadow-md">
                            <p class="text-sm font-bold uppercase tracking-wide text-talents-600">Mais resultado</p>
                            <p class="mt-2 text-sm text-slate-600">Evolução mensurável em desempenho, engajamento e cultura.</p>
                        </article>
                    </div>
                </div>
            </section>

            <section id="contato" class="border-t border-white/30 bg-white/30 py-16 backdrop-blur-sm">
                <div class="mx-auto max-w-2xl px-4">
                    <h2 class="text-center text-2xl font-bold text-slate-900 md:text-3xl">Pronto para evoluir sua estratégia?</h2>
                    <p class="mx-auto mt-3 max-w-xl text-center text-slate-600">
                        Deixe seus dados e a Talents entra em contato para mapear prioridades com você.
                    </p>

                    <form class="surface-card mt-8 space-y-5 p-6 shadow-md sm:p-8" @submit.prevent="submitInterest">
                        <div>
                            <label class="block text-sm font-medium text-slate-700" for="strat-name">Nome</label>
                            <input
                                id="strat-name"
                                v-model="form.name"
                                type="text"
                                required
                                class="mt-1 w-full rounded-lg border-slate-200 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700" for="strat-email">E-mail</label>
                            <input
                                id="strat-email"
                                v-model="form.email"
                                type="email"
                                required
                                class="mt-1 w-full rounded-lg border-slate-200 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700" for="strat-phone">Telefone / WhatsApp</label>
                            <input
                                id="strat-phone"
                                v-model="form.phone"
                                type="tel"
                                class="mt-1 w-full rounded-lg border-slate-200 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            />
                        </div>
                        <button
                            type="submit"
                            class="w-full rounded-full bg-talents-600 px-6 py-3 text-sm font-bold text-white shadow-md transition hover:bg-talents-700 disabled:opacity-60"
                            :disabled="form.processing"
                        >
                            {{ form.processing ? 'Enviando…' : 'Enviar interesse' }}
                        </button>
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
                <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl" @click.stop>
                    <form class="space-y-4 px-5 py-5" @submit.prevent="submitInterest">
                        <h3 class="text-lg font-bold text-slate-900">Fale com um especialista Talents</h3>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            placeholder="Nome"
                            class="w-full rounded-lg border-slate-200 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        />
                        <input
                            v-model="form.email"
                            type="email"
                            required
                            placeholder="E-mail"
                            class="w-full rounded-lg border-slate-200 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        />
                        <input
                            v-model="form.phone"
                            type="tel"
                            placeholder="Telefone / WhatsApp"
                            class="w-full rounded-lg border-slate-200 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        />
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
