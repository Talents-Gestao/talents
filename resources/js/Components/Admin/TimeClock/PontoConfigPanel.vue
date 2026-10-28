<script setup>
import CompanyRhidCredentialsForm from '@/Components/Admin/Rhid/CompanyRhidCredentialsForm.vue';
import EspelhoAutoImportStatus from '@/Components/Admin/TimeClock/EspelhoAutoImportStatus.vue';
import PunchScheduleSettingsForm from '@/Components/Rhid/PunchScheduleSettingsForm.vue';
import { computed } from 'vue';

const props = defineProps({
    company: { type: Object, required: true },
});

const configured = computed(() => Boolean(props.company.rhid_configured));

const punchScheduleRoutes = computed(() => ({
    show: () => route('admin.ponto.companies.punch-schedule.show', props.company.id),
    update: () => route('admin.ponto.companies.punch-schedule.update', props.company.id),
    people: () => route('admin.ponto.companies.people', props.company.id),
    batchPrefs: () => route('admin.ponto.companies.schedule-preferences.batch', props.company.id),
}));
</script>

<template>
    <div class="space-y-8">
        <p class="text-sm text-slate-600">
            Credenciais da API e horários de aderência desta empresa. Os espelhos entram sozinhos após o
            fechamento do dia.
        </p>

        <CompanyRhidCredentialsForm
            :company-id="company.id"
            :settings="company.rhid_settings"
        />

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <PunchScheduleSettingsForm
                :configured="configured"
                :route-map="punchScheduleRoutes"
            />
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <EspelhoAutoImportStatus
                :company-id="company.id"
                :configured="configured"
            />
        </div>
    </div>
</template>
