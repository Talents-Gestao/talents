<script setup>
import '../../css/nr1-shell.css';
import '../../css/nr1-shell-pages.css';
import '../../css/nr1-shell-buttons.css';
import '../../css/nr1-shell-results.css';
import '../../css/nr1-theme.css';
import '../../css/nr1-shell-motion.css';
import '../../css/nr1-shell-header.css';
import '../../css/nr1-shell-d.css';
import ShellHeader from '@/Components/Nr1Shell/ShellHeader.vue';
import ToastHost from '@/Components/Nr1Shell/ToastHost.vue';
import { Link, router } from '@inertiajs/vue3';
import { onBeforeUnmount, ref } from 'vue';

defineProps({
    shell: { type: Object, required: true },
    context: { type: Object, required: true },
});

const menuOpen = ref(false);

// Barra de carregamento entre as seções do shell.
const loading = ref(false);
const offStart = router.on('start', () => (loading.value = true));
const offFinish = router.on('finish', () => (loading.value = false));
onBeforeUnmount(() => {
    offStart();
    offFinish();
});
</script>

<template>
    <div class="nr1-shell">
        <div v-if="loading" class="n1-loading" role="progressbar" aria-label="Carregando"></div>
        <ToastHost />
        <aside id="nr1-menu" class="n1-sidebar" :class="{ open: menuOpen }" aria-label="Menu do NR-1">
            <div class="n1-logo">
                <strong>talents<span>.</span></strong>
                <small>Gestão de Pessoas</small>
            </div>
            <div class="n1-navcap">Inteligência organizacional</div>
            <nav aria-label="Navegação NR-1">
                <Link
                    v-for="item in shell.menu"
                    :key="item.key"
                    :href="item.href"
                    class="n1-nav"
                    :aria-current="shell.current === item.key ? 'page' : undefined"
                    @click="menuOpen = false"
                >
                    <i aria-hidden="true">{{ item.icon }}</i>{{ item.label }}
                </Link>
            </nav>
            <div class="n1-sidebar-bottom">Talents · Gestão de Pessoas</div>
        </aside>

        <div class="n1-main">
            <ShellHeader :shell="shell" :context="context" :menu-open="menuOpen" @toggle-menu="menuOpen = !menuOpen" />

            <main class="n1-content">
                <slot />
            </main>
        </div>
    </div>
</template>
