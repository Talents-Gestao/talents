<script setup>
import { Link } from '@inertiajs/vue3';
import { EllipsisHorizontalIcon } from '@heroicons/vue/24/outline';
import { computed, nextTick, onBeforeUnmount, ref } from 'vue';

/**
 * Menu de ações secundárias (ícone + rótulo). Itens destrutivos (`danger`) ficam por último,
 * separados por uma linha. Só apresentação: cada item chama o mesmo link ou função de antes.
 *
 * Item: { key, label, icon?, href?, external?, onSelect?, danger?, disabled? }
 */
const props = defineProps({
    items: { type: Array, required: true },
    label: { type: String, default: 'Mais ações' },
    /** Mostra o rótulo ao lado do ícone (no cabeçalho); na linha de uma lista, só o ícone. */
    showLabel: { type: Boolean, default: false },
});

const open = ref(false);
const root = ref(null);
const menu = ref(null);
const menuId = `feedback-actions-${Math.random().toString(36).slice(2, 9)}`;

const regular = computed(() => props.items.filter((item) => !item.danger));
const danger = computed(() => props.items.filter((item) => item.danger));

function onDocumentClick(event) {
    if (root.value && !root.value.contains(event.target)) close();
}

function onKeydown(event) {
    if (event.key === 'Escape') {
        close();
        root.value?.querySelector('[data-menu-trigger]')?.focus();
        return;
    }
    if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') return;
    const entries = [...(menu.value?.querySelectorAll('[role="menuitem"]:not([disabled])') ?? [])];
    if (!entries.length) return;
    event.preventDefault();
    const index = entries.indexOf(document.activeElement);
    const next = event.key === 'ArrowDown' ? index + 1 : index - 1;
    entries[(next + entries.length) % entries.length].focus();
}

async function toggle() {
    open.value = !open.value;
    if (open.value) {
        document.addEventListener('click', onDocumentClick);
        await nextTick();
        menu.value?.querySelector('[role="menuitem"]:not([disabled])')?.focus();
    } else {
        document.removeEventListener('click', onDocumentClick);
    }
}

function close() {
    open.value = false;
    document.removeEventListener('click', onDocumentClick);
}

function select(item) {
    close();
    item.onSelect?.();
}

onBeforeUnmount(() => document.removeEventListener('click', onDocumentClick));

const itemClass = (item) => [
    'flex min-h-[40px] w-full items-center gap-2.5 px-3 py-2 text-left text-sm font-medium transition focus:outline-none disabled:cursor-not-allowed disabled:opacity-50',
    item.danger
        ? 'text-rose-700 hover:bg-rose-50 focus-visible:bg-rose-50'
        : 'text-slate-700 hover:bg-slate-50 focus-visible:bg-talents-50 focus-visible:text-talents-800',
];
</script>

<template>
    <div ref="root" class="relative inline-block text-left" @keydown="onKeydown">
        <button
            type="button"
            data-menu-trigger
            class="inline-flex min-h-[40px] min-w-[40px] items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500/50 focus-visible:ring-offset-2"
            :aria-expanded="open ? 'true' : 'false'"
            :aria-controls="menuId"
            aria-haspopup="menu"
            :aria-label="showLabel ? undefined : label"
            @click.stop="toggle"
        >
            <EllipsisHorizontalIcon class="h-5 w-5" aria-hidden="true" />
            <span v-if="showLabel" class="pr-1">{{ label }}</span>
        </button>

        <div
            v-show="open"
            :id="menuId"
            ref="menu"
            role="menu"
            class="absolute right-0 z-30 mt-2 w-56 origin-top-right overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-lg"
        >
            <template v-for="item in regular" :key="item.key">
                <a v-if="item.href && item.external" :href="item.href" role="menuitem" :class="itemClass(item)" @click="close">
                    <component :is="item.icon" v-if="item.icon" class="h-4 w-4 shrink-0 text-slate-500" aria-hidden="true" />
                    {{ item.label }}
                </a>
                <Link v-else-if="item.href" :href="item.href" role="menuitem" :class="itemClass(item)" @click="close">
                    <component :is="item.icon" v-if="item.icon" class="h-4 w-4 shrink-0 text-slate-500" aria-hidden="true" />
                    {{ item.label }}
                </Link>
                <button v-else type="button" role="menuitem" :disabled="item.disabled" :class="itemClass(item)" @click="select(item)">
                    <component :is="item.icon" v-if="item.icon" class="h-4 w-4 shrink-0 text-slate-500" aria-hidden="true" />
                    {{ item.label }}
                </button>
            </template>
            <div v-if="danger.length && regular.length" class="my-1 border-t border-slate-100" role="separator" />
            <button
                v-for="item in danger"
                :key="item.key"
                type="button"
                role="menuitem"
                :disabled="item.disabled"
                :class="itemClass(item)"
                @click="select(item)"
            >
                <component :is="item.icon" v-if="item.icon" class="h-4 w-4 shrink-0" aria-hidden="true" />
                {{ item.label }}
            </button>
        </div>
    </div>
</template>
