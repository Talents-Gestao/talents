<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    modules: Array,
    strategicCalendarViewPeriodOptions: { type: Array, default: () => [] },
});

const form = useForm({
    name: '',
    price_reais: '',
    max_employees: null,
    max_surveys_per_year: null,
    strategic_calendar_view_period: '',
    module_ids: [],
    is_active: true,
});

const submit = () => {
    form.transform((data) => ({
        ...data,
        price_monthly_cents: Math.round(Number(String(data.price_reais).replace(',', '.')) * 100),
        price_reais: undefined,
    })).post(route('admin.plans.store'));
};

const toggleModule = (id) => {
    const set = new Set(form.module_ids);
    if (set.has(id)) set.delete(id);
    else set.add(id);
    form.module_ids = Array.from(set);
};
</script>

<template>
    <Head title="Novo plano" />

    <AdminLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-900">Novo plano</h2>
        </template>

        <form class="max-w-xl space-y-4 surface-card p-6 text-slate-900" @submit.prevent="submit">
            <div>
                <InputLabel for="name" value="Nome" />
                <TextInput id="name" v-model="form.name" class="mt-1 block w-full" required />
            </div>
            <div>
                <InputLabel for="price" value="Preço mensal (R$)" />
                <TextInput id="price" v-model="form.price_reais" type="number" step="0.01" class="mt-1 block w-full" />
            </div>
            <div>
                <InputLabel for="max_employees" value="Máx. colaboradores" />
                <TextInput id="max_employees" type="number" v-model="form.max_employees" class="mt-1 block w-full" />
            </div>
            <div>
                <InputLabel for="max_surveys" value="Máx. pesquisas / ano" />
                <TextInput id="max_surveys" type="number" v-model="form.max_surveys_per_year" class="mt-1 block w-full" />
            </div>
            <div>
                <InputLabel for="strategic_calendar_view_period" value="Período visível do calendário estratégico (cliente)" />
                <select
                    id="strategic_calendar_view_period"
                    v-model="form.strategic_calendar_view_period"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                >
                    <option value="">Sem limite</option>
                    <option
                        v-for="opt in props.strategicCalendarViewPeriodOptions"
                        :key="opt.value"
                        :value="opt.value"
                    >
                        {{ opt.label }}
                    </option>
                </select>
                <p class="mt-1 text-xs text-gray-500">
                    Aplica-se ao portal do cliente quando o plano inclui o módulo Calendário estratégico.
                </p>
            </div>
            <div>
                <p class="text-sm text-gray-600">Módulos</p>
                <div class="mt-2 space-y-2">
                    <label v-for="m in modules" :key="m.id" class="flex items-center gap-2 text-sm">
                        <input type="checkbox" :checked="form.module_ids.includes(m.id)" @change="toggleModule(m.id)" />
                        {{ m.name }}
                    </label>
                </div>
            </div>
            <PrimaryButton :disabled="form.processing">Salvar</PrimaryButton>
        </form>
    </AdminLayout>
</template>
