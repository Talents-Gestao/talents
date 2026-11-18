<script setup>
import {
    CheckCircleIcon,
    ExclamationTriangleIcon,
    InformationCircleIcon,
    LockClosedIcon,
    XCircleIcon,
} from '@heroicons/vue/24/outline';
import { computed } from 'vue';

/**
 * Aviso de Feedbacks com ícone, cor e texto (a cor nunca é a única pista). Só apresentação.
 * tone: info | success | warning | danger | locked. Título opcional; corpo e ações por slot.
 */
const props = defineProps({
    tone: { type: String, default: 'info' },
    title: { type: String, default: '' },
    /** Erros pedem atenção imediata (role="alert"); os demais são status. */
    live: { type: String, default: '' },
});

const tones = {
    info: { box: 'border-talents-200 bg-talents-50/70 text-talents-950', icon: InformationCircleIcon, iconClass: 'text-talents-700', label: 'Informação' },
    success: { box: 'border-emerald-200 bg-emerald-50 text-emerald-950', icon: CheckCircleIcon, iconClass: 'text-emerald-700', label: 'Concluído' },
    warning: { box: 'border-amber-300 bg-amber-50 text-amber-950', icon: ExclamationTriangleIcon, iconClass: 'text-amber-700', label: 'Atenção' },
    danger: { box: 'border-rose-300 bg-rose-50 text-rose-950', icon: XCircleIcon, iconClass: 'text-rose-700', label: 'Erro' },
    locked: { box: 'border-slate-300 bg-slate-100 text-slate-900', icon: LockClosedIcon, iconClass: 'text-slate-600', label: 'Bloqueado' },
};

const t = computed(() => tones[props.tone] ?? tones.info);
const role = computed(() => props.live || (props.tone === 'danger' ? 'alert' : 'status'));
</script>

<template>
    <div class="flex items-start gap-3 rounded-xl border px-4 py-3 text-sm" :class="t.box" :role="role" :data-feedback-notice="tone">
        <component :is="t.icon" class="mt-0.5 h-5 w-5 shrink-0" :class="t.iconClass" aria-hidden="true" />
        <div class="min-w-0 flex-1 space-y-1">
            <p v-if="title" class="font-semibold"><span class="sr-only">{{ t.label }}: </span>{{ title }}</p>
            <div class="leading-relaxed [&_p]:text-pretty"><slot /></div>
            <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2 pt-2">
                <slot name="actions" />
            </div>
        </div>
    </div>
</template>
