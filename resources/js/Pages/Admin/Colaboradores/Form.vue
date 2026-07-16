<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import FormPageHeader from '@/Components/FormPageHeader.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { maskPhoneBr } from '@/utils/formatPhone';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    mode: { type: String, required: true },
    employee: { type: Object, default: null },
    company: { type: Object, required: true },
    departments: { type: Array, default: () => [] },
    positions: { type: Array, default: () => [] },
    leaders: { type: Array, default: () => [] },
});

const fieldClass =
    'mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/70';

const form = useForm({
    company_id: props.employee?.company_id ?? props.company.id,
    name: props.employee?.name ?? '',
    email: props.employee?.email ?? '',
    birth_date: props.employee?.birth_date ?? '',
    phone: maskPhoneBr(props.employee?.phone ?? ''),
    address: props.employee?.address ?? '',
    emergency_contact_name: props.employee?.emergency_contact_name ?? '',
    emergency_contact_relationship: props.employee?.emergency_contact_relationship ?? '',
    emergency_contact_phone: maskPhoneBr(props.employee?.emergency_contact_phone ?? ''),
    department_id: props.employee?.department_id ?? '',
    position_id: props.employee?.position_id ?? '',
    leader_user_id: props.employee?.leader_user_id ?? '',
    admission_date: props.employee?.admission_date ?? '',
    work_schedule: props.employee?.work_schedule ?? '',
    cpf: props.employee?.cpf ?? '',
    rg: props.employee?.rg ?? '',
    is_active: props.employee?.is_active ?? true,
    notes: props.employee?.notes ?? '',
});

const onPhoneInput = (field) => (event) => {
    form[field] = maskPhoneBr(event.target.value);
};

const maskCpf = (value) => {
    const digits = String(value ?? '').replace(/\D/g, '').slice(0, 11);
    if (digits.length <= 3) return digits;
    if (digits.length <= 6) return `${digits.slice(0, 3)}.${digits.slice(3)}`;
    if (digits.length <= 9) return `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6)}`;
    return `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6, 9)}-${digits.slice(9)}`;
};

const onCpfInput = (event) => {
    form.cpf = maskCpf(event.target.value);
};

const submit = () => {
    if (props.mode === 'edit') {
        form.put(route('admin.colaboradores.update', props.employee.id));
    } else {
        form.post(route('admin.colaboradores.store'));
    }
};

const backHref =
    props.mode === 'edit'
        ? route('admin.colaboradores.show', props.employee.id)
        : route('admin.colaboradores.index', { company_id: props.company.id });
</script>

<template>
    <Head :title="mode === 'edit' ? 'Editar colaborador' : 'Novo colaborador'" />

    <AdminLayout>
        <template #header>
            <FormPageHeader
                :back-href="backHref"
                back-label="Colaboradores"
                :title="mode === 'edit' ? 'Editar ficha' : 'Nova ficha do colaborador'"
                :subtitle="`Empresa: ${company.name}`"
            />
        </template>

        <form class="mx-auto max-w-3xl space-y-4" @submit.prevent="submit">
            <section class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-6 py-4">
                    <h3 class="text-sm font-semibold text-talents-900">Dados pessoais</h3>
                </div>
                <div class="grid gap-4 p-6 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <InputLabel value="Nome completo" />
                        <input v-model="form.name" required :class="fieldClass" />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div>
                        <InputLabel value="Data de nascimento" />
                        <input v-model="form.birth_date" type="date" :class="fieldClass" />
                        <InputError :message="form.errors.birth_date" />
                    </div>
                    <div>
                        <InputLabel value="Telefone" />
                        <input
                            :value="form.phone"
                            type="tel"
                            inputmode="numeric"
                            placeholder="(11) 98765-4321"
                            maxlength="16"
                            :class="fieldClass"
                            @input="onPhoneInput('phone')"
                        />
                        <InputError :message="form.errors.phone" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="E-mail" />
                        <input v-model="form.email" type="email" :class="fieldClass" />
                        <InputError :message="form.errors.email" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Endereço" />
                        <input v-model="form.address" :class="fieldClass" />
                        <InputError :message="form.errors.address" />
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-6 py-4">
                    <h3 class="text-sm font-semibold text-talents-900">Contato de emergência</h3>
                </div>
                <div class="grid gap-4 p-6 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <InputLabel value="Nome" />
                        <input v-model="form.emergency_contact_name" :class="fieldClass" />
                        <InputError :message="form.errors.emergency_contact_name" />
                    </div>
                    <div>
                        <InputLabel value="Parentesco" />
                        <input v-model="form.emergency_contact_relationship" :class="fieldClass" />
                        <InputError :message="form.errors.emergency_contact_relationship" />
                    </div>
                    <div>
                        <InputLabel value="Telefone" />
                        <input
                            :value="form.emergency_contact_phone"
                            type="tel"
                            inputmode="numeric"
                            placeholder="(11) 98765-4321"
                            maxlength="16"
                            :class="fieldClass"
                            @input="onPhoneInput('emergency_contact_phone')"
                        />
                        <InputError :message="form.errors.emergency_contact_phone" />
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-6 py-4">
                    <h3 class="text-sm font-semibold text-talents-900">Dados profissionais</h3>
                </div>
                <div class="grid gap-4 p-6 sm:grid-cols-2">
                    <div>
                        <InputLabel value="Cargo" />
                        <select v-model="form.position_id" :class="fieldClass">
                            <option value="">—</option>
                            <option v-for="p in positions" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                        <InputError :message="form.errors.position_id" />
                    </div>
                    <div>
                        <InputLabel value="Setor" />
                        <select v-model="form.department_id" :class="fieldClass">
                            <option value="">—</option>
                            <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                        </select>
                        <InputError :message="form.errors.department_id" />
                    </div>
                    <div>
                        <InputLabel value="Data de admissão" />
                        <input v-model="form.admission_date" type="date" :class="fieldClass" />
                        <InputError :message="form.errors.admission_date" />
                    </div>
                    <div>
                        <InputLabel value="Gestor responsável" />
                        <select v-model="form.leader_user_id" :class="fieldClass">
                            <option value="">—</option>
                            <option v-for="l in leaders" :key="l.id" :value="l.id">{{ l.name }}</option>
                        </select>
                        <InputError :message="form.errors.leader_user_id" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Jornada de trabalho" />
                        <input
                            v-model="form.work_schedule"
                            placeholder="Ex.: Segunda a sexta, 08h às 17h"
                            :class="fieldClass"
                        />
                        <InputError :message="form.errors.work_schedule" />
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-6 py-4">
                    <h3 class="text-sm font-semibold text-talents-900">Documentos</h3>
                </div>
                <div class="grid gap-4 p-6 sm:grid-cols-2">
                    <div>
                        <InputLabel value="CPF" />
                        <input
                            :value="form.cpf"
                            inputmode="numeric"
                            placeholder="000.000.000-00"
                            maxlength="14"
                            :class="fieldClass"
                            @input="onCpfInput"
                        />
                        <InputError :message="form.errors.cpf" />
                    </div>
                    <div>
                        <InputLabel value="RG" />
                        <input v-model="form.rg" :class="fieldClass" />
                        <InputError :message="form.errors.rg" />
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-6 py-4">
                    <h3 class="text-sm font-semibold text-talents-900">Observações</h3>
                </div>
                <div class="space-y-4 p-6">
                    <div>
                        <InputLabel value="Anotações importantes" />
                        <textarea v-model="form.notes" rows="4" :class="fieldClass" />
                        <InputError :message="form.errors.notes" />
                    </div>
                    <label
                        class="flex items-center gap-2.5 rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2.5 text-sm text-slate-700"
                    >
                        <input
                            v-model="form.is_active"
                            type="checkbox"
                            class="rounded border-slate-300 text-talents-700 focus:ring-talents-500"
                        />
                        Colaborador ativo
                    </label>
                </div>
            </section>

            <div class="flex flex-wrap gap-2">
                <PrimaryButton type="submit" :disabled="form.processing">Salvar ficha</PrimaryButton>
                <Link :href="backHref">
                    <SecondaryButton type="button">Cancelar</SecondaryButton>
                </Link>
            </div>
        </form>
    </AdminLayout>
</template>
