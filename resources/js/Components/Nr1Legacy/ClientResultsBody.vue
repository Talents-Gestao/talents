<script setup>
import Nr1SurveyResultsPanel from '@/Components/Nr1SurveyResultsPanel.vue';

defineProps({
    survey: Object,
    bandLabels: { type: Object, required: true },
    overall: Object,
    bySection: Array,
    deptOveralls: Array,
    deptSectionsByDepartment: Array,
    insights: Array,
    questionDistributions: Array,
    departmentParticipation: Array,
    questionDistributionsByDepartment: Array,
    aiEnabled: { type: Boolean, default: false },
    aiAnalysis: { type: Object, default: null },
    aiAnalysisPending: { type: Boolean, default: false },
    riskScenarioLabel: { type: String, default: null },
    actionPlanPublished: { type: Boolean, default: false },
});
</script>

<template>
    <div
        v-if="$page.props.flash?.success"
        class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
    >
        {{ $page.props.flash.success }}
    </div>
    <div
        v-if="$page.props.flash?.error"
        class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900"
    >
        {{ $page.props.flash.error }}
    </div>
    <div
        v-if="$page.props.flash?.info"
        class="mb-4 rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900"
    >
        {{ $page.props.flash.info }}
    </div>

    <Nr1SurveyResultsPanel
        :survey="survey"
        :band-labels="bandLabels"
        :overall="overall"
        :by-section="bySection"
        :dept-overalls="deptOveralls"
        :dept-sections-by-department="deptSectionsByDepartment"
        :insights="insights"
        :question-distributions="questionDistributions"
        :department-participation="departmentParticipation"
        :question-distributions-by-department="questionDistributionsByDepartment"
    />
</template>
