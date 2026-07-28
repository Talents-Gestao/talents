<script setup>
import { Bars3Icon, XMarkIcon } from '@heroicons/vue/24/outline';
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import ContactForm from './ContactForm.vue';

const props = defineProps({
    title: { type: String, required: true },
    description: { type: String, required: true },
    canonical: { type: String, default: null },
    canLogin: Boolean,
    canRegister: Boolean,
});

const mobileOpen = ref(false);
const showContactModal = ref(false);

const navLinks = [
    { label: 'Home', route: 'landing.home', href: '/' },
    { label: 'Para Empresas', route: 'landing.empresas', href: null },
    { label: 'Para Pessoas', route: 'landing.pessoas', href: null },
    { label: 'Nossos Clientes', route: 'landing.clientes', href: null },
    { label: 'Sobre', route: 'landing.sobre', href: null },
    { label: 'Contato', route: 'landing.contato', href: null },
    {
        label: 'Vagas',
        href: 'https://talents.vagas.solides.com.br/',
        external: true,
    },
];

const canonicalUrl = computed(() => {
    if (props.canonical) {
        return props.canonical;
    }
    if (typeof window !== 'undefined') {
        return window.location.origin + window.location.pathname;
    }
    return '';
});

const ogImage = computed(() => {
    if (typeof window !== 'undefined') {
        return `${window.location.origin}/images/logo.png`;
    }
    return '/images/logo.png';
});

const openContact = () => {
    showContactModal.value = true;
    mobileOpen.value = false;
};

const onKeydown = (e) => {
    if (e.key === 'Escape') {
        showContactModal.value = false;
        mobileOpen.value = false;
    }
};

onMounted(() => window.addEventListener('keydown', onKeydown));
onUnmounted(() => window.removeEventListener('keydown', onKeydown));

defineExpose({ openContact });
</script>

<template>
    <Head :title="title">
        <meta head-key="description" name="description" :content="description" />
        <meta head-key="og:title" property="og:title" :content="title" />
        <meta head-key="og:description" property="og:description" :content="description" />
        <meta head-key="og:type" property="og:type" content="website" />
        <meta head-key="og:image" property="og:image" :content="ogImage" />
        <meta head-key="twitter:card" name="twitter:card" content="summary_large_image" />
        <meta head-key="twitter:title" name="twitter:title" :content="title" />
        <meta head-key="twitter:description" name="twitter:description" :content="description" />
        <link v-if="canonicalUrl" head-key="canonical" rel="canonical" :href="canonicalUrl" />
        <slot name="head" />
    </Head>

    <div class="app-shell min-h-screen scroll-smooth text-slate-900">
        <header class="landing-header">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-4">
                <Link href="/" class="inline-flex shrink-0 items-center">
                    <img src="/images/logo.png" alt="Talents — Gestão de Pessoas" class="h-14 w-auto md:h-16" />
                </Link>

                <nav class="hidden items-center gap-1 lg:flex">
                    <template v-for="item in navLinks" :key="item.label">
                        <a
                            v-if="item.external"
                            :href="item.href"
                            class="landing-nav-link"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            {{ item.label }}
                        </a>
                        <Link
                            v-else
                            :href="item.href ?? route(item.route)"
                            class="landing-nav-link"
                        >
                            {{ item.label }}
                        </Link>
                    </template>
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
                        class="landing-nav-link"
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
                    <button type="button" class="btn-primary ml-2 px-4 py-2" @click="openContact">
                        Falar com Especialista
                    </button>
                </nav>

                <div class="flex items-center gap-2 lg:hidden">
                    <button type="button" class="btn-primary px-3 py-2 text-xs" @click="openContact">
                        Contato
                    </button>
                    <button
                        type="button"
                        class="rounded-lg p-2 text-slate-600 hover:bg-slate-100"
                        aria-label="Abrir menu"
                        @click="mobileOpen = !mobileOpen"
                    >
                        <Bars3Icon v-if="!mobileOpen" class="h-6 w-6" />
                        <XMarkIcon v-else class="h-6 w-6" />
                    </button>
                </div>
            </div>

            <nav
                v-if="mobileOpen"
                class="border-t border-white/60 bg-white/95 px-4 py-4 lg:hidden"
            >
                <div class="flex flex-col gap-1">
                    <template v-for="item in navLinks" :key="item.label">
                        <a
                            v-if="item.external"
                            :href="item.href"
                            class="landing-nav-link block"
                            target="_blank"
                            rel="noopener noreferrer"
                            @click="mobileOpen = false"
                        >
                            {{ item.label }}
                        </a>
                        <Link
                            v-else
                            :href="item.href ?? route(item.route)"
                            class="landing-nav-link block"
                            @click="mobileOpen = false"
                        >
                            {{ item.label }}
                        </Link>
                    </template>
                    <Link
                        v-if="canRegister && !$page.props.auth.user"
                        :href="route('register')"
                        class="landing-nav-link block"
                        @click="mobileOpen = false"
                    >
                        Criar conta
                    </Link>
                    <Link
                        v-if="canLogin && !$page.props.auth.user"
                        :href="route('login')"
                        class="landing-nav-link block"
                        @click="mobileOpen = false"
                    >
                        Entrar
                    </Link>
                    <Link
                        v-if="$page.props.auth.user"
                        :href="route('dashboard')"
                        class="landing-nav-link block"
                        @click="mobileOpen = false"
                    >
                        Painel
                    </Link>
                </div>
            </nav>
        </header>

        <main>
            <slot />
        </main>

        <footer class="border-t border-white/30 py-10 text-center backdrop-blur-sm">
            <div class="mx-auto max-w-6xl px-4">
                <p class="text-sm font-semibold text-slate-700">Talents — Gestão de Pessoas</p>
                <p class="mt-1 text-xs text-slate-500">Consultoria estratégica em gestão de pessoas</p>
                <p class="mt-3 text-xs text-slate-500">
                    <a href="mailto:contato@talentsgestao.com" class="hover:text-talents-700">contato@talentsgestao.com</a>
                    · Várzea Paulista, SP
                </p>
                <div class="mt-4 flex flex-wrap items-center justify-center gap-3 text-xs">
                    <Link :href="route('landing.empresas')" class="text-talents-700 hover:underline">Para Empresas</Link>
                    <Link :href="route('landing.pessoas')" class="text-talents-700 hover:underline">Para Pessoas</Link>
                    <Link :href="route('landing.clientes')" class="text-talents-700 hover:underline">Nossos Clientes</Link>
                    <Link :href="route('landing.sobre')" class="text-talents-700 hover:underline">Sobre</Link>
                    <Link :href="route('landing.contato')" class="text-talents-700 hover:underline">Contato</Link>
                    <a
                        href="https://talents.vagas.solides.com.br/"
                        class="text-talents-700 hover:underline"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        Vagas
                    </a>
                </div>
            </div>
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
                            <h3 class="text-lg font-bold text-slate-900">Falar com Especialista</h3>
                            <p class="text-sm text-slate-600">Consultoria estratégica em gestão de pessoas</p>
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
                    <div class="px-5 py-5">
                        <ContactForm :show-heading="false" />
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
