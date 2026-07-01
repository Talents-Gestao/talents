<script setup>
import FormPageHeader from '@/Components/FormPageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    plan: Object,
    modules: Array,
    strategicCalendarViewPeriodOptions: { type: Array, default: () => [] },
});

const form = useForm({
    name: props.plan.name,
    price_monthly_cents: props.plan.price_monthly_cents,
    max_employees: props.plan.max_employees,
    max_surveys_per_year: props.plan.max_surveys_per_year,
    strategic_calendar_view_period: props.plan.strategic_calendar_view_period ?? '',
    module_ids: props.plan.modules?.map((m) => m.id) ?? [],
    is_active: props.plan.is_active,
});

const submit = () => {
    form.put(route('admin.plans.update', props.plan.id));
};

const toggleModule = (id) => {
    const set = new Set(form.module_ids);
    if (set.has(id)) set.delete(id);
    else set.add(id);
    form.module_ids = Array.from(set);
};
</script>

<template>
    <Head title="Editar plano" />

    <AdminLayout>
        <template #header>
            <FormPageHeader :back-href="route('admin.plans.index')" title="Editar plano" />
        </template>

        <form class="max-w-xl space-y-4 surface-card p-6 text-slate-900" @submit.prevent="submit">
            <div>
                <InputLabel for="name" value="Nome" />
                <TextInput id="name" v-model="form.name" class="mt-1 block w-full" required />
            </div>
            <div>
                <InputLabel for="price" value="Preço mensal (centavos)" />
                <TextInput id="price" type="number" v-model="form.price_monthly_cents" class="mt-1 block w-full" />
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
            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-talents-600 focus:ring-talents-500" />
                Ativo
            </label>
            <PrimaryButton :disabled="form.processing">Atualizar</PrimaryButton>
        </form>
    </AdminLayout>
</template>
