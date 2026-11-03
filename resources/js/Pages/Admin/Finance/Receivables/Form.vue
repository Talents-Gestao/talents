<script setup>
import FormPageHeader from '@/Components/FormPageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import MoneyInput from '@/Components/MoneyInput.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { todayIsoInSaoPaulo } from '@/utils/financeLedger';

const props = defineProps({
    mode: { type: String, required: true },
    receivable: { type: Object, default: null },
    statusOptions: { type: Array, default: () => [] },
    paymentMethods: { type: Array, default: () => [] },
    bankAccounts: { type: Array, default: () => [] },
    today: { type: String, default: '' },
});

const maxPaidDate = computed(() => props.today || todayIsoInSaoPaulo());
/** Já recebida ao abrir: mudar a data é uma correção auditada (com motivo opcional). */
const wasPaid = computed(() => props.receivable?.status === 'paid');
/** Conta exigida só na baixa nova; já recebida sem conta (legado) mantém a conta como está. */
const bankPlaceholder = computed(() => {
    if (form.status !== 'paid') {
        return 'Opcional (pendente)';
    }
    return wasPaid.value && !props.receivable?.bank_account_id ? 'Sem conta registrada (mantém)' : 'Selecione a conta';
});

const fieldClass =
    'mt-1 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/60';

const form = useForm({
    title: props.receivable?.title ?? '',
    payer_name: props.receivable?.payer_name ?? '',
    amount_reais: props.receivable?.amount_reais ?? '',
    due_date: props.receivable?.due_date ?? '',
    status: props.receivable?.status ?? 'pending',
    payment_method_id: props.receivable?.payment_method_id ?? '',
    bank_account_id: props.receivable?.bank_account_id ?? '',
    notes: props.receivable?.notes ?? '',
    // Recebida sem data (legado): campo vazio e obrigatório, para não gravar «hoje» sem querer.
    paid_at: wasPaid.value ? (props.receivable?.paid_at ?? '') : maxPaidDate.value,
    payment_date_reason: '',
});

const submit = () => {
    form.transform((data) => ({
        ...data,
        paid_at: data.status === 'paid' ? data.paid_at : null,
        payment_date_reason: data.status === 'paid' && wasPaid.value ? data.payment_date_reason : null,
    }));

    if (props.mode === 'edit') {
        form.put(route('admin.financeiro.contas-a-receber.update', props.receivable.id));
    } else {
        form.post(route('admin.financeiro.contas-a-receber.store'));
    }
};
</script>

<template>
    <Head :title="mode === 'edit' ? 'Editar conta a receber' : 'Nova conta a receber'" />

    <AdminLayout>
        <template #header>
            <FormPageHeader
                :back-href="route('admin.financeiro.contas-a-receber.index')"
                back-label="Contas a receber"
                :title="mode === 'edit' ? 'Editar recebimento' : 'Novo recebimento'"
                subtitle="Registro manual fora do funil de vendas"
            />
        </template>

        <form class="surface-card mx-auto max-w-xl space-y-4 p-6" @submit.prevent="submit">
            <div>
                <InputLabel for="title" value="Título" />
                <TextInput id="title" v-model="form.title" class="mt-1 block w-full" required />
                <InputError class="mt-1" :message="form.errors.title" />
            </div>

            <div>
                <InputLabel for="payer_name" value="Pagador / cliente" />
                <TextInput id="payer_name" v-model="form.payer_name" class="mt-1 block w-full" />
                <InputError class="mt-1" :message="form.errors.payer_name" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel for="amount_reais" value="Valor (R$)" />
                    <MoneyInput
                        id="amount_reais"
                        v-model="form.amount_reais"
                        class="mt-1 block w-full"
                        required
                    />
                    <InputError class="mt-1" :message="form.errors.amount_reais" />
                </div>
                <div>
                    <InputLabel for="due_date" value="Vencimento" />
                    <TextInput id="due_date" v-model="form.due_date" type="date" class="mt-1 block w-full" required />
                    <InputError class="mt-1" :message="form.errors.due_date" />
                </div>
            </div>

            <div>
                <InputLabel for="status" value="Status" />
                <select id="status" v-model="form.status" :class="fieldClass" required>
                    <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
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

            <div>
                <InputLabel for="bank_account_id" value="Conta de destino/recebimento" />
                <select
                    id="bank_account_id"
                    v-model="form.bank_account_id"
                    :required="form.status === 'paid' && !wasPaid"
                    :class="fieldClass"
                >
                    <option value="">{{ bankPlaceholder }}</option>
                    <option v-for="a in bankAccounts" :key="a.id" :value="a.id">{{ a.name }}</option>
                </select>
                <p class="mt-1 text-xs text-slate-500">
                    Obrigatória ao marcar como recebida — é onde o valor foi creditado.
                </p>
                <InputError class="mt-1" :message="form.errors.bank_account_id" />
            </div>

            <div v-if="form.status === 'paid'" class="grid gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel for="paid_at" value="Data do recebimento" />
                    <input
                        id="paid_at"
                        v-model="form.paid_at"
                        type="date"
                        :max="maxPaidDate"
                        required
                        :class="fieldClass"
                    />
                    <p v-if="wasPaid && !form.paid_at" class="mt-1 text-xs text-amber-700">
                        Recebida sem data registrada: informe a data do recebimento.
                    </p>
                    <InputError class="mt-1" :message="form.errors.paid_at" />
                </div>
                <div v-if="wasPaid">
                    <InputLabel for="payment_date_reason" value="Motivo da alteração da data (opcional)" />
                    <input
                        id="payment_date_reason"
                        v-model="form.payment_date_reason"
                        type="text"
                        maxlength="500"
                        placeholder="Ex.: recebimento lançado com atraso"
                        :class="fieldClass"
                    />
                    <InputError class="mt-1" :message="form.errors.payment_date_reason" />
                </div>
                <p v-if="wasPaid" class="text-xs text-slate-500 sm:col-span-2">
                    A alteração da data fica registrada no histórico financeiro.
                </p>
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
