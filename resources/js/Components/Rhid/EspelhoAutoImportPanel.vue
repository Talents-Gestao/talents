<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { formatApiDatePtBr } from '@/utils/rhidDate';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    configured: { type: Boolean, required: true },
    statusUrl: { type: String, required: true },
    runUrl: { type: String, required: true },
    settingsHref: { type: String, default: null },
    showRunButton: { type: Boolean, default: true },
    compact: { type: Boolean, default: false },
});

const emit = defineEmits(['imported', 'status-loaded']);

const autoImportBatch = ref(null);
const loadingStatus = ref(false);
const importing = ref(false);
const actionMessage = ref(null);
const actionError = ref(null);
let pollTimer = null;

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

const batchInFlight = computed(() =>
    ['pending', 'running'].includes(autoImportBatch.value?.status),
);

const stopPolling = () => {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
};

const startPolling = () => {
    if (pollTimer || !props.configured) {
        return;
    }
    pollTimer = setInterval(() => {
        loadAutoImportStatus(true);
    }, 5000);
};

const loadAutoImportStatus = async (silent = false) => {
    if (!props.configured) {
        autoImportBatch.value = null;
        stopPolling();
        emit('status-loaded', null);
        return;
    }
    if (!silent) {
        loadingStatus.value = true;
    }
    try {
        const { data } = await axios.get(props.statusUrl);
        autoImportBatch.value = data?.batch ?? null;
        emit('status-loaded', autoImportBatch.value);
        if (['pending', 'running'].includes(autoImportBatch.value?.status)) {
            startPolling();
        } else {
            stopPolling();
        }
    } catch {
        autoImportBatch.value = null;
        stopPolling();
        emit('status-loaded', null);
    } finally {
        if (!silent) {
            loadingStatus.value = false;
        }
    }
};

const runAutoImport = async () => {
    if (!props.configured || importing.value) {
        return;
    }
    importing.value = true;
    actionMessage.value = null;
    actionError.value = null;
    try {
        const { data } = await axios.post(props.runUrl, {}, { timeout: 60000 });
        const result = data?.result;
        if (data?.batch) {
            autoImportBatch.value = data.batch;
        }
        if (result?.action === 'queued') {
            actionMessage.value = result.backfill
                ? 'Importação enfileirada com backfill do mês. Aguarde a fila processar e atualize a visão.'
                : 'Importação do dia fechado enfileirada. Aguarde a fila processar e atualize a visão.';
        } else if (result?.action === 'skipped') {
            actionMessage.value = result.reason || 'Nada a importar neste momento.';
        } else {
            actionError.value = result?.reason || 'Não foi possível enfileirar a importação.';
        }
        emit('imported', result ?? null);
        await loadAutoImportStatus();
    } catch (e) {
        actionError.value =
            e?.response?.data?.message || e?.response?.data?.result?.reason || 'Falha ao enfileirar a importação automática.';
    } finally {
        importing.value = false;
    }
};

watch(
    () => [props.configured, props.statusUrl],
    () => {
        actionMessage.value = null;
        actionError.value = null;
        stopPolling();
        loadAutoImportStatus();
    },
);

onMounted(() => {
    loadAutoImportStatus();
});

onBeforeUnmount(() => {
    stopPolling();
});

defineExpose({
    refresh: loadAutoImportStatus,
    run: runAutoImport,
});
</script>

<template>
    <div :class="compact ? '' : 'rounded-2xl border border-slate-200 bg-white p-4 shadow-sm'">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <h3 class="text-sm font-semibold text-talents-800">Importação automática</h3>
                <p class="mt-1 text-xs leading-relaxed text-slate-600">
                    Todo dia após 00:30 importamos o dia anterior (fechado às 23h59). Não é preciso gerar espelho
                    manualmente para alimentar pontualidade, faltas e atestados.
                </p>
            </div>
            <PrimaryButton
                v-if="showRunButton && configured"
                type="button"
                class="shrink-0"
                :disabled="importing || loadingStatus || batchInFlight"
                @click="runAutoImport"
            >
                {{ importing ? 'Enfileirando…' : batchInFlight ? 'Importação em andamento…' : 'Importar espelhos agora' }}
            </PrimaryButton>
        </div>

        <p
            v-if="!configured"
            class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
        >
            Configure as credenciais RHID
            <Link
                v-if="settingsHref"
                :href="settingsHref"
                class="font-semibold underline"
            >aqui</Link>
            <template v-else>nas configurações de ponto</template>
            para a importação automática passar a rodar.
        </p>

        <div v-else class="mt-3 space-y-2 text-sm text-slate-700">
            <p v-if="loadingStatus" class="text-slate-500">Consultando último lote…</p>
            <p v-else-if="autoImportBatch">
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

        <p
            v-if="actionMessage"
            class="mt-3 rounded-lg bg-sky-50 px-3 py-2 text-xs text-sky-900 ring-1 ring-sky-100"
        >
            {{ actionMessage }}
        </p>
        <p
            v-if="actionError"
            class="mt-3 rounded-lg bg-rose-50 px-3 py-2 text-xs text-rose-800 ring-1 ring-rose-100"
        >
            {{ actionError }}
        </p>
    </div>
</template>
