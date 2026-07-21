<script setup>
import { useForm } from '@inertiajs/vue3';
import { BuildingOffice2Icon } from '@heroicons/vue/24/outline';

const props = defineProps({
    companies: { type: Array, required: true },
    activeCompanyId: { type: [Number, String], default: null },
    compact: { type: Boolean, default: false },
});

const form = useForm({
    company_id: props.activeCompanyId ?? '',
});

const submit = () => {
    form.post(route('admin.desligamento.company.store'));
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
            <p class="mt-1 text-sm text-slate-600">Escolha o cliente para a Pesquisa de Desligamento.</p>
        </div>

        <form
            class="flex flex-col gap-3 sm:flex-row sm:items-end"
            :class="compact ? 'p-4' : ''"
            @submit.prevent="submit"
        >
            <div class="min-w-0 flex-1">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    {{ compact ? 'Trocar empresa' : 'Empresa' }}
                </label>
                <select
                    v-model="form.company_id"
                    required
                    class="mt-1 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/60"
                >
                    <option value="" disabled>Selecione</option>
                    <option v-for="company in companies" :key="company.id" :value="company.id">
                        {{ company.name }}
                    </option>
                </select>
            </div>
            <button
                type="submit"
                class="rounded-xl bg-talents-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-talents-800 disabled:opacity-50"
                :disabled="form.processing || !form.company_id"
            >
                {{ compact ? 'Aplicar' : 'Continuar' }}
            </button>
        </form>
    </div>
</template>
