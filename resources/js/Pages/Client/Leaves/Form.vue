<script setup>
import FeriasLayout from '@/Components/Leaves/FeriasLayout.vue';
import FormPageHeader from '@/Components/FormPageHeader.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { feriasRoute } from '@/composables/useFeriasRoutes';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    mode: String,
    leave: Object,
    statusOptions: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
});

const fieldClass =
    'mt-1 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/60';

const form = useForm({
    employee_name: props.leave?.employee_name ?? '',
    employee_email: props.leave?.employee_email ?? '',
    start_date: props.leave?.start_date ?? '',
    end_date: props.leave?.end_date ?? '',
    status: props.leave?.status ?? 'scheduled',
    notes: props.leave?.notes ?? '',
});

const canSubmit = computed(() => Boolean(String(form.employee_name ?? '').trim()));

const onEmployeeNameInput = () => {
    const name = String(form.employee_name ?? '').trim().toLowerCase();
    if (!name) {
        return;
    }
    const match = props.employees.find((e) => String(e.name ?? '').trim().toLowerCase() === name);
    if (match?.email && !String(form.employee_email ?? '').trim()) {
        form.employee_email = match.email;
    }
};

const submit = () => {
    if (!canSubmit.value) {
        return;
    }

    if (props.mode === 'edit') {
        form.put(feriasRoute('update', props.leave.id));
    } else {
        form.post(feriasRoute('store'));
    }
};
</script>

<template>
    <Head :title="mode === 'edit' ? 'Editar férias' : 'Novo período de férias'" />

    <FeriasLayout>
        <template #header>
            <FormPageHeader
                :back-href="feriasRoute('index')"
                back-label="Férias"
                :title="mode === 'edit' ? 'Editar período' : 'Novo período'"
                subtitle="Registre o intervalo de férias do colaborador"
            />
        </template>

        <form
            class="mx-auto max-w-xl overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm"
            @submit.prevent="submit"
        >
            <div class="space-y-5 p-6">
                <div>
                    <InputLabel value="Nome do colaborador" />
                    <input
                        v-model="form.employee_name"
                        type="text"
                        list="ferias-employee-suggestions"
                        required
                        maxlength="255"
                        :class="fieldClass"
                        placeholder="Ex.: Maria Silva"
                        autocomplete="name"
                        @change="onEmployeeNameInput"
                    />
                    <datalist id="ferias-employee-suggestions">
                        <option v-for="e in employees" :key="e.id" :value="e.name">
                            {{ e.email || 'Sem e-mail' }}
                        </option>
                    </datalist>
                    <InputError :message="form.errors.employee_name" />
                    <p class="mt-1.5 text-xs text-slate-500">
                        Digite o nome. Colaboradores já usados na empresa aparecem como sugestão.
                    </p>
                </div>

                <div>
                    <InputLabel value="E-mail (opcional)" />
                    <input
                        v-model="form.employee_email"
                        type="email"
                        maxlength="255"
                        :class="fieldClass"
                        autocomplete="email"
                    />
                    <InputError :message="form.errors.employee_email" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel value="Data de início" />
                        <input v-model="form.start_date" type="date" required :class="fieldClass" />
                        <InputError :message="form.errors.start_date" />
                    </div>
                    <div>
                        <InputLabel value="Data de fim" />
                        <input v-model="form.end_date" type="date" required :class="fieldClass" />
                        <InputError :message="form.errors.end_date" />
                    </div>
                </div>

                <div>
                    <InputLabel value="Status" />
                    <select v-model="form.status" required :class="fieldClass">
                        <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
                            {{ opt.label }}
                        </option>
                    </select>
                    <InputError :message="form.errors.status" />
                </div>

                <div>
                    <InputLabel value="Observações" />
                    <textarea v-model="form.notes" rows="4" :class="fieldClass" />
                    <InputError :message="form.errors.notes" />
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-slate-100 bg-slate-50/70 px-6 py-4">
                <Link :href="feriasRoute('index')">
                    <SecondaryButton type="button">Cancelar</SecondaryButton>
                </Link>
                <PrimaryButton type="submit" :disabled="form.processing || !canSubmit">
                    {{ mode === 'edit' ? 'Salvar' : 'Cadastrar' }}
                </PrimaryButton>
            </div>
        </form>
    </FeriasLayout>
</template>
