<script setup>
import '../../../css/nr1-shell.css';
import '../../../css/nr1-shell-pages.css';
import '../../../css/nr1-shell-buttons.css';
import '../../../css/nr1-shell-results.css';
import '../../../css/nr1-theme.css';
import '../../../css/nr1-shell-motion.css';
import '../../../css/nr1-shell-header.css';
import '../../../css/nr1-shell-d.css';
import '../../../css/nr1-shell-container.css';
import '../../../css/nr1-frame-scroll.css';
import Nr1SubNav from './Nr1SubNav.vue';
import { subNavBadges } from './nr1SubNav';
import ShellHeader from './ShellHeader.vue';
import ToastHost from './ToastHost.vue';
import { router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref } from 'vue';

/**
 * Moldura do painel NR-1 dentro do layout padrão (AdminLayout / ClientLayout): sub-sidebar à esquerda e, à direita, o
 * cabeçalho e o conteúdo no tema .nr1-shell (escopado: não vaza para o resto do sistema). `public`: jornada do
 * colaborador, sem login e sem layout de gestão (só a moldura mínima com as abas).
 */
const props = defineProps({
    shell: { type: Object, required: true },
    context: { type: Object, required: true },
    public: { type: Boolean, default: false },
});

const badges = computed(() => subNavBadges(props.context, props.shell.menu));

// Trilha recolhida/expandida (só em xl): lembrada no navegador; padrão expandida. Falha de storage não quebra a tela.
const COLLAPSE_KEY = 'talents.nr1.subnav.collapsed';
const readCollapsed = () => {
    try {
        return window.localStorage.getItem(COLLAPSE_KEY) === '1';
    } catch {
        return false;
    }
};
const collapsed = ref(!props.public && readCollapsed());
const toggleCollapsed = () => {
    collapsed.value = !collapsed.value;
    try {
        window.localStorage.setItem(COLLAPSE_KEY, collapsed.value ? '1' : '0');
    } catch {
        // Preferência local é opcional.
    }
};

// Barra de carregamento entre as seções (a moldura não recarrega: só o conteúdo troca).
const loading = ref(false);
const offStart = router.on('start', () => (loading.value = true));
const offFinish = router.on('finish', () => (loading.value = false));
onBeforeUnmount(() => {
    offStart();
    offFinish();
});
</script>

<template>
    <div
        class="nr1-frame"
        :class="
            public
                ? 'nr1-frame--public'
                : [
                      'xl:grid xl:items-start xl:gap-7 xl:transition-[grid-template-columns] xl:duration-200 xl:ease-in-out motion-reduce:transition-none',
                      collapsed ? 'xl:grid-cols-[64px_minmax(0,1fr)]' : 'xl:grid-cols-[236px_minmax(0,1fr)]',
                  ]
        "
    >
        <Nr1SubNav
            v-if="shell.menu?.length"
            :class="public ? 'mb-4' : 'mb-4 xl:mb-0'"
            :menu="shell.menu"
            :current="shell.current"
            :picker-url="shell.pickerUrl ?? null"
            :badges="badges"
            :tabs="public"
            :collapsed="collapsed"
            @toggle="toggleCollapsed"
        />

        <div class="nr1-shell min-w-0">
            <div v-if="loading" class="n1-loading" role="progressbar" aria-label="Carregando"></div>
            <ToastHost />
            <!-- Container do miolo: o layout responde à largura disponível (as duas sidebars), não à janela. A barra
                 de carregamento e os toasts ficam fora (são fixos e a contenção os prenderia ao container). -->
            <div class="n1-cq">
                <ShellHeader :shell="shell" :context="context" />

                <div id="nr1-conteudo" class="n1-content">
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
