<script setup>
import FormPageHeader from '@/Components/FormPageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { COLLECTIVE_BARGAINING_MONTHS } from '@/utils/collectiveBargainingMonths';
import axios from 'axios';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineProps({ plans: Array });

const form = useForm({
    name: '',
    contact_email: '',
    legal_name: '',
    cnpj: '',
    segment: '',
    activity_branch: '',
    collective_bargaining_month: null,
    address_street: '',
    address_neighborhood: '',
    address_city: '',
    address_state: '',
    address_zip: '',
    tax_regime: '',
    employee_count_estimate: null,
    plan_id: null,
    is_active: true,
});

const lookupLoading = ref(false);
const lookupError = ref('');

const cnpjDigitCount = computed(() => (form.cnpj.match(/\d/g) || []).length);
const canLookupCnpj = computed(() => cnpjDigitCount.value === 14);

const fetchCnpjFromReceita = async () => {
    lookupError.value = '';
    if (!canLookupCnpj.value) {
        lookupError.value = 'Informe um CNPJ com 14 dígitos.';
        return;
    }
    lookupLoading.value = true;
    try {
        const { data } = await axios.get(route('admin.companies.lookup-cnpj'), {
            params: { cnpj: form.cnpj },
        });
        form.legal_name = data.legal_name ?? '';
        form.name = data.name ?? '';
        form.contact_email = data.contact_email ?? '';
        form.cnpj = data.cnpj ?? form.cnpj;
        form.segment = data.segment ?? '';
        form.address_street = data.address_street ?? '';
        form.address_neighborhood = data.address_neighborhood ?? '';
        form.address_city = data.address_city ?? '';
        form.address_state = data.address_state ?? '';
        form.address_zip = data.address_zip ?? '';
        form.tax_regime = data.tax_regime ?? '';
    } catch (e) {
        const d = e.response?.data;
        const msg =
            typeof d?.message === 'string'
                ? d.message
                : d?.errors?.cnpj?.[0] ?? 'Não foi possível consultar o CNPJ.';
        lookupError.value = msg;
    } finally {
        lookupLoading.value = false;
    }
};

const submit = () => {
    form.post(route('admin.companies.store'));
};
</script>

<template>
    <Head title="Nova empresa" />

    <AdminLayout>
        <template #header>
            <FormPageHeader :back-href="route('admin.companies.index')" title="Nova empresa" />
        </template>

        <form class="surface-card max-w-4xl space-y-4 p-6 text-slate-900" @submit.prevent="submit">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 md:items-start">
                <div>
                    <InputLabel for="cnpj" value="CNPJ" />
                    <p class="mt-0.5 text-xs text-gray-500">
                        Informe o CNPJ e busque na Receita Federal para preencher razão social, nome fantasia, endereço, segmento e regime tributário.
                    </p>
                    <div class="mt-2 flex flex-col gap-2 sm:flex-row sm:items-stretch">
                        <TextInput id="cnpj" v-model="form.cnpj" class="block w-full sm:max-w-md" placeholder="00.000.000/0001-00" />
                        <SecondaryButton
                            type="button"
                            class="shrink-0 justify-center disabled:!opacity-100 sm:self-auto sm:py-2"
                            :disabled="form.processing || lookupLoading"
                            @click="fetchCnpjFromReceita"
                        >
                            {{ lookupLoading ? 'Buscando…' : 'Buscar na Receita Federal' }}
                        </SecondaryButton>
                    </div>
                    <p v-if="lookupError" class="mt-2 text-sm text-red-600">{{ lookupError }}</p>
                </div>
                <div>
                    <InputLabel for="legal_name" value="Razão social" />
                    <TextInput id="legal_name" v-model="form.legal_name" class="mt-1 block w-full" />
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 md:items-start">
                <div>
                    <InputLabel for="name" value="Nome fantasia" />
                    <TextInput id="name" v-model="form.name" class="mt-1 block w-full" required />
                    <InputError class="mt-2" :message="form.errors.name" />
                </div>
                <div>
                    <InputLabel for="contact_email" value="E-mail do administrador da empresa" />
                    <TextInput id="contact_email" v-model="form.contact_email" type="email" class="mt-1 block w-full" required autocomplete="email" />
                    <p class="mt-1 text-xs text-gray-500">
                        Será criado um usuário com este e-mail (administrador da empresa). Ele receberá um link para definir a senha e acessar o portal em /client.
                    </p>
                    <InputError class="mt-2" :message="form.errors.contact_email" />
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 md:items-start">
                <div>
                    <InputLabel for="address_street" value="Logradouro (rua, nº, complemento)" />
                    <TextInput id="address_street" v-model="form.address_street" class="mt-1 block w-full" />
                    <InputError class="mt-2" :message="form.errors.address_street" />
                </div>
                <div>
                    <InputLabel for="address_neighborhood" value="Bairro" />
                    <TextInput id="address_neighborhood" v-model="form.address_neighborhood" class="mt-1 block w-full" />
                    <InputError class="mt-2" :message="form.errors.address_neighborhood" />
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 md:items-start">
                <div>
                    <InputLabel for="address_city" value="Município" />
                    <TextInput id="address_city" v-model="form.address_city" class="mt-1 block w-full" />
                    <InputError class="mt-2" :message="form.errors.address_city" />
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
                    <InputError class="mt-2" :message="form.errors.address_state" />
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 md:items-start">
                <div>
                    <InputLabel for="address_zip" value="CEP" />
                    <TextInput id="address_zip" v-model="form.address_zip" class="mt-1 block w-full max-w-[10rem]" placeholder="00000-000" />
                    <InputError class="mt-2" :message="form.errors.address_zip" />
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 md:items-start">
                <div>
                    <InputLabel for="segment" value="Segmento" />
                    <TextInput id="segment" v-model="form.segment" class="mt-1 block w-full" />
                </div>
                <div>
                    <InputLabel for="activity_branch" value="Ramo de atividade" />
                    <p class="mt-0.5 text-xs text-gray-500">
                        Usado para campanhas de contribuição associativa (sindical).
                    </p>
                    <TextInput id="activity_branch" v-model="form.activity_branch" class="mt-1 block w-full" />
                    <InputError class="mt-2" :message="form.errors.activity_branch" />
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 md:items-start">
                <div>
                    <InputLabel for="collective_bargaining_month" value="Mês do dissídio" />
                    <select
                        id="collective_bargaining_month"
                        v-model="form.collective_bargaining_month"
                        class="mt-1 block w-full rounded-md border border-gray-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    >
                        <option :value="null">— Selecione —</option>
                        <option v-for="month in COLLECTIVE_BARGAINING_MONTHS" :key="month.value" :value="month.value">
                            {{ month.label }}
                        </option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.collective_bargaining_month" />
                </div>
                <div>
                    <InputLabel for="tax_regime" value="Regime de tributação" />
                    <TextInput
                        id="tax_regime"
                        v-model="form.tax_regime"
                        class="mt-1 block w-full"
                        placeholder="Ex.: Simples Nacional, conforme a Receita Federal"
                    />
                    <InputError class="mt-2" :message="form.errors.tax_regime" />
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 md:items-start">
                <div>
                    <InputLabel for="employee_count_estimate" value="Qtd. colaboradores (estimativa)" />
                    <TextInput id="employee_count_estimate" type="number" v-model="form.employee_count_estimate" class="mt-1 block w-full" />
                </div>
            </div>

            <div>
                <InputLabel for="plan_id" value="Plano inicial (opcional)" />
                <select id="plan_id" v-model="form.plan_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-talents-500 focus:ring-talents-500">
                    <option :value="null">—</option>
                    <option v-for="p in plans" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
            </div>
            <PrimaryButton :disabled="form.processing">Salvar</PrimaryButton>
        </form>
    </AdminLayout>
</template>
