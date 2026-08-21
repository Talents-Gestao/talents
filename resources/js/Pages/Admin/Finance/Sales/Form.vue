<script setup>
import FinanceModuleNav from '@/Components/Finance/FinanceModuleNav.vue';
import FormPageHeader from '@/Components/FormPageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { formatBRL } from '@/composables/useCommercialPricing';
import OptionalCommissionFields from '@/Components/Commercial/OptionalCommissionFields.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    mode: { type: String, default: 'create' },
    sale: { type: Object, default: null },
    sellers: { type: Array, default: () => [] },
    paymentMethodOptions: { type: Array, default: () => [] },
    default_commission_percent: { type: Number, default: 0 },
});

const isEdit = computed(() => props.mode === 'edit' && !!props.sale);
const page = usePage();
const isCurrentUserOwner = computed(() => page.props.auth?.user?.is_owner === true);

const sellerIsOwner = (sellerId) => {
    const id = Number(sellerId);
    if (!Number.isFinite(id) || id < 1) {
        return false;
    }
    return props.sellers.some((s) => Number(s.id) === id && s.is_owner === true);
};

const defaultPayCommissionFor = (sellerId) => {
    if (!sellerId) {
        return !isCurrentUserOwner.value;
    }
    return !sellerIsOwner(sellerId);
};

const fieldClass =
    'mt-1 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/60';

const MIX_METHOD_OPTIONS = [
    { value: 'pix', label: 'PIX' },
    { value: 'boleto', label: 'Boleto' },
    { value: 'cartao', label: 'Cartão' },
];

const localTodayDate = () => {
    const d = new Date();
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
};

const defaultMixParts = () => [
    { method: 'pix', percent: 50 },
    { method: 'cartao', percent: 50 },
];

const clientErrors = ref([]);

const form = useForm({
    client_name: props.sale?.client_name ?? '',
    client_cnpj: props.sale?.client_cnpj ?? '',
    client_email: props.sale?.client_email ?? '',
    client_phone: props.sale?.client_phone ?? '',
    seller_id: props.sale?.seller_id ?? '',
    sold_at: props.sale?.sold_at ?? localTodayDate(),
    total_reais: props.sale?.total_reais ?? '',
    pay_commission: defaultPayCommissionFor(props.sale?.seller_id ?? ''),
    commission_percent: defaultPayCommissionFor(props.sale?.seller_id ?? '')
        ? Number(props.default_commission_percent) || 0
        : 0,
    payment_method: props.sale?.payment_method ?? 'pix',
    installments_count: props.sale?.installments_count ?? 1,
    first_due_date: localTodayDate(),
    notes: props.sale?.notes ?? '',
    mix_parts: [],
});

const isMisto = computed(() => form.payment_method === 'misto');

const mixPercentSum = computed(() =>
    (form.mix_parts || []).reduce((sum, part) => sum + (Number(part.percent) || 0), 0),
);

const totalCents = computed(() => Math.round((Number(form.total_reais) || 0) * 100));

const estimatedCommissionCents = computed(() => {
    if (!form.pay_commission) {
        return 0;
    }
    const percent = Number(form.commission_percent) || 0;
    if (percent <= 0) {
        return 0;
    }
    return Math.round((totalCents.value * percent) / 100);
});

const paymentMethodLabel = computed(() => {
    const opt = props.paymentMethodOptions.find((o) => o.value === props.sale?.payment_method);
    return opt?.label ?? props.sale?.payment_method ?? '—';
});

watch(
    () => form.seller_id,
    (sellerId) => {
        if (isEdit.value) {
            return;
        }
        const pay = defaultPayCommissionFor(sellerId);
        form.pay_commission = pay;
        form.commission_percent = pay ? Number(props.default_commission_percent) || 0 : 0;
    },
);

const mixPartsWithAmounts = computed(() => {
    const total = totalCents.value;
    const parts = form.mix_parts || [];
    let allocated = 0;

    return parts.map((part, index) => {
        const percent = Number(part.percent) || 0;
        let amountCents = 0;
        if (total > 0 && percent > 0) {
            if (index === parts.length - 1) {
                amountCents = Math.max(0, total - allocated);
            } else {
                amountCents = Math.round(total * (percent / 100));
                allocated += amountCents;
            }
        }

        return {
            ...part,
            amount_cents: amountCents,
            label: MIX_METHOD_OPTIONS.find((o) => o.value === part.method)?.label ?? part.method,
        };
    });
});

watch(
    () => form.payment_method,
    (method, previous) => {
        if (isEdit.value) {
            return;
        }
        if (method === 'misto' && previous !== 'misto') {
            form.mix_parts = defaultMixParts();
            form.installments_count = form.mix_parts.length;
        }
        if (method !== 'misto' && previous === 'misto') {
            form.mix_parts = [];
            form.installments_count = 1;
        }
        clientErrors.value = [];
        form.clearErrors('mix_parts', 'installments_count');
    },
);

const addMixPart = () => {
    form.mix_parts.push({ method: 'pix', percent: '' });
};

const removeMixPart = (index) => {
    if ((form.mix_parts?.length ?? 0) <= 2) {
        return;
    }
    form.mix_parts.splice(index, 1);
};

const validate = () => {
    clientErrors.value = [];
    form.clearErrors();

    if (!form.client_name?.trim()) {
        form.setError('client_name', 'Informe o nome do cliente.');
    }

    if (isEdit.value) {
        if (!form.sold_at) {
            form.setError('sold_at', 'Informe a data da venda.');
        }
        return Object.keys(form.errors).length === 0;
    }

    if (!form.total_reais || Number(form.total_reais) <= 0) {
        form.setError('total_reais', 'Informe o valor total.');
    }
    if (!form.payment_method) {
        form.setError('payment_method', 'Selecione a forma de pagamento.');
    }
    if (!form.first_due_date) {
        form.setError('first_due_date', 'Informe o 1º vencimento.');
    }

    if (isMisto.value) {
        const parts = form.mix_parts || [];
        if (parts.length < 2) {
            clientErrors.value.push('Informe pelo menos 2 partes na composição.');
        }
        parts.forEach((part, index) => {
            if (!part.method) {
                form.setError(`mix_parts.${index}.method`, 'Selecione a forma.');
            }
            const percent = Number(part.percent);
            if (!Number.isFinite(percent) || percent <= 0) {
                form.setError(`mix_parts.${index}.percent`, 'Informe um percentual maior que zero.');
            }
        });
        if (Math.abs(mixPercentSum.value - 100) > 0.05) {
            clientErrors.value.push('A soma dos percentuais deve ser 100%.');
        }
    } else {
        const count = Number(form.installments_count);
        if (!Number.isInteger(count) || count < 1 || count > 60) {
            form.setError('installments_count', 'Informe o número de parcelas (1 a 60).');
        }
    }

    return Object.keys(form.errors).length === 0 && clientErrors.value.length === 0;
};

const submit = () => {
    if (!validate()) {
        return;
    }

    if (isEdit.value) {
        form
            .transform((data) => ({
                client_name: data.client_name,
                client_cnpj: data.client_cnpj || null,
                client_email: data.client_email || null,
                client_phone: data.client_phone || null,
                seller_id: data.seller_id || null,
                sold_at: data.sold_at,
                notes: data.notes || null,
            }))
            .put(route('admin.financeiro.vendas.update', props.sale.id));
        return;
    }

    form
        .transform((data) => {
            const payload = {
                client_name: data.client_name,
                client_cnpj: data.client_cnpj || null,
                client_email: data.client_email || null,
                client_phone: data.client_phone || null,
                seller_id: data.seller_id || null,
                total_reais: data.total_reais,
                pay_commission: !!data.pay_commission,
                commission_percent: data.pay_commission ? data.commission_percent || 0 : 0,
                payment_method: data.payment_method,
                first_due_date: data.first_due_date,
                notes: data.notes || null,
            };

            if (data.payment_method === 'misto') {
                payload.mix_parts = data.mix_parts;
            } else {
                payload.installments_count = data.installments_count;
            }

            return payload;
        })
        .post(route('admin.financeiro.vendas.store'));
};

const pageTitle = computed(() =>
    isEdit.value
        ? `Editar venda${props.sale?.client_name ? ` — ${props.sale.client_name}` : ''}`
        : 'Nova venda manual',
);
const pageSubtitle = computed(() =>
    isEdit.value
        ? 'Atualize os dados comerciais da venda. Parcelas e valor total não são alterados aqui.'
        : 'Cadastro avulso sem proposta comercial',
);
</script>

<template>
    <Head :title="pageTitle" />

    <AdminLayout>
        <template #header>
            <FormPageHeader
                :back-href="
                    isEdit
                        ? route('admin.financeiro.vendas.show', sale.id)
                        : route('admin.financeiro.vendas.index')
                "
                :back-label="isEdit ? 'Detalhe' : 'Vendas'"
                :title="pageTitle"
                :subtitle="pageSubtitle"
            />
        </template>

        <FinanceModuleNav />

        <form class="surface-card mx-auto max-w-2xl space-y-4 p-6" @submit.prevent="submit">
            <div
                v-if="clientErrors.length || Object.keys(form.errors).length"
                class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800"
                role="alert"
            >
                <p v-for="(msg, idx) in clientErrors" :key="`ce-${idx}`">{{ msg }}</p>
                <p v-for="(msg, key) in form.errors" :key="`fe-${key}`">{{ msg }}</p>
            </div>

            <div
                v-if="isEdit && sale?.proposal_id"
                class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700"
            >
                Proposta vinculada:
                <Link
                    :href="route('admin.comercial.propostas.edit', sale.proposal_id)"
                    class="font-medium text-talents-700 hover:underline"
                >
                    Ver proposta
                </Link>
                <span class="text-slate-500"> (somente leitura)</span>
            </div>

            <div>
                <InputLabel for="client_name" value="Cliente" />
                <TextInput id="client_name" v-model="form.client_name" class="mt-1 block w-full" required />
                <InputError class="mt-1" :message="form.errors.client_name" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel for="client_cnpj" value="CNPJ (opcional)" />
                    <TextInput id="client_cnpj" v-model="form.client_cnpj" class="mt-1 block w-full" />
                    <InputError class="mt-1" :message="form.errors.client_cnpj" />
                </div>
                <div>
                    <InputLabel for="seller_id" value="Vendedor" />
                    <select id="seller_id" v-model="form.seller_id" :class="fieldClass">
                        <option value="">Sem vendedor</option>
                        <option v-for="s in sellers" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.seller_id" />
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel for="client_email" value="E-mail (opcional)" />
                    <TextInput id="client_email" v-model="form.client_email" type="email" class="mt-1 block w-full" />
                    <InputError class="mt-1" :message="form.errors.client_email" />
                </div>
                <div>
                    <InputLabel for="client_phone" value="Telefone (opcional)" />
                    <TextInput id="client_phone" v-model="form.client_phone" class="mt-1 block w-full" />
                    <InputError class="mt-1" :message="form.errors.client_phone" />
                </div>
            </div>

            <template v-if="isEdit">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="sold_at" value="Data da venda" />
                        <TextInput id="sold_at" v-model="form.sold_at" type="date" class="mt-1 block w-full" required />
                        <InputError class="mt-1" :message="form.errors.sold_at" />
                    </div>
                    <div>
                        <InputLabel value="Valor total" />
                        <p class="mt-2 text-sm font-semibold tabular-nums text-slate-900">
                            {{ formatBRL(Math.round(Number(sale.total_reais || 0) * 100)) }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500">
                            Somente leitura — ajuste parcelas em Contas a receber.
                        </p>
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel value="Forma de pagamento" />
                        <p class="mt-2 text-sm text-slate-800">{{ paymentMethodLabel }}</p>
                    </div>
                    <div>
                        <InputLabel value="Parcelas" />
                        <p class="mt-2 text-sm text-slate-800">{{ sale.installments_count }}</p>
                    </div>
                </div>
            </template>

            <template v-else>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="total_reais" value="Valor total (R$)" />
                        <TextInput
                            id="total_reais"
                            v-model="form.total_reais"
                            type="number"
                            step="0.01"
                            min="0.01"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError class="mt-1" :message="form.errors.total_reais" />
                    </div>
                </div>

                <OptionalCommissionFields
                    v-model:pay-commission="form.pay_commission"
                    v-model:commission-percent="form.commission_percent"
                    :default-percent="Number(default_commission_percent) || 0"
                    :estimated-cents="estimatedCommissionCents"
                    :errors="form.errors"
                    :disabled="form.processing"
                />

                <div>
                    <InputLabel for="payment_method" value="Forma de pagamento" />
                    <select id="payment_method" v-model="form.payment_method" :class="fieldClass" required>
                        <option v-for="opt in paymentMethodOptions" :key="opt.value" :value="opt.value">
                            {{ opt.label }}
                        </option>
                    </select>
                    <p class="mt-1 text-xs text-slate-500">
                        Em «Misto», informe a composição do valor (ex.: 50% PIX + 50% cartão).
                    </p>
                    <InputError class="mt-1" :message="form.errors.payment_method" />
                </div>

                <div v-if="!isMisto">
                    <InputLabel for="installments_count" value="Nº de parcelas" />
                    <TextInput
                        id="installments_count"
                        v-model.number="form.installments_count"
                        type="number"
                        min="1"
                        max="60"
                        class="mt-1 block w-full"
                        required
                    />
                    <InputError class="mt-1" :message="form.errors.installments_count" />
                </div>

                <div v-else class="space-y-3 rounded-xl border border-slate-200 bg-slate-50/80 p-4">
                    <div>
                        <h4 class="text-sm font-semibold text-slate-900">Composição do pagamento</h4>
                        <p class="mt-1 text-xs text-slate-600">A soma dos percentuais deve ser 100%.</p>
                    </div>
                    <div
                        v-for="(part, index) in form.mix_parts"
                        :key="`mix-${index}`"
                        class="grid grid-cols-12 items-start gap-2"
                    >
                        <div class="col-span-5">
                            <select v-model="part.method" :class="fieldClass">
                                <option v-for="opt in MIX_METHOD_OPTIONS" :key="opt.value" :value="opt.value">
                                    {{ opt.label }}
                                </option>
                            </select>
                        </div>
                        <div class="col-span-5">
                            <input
                                v-model="part.percent"
                                type="number"
                                step="0.01"
                                min="0.01"
                                max="100"
                                placeholder="%"
                                :class="fieldClass"
                            />
                            <p class="mt-1 text-xs text-slate-500">
                                {{ formatBRL(mixPartsWithAmounts[index]?.amount_cents ?? 0) }}
                            </p>
                        </div>
                        <div class="col-span-2 flex justify-end pt-2">
                            <button
                                type="button"
                                class="text-xs font-medium text-red-600 hover:underline disabled:opacity-40"
                                :disabled="form.mix_parts.length <= 2"
                                @click="removeMixPart(index)"
                            >
                                Remover
                            </button>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="text-sm font-semibold text-talents-700 hover:underline"
                        @click="addMixPart"
                    >
                        + Adicionar parte
                    </button>
                    <p class="text-xs text-slate-600">Soma: {{ mixPercentSum.toFixed(2) }}%</p>
                </div>

                <div>
                    <InputLabel for="first_due_date" value="1º vencimento" />
                    <TextInput
                        id="first_due_date"
                        v-model="form.first_due_date"
                        type="date"
                        class="mt-1 block w-full"
                        required
                    />
                    <InputError class="mt-1" :message="form.errors.first_due_date" />
                </div>
            </template>

            <div>
                <InputLabel for="notes" value="Observações" />
                <textarea id="notes" v-model="form.notes" rows="3" :class="fieldClass" />
                <InputError class="mt-1" :message="form.errors.notes" />
            </div>

            <div class="flex justify-end pt-2">
                <PrimaryButton type="submit" :disabled="form.processing">
                    <template v-if="isEdit">
                        {{ form.processing ? 'Salvando…' : 'Salvar alterações' }}
                    </template>
                    <template v-else>
                        {{ form.processing ? 'Criando…' : 'Criar venda' }}
                    </template>
                </PrimaryButton>
            </div>
        </form>
    </AdminLayout>
</template>
