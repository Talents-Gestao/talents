<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    align: {
        type: String,
        default: 'right',
    },
    width: {
        type: String,
        default: '48',
    },
    contentClasses: {
        type: String,
        default: 'py-1 bg-white',
    },
    /** Abre o painel acima do gatilho (rodapé da sidebar). */
    openUpward: {
        type: Boolean,
        default: false,
    },
});

const closeOnEscape = (e) => {
    if (open.value && e.key === 'Escape') {
        open.value = false;
    }
};

onMounted(() => document.addEventListener('keydown', closeOnEscape));
onUnmounted(() => document.removeEventListener('keydown', closeOnEscape));

const menuWidthPx = computed(() => {
    const map = { 48: 192 };
    return map[props.width.toString()] ?? 192;
});

const open = ref(false);
const rootRef = ref(null);
const menuStyle = ref({});

function positionMenu() {
    const root = rootRef.value;
    if (!root || !open.value) {
        return;
    }
    const triggerEl = root.firstElementChild;
    if (!triggerEl || !(triggerEl instanceof HTMLElement)) {
        return;
    }
    const r = triggerEl.getBoundingClientRect();
    const gap = 8;
    const w = menuWidthPx.value;
    let left;
    if (props.align === 'left') {
        left = r.left;
    } else if (props.align === 'right') {
        left = r.right - w;
    } else {
        left = r.left + r.width / 2 - w / 2;
    }
    left = Math.max(8, Math.min(left, window.innerWidth - w - 8));

    if (props.openUpward) {
        menuStyle.value = {
            left: `${left}px`,
            bottom: `${window.innerHeight - r.top + gap}px`,
            width: `${w}px`,
            top: 'auto',
        };
    } else {
        menuStyle.value = {
            left: `${left}px`,
            top: `${r.bottom + gap}px`,
            width: `${w}px`,
            bottom: 'auto',
        };
    }
}

function onViewportChange() {
    if (open.value) {
        positionMenu();
    }
}

watch(open, async (isOpen) => {
    if (isOpen) {
        await nextTick();
        positionMenu();
    }
});

onMounted(() => {
    window.addEventListener('resize', onViewportChange);
    window.addEventListener('scroll', onViewportChange, true);
});
onUnmounted(() => {
    window.removeEventListener('resize', onViewportChange);
    window.removeEventListener('scroll', onViewportChange, true);
});

function toggle() {
    open.value = !open.value;
}
</script>

<template>
    <div ref="rootRef" class="relative">
        <div @click.stop="toggle">
            <slot name="trigger" />
        </div>

        <Teleport to="body">
            <div
                v-show="open"
                class="fixed inset-0 z-[200]"
                aria-hidden="true"
                @click="open = false"
            />

            <div
                v-show="open"
                class="fixed z-[210] rounded-md shadow-lg"
                :style="menuStyle"
                role="menu"
            >
                <div
                    class="rounded-md ring-1 ring-black ring-opacity-5"
                    :class="contentClasses"
                >
                    <slot name="content" />
                </div>
            </div>
        </Teleport>
    </div>
</template>
