<script setup>
import { formatBRL } from '@/composables/useCommercialPricing';

const props = defineProps({
    metrics: {
        type: Object,
        default: () => ({
            won_month_count: 0,
            won_month_cents: 0,
            total_count: 0,
            leads_count: 0,
            pipeline_open_cents: 0,
            open_count: 0,
            avg_ticket_open_cents: 0,
            expiring_count: 0,
            expired_count: 0,
            no_contact_count: 0,
            zapsign_pending_count: 0,
        }),
    },
});

const emit = defineEmits(['open-expiring']);

const leadsHint = () => {
    const n = Number(props.metrics?.leads_count || 0);
    return n === 1 ? '1 lead' : `${n} leads`;
};

const expiredHint = () => {
    const n = Number(props.metrics?.expired_count || 0);
    return n === 1 ? '1 expirada' : `${n} expiradas`;
};

const pipelineHint = () => {
    const open = Number(props.metrics?.open_count || 0);
    const ticket = formatBRL(props.metrics?.avg_ticket_open_cents);
    if (open <= 0) {
        return 'Nenhuma aberta';
    }
    const countLabel = open === 1 ? '1 aberta' : `${open} abertas`;
    return `${countLabel} · ticket ${ticket}`;
};
</script>

<template>
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
        <article class="rounded-xl border border-emerald-200/80 bg-emerald-50/70 px-3.5 py-3 shadow-sm">
            <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-emerald-800/80">
                Ganhos no mês
            </p>
            <p class="mt-1 text-2xl font-semibold tabular-nums tracking-tight text-emerald-800">
                {{ metrics.won_month_count ?? 0 }}
            </p>
            <p class="mt-0.5 text-xs tabular-nums text-slate-500">
                {{ formatBRL(metrics.won_month_cents) }}
            </p>
        </article>

        <article class="rounded-xl border border-violet-200/80 bg-violet-50/70 px-3.5 py-3 shadow-sm">
            <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-violet-800/80">
                Propostas totais
            </p>
            <p class="mt-1 text-2xl font-semibold tabular-nums tracking-tight text-violet-800">
                {{ metrics.total_count ?? 0 }}
            </p>
            <p class="mt-0.5 text-xs text-slate-500">
                {{ leadsHint() }}
            </p>
        </article>

        <article class="rounded-xl border border-sky-200/80 bg-sky-50/50 px-3.5 py-3 shadow-sm">
            <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-sky-800/80">
                Pipeline aberto
            </p>
            <p class="mt-1 text-xl font-semibold tabular-nums tracking-tight text-slate-900 sm:text-2xl">
                {{ formatBRL(metrics.pipeline_open_cents) }}
            </p>
            <p class="mt-0.5 text-xs text-slate-500">
                {{ pipelineHint() }}
            </p>
        </article>

        <button
            type="button"
            class="rounded-xl border border-amber-200/90 bg-amber-50/80 px-3.5 py-3 text-left shadow-sm transition hover:border-amber-300 hover:bg-amber-50"
            :disabled="!(metrics.expiring_count > 0)"
            @click="emit('open-expiring')"
        >
            <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-amber-900/80">
                A vencer / expiradas
            </p>
            <p class="mt-1 text-2xl font-semibold tabular-nums tracking-tight text-amber-900">
                {{ metrics.expiring_count ?? 0 }}
            </p>
            <p class="mt-0.5 text-xs text-slate-500">
                {{ expiredHint() }}
            </p>
        </button>

        <article class="rounded-xl border border-rose-200/80 bg-rose-50/70 px-3.5 py-3 shadow-sm">
            <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-rose-800/80">
                Sem contato
            </p>
            <p class="mt-1 text-2xl font-semibold tabular-nums tracking-tight text-rose-800">
                {{ metrics.no_contact_count ?? 0 }}
            </p>
            <p class="mt-0.5 text-xs text-slate-500">
                ≥ 7 dias
            </p>
        </article>

        <article class="rounded-xl border border-indigo-200/80 bg-indigo-50/70 px-3.5 py-3 shadow-sm">
            <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-indigo-800/80">
                ZapSign pendente
            </p>
            <p class="mt-1 text-2xl font-semibold tabular-nums tracking-tight text-indigo-800">
                {{ metrics.zapsign_pending_count ?? 0 }}
            </p>
            <p class="mt-0.5 text-xs text-slate-500">
                Aguardando assinatura
            </p>
        </article>
    </div>
</template>
