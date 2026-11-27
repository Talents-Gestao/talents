<script setup>
import { formatDateTime } from '@/utils/dateOnly';
import { communicationStatusClass } from '@/utils/hiringCommunication';
import { ref } from 'vue';

/**
 * Histórico das comunicações da vaga. Status: rascunho, enviado ao servidor de e-mail, falhou, texto copiado.
 * Nunca «entregue» nem «lido». No WhatsApp, «Copiar» copia o texto e registra «Texto copiado».
 */
const props = defineProps({
    items: { type: Array, default: () => [] },
    canCopy: { type: Boolean, default: false },
    emptyText: { type: String, default: 'Nenhuma comunicação registrada.' },
});

const local = ref({});
const copyError = ref(null);

const statusOf = (item) => local.value[item.id] ?? { status: item.status, status_label: item.status_label };

const copy = async (item) => {
    copyError.value = null;
    try {
        await navigator.clipboard.writeText(item.body);
    } catch {
        copyError.value = 'Não foi possível copiar automaticamente. Selecione o texto e copie manualmente.';
        return;
    }
    try {
        const { data } = await window.axios.post(item.copy_url);
        local.value = { ...local.value, [item.id]: data };
    } catch {
        copyError.value = 'O texto foi copiado, mas não foi possível registrar a cópia. Tente novamente.';
    }
};
</script>

<template>
    <div>
        <p v-if="copyError" class="mb-2 text-sm text-red-600" role="alert">{{ copyError }}</p>
        <ul v-if="items.length" class="space-y-2">
            <li v-for="item in items" :key="item.id" class="rounded-xl border border-slate-200 bg-white p-3">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-900">
                            {{ item.kind_label }} · {{ item.channel_label }}
                            <span class="font-normal text-slate-500">para {{ item.contact_name ?? '—' }}</span>
                        </p>
                        <p class="text-xs text-slate-500">
                            <template v-if="item.stage_name">Etapa {{ item.stage_name }} · </template>
                            {{ item.recipient ?? 'sem destinatário' }} ·
                            {{ formatDateTime(item.sent_at ?? item.created_at) }}
                            <template v-if="item.created_by"> · por {{ item.created_by }}</template>
                        </p>
                    </div>
                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="communicationStatusClass(statusOf(item).status)">
                        {{ statusOf(item).status_label }}
                    </span>
                </div>
                <p v-if="item.subject" class="mt-2 text-xs font-semibold text-slate-700">{{ item.subject }}</p>
                <details class="mt-1">
                    <summary class="cursor-pointer text-xs font-semibold text-talents-700">Ver texto</summary>
                    <p class="mt-1 whitespace-pre-wrap rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-700">{{ item.body }}</p>
                </details>
                <p v-if="item.failure_reason" class="mt-2 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-800">{{ item.failure_reason }}</p>
                <div v-if="canCopy && item.copy_url" class="mt-2">
                    <button
                        type="button"
                        class="inline-flex items-center rounded-lg border border-talents-200 bg-white px-3 py-1.5 text-xs font-semibold text-talents-800 hover:bg-talents-50"
                        :aria-label="`Copiar texto do WhatsApp para ${item.contact_name}`"
                        @click="copy(item)"
                    >
                        Copiar
                    </button>
                    <span class="ml-2 text-xs text-slate-500">Cole no WhatsApp e envie você mesmo; o sistema não envia.</span>
                </div>
            </li>
        </ul>
        <p v-else class="text-sm text-slate-500">{{ emptyText }}</p>
    </div>
</template>
