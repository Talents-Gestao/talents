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
    <Head title="Talents — NR-1 e gestão de riscos psicossociais" />

    <div class="app-shell min-h-screen scroll-smooth text-slate-900">
        <header class="sticky top-0 z-30 border-b border-white/20 bg-slate-950/85 text-white shadow-lg backdrop-blur-md">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4">
                <img src="/images/logo.png" alt="Talents" class="h-10 w-auto brightness-0 invert" />
                <nav class="flex flex-wrap items-center justify-end gap-2 sm:gap-3">
                    <a
                        href="#contato"
                        class="rounded-full border border-white/30 px-3 py-1.5 text-xs font-semibold text-white hover:bg-white/10 sm:text-sm"
                    >
                        Fale conosco
                    </a>
                    <button
                        type="button"
                        class="rounded-full bg-white px-3 py-1.5 text-xs font-bold text-talents-800 shadow hover:bg-talents-50 sm:text-sm"
                        @click="showContactModal = true"
                    >
                        Entre em contato
                    </button>
                    <Link
                        v-if="canRegister && !$page.props.auth.user"
                        :href="route('register')"
                        class="hidden text-sm font-semibold text-talents-200 hover:underline sm:inline"
                    >
                        Criar conta
                    </Link>
                    <Link
                        v-if="canLogin && !$page.props.auth.user"
                        :href="route('login')"
                        class="rounded-full bg-talents-500 px-3 py-1.5 text-xs font-semibold text-white hover:bg-talents-400 sm:text-sm"
                    >
                        Entrar
                    </Link>
                    <Link
                        v-if="$page.props.auth.user"
                        :href="route('dashboard')"
                        class="text-sm font-semibold text-talents-200 hover:underline"
                    >
                        Painel
                    </Link>
                </nav>
            </div>
        </header>

        <main>
            <!-- Hero NR-1 -->
            <section class="relative overflow-hidden bg-gradient-to-b from-slate-950 via-slate-900 to-slate-950 px-4 py-16 text-white md:py-24">
                <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-talents-900/40 via-transparent to-transparent" />
                <div class="relative mx-auto max-w-4xl text-center">
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-talents-300 md:text-sm">NR-1 · Saúde e segurança no trabalho</p>
                    <h1 class="mt-4 text-3xl font-bold leading-tight md:text-5xl">
                        A Talents ajuda sua empresa a caminhar rumo à <span class="text-talents-300">conformidade com a NR-1</span> na gestão de riscos psicossociais
                    </h1>
                    <p class="mx-auto mt-4 max-w-2xl text-lg text-slate-300 md:text-xl">
                        Com <strong class="text-white">método</strong>, <strong class="text-white">ciência</strong> e
                        <strong class="text-white">estratégia</strong> — da identificação ao monitoramento, com rastreabilidade para o
                        PGR.
                    </p>

                    <div
                        class="mx-auto mt-10 flex max-w-3xl flex-wrap items-center justify-center gap-2 text-sm font-bold md:text-base"
                    >
                        <span class="rounded-lg border-l-4 border-red-500 bg-white/5 px-3 py-2 shadow-sm">IDENTIFICAR</span>
                        <span class="text-talents-200">→</span>
                        <span class="rounded-lg border-l-4 border-amber-400 bg-white/5 px-3 py-2 shadow-sm">📊 AVALIAR</span>
                        <span class="text-talents-200">→</span>
                        <span class="rounded-lg border-l-4 border-emerald-500 bg-white/5 px-3 py-2 shadow-sm">⚙️ IMPLEMENTAR</span>
                        <span class="text-talents-200">→</span>
                        <span class="rounded-lg border-l-4 border-blue-500 bg-white/5 px-3 py-2 shadow-sm">📈 MONITORAR</span>
                    </div>

                    <blockquote class="mx-auto mt-10 max-w-2xl border-l-4 border-talents-400 pl-4 text-left text-lg italic text-slate-200">
                        Gestão de risco psicossocial não é evento. <span class="font-semibold not-italic text-white">É processo.</span>
                    </blockquote>

                    <div class="mt-10 flex flex-wrap items-center justify-center gap-4">
                        <a
                            href="#contato"
                            class="rounded-full bg-talents-500 px-8 py-3 text-sm font-bold text-white shadow-lg transition hover:bg-talents-400"
                        >
                            Solicitar conversa
                        </a>
                        <button
                            type="button"
                            class="rounded-full border-2 border-white/40 px-8 py-3 text-sm font-bold text-white hover:bg-white/10"
                            @click="showContactModal = true"
                        >
                            Formulário rápido
                        </button>
                        <Link
                            v-if="canLogin && !$page.props.auth.user"
                            :href="route('login')"
                            class="text-sm font-semibold text-talents-200 underline hover:text-white"
                        >
                            Já sou cliente — entrar
                        </Link>
                    </div>
                </div>
            </section>

            <!-- Orientação técnica -->
            <section class="border-y border-slate-200 bg-white py-14">
                <div class="mx-auto max-w-4xl px-4">
                    <h2 class="text-2xl font-bold text-slate-900 md:text-3xl">Buscar orientação técnica especializada</h2>
                    <p class="mt-4 text-lg leading-relaxed text-slate-600">
                        A NR-1 exige <strong>método e estrutura</strong>. Ter apoio especializado reduz improviso, fortalece decisões e
                        ajuda a evitar exposição jurídica desnecessária ao formalizar riscos, prioridades e plano de ação no PGR.
                    </p>
                </div>
            </section>

            <!-- Etapas detalhadas -->
            <section class="bg-slate-50 py-14">
                <div class="mx-auto max-w-4xl space-y-12 px-4">
                    <article class="surface-card border-l-4 border-red-500 p-6 shadow-md md:p-8">
                        <h3 class="text-xl font-bold text-slate-900">2 · IDENTIFICAR</h3>
                        <ul class="mt-4 list-inside list-disc space-y-2 text-slate-600">
                            <li>Sobrecarga e pressão excessiva</li>
                            <li>Conflitos recorrentes</li>
                            <li>Liderança despreparada</li>
                            <li>Lacunas no PGR</li>
                        </ul>
                    </article>

                    <article class="surface-card border-l-4 border-amber-500 p-6 shadow-md md:p-8">
                        <h3 class="text-xl font-bold text-slate-900">3 · AVALIAR</h3>
                        <ul class="mt-4 list-inside list-disc space-y-2 text-slate-600">
                            <li>Classificar nível de exposição (baixo, médio, alto)</li>
                            <li>Definir prioridades</li>
                            <li>Avaliar impacto organizacional</li>
                        </ul>
                    </article>

                    <article class="surface-card border-l-4 border-emerald-600 p-6 shadow-md md:p-8">
                        <h3 class="text-xl font-bold text-slate-900">4 · IMPLEMENTAR</h3>
                        <ul class="mt-4 list-inside list-disc space-y-2 text-slate-600">
                            <li>Estruturar plano de ação</li>
                            <li>Treinamentos e ajustes organizacionais</li>
                            <li>Formalização no PGR</li>
                        </ul>
                    </article>

                    <article class="surface-card border-l-4 border-blue-600 p-6 shadow-md md:p-8">
                        <h3 class="text-xl font-bold text-slate-900">5 · MONITORAR</h3>
                        <ul class="mt-4 list-inside list-disc space-y-2 text-slate-600">
                            <li>Acompanhar indicadores humanos</li>
                            <li>Reavaliar periodicamente</li>
                            <li>Atualizar plano de ação</li>
                        </ul>
                    </article>
                </div>
            </section>

            <!-- Checklist maturidade -->
            <section class="bg-white py-14">
                <div class="mx-auto max-w-3xl px-4">
                    <h2 class="text-center text-2xl font-bold text-slate-900 md:text-3xl">Checklist de maturidade</h2>
                    <p class="mx-auto mt-3 max-w-xl text-center text-slate-600">
                        Marque o que já existe na sua organização (apenas orientativo).
                    </p>

                    <div class="surface-card mt-8 space-y-4 p-6 shadow-md md:p-8">
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
                            Se marcou menos de 6 itens, sua empresa ainda pode estar vulnerável. Vamos conversar.
                        </p>

                        <div class="mt-6 flex flex-col items-center justify-center gap-3 sm:flex-row">
                            <button
                                type="button"
                                class="w-full rounded-full bg-talents-600 px-8 py-3 text-sm font-bold text-white shadow-md transition hover:bg-talents-700 sm:w-auto"
                                @click="showContactModal = true"
                            >
                                Entre em contato
                            </button>
                            <a
                                href="#contato"
                                class="w-full rounded-full border-2 border-talents-600 px-8 py-3 text-center text-sm font-bold text-talents-700 hover:bg-talents-50 sm:w-auto"
                            >
                                Ir ao formulário na página
                            </a>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Formulário -->
            <section id="contato" class="border-t border-slate-200 bg-gradient-to-b from-slate-100 to-white py-16">
                <div class="mx-auto max-w-2xl px-4">
                    <h2 class="text-center text-2xl font-bold text-slate-900 md:text-3xl">Fale com a Talents</h2>
                    <p class="mx-auto mt-3 max-w-xl text-center text-slate-600">
                        Conte quem você é e como podemos ajudar. Inclua telefone/WhatsApp se quiser retorno mais rápido.
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

                    <form class="surface-card mt-8 space-y-5 p-6 shadow-lg sm:p-8" @submit.prevent="submitInterest">
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
                                placeholder="Contexto, número de colaboradores, dúvidas sobre NR-1…"
                            />
                            <p v-if="form.errors.message" class="mt-1 text-sm text-red-600">{{ form.errors.message }}</p>
                        </div>
                        <div class="pt-2">
                            <button
                                type="submit"
                                class="w-full rounded-full bg-talents-600 px-6 py-3 text-sm font-bold text-white shadow-md transition hover:bg-talents-700 disabled:opacity-60 sm:w-auto"
                                :disabled="form.processing"
                            >
                                {{ form.processing ? 'Enviando…' : 'Enviar' }}
                            </button>
                        </div>
                    </form>
                </div>
            </section>
        </main>

        <footer class="border-t border-slate-200 bg-slate-950 py-10 text-center text-xs text-slate-400">
            <p class="font-semibold text-slate-300">Talents — Gestão de pessoas e conformidade em NR-1</p>
            <p class="mt-2 max-w-lg mx-auto px-4">
                As informações desta página têm caráter educativo e comercial. A adequação legal depende do seu PGR, documentação e
                contexto — consulte sempre um profissional habilitado.
            </p>
        </footer>

        <!-- Modal contato -->
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
                        <h3 class="text-lg font-bold text-slate-900">Entre em contato</h3>
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
