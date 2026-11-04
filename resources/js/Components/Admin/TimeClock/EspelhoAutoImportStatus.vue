<script setup>
import EspelhoAutoImportPanel from '@/Components/Rhid/EspelhoAutoImportPanel.vue';
import { computed } from 'vue';

const props = defineProps({
    companyId: { type: Number, required: true },
    configured: { type: Boolean, required: true },
});

const statusUrl = computed(() => route('admin.ponto.companies.espelhos.auto-import', props.companyId));
const runUrl = computed(() => route('admin.ponto.companies.espelhos.auto-import.run', props.companyId));
const settingsHref = computed(() => route('admin.ponto.companies.settings.edit', props.companyId));
</script>

<template>
    <EspelhoAutoImportPanel
        :configured="configured"
        :status-url="statusUrl"
        :run-url="runUrl"
        :settings-href="settingsHref"
        :compact="true"
    />
</template>
