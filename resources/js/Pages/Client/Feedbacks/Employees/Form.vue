<script setup>
import FeedbacksLayout from '@/Components/Feedback/FeedbacksLayout.vue';
import FormPageHeader from '@/Components/FormPageHeader.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { feedbackFieldClass } from '@/utils/feedbackStatus';
import { maskPhoneBr } from '@/utils/formatPhone';
import { feedbackRoute } from '@/composables/useFeedbackRoutes';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    mode: String,
    employee: Object,
    departments: Array,
    positions: Array,
    leaders: Array,
});

const form = useForm({
    name: props.employee?.name ?? '',
    email: props.employee?.email ?? '',
    phone: maskPhoneBr(props.employee?.phone ?? ''),
    department_id: props.employee?.department_id ?? '',
    position_id: props.employee?.position_id ?? '',
    leader_user_id: props.employee?.leader_user_id ?? '',
    is_active: props.employee?.is_active ?? true,
    notes: props.employee?.notes ?? '',
});

const onPhoneInput = (event) => {
    form.phone = maskPhoneBr(event.target.value);
};

const submit = () => {
    if (props.mode === 'edit') {
        form.put(feedbackRoute('employees.update', props.employee.id));
    } else {
        form.post(feedbackRoute('employees.store'));
    }
};
</script>

<template>
    <Head :title="mode === 'edit' ? 'Editar colaborador' : 'Cadastrar colaborador'" />

    <FeedbacksLayout>
        <template #header>
            <FormPageHeader
                :back-href="feedbackRoute('employees.index')"
                back-label="Colaboradores"
                :title="mode === 'edit' ? 'Editar colaborador' : 'Cadastrar colaborador'"
                subtitle="Dados usados nos alinhamentos e convites de assinatura"
            />
        </template>

        <form
            class="mx-auto max-w-xl overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm"
            @submit.prevent="submit"
        >
            <div class="space-y-5 p-6">
                <div>
                    <InputLabel value="Nome" />
                    <input v-model="form.name" required :class="feedbackFieldClass" />
                    <InputError :message="form.errors.name" />
                </div>
                <div>
                    <InputLabel value="E-mail" />
                    <input v-model="form.email" type="email" required :class="feedbackFieldClass" />
                    <InputError :message="form.errors.email" />
                </div>
                <div>
                    <InputLabel value="Telefone" />
                    <input
                        :value="form.phone"
                        type="tel"
                        inputmode="numeric"
                        autocomplete="tel"
                        placeholder="(11) 98765-4321"
                        maxlength="16"
                        :class="feedbackFieldClass"
                        @input="onPhoneInput"
                    />
                    <InputError :message="form.errors.phone" />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel value="Setor" />
                        <select v-model="form.department_id" :class="feedbackFieldClass">
                            <option value="">—</option>
                            <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Cargo" />
                        <select v-model="form.position_id" :class="feedbackFieldClass">
                            <option value="">—</option>
                            <option v-for="p in positions" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </div>
                </div>
                <div>
                    <InputLabel value="Líder" />
                    <select v-model="form.leader_user_id" :class="feedbackFieldClass">
                        <option value="">—</option>
                        <option v-for="l in leaders" :key="l.id" :value="l.id">{{ l.name }}</option>
                    </select>
                </div>
                <div>
                    <InputLabel value="Observações" />
                    <textarea v-model="form.notes" rows="3" :class="feedbackFieldClass" />
                </div>
                <label class="flex items-center gap-2.5 rounded-lg border border-slate-200 bg-slate-50/50 px-3 py-2.5 text-sm text-slate-700">
                    <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300 text-talents-700 focus:ring-talents-500" />
                    Colaborador ativo
                </label>
            </div>

            <div class="flex flex-wrap gap-2 border-t border-slate-100 bg-slate-50/50 px-6 py-4">
                <PrimaryButton type="submit" :disabled="form.processing">Salvar</PrimaryButton>
                <Link :href="feedbackRoute('employees.index')">
                    <SecondaryButton type="button">Cancelar</SecondaryButton>
                </Link>
            </div>
        </form>
    </FeedbacksLayout>
</template>
