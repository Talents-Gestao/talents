<script setup>
import CompanyMetricsView from '@/Components/Admin/Rhid/CompanyMetricsView.vue';
import axios from 'axios';
import { onMounted, ref } from 'vue';

const props = defineProps({
    companyId: { type: Number, required: true },
    rhidConfigured: { type: Boolean, default: false },
});

const loading = ref(false);
const error = ref(null);
const metrics = ref(null);
const loadedAt = ref(null);

const loadMetrics = async (refresh = false) => {
    if (!props.rhidConfigured) {
        return;
    }
    loading.value = true;
    error.value = null;
    try {
        const { data } = await axios.get(route('admin.companies.rhid-metrics', props.companyId), {
            params: refresh ? { refresh: 1 } : {},
        });
        metrics.value = data;
        loadedAt.value = new Date();
    } catch (e) {
        error.value = e?.response?.data?.message || 'Falha ao carregar indicadores RHID.';
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    loadMetrics(false);
});
</script>

<template>
    <CompanyMetricsView
        class="lg:col-span-2"
        :rhid-configured="rhidConfigured"
        :loading="loading"
        :error="error"
        :metrics="metrics ?? null"
        :loaded-at="loadedAt"
        :show-refresh="true"
        @refresh="loadMetrics(true)"
    />
</template>
