<script setup>
import { formatApiDatePtBr } from '@/utils/rhidDate';
import axios from 'axios';
import { onMounted, ref, watch } from 'vue';

const props = defineProps({
    companyId: { type: Number, required: true },
    configured: { type: Boolean, required: true },
});

const autoImportBatch = ref(null);

const autoImportStatusLabel = (status) => {
    if (status === 'completed') {
        return 'Concluído';
    }
    if (status === 'running') {
        return 'Em andamento';
    }
    if (status === 'pending') {
        return 'Na fila';
    }
    if (status === 'failed') {
        return 'Falhou';
    }
    return status || '—';
};

const loadAutoImportStatus = async () => {
    if (!props.configured) {
        autoImportBatch.value = null;
        return;
    }
    try {
        const { data } = await axios.get(route('admin.ponto.companies.espelhos.auto-import', props.companyId));
        autoImportBatch.value = data?.batch ?? null;
    } catch {
        autoImportBatch.value = null;
    }
};

watch(
    () => [props.companyId, props.configured],
    () => {
        loadAutoImportStatus();
    },
);

onMounted(() => {
    loadAutoImportStatus();
});
</script>

<template>
    <div>
        <h3 class="text-sm font-semibold text-slate-900">Importação automática</h3>
        <p
            v-if="!configured"
            class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
        >
            Grave as credenciais RHID para a importação automática passar a rodar nesta empresa.
        </p>
        <div v-else class="mt-3 space-y-2 text-sm text-slate-700">
            <p>
                Todo dia após 00:30 importamos o dia anterior (fechado às 23h59). Não é preciso gerar espelho
                nesta tela.
            </p>
            <p v-if="autoImportBatch">
                Último lote:
                <span class="font-semibold text-slate-800">{{ autoImportStatusLabel(autoImportBatch.status) }}</span>
                <template v-if="autoImportBatch.closed_day">
                    · dia {{ formatApiDatePtBr(autoImportBatch.closed_day) }}
                </template>
                <template v-if="autoImportBatch.created_at">
                    · {{ formatApiDatePtBr(autoImportBatch.created_at) }}
                </template>
                <template v-if="autoImportBatch.total != null">
                    · {{ autoImportBatch.succeeded ?? 0 }}/{{ autoImportBatch.total }} importado(s)
                </template>
                <template v-if="autoImportBatch.skipped">
                    · {{ autoImportBatch.skipped }} ignorado(s)
                </template>
                <span v-if="autoImportBatch.message" class="mt-1 block text-slate-500">
                    {{ autoImportBatch.message }}
                </span>
            </p>
            <p v-else class="text-slate-500">
                Ainda não houve lote automático nesta empresa.
            </p>
        </div>
    </div>
</template>
