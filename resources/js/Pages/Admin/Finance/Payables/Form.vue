<script setup>
import FinanceModuleNav from '@/Components/Finance/FinanceModuleNav.vue';
import FormPageHeader from '@/Components/FormPageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { formatBRL } from '@/composables/useCommercialPricing';

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
    is_recurring: false,
    recurring_months: '',
});

const isCreate = computed(() => props.mode !== 'edit');
const recurringMonthsCount = computed(() => {
    const months = Number(form.recurring_months);
    return Number.isInteger(months) && months > 0 ? months : 0;
});
const monthlyCents = computed(() => {
    const reais = Number(form.amount_reais);
    return Number.isFinite(reais) && reais > 0 ? Math.round(reais * 100) : 0;
});
const periodTotalCents = computed(() => recurringMonthsCount.value * monthlyCents.value);

watch(
    () => form.is_recurring,
    (recurring) => {
        if (!recurring) {
            form.recurring_months = '';
            form.clearErrors('recurring_months');
        }
    },
);

const submit = () => {
    if (props.mode === 'edit') {
        form.put(route('admin.financeiro.contas-a-pagar.update', props.payable.id));
        return;
    }
    form.post(route('admin.financeiro.contas-a-pagar.store'));
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
                    <InputLabel
                        for="amount_reais"
                        :value="isCreate && form.is_recurring ? 'Valor mensal (R$)' : 'Valor (R$)'"
                    />
                    <TextInput
                        id="amount_reais"
                        v-model="form.amount_reais"
                        type="number"
                        step="0.01"
                        min="0.01"
                        class="mt-1 block w-full"
                        required
                    />
                    <p v-if="isCreate && form.is_recurring" class="mt-1 text-xs text-slate-500">
                        Valor de cada mês — não o total do período.
                    </p>
                    <InputError class="mt-1" :message="form.errors.amount_reais" />
                </div>
                <div>
                    <InputLabel for="due_date" :value="isCreate && form.is_recurring ? 'Primeiro vencimento' : 'Vencimento'" />
                    <input id="due_date" v-model="form.due_date" type="date" required :class="fieldClass" />
                    <p v-if="isCreate && form.is_recurring" class="mt-1 text-xs text-slate-500">
                        Os meses seguintes usam o mesmo dia, sem transbordar o fim do mês.
                    </p>
                    <InputError class="mt-1" :message="form.errors.due_date" />
                </div>
            </div>
            <div
                v-if="props.payable?.is_recurring"
                class="rounded-lg border border-talents-100 bg-talents-50 px-3 py-2 text-sm text-talents-900"
            >
                {{ props.payable.recurring_label || 'Recorrente' }}.
                A edição altera só este lançamento — os demais da série não são regenerados.
            </div>
            <div v-if="isCreate" class="space-y-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-3">
                <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-800">
                    <input
                        v-model="form.is_recurring"
                        type="checkbox"
                        class="rounded border-slate-300 text-talents-700 focus:ring-talents-500"
                    />
                    Recorrente
                </label>
                <p class="text-xs text-slate-500">
                    Gera um lançamento por mês, com o mesmo valor, fornecedor e forma de pagamento.
                </p>
                <div v-if="form.is_recurring">
                    <InputLabel for="recurring_months" value="Duração (meses)" />
                    <TextInput
                        id="recurring_months"
                        v-model="form.recurring_months"
                        type="number"
                        min="2"
                        max="60"
                        step="1"
                        class="mt-1 block w-full"
                        required
                    />
                    <InputError class="mt-1" :message="form.errors.recurring_months" />
                    <p
                        v-if="recurringMonthsCount >= 2 && monthlyCents > 0"
                        class="mt-2 text-xs text-slate-600"
                    >
                        {{ recurringMonthsCount }} lançamentos mensais de
                        {{ formatBRL(monthlyCents) }} — total do período
                        {{ formatBRL(periodTotalCents) }}.
                    </p>
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
