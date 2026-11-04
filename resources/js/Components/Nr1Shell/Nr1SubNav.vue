<script setup>
import {
    ArrowTrendingUpIcon,
    ArrowsRightLeftIcon,
    ChartBarIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    ClipboardDocumentListIcon,
    DocumentTextIcon,
    HeartIcon,
    PencilSquareIcon,
    PlayCircleIcon,
    SparklesIcon,
    Squares2X2Icon,
} from '@heroicons/vue/24/outline';
import { Link } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { accentFor, subNavItems } from './nr1SubNav';

/**
 * Sub-navegação do NR-1: trilha vertical integrada ao conteúdo (sem cartão), com um filete ligando as etapas, ícones em
 * quadrados pastel e o ativo marcado por uma barra à esquerda na cor da marca. Abaixo de xl (ou `tabs`) vira uma barra
 * de abas horizontal rolável com o mesmo vocabulário: ícone pastel e barra inferior no ativo.
 * Fora do escopo .nr1-shell: usa os tokens do Talents (primary* acompanha o brandTheme da empresa).
 */
const props = defineProps({
    menu: { type: Array, required: true },
    current: { type: String, default: '' },
    pickerUrl: { type: String, default: null },
    /** Selos só com dados que já vêm no contexto do cabeçalho (subNavBadges). */
    badges: { type: Object, default: () => ({}) },
    /** Sempre em abas (jornada do colaborador, sem sidebar de gestão). */
    tabs: { type: Boolean, default: false },
    /** Trilha recolhida (só em xl): só os ícones, com o nome no tooltip; o estado fica no Nr1Frame. */
    collapsed: { type: Boolean, default: false },
});
defineEmits(['toggle']);

const toggleLabel = computed(() => (props.collapsed ? 'Expandir menu' : 'Recolher menu'));
/** Classes que só valem com a trilha recolhida (prefixo xl:, então não mexem nas abas das telas menores). */
const whenCollapsed = (classes) => (props.collapsed && !props.tabs ? classes : '');

const ICONS = {
    'visao-geral': Squares2X2Icon,
    campanha: PlayCircleIcon,
    'minha-jornada': HeartIcon,
    resultados: ChartBarIcon,
    'plano-de-acao': ClipboardDocumentListIcon,
    documentos: DocumentTextIcon,
    parecer: PencilSquareIcon,
    solucoes: SparklesIcon,
    oportunidades: ArrowTrendingUpIcon,
};

/** Fundo pastel + traço do ícone (contraste ≥ 3:1 para o ícone sobre o próprio quadrado). */
const ACCENT_CLASSES = {
    lilac: 'bg-[#f3eaf9] text-[#7a3f94]',
    green: 'bg-[#e3f3ee] text-[#137567]',
    gold: 'bg-[#fcf0db] text-[#9a5c0d]',
    coral: 'bg-[#fde8ea] text-[#b23d55]',
};

const BADGE_CLASSES = {
    ok: 'bg-emerald-50 text-emerald-800 ring-emerald-200/70',
    warn: 'bg-amber-50 text-amber-800 ring-amber-200/70',
    gray: 'bg-slate-100 text-slate-700 ring-slate-200/80',
};

/** Em `tabs` a trilha (classes xl:) não se aplica. */
const cls = (...parts) => {
    const list = parts.flat().filter(Boolean).join(' ').split(/\s+/);

    return props.tabs ? list.filter((c) => !c.startsWith('xl:')).join(' ') : list.join(' ');
};

// Abas (telas menores): a aba ativa fica visível na barra rolável, sem rolar a página na vertical.
const listEl = ref(null);
const revealActive = () => {
    const list = listEl.value;
    const active = list?.querySelector('[aria-current="page"]');
    if (!list || !active || list.scrollWidth <= list.clientWidth) return;
    const li = active.closest('li') ?? active;
    list.scrollLeft = Math.max(0, li.offsetLeft - (list.clientWidth - li.offsetWidth) / 2);
};
onMounted(() => nextTick(revealActive));
watch(() => props.current, () => nextTick(revealActive));

const items = computed(() => subNavItems(props.menu, props.current).map((item, index) => ({ ...item, accent: accentFor(item.key, index), badge: props.badges?.[item.key] ?? null })));

const listClass = computed(() =>
    cls(
        'scrollbar-none relative flex min-w-0 gap-1 overflow-x-auto overscroll-x-contain border-b border-slate-200/80',
        'xl:flex-col xl:gap-0.5 xl:overflow-visible xl:border-b-0',
        // Filete vertical ligando os ícones (centro do quadrado de 32 px + recuo de 12 px = 28 px).
        "xl:before:pointer-events-none xl:before:absolute xl:before:bottom-5 xl:before:left-[27px] xl:before:top-5 xl:before:w-px xl:before:bg-slate-200 xl:before:content-['']",
    ),
);

const itemClass = (active) =>
    cls(
        'group relative flex shrink-0 items-center gap-2.5 whitespace-nowrap px-2.5 pb-2.5 pt-1.5 text-sm font-medium transition-colors duration-150 motion-reduce:transition-none',
        'focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-ring focus-visible:ring-offset-1 rounded-t-xl',
        "after:absolute after:inset-x-2 after:-bottom-px after:h-[3px] after:rounded-full after:content-['']",
        'xl:rounded-xl xl:py-1.5 xl:pl-3 xl:pr-2 xl:after:hidden',
        "xl:before:absolute xl:before:-left-0 xl:before:bottom-2 xl:before:top-2 xl:before:w-[3px] xl:before:rounded-full xl:before:content-['']",
        active
            ? 'text-primary-text after:bg-primary xl:bg-primary-soft/60 xl:before:bg-primary'
            : 'text-slate-700 after:bg-transparent hover:text-slate-900 xl:hover:bg-slate-900/[0.035] xl:before:bg-transparent',
    );

const iconBoxClass = (accent) =>
    cls('relative z-[1] inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-[10px] ring-1 ring-white', ACCENT_CLASSES[accent] ?? ACCENT_CLASSES.lilac);
</script>

<template>
    <nav aria-label="Navegação do NR-1" data-nr1-subnav :class="cls('min-w-0', 'xl:sticky xl:top-0 xl:self-start xl:overflow-x-clip xl:pl-1 xl:pt-1')">
        <div :class="tabs ? 'sr-only' : cls('sr-only', 'xl:not-sr-only xl:mb-2 xl:flex xl:min-h-7 xl:items-center xl:justify-between xl:gap-2 xl:pl-3', whenCollapsed('xl:justify-start'))">
            <p :class="cls('text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500', whenCollapsed('xl:sr-only'))">Inteligência organizacional</p>
            <button
                v-if="!tabs"
                type="button"
                class="hidden h-7 w-7 shrink-0 items-center justify-center rounded-lg text-slate-500 transition-colors hover:bg-slate-900/[0.05] hover:text-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-ring motion-reduce:transition-none xl:inline-flex"
                :class="collapsed ? 'xl:ml-0.5' : ''"
                :title="toggleLabel"
                :aria-label="toggleLabel"
                :aria-expanded="!collapsed"
                aria-controls="nr1-subnav-list"
                @click="$emit('toggle')"
            >
                <component :is="collapsed ? ChevronRightIcon : ChevronLeftIcon" class="h-4 w-4" aria-hidden="true" />
            </button>
        </div>
        <ul id="nr1-subnav-list" ref="listEl" :class="listClass">
            <li v-for="item in items" :key="item.key" class="shrink-0">
                <Link :href="item.href" :class="itemClass(item.active)" :aria-current="item.active ? 'page' : undefined" :title="collapsed && !tabs ? item.label : undefined">
                    <span :class="iconBoxClass(item.accent)" aria-hidden="true">
                        <component :is="ICONS[item.key]" v-if="ICONS[item.key]" class="h-[18px] w-[18px]" />
                        <span v-else class="text-sm leading-none">{{ item.glyph }}</span>
                    </span>
                    <span :class="whenCollapsed('xl:sr-only')">{{ item.label }}</span>
                    <span
                        v-if="item.badge"
                        class="ml-auto inline-flex shrink-0 items-center rounded-full px-1.5 py-0.5 text-[10.5px] font-semibold leading-none ring-1"
                        :class="[BADGE_CLASSES[item.badge.tone] ?? BADGE_CLASSES.gray, whenCollapsed('xl:hidden')]"
                    >
                        <span class="sr-only">Estado: </span>{{ item.badge.label }}
                    </span>
                </Link>
            </li>
        </ul>
        <div v-if="pickerUrl" :class="cls('mt-2', 'xl:mt-3 xl:border-t xl:border-slate-200/80 xl:pt-3')">
            <Link :href="pickerUrl" :class="itemClass(false)" :title="collapsed && !tabs ? 'Trocar empresa' : undefined">
                <span :class="cls('relative z-[1] inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-[10px] bg-slate-100 text-slate-600')" aria-hidden="true">
                    <ArrowsRightLeftIcon class="h-[18px] w-[18px]" />
                </span>
                <span :class="whenCollapsed('xl:sr-only')">Trocar empresa</span>
            </Link>
        </div>
    </nav>
</template>
