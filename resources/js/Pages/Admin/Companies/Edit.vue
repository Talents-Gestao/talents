<script setup>
import FormPageHeader from '@/Components/FormPageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    company: Object,
    plans: { type: Array, default: () => [] },
    activePlanId: { default: null },
});

const accessMode = () => {
    const v = props.company.strategic_calendar_access;
    if (v === true) return 'enabled';
    if (v === false) return 'disabled';
    return 'inherit';
};

const tasksAccessMode = () => {
    const v = props.company.tasks_access;
    if (v === true) return 'enabled';
    if (v === false) return 'disabled';
    return 'inherit';
};

const rhidAccessMode = () => {
    const v = props.company.rhid_access;
    if (v === true) return 'enabled';
    if (v === false) return 'disabled';
    return 'inherit';
};

const denunciasAccessMode = () => {
    const v = props.company.denuncias_access;
    if (v === true) return 'enabled';
    if (v === false) return 'disabled';
    return 'inherit';
};

const form = useForm({
    name: props.company.name,
    contact_email: props.company.contact_email ?? '',
    legal_name: props.company.legal_name ?? '',
    cnpj: props.company.cnpj ?? '',
    segment: props.company.segment ?? '',
    address_street: props.company.address_street ?? '',
    address_neighborhood: props.company.address_neighborhood ?? '',
    address_city: props.company.address_city ?? '',
    address_state: props.company.address_state ?? '',
    address_zip: props.company.address_zip ?? '',
    tax_regime: props.company.tax_regime ?? '',
    employee_count_estimate: props.company.employee_count_estimate,
    is_active: props.company.is_active,
    strategic_calendar_access_mode: accessMode(),
    tasks_access_mode: tasksAccessMode(),
    rhid_access_mode: rhidAccessMode(),
    denuncias_access_mode: denunciasAccessMode(),
    plan_id: props.activePlanId ?? null,
});

const submit = () => {
    form.put(route('admin.companies.update', props.company.id));
};
</script>

<template>
    <Head title="Editar empresa" />

    <AdminLayout>
        <template #header>
            <FormPageHeader :back-href="route('admin.companies.index')" title="Editar empresa" />
        </template>

        <form class="surface-card max-w-4xl space-y-4 p-6 text-slate-900" @submit.prevent="submit">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 md:items-start">
                <div>
                    <InputLabel for="name" value="Nome" />
                    <TextInput id="name" v-model="form.name" class="mt-1 block w-full" required />
                </div>
                <div>
                    <InputLabel for="contact_email" value="E-mail de contato / administrador" />
                    <TextInput id="contact_email" v-model="form.contact_email" type="email" class="mt-1 block w-full" autocomplete="email" />
                </div>
            </div>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 md:items-start">
                <div>
                    <InputLabel for="legal_name" value="Razão social" />
                    <TextInput id="legal_name" v-model="form.legal_name" class="mt-1 block w-full" />
                </div>
                <div>
                    <InputLabel for="cnpj" value="CNPJ" />
                    <TextInput id="cnpj" v-model="form.cnpj" class="mt-1 block w-full" />
                </div>
            </div>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 md:items-start">
                <div>
                    <InputLabel for="address_street" value="Logradouro (rua, nº, complemento)" />
                    <TextInput id="address_street" v-model="form.address_street" class="mt-1 block w-full" />
                </div>
                <div>
                    <InputLabel for="address_neighborhood" value="Bairro" />
                    <TextInput id="address_neighborhood" v-model="form.address_neighborhood" class="mt-1 block w-full" />
                </div>
            </div>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 md:items-start">
                <div>
                    <InputLabel for="address_city" value="Município" />
                    <TextInput id="address_city" v-model="form.address_city" class="mt-1 block w-full" />
                </div>
                <div>
                    <InputLabel for="address_state" value="Estado (UF)" />
                    <TextInput
                        id="address_state"
                        v-model="form.address_state"
                        class="mt-1 block w-full max-w-[8rem] uppercase"
                        maxlength="2"
                        placeholder="SP"
                    />
                </div>
            </div>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 md:items-start">
                <div>
                    <InputLabel for="address_zip" value="CEP" />
                    <TextInput id="address_zip" v-model="form.address_zip" class="mt-1 block w-full max-w-[10rem]" placeholder="00000-000" />
                </div>
                <div>
                    <InputLabel for="segment" value="Segmento" />
                    <TextInput id="segment" v-model="form.segment" class="mt-1 block w-full" />
                </div>
            </div>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 md:items-start">
                <div>
                    <InputLabel for="tax_regime" value="Regime de tributação" />
                    <TextInput id="tax_regime" v-model="form.tax_regime" class="mt-1 block w-full" />
                </div>
                <div>
                    <InputLabel for="employee_count_estimate" value="Qtd. colaboradores" />
                    <TextInput id="employee_count_estimate" type="number" v-model="form.employee_count_estimate" class="mt-1 block w-full" />
                </div>
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-talents-600 focus:ring-talents-500" />
                Ativa
            </label>
            <div>
                <InputLabel for="plan_id" value="Plano (assinatura ativa)" />
                <p class="mt-0.5 text-xs text-gray-500">
                    Define o plano da assinatura ativa da empresa. Deixe em branco para cancelar assinaturas ativas.
                </p>
                <select
                    id="plan_id"
                    v-model="form.plan_id"
                    class="mt-1 block w-full max-w-md rounded-md border border-gray-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                >
                    <option :value="null">— Sem plano —</option>
                    <option v-for="p in plans" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
            </div>
            <div>
                <InputLabel for="strategic_calendar_access_mode" value="Calendário estratégico" />
                <p class="mt-0.5 text-xs text-gray-500">
                    Por padrão segue o plano (módulo no plano). Você pode forçar habilitado ou desabilitado para esta empresa.
                </p>
                <select
                    id="strategic_calendar_access_mode"
                    v-model="form.strategic_calendar_access_mode"
                    class="mt-1 block w-full max-w-md rounded-md border border-gray-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                >
                    <option value="inherit">Seguir o plano</option>
                    <option value="enabled">Forçar habilitado</option>
                    <option value="disabled">Forçar desabilitado</option>
                </select>
            </div>
            <div>
                <InputLabel for="tasks_access_mode" value="Tarefas (Kanban)" />
                <p class="mt-0.5 text-xs text-gray-500">
                    Por padrão segue o plano (módulo «tarefas»). Pode forçar por empresa.
                </p>
                <select
                    id="tasks_access_mode"
                    v-model="form.tasks_access_mode"
                    class="mt-1 block w-full max-w-md rounded-md border border-gray-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                >
                    <option value="inherit">Seguir o plano</option>
                    <option value="enabled">Forçar habilitado</option>
                    <option value="disabled">Forçar desabilitado</option>
                </select>
            </div>
            <div>
                <InputLabel for="rhid_access_mode" value="RHID / Ponto" />
                <p class="mt-0.5 text-xs text-gray-500">
                    Por padrão segue o plano (módulo «rhid»). Pode forçar por empresa.
                </p>
                <select
                    id="rhid_access_mode"
                    v-model="form.rhid_access_mode"
                    class="mt-1 block w-full max-w-md rounded-md border border-gray-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                >
                    <option value="inherit">Seguir o plano</option>
                    <option value="enabled">Forçar habilitado</option>
                    <option value="disabled">Forçar desabilitado</option>
                </select>
            </div>
            <div>
                <InputLabel for="denuncias_access_mode" value="Canal de denúncias" />
                <p class="mt-0.5 text-xs text-gray-500">
                    Por padrão segue o plano (módulo «denuncias»). Pode forçar por empresa.
                </p>
                <select
                    id="denuncias_access_mode"
                    v-model="form.denuncias_access_mode"
                    class="mt-1 block w-full max-w-md rounded-md border border-gray-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                >
                    <option value="inherit">Seguir o plano</option>
                    <option value="enabled">Forçar habilitado</option>
                    <option value="disabled">Forçar desabilitado</option>
                </select>
            </div>
            <PrimaryButton :disabled="form.processing">Atualizar</PrimaryButton>
        </form>
    </AdminLayout>
</template>
