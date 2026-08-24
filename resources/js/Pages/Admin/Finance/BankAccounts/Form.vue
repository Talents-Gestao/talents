<script setup>
import FinanceModuleNav from '@/Components/Finance/FinanceModuleNav.vue';
import FormPageHeader from '@/Components/FormPageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import MoneyInput from '@/Components/MoneyInput.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    mode: { type: String, required: true },
    account: { type: Object, default: null },
    typeOptions: { type: Array, default: () => [] },
});

const fieldClass =
    'mt-1 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/60';

const form = useForm({
    name: props.account?.name ?? '',
    bank_name: props.account?.bank_name ?? '',
    agency: props.account?.agency ?? '',
    account_number: props.account?.account_number ?? '',
    type: props.account?.type ?? 'checking',
    initial_balance_reais: props.account?.initial_balance_reais ?? '0.00',
    initial_balance_at: props.account?.initial_balance_at ?? '',
    is_active: props.account?.is_active ?? true,
    sort_order: props.account?.sort_order ?? 0,
    notes: props.account?.notes ?? '',
});

const submit = () => {
    if (props.mode === 'edit') {
        form.put(route('admin.financeiro.contas-bancarias.update', props.account.id));
    } else {
        form.post(route('admin.financeiro.contas-bancarias.store'));
    }
};
</script>

<template>
    <Head :title="mode === 'edit' ? 'Editar conta bancária' : 'Nova conta bancária'" />

    <AdminLayout>
        <template #header>
            <FormPageHeader
                :back-href="route('admin.financeiro.contas-bancarias.index')"
                back-label="Contas bancárias"
                :title="mode === 'edit' ? 'Editar conta' : 'Nova conta'"
                subtitle="Documente bancos e caixa usados no financeiro"
            />
        </template>

        <FinanceModuleNav />

        <form class="surface-card mx-auto max-w-xl space-y-4 p-6" @submit.prevent="submit">
            <div>
                <InputLabel for="name" value="Nome da conta" />
                <TextInput id="name" v-model="form.name" class="mt-1 block w-full" required />
                <InputError class="mt-1" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel for="bank_name" value="Banco" />
                <TextInput id="bank_name" v-model="form.bank_name" class="mt-1 block w-full" />
                <InputError class="mt-1" :message="form.errors.bank_name" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel for="agency" value="Agência" />
                    <TextInput id="agency" v-model="form.agency" class="mt-1 block w-full" />
                    <InputError class="mt-1" :message="form.errors.agency" />
                </div>
                <div>
                    <InputLabel for="account_number" value="Número da conta" />
                    <TextInput id="account_number" v-model="form.account_number" class="mt-1 block w-full" />
                    <InputError class="mt-1" :message="form.errors.account_number" />
                </div>
            </div>

            <div>
                <InputLabel for="type" value="Tipo" />
                <select id="type" v-model="form.type" :class="fieldClass" required>
                    <option v-for="opt in typeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
                <InputError class="mt-1" :message="form.errors.type" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel for="initial_balance_reais" value="Saldo inicial (R$)" />
                    <MoneyInput
                        id="initial_balance_reais"
                        v-model="form.initial_balance_reais"
                        class="mt-1 block w-full"
                    />
                    <InputError class="mt-1" :message="form.errors.initial_balance_reais" />
                </div>
                <div>
                    <InputLabel for="initial_balance_at" value="Data do saldo" />
                    <TextInput
                        id="initial_balance_at"
                        v-model="form.initial_balance_at"
                        type="date"
                        class="mt-1 block w-full"
                    />
                    <InputError class="mt-1" :message="form.errors.initial_balance_at" />
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel for="sort_order" value="Ordem" />
                    <TextInput
                        id="sort_order"
                        v-model="form.sort_order"
                        type="number"
                        min="0"
                        class="mt-1 block w-full"
                    />
                    <InputError class="mt-1" :message="form.errors.sort_order" />
                </div>
                <div class="flex items-end pb-2">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300" />
                        Conta ativa
                    </label>
                </div>
            </div>

            <div>
                <InputLabel for="notes" value="Observações" />
                <textarea id="notes" v-model="form.notes" rows="3" :class="fieldClass" />
                <InputError class="mt-1" :message="form.errors.notes" />
            </div>

            <div class="flex justify-end pt-2">
                <PrimaryButton type="submit" :disabled="form.processing">
                    {{ mode === 'edit' ? 'Salvar' : 'Cadastrar' }}
                </PrimaryButton>
            </div>
        </form>
    </AdminLayout>
</template>
