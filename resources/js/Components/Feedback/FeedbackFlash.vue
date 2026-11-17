<script setup>
import { usePage } from '@inertiajs/vue3';
import { CheckCircleIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';
import { computed } from 'vue';

/** Mensagens flash (sucesso e erro) das telas de Feedbacks, com ícone e texto. */
const page = usePage();
const success = computed(() => page.props.flash?.success);
const error = computed(() => page.props.flash?.error);
</script>

<template>
    <div v-if="success || error" class="space-y-2">
        <div
            v-if="success"
            class="flex items-start gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
            role="status"
        >
            <CheckCircleIcon class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true" />
            <p>{{ success }}</p>
        </div>
        <div
            v-if="error"
            class="flex items-start gap-2 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900"
            role="alert"
        >
            <ExclamationTriangleIcon class="mt-0.5 h-5 w-5 shrink-0 text-rose-600" aria-hidden="true" />
            <p>{{ error }}</p>
        </div>
    </div>
</template>
