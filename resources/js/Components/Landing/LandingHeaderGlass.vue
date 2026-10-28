<script setup>
import { Bars3Icon, XMarkIcon } from '@heroicons/vue/24/outline';
import { Link } from '@inertiajs/vue3';
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import './landingGlass.css';

/**
 * Cabeçalho de vidro claro (redesenho): fixo, transparente no topo e com borda fina + vidro ao rolar;
 * no mobile o menu abre em painel de tela cheia. Mesmos links e ações do cabeçalho original.
 */
const props = defineProps({
    navLinks: { type: Array, required: true },
    canLogin: Boolean,
    canRegister: Boolean,
    open: Boolean,
});
const emit = defineEmits(['update:open', 'contact']);

const scrolled = ref(false);
const toggleEl = ref(null);
const panelEl = ref(null);

const onScroll = () => {
    scrolled.value = window.scrollY > 8;
};

const close = () => emit('update:open', false);

const trapTab = (e) => {
    if (e.key !== 'Tab' || !panelEl.value) return;
    const f = panelEl.value.querySelectorAll('a[href], button:not([disabled])');
    if (!f.length) return;
    const first = f[0];
    const last = f[f.length - 1];
    if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
    }
};

watch(
    () => props.open,
    async (isOpen) => {
        document.documentElement.style.overflow = isOpen ? 'hidden' : '';
        if (isOpen) {
            await nextTick();
            panelEl.value?.querySelector('a[href], button')?.focus();
        } else {
            toggleEl.value?.focus();
        }
    },
);

onMounted(() => {
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll);
    document.documentElement.style.overflow = '';
});
</script>

<template>
    <header class="lg-header" :class="{ 'lg-header--scrolled': scrolled }">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-3 md:py-4">
            <Link href="/" class="inline-flex shrink-0 items-center" aria-label="Talents — Gestão de Pessoas, página inicial">
                <img
                    src="/images/logo.png"
                    alt="Talents — Gestão de Pessoas"
                    width="483"
                    height="190"
                    class="h-12 w-auto md:h-14"
                />
            </Link>

            <nav class="hidden items-center gap-1 lg:flex" aria-label="Principal">
                <template v-for="item in navLinks" :key="item.label">
                    <a
                        v-if="item.external"
                        :href="item.href"
                        class="lg-nav-link"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        {{ item.label }}
                    </a>
                    <Link
                        v-else
                        :href="item.href ?? route(item.route)"
                        class="lg-nav-link"
                        :aria-current="item.route && route().current(item.route) ? 'page' : undefined"
                    >
                        {{ item.label }}
                    </Link>
                </template>
                <Link v-if="canRegister && !$page.props.auth.user" :href="route('register')" class="lg-nav-link">
                    Criar conta
                </Link>
                <Link v-if="canLogin && !$page.props.auth.user" :href="route('login')" class="lg-nav-link">
                    Entrar
                </Link>
                <Link v-if="$page.props.auth.user" :href="route('dashboard')" class="lg-nav-link">Painel</Link>
                <button type="button" class="lg-btn ml-2" @click="emit('contact')">Falar com Especialista</button>
            </nav>

            <div class="flex items-center gap-2 lg:hidden">
                <button type="button" class="lg-btn !min-h-10 !px-4 !py-2 !text-xs" @click="emit('contact')">
                    Contato
                </button>
                <button
                    ref="toggleEl"
                    type="button"
                    class="lg-icon-btn"
                    :aria-expanded="open"
                    aria-controls="lg-menu-mobile"
                    :aria-label="open ? 'Fechar menu' : 'Abrir menu'"
                    @click="emit('update:open', !open)"
                >
                    <Bars3Icon v-if="!open" class="h-6 w-6" aria-hidden="true" />
                    <XMarkIcon v-else class="h-6 w-6" aria-hidden="true" />
                </button>
            </div>
        </div>

        <div
            v-if="open"
            id="lg-menu-mobile"
            ref="panelEl"
            class="lg-mobile-panel lg:hidden"
            role="dialog"
            aria-modal="true"
            aria-label="Menu"
            @keydown="trapTab"
        >
            <nav class="mx-auto flex max-w-6xl flex-col gap-1 px-4 pb-10 pt-24" aria-label="Principal (mobile)">
                <template v-for="item in navLinks" :key="item.label">
                    <a
                        v-if="item.external"
                        :href="item.href"
                        class="lg-mobile-link"
                        target="_blank"
                        rel="noopener noreferrer"
                        @click="close"
                    >
                        {{ item.label }}
                    </a>
                    <Link
                        v-else
                        :href="item.href ?? route(item.route)"
                        class="lg-mobile-link"
                        :aria-current="item.route && route().current(item.route) ? 'page' : undefined"
                        @click="close"
                    >
                        {{ item.label }}
                    </Link>
                </template>
                <Link
                    v-if="canRegister && !$page.props.auth.user"
                    :href="route('register')"
                    class="lg-mobile-link"
                    @click="close"
                >
                    Criar conta
                </Link>
                <Link
                    v-if="canLogin && !$page.props.auth.user"
                    :href="route('login')"
                    class="lg-mobile-link"
                    @click="close"
                >
                    Entrar
                </Link>
                <Link v-if="$page.props.auth.user" :href="route('dashboard')" class="lg-mobile-link" @click="close">
                    Painel
                </Link>
                <button type="button" class="lg-btn mt-6" @click="emit('contact')">Falar com Especialista</button>
            </nav>
        </div>
    </header>
</template>

<style scoped>
.lg-header {
    position: sticky;
    top: 0;
    z-index: 40;
}

/* O vidro vai num pseudo-elemento: backdrop-filter no próprio header prenderia o painel fixed do mobile. */
.lg-header::before {
    content: '';
    position: absolute;
    inset: 0;
    z-index: -1;
    background: rgba(255, 255, 255, 0.55);
    border-bottom: 1px solid transparent;
    transition:
        background-color 0.25s ease,
        border-color 0.25s ease,
        box-shadow 0.25s ease;
}

.lg-header--scrolled::before {
    background: rgba(255, 255, 255, 0.78);
    -webkit-backdrop-filter: blur(16px) saturate(1.2);
    backdrop-filter: blur(16px) saturate(1.2);
    border-bottom-color: rgba(99, 42, 126, 0.14);
    box-shadow: 0 10px 30px -22px rgba(74, 32, 112, 0.5);
}

.lg-nav-link {
    border-radius: 999px;
    padding: 0.4rem 0.8rem;
    font-size: 0.875rem;
    font-weight: 600;
    color: #4a2070;
    transition: background-color 0.2s ease;
}

.lg-nav-link:hover,
.lg-nav-link[aria-current='page'] {
    background: rgba(179, 136, 217, 0.18);
}

.lg-nav-link:focus-visible,
.lg-mobile-link:focus-visible,
.lg-icon-btn:focus-visible {
    outline: 2px solid #7b4fa2;
    outline-offset: 2px;
}

.lg-icon-btn {
    display: inline-flex;
    height: 2.75rem;
    width: 2.75rem;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    color: #4a2070;
    background: rgba(255, 255, 255, 0.7);
    border: 1px solid rgba(99, 42, 126, 0.14);
}

/* Painel de tela cheia (mobile): vidro claro sobre a página. */
.lg-mobile-panel {
    position: fixed;
    inset: 0;
    z-index: -2;
    overflow-y: auto;
    background:
        radial-gradient(60% 40% at 15% 10%, rgba(179, 136, 217, 0.3), transparent 70%),
        rgba(250, 248, 252, 0.96);
    -webkit-backdrop-filter: blur(20px);
    backdrop-filter: blur(20px);
}

.lg-mobile-link {
    display: block;
    border-radius: 1rem;
    padding: 0.9rem 1rem;
    font-size: 1.35rem;
    font-weight: 600;
    color: #2a1042;
}

.lg-mobile-link[aria-current='page'],
.lg-mobile-link:active {
    background: rgba(179, 136, 217, 0.2);
}
</style>
