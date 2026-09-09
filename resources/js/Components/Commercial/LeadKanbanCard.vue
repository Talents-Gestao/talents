<script setup>
import {
    BuildingOffice2Icon,
    EnvelopeIcon,
    PhoneIcon,
    UserIcon,
} from '@heroicons/vue/24/outline';
import { computed } from 'vue';

const props = defineProps({
    lead: { type: Object, required: true },
});

const formatDate = (iso) => (iso ? new Date(iso).toLocaleDateString('pt-BR') : '—');

const title = computed(() => props.lead.client_name || props.lead.contact_name || 'Lead');

const qualifiedClass = computed(() => {
    if (props.lead.is_qualified === true) {
        return 'bg-emerald-50 text-emerald-800 ring-emerald-200/80';
    }
    if (props.lead.is_qualified === false) {
        return 'bg-rose-50 text-rose-700 ring-rose-200/70';
    }
    return 'bg-sky-50 text-sky-800 ring-sky-200/80';
});
</script>

<template>
    <article
        class="proposal-kanban-card group relative cursor-grab rounded-xl border border-sky-200/90 bg-white shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-sky-300 hover:shadow-md active:cursor-grabbing"
        :data-card-key="lead.card_key"
        :data-card-kind="'lead'"
        :data-lead-id="lead.id"
        title="Arraste para Aberta, Fechada ou Perdida para criar uma proposta"
    >
        <div
            class="absolute inset-y-3 left-0 w-1 rounded-full bg-sky-500"
            aria-hidden="true"
        />

        <div class="pl-3.5 pr-3 pt-3 pb-3">
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-sky-600/80">
                    {{ lead.code }}
                </p>
                <h4
                    class="mt-0.5 truncate text-sm font-semibold leading-snug text-slate-900"
                    :title="title"
                >
                    {{ title }}
                </h4>
                <p
                    v-if="lead.company && lead.contact_name && lead.company !== lead.contact_name"
                    class="mt-0.5 flex items-center gap-1 truncate text-xs text-slate-500"
                >
                    <UserIcon class="h-3.5 w-3.5 shrink-0 text-slate-400" aria-hidden="true" />
                    <span class="truncate">{{ lead.contact_name }}</span>
                </p>
            </div>

            <div class="mt-2 space-y-1 text-xs text-slate-500">
                <p v-if="lead.client_email" class="flex items-center gap-1.5 truncate">
                    <EnvelopeIcon class="h-3.5 w-3.5 shrink-0 text-slate-400" aria-hidden="true" />
                    <span class="truncate">{{ lead.client_email }}</span>
                </p>
                <p v-if="lead.client_phone" class="flex items-center gap-1.5 truncate">
                    <PhoneIcon class="h-3.5 w-3.5 shrink-0 text-slate-400" aria-hidden="true" />
                    <span class="truncate">{{ lead.client_phone }}</span>
                </p>
                <p v-if="lead.company" class="flex items-center gap-1.5 truncate">
                    <BuildingOffice2Icon class="h-3.5 w-3.5 shrink-0 text-slate-400" aria-hidden="true" />
                    <span class="truncate">{{ lead.company }}</span>
                </p>
            </div>

            <div class="mt-3 flex flex-wrap items-center gap-1.5">
                <span
                    class="rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1"
                    :class="qualifiedClass"
                >
                    {{ lead.qualified_label || 'Lead' }}
                </span>
                <span
                    v-if="lead.source_label"
                    class="rounded-full bg-slate-50 px-2 py-0.5 text-[11px] font-medium text-slate-600 ring-1 ring-slate-200/80"
                >
                    {{ lead.source_label }}
                </span>
                <span class="ml-auto text-[11px] tabular-nums text-slate-400">
                    {{ formatDate(lead.created_at) }}
                </span>
            </div>
        </div>
    </article>
</template>
