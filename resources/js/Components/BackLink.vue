<script setup>
import { ChevronLeftIcon } from '@heroicons/vue/24/outline';
import { router } from '@inertiajs/vue3';
import { consumeBackTarget, normalizeAppUrl } from '@/utils/navigationHistory';

const props = defineProps({
    /** Fallback / destino hierárquico quando não há (ou não se usa) histórico interno. */
    href: { type: String, required: true },
    label: { type: String, default: 'Voltar' },
    /**
     * Se true, tenta a tela anterior do histórico interno.
     * Em create/edit, preferir false para respeitar a hierarquia (backHref).
     */
    preferHistory: { type: Boolean, default: true },
});

const goBack = (event) => {
    event.preventDefault();

    const fallback = normalizeAppUrl(props.href) || props.href;

    if (props.preferHistory) {
        const previous = consumeBackTarget();
        if (previous) {
            router.visit(previous);
            return;
        }
    }

    // Hierarquia: substitui a tela atual no histórico (evita voltar ao create/edit).
    router.visit(fallback, { replace: true });
};
</script>

<template>
    <button
        type="button"
        class="inline-flex items-center gap-1 rounded-lg py-1.5 pr-2.5 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900"
        @click="goBack"
    >
        <ChevronLeftIcon class="h-4 w-4 shrink-0" aria-hidden="true" />
        {{ label }}
    </button>
</template>
