<script setup>
import CompanySearchSelect from '@/Components/CompanySearchSelect.vue';
import { router, useForm } from '@inertiajs/vue3';
import { ArrowsRightLeftIcon, BuildingOffice2Icon } from '@heroicons/vue/24/outline';
import { ref } from 'vue';

const props = defineProps({
    companies: { type: Array, required: true },
    activeCompanyId: { type: [Number, String], default: null },
    compact: { type: Boolean, default: false },
    // «Escolher outra empresa»: só no admin, com empresa vinda da seleção (regra no servidor).
    canChooseOtherCompany: { type: Boolean, default: false },
});

const form = useForm({
    company_id: props.activeCompanyId ?? '',
});

const clearing = ref(false);

const submit = () => {
    form.post(route('admin.feedbacks.company.store'));
};

const chooseOtherCompany = () => {
    router.delete(route('admin.feedbacks.company.destroy'), {
        onStart: () => {
            clearing.value = true;
        },
        onFinish: () => {
            clearing.value = false;
        },
    });
};
</script>

<template>
    <div
        class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm"
        :class="compact ? '' : 'p-6 sm:p-8'"
    >
        <div v-if="!compact" class="mb-6 text-center">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-talents-100 text-talents-700">
                <BuildingOffice2Icon class="h-6 w-6" />
            </div>
            <h3 class="mt-4 text-lg font-semibold text-talents-900">Selecione a empresa</h3>
            <p class="mt-1 text-sm text-slate-600">Escolha o cliente para ver e gerenciar os feedbacks internos.</p>
        </div>

        <form
            class="flex flex-col gap-3 sm:flex-row sm:items-end"
            :class="compact ? 'p-4' : ''"
            @submit.prevent="submit"
        >
            <div class="min-w-0 flex-1">
                <label for="feedback_company_picker" class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    {{ compact ? 'Trocar empresa' : 'Empresa' }}
                </label>
                <CompanySearchSelect
                    id="feedback_company_picker"
                    v-model="form.company_id"
                    :companies="companies"
                    required
                    placeholder="Selecione"
                    class="mt-1"
                    input-class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/60"
                />
            </div>
            <button
                type="submit"
                class="min-h-[42px] rounded-xl bg-talents-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-talents-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="form.processing || !form.company_id"
            >
                {{ compact ? 'Aplicar' : 'Continuar' }}
            </button>
            <button
                v-if="compact && canChooseOtherCompany"
                type="button"
                class="inline-flex min-h-[42px] items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500/50 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="form.processing || clearing"
                @click="chooseOtherCompany"
            >
                <ArrowsRightLeftIcon class="h-4 w-4" aria-hidden="true" />
                Escolher outra empresa
            </button>
        </form>
    </div>
</template>
