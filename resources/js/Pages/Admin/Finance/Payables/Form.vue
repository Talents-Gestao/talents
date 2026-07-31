<script setup>
import FinanceModuleNav from '@/Components/Finance/FinanceModuleNav.vue';
import FormPageHeader from '@/Components/FormPageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    mode: { type: String, required: true },
    payable: { type: Object, default: null },
    statusOptions: { type: Array, default: () => [] },
    paymentMethods: { type: Array, default: () => [] },
});

const fieldClass =
    'mt-1 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/60';

const form = useForm({
    title: props.payable?.title ?? '',
    supplier_name: props.payable?.supplier_name ?? '',
    amount_reais: props.payable?.amount_reais ?? '',
    due_date: props.payable?.due_date ?? '',
    status: props.payable?.status ?? 'pending',
    payment_method_id: props.payable?.payment_method_id ?? '',
    notes: props.payable?.notes ?? '',
});

const submit = () => {
    if (props.mode === 'edit') {
        form.put(route('admin.financeiro.contas-a-pagar.update', props.payable.id));
    } else {
        form.post(route('admin.financeiro.contas-a-pagar.store'));
    }
};
</script>

<template>
    <Head :title="mode === 'edit' ? 'Editar conta a pagar' : 'Nova conta a pagar'" />

    <AdminLayout>
        <template #header>
            <FormPageHeader
                :back-href="route('admin.financeiro.contas-a-pagar.index')"
                back-label="Contas a pagar"
                :title="mode === 'edit' ? 'Editar conta' : 'Nova conta'"
                subtitle="Registro manual de gasto ou obrigação"
            />
        </template>

        <FinanceModuleNav />

        <form class="surface-card mx-auto max-w-xl space-y-4 p-6" @submit.prevent="submit">
            <div>
                <InputLabel for="title" value="Título" />
                <TextInput id="title" v-model="form.title" class="mt-1 block w-full" required />
                <InputError class="mt-1" :message="form.errors.title" />
            </div>
            <div>
                <InputLabel for="supplier_name" value="Fornecedor (opcional)" />
                <TextInput id="supplier_name" v-model="form.supplier_name" class="mt-1 block w-full" />
                <InputError class="mt-1" :message="form.errors.supplier_name" />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel for="amount_reais" value="Valor (R$)" />
                    <TextInput
                        id="amount_reais"
                        v-model="form.amount_reais"
                        type="number"
                        step="0.01"
                        min="0.01"
                        class="mt-1 block w-full"
                        required
                    />
                    <InputError class="mt-1" :message="form.errors.amount_reais" />
                </div>
                <div>
                    <InputLabel for="due_date" value="Vencimento" />
                    <input id="due_date" v-model="form.due_date" type="date" required :class="fieldClass" />
                    <InputError class="mt-1" :message="form.errors.due_date" />
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel for="status" value="Status" />
                    <select id="status" v-model="form.status" required :class="fieldClass">
                        <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
                            {{ opt.label }}
                        </option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.status" />
                </div>
                <div>
                    <InputLabel for="payment_method_id" value="Forma de pagamento" />
                    <select id="payment_method_id" v-model="form.payment_method_id" :class="fieldClass">
                        <option value="">Não informado</option>
                        <option v-for="m in paymentMethods" :key="m.id" :value="m.id">{{ m.name }}</option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.payment_method_id" />
                </div>
            </div>
            <div>
                <InputLabel for="notes" value="Observações" />
                <textarea id="notes" v-model="form.notes" rows="3" :class="fieldClass" />
                <InputError class="mt-1" :message="form.errors.notes" />
            </div>
            <PrimaryButton :disabled="form.processing">
                {{ mode === 'edit' ? 'Salvar' : 'Cadastrar' }}
            </PrimaryButton>
        </form>
    </AdminLayout>
</template>
