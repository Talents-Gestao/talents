<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import FormPageHeader from '@/Components/FormPageHeader.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { formatCnpj, maskCnpj } from '@/utils/formatCnpj';
import { StarIcon as StarOutline } from '@heroicons/vue/24/outline';
import { StarIcon as StarSolid } from '@heroicons/vue/24/solid';
import { Head, Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';

const props = defineProps({
    mode: { type: String, required: true },
    diagnostic: { type: Object, default: null },
    prefill: { type: Object, default: null },
    companies: { type: Array, default: () => [] },
});

const initial = props.diagnostic ?? props.prefill ?? {};

const form = useForm({
    company_id: initial.company_id ?? '',
    company_name: initial.company_name ?? '',
    cnpj: maskCnpj(initial.cnpj ?? ''),
    segment: initial.segment ?? '',
    employee_count: initial.employee_count ?? '',
    responsible_name: initial.responsible_name ?? '',
    email: initial.email ?? '',
    phone: initial.phone ?? '',
    company_history: initial.company_history ?? '',
    biggest_challenge: initial.biggest_challenge ?? '',
    hr_maturity: initial.hr_maturity ?? null,
});

const fieldClass =
    'mt-1 block w-full rounded-xl border-slate-200 shadow-sm focus:border-talents-400 focus:ring-talents-200/70';

const lookupLoading = ref(false);
const lookupError = ref('');
const lookupSuccess = ref('');
/** null | 'found' | 'not_found' */
const cnpjLookupStatus = ref(null);

const cnpjDigitCount = computed(() => (String(form.cnpj || '').match(/\d/g) || []).length);
const canLookupCnpj = computed(() => cnpjDigitCount.value === 14);
const cnpjBlocksSave = computed(() => cnpjLookupStatus.value === 'not_found');
const canSubmit = computed(() => !form.processing && !lookupLoading.value && !cnpjBlocksSave.value);

const fetchCnpjFromReceita = async () => {
    lookupError.value = '';
    lookupSuccess.value = '';
    cnpjLookupStatus.value = null;
    if (!canLookupCnpj.value) {
        lookupError.value = 'Informe um CNPJ com 14 dígitos.';
        return;
    }
    lookupLoading.value = true;
    try {
        const { data } = await axios.get(route('admin.companies.lookup-cnpj'), {
            params: { cnpj: form.cnpj },
        });
        form.cnpj = maskCnpj(data.cnpj ?? form.cnpj);
        lookupSuccess.value = 'Empresa encontrada';
        cnpjLookupStatus.value = 'found';
    } catch (e) {
        const d = e.response?.data;
        const apiMessage =
            typeof d?.message === 'string'
                ? d.message
                : d?.errors?.cnpj?.[0] ?? '';

        // Erros de infraestrutura/configuração: manter detalhe; CNPJ inválido/inexistente: label simples.
        if (/configurada|limite|fila|indisponível|consultar o CNPJ|Resposta|14 dígitos/i.test(apiMessage)) {
            lookupError.value = apiMessage;
            cnpjLookupStatus.value = null;
        } else {
            lookupError.value = 'Empresa não encontrada';
            cnpjLookupStatus.value = 'not_found';
        }
    } finally {
        lookupLoading.value = false;
    }
};

const onCnpjInput = (event) => {
    form.cnpj = maskCnpj(event.target.value);
    lookupError.value = '';
    lookupSuccess.value = '';
    cnpjLookupStatus.value = null;
};

const onCompanySelect = () => {
    const id = form.company_id ? Number(form.company_id) : null;
    const company = props.companies.find((c) => c.id === id);
    if (company && !form.company_name) {
        form.company_name = company.name;
    }
};

const setMaturity = (value) => {
    form.hr_maturity = form.hr_maturity === value ? null : value;
};

const clearForm = () => {
    form.company_id = '';
    form.company_name = '';
    form.cnpj = '';
    form.segment = '';
    form.employee_count = '';
    form.responsible_name = '';
    form.email = '';
    form.phone = '';
    form.company_history = '';
    form.biggest_challenge = '';
    form.hr_maturity = null;
    lookupError.value = '';
    lookupSuccess.value = '';
    cnpjLookupStatus.value = null;
    form.clearErrors();
};

const submit = () => {
    if (cnpjBlocksSave.value) {
        lookupError.value = 'Empresa não encontrada';
        return;
    }

    const payload = {
        ...form.data(),
        company_id: form.company_id || null,
        cnpj: formatCnpj(form.cnpj),
    };

    if (props.mode === 'create') {
        form.transform(() => payload).post(route('admin.diagnostico-empresarial.store'));
        return;
    }

    form.transform(() => payload).put(route('admin.diagnostico-empresarial.update', props.diagnostic.id));
};
</script>

<template>
    <Head :title="mode === 'create' ? 'Novo diagnóstico empresarial' : 'Editar diagnóstico'" />

    <AdminLayout>
        <template #header>
            <FormPageHeader
                :back-href="route('admin.diagnostico-empresarial.index')"
                back-label="Diagnósticos"
                :title="mode === 'create' ? 'Novo diagnóstico empresarial' : 'Editar diagnóstico'"
                subtitle="Diagnóstico Talents de maturidade em gestão de pessoas"
            />
        </template>

        <div class="mx-auto max-w-3xl space-y-6">
            <section class="surface-card overflow-hidden p-6 sm:p-8">
                <div class="flex items-start gap-4">
                    <img
                        src="/images/logo-icon.png"
                        alt=""
                        class="h-12 w-12 shrink-0 object-contain"
                        aria-hidden="true"
                    />
                    <div>
                        <h2 class="text-xl font-bold text-slate-900">Diagnóstico Gestão de Pessoas Talents</h2>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600">
                            O Diagnóstico Talents é uma ferramenta estratégica para identificar o grau de maturidade da
                            sua empresa em Gestão de Pessoas. Nosso objetivo é mapear pontos fortes, vulnerabilidades e
                            oportunidades de desenvolvimento para que sua organização possa crescer de forma
                            sustentável. Ao final deste processo, você terá um retrato claro da sua realidade e um
                            direcionamento prático para evoluir.
                        </p>
                        <p class="mt-3 text-sm font-semibold text-talents-700">
                            Conectando Talentos e Transformando Negócios
                        </p>
                    </div>
                </div>
            </section>

            <form class="surface-card space-y-5 p-6 sm:p-8" @submit.prevent="submit">
                <div>
                    <InputLabel for="company_id" value="Vincular a cliente (opcional)" />
                    <select
                        id="company_id"
                        v-model="form.company_id"
                        :class="fieldClass"
                        @change="onCompanySelect"
                    >
                        <option value="">Sem vínculo</option>
                        <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.company_id" />
                </div>

                <div>
                    <InputLabel for="company_name" value="Nome da Empresa" />
                    <TextInput id="company_name" v-model="form.company_name" class="mt-1 block w-full" required />
                    <InputError class="mt-1" :message="form.errors.company_name" />
                </div>

                <div>
                    <InputLabel for="cnpj" value="CNPJ" />
                    <p class="mt-0.5 text-xs text-slate-500">
                        Informe o CNPJ e busque na Receita Federal para verificar se a empresa existe.
                    </p>
                    <div class="mt-2 flex flex-col gap-2 sm:flex-row sm:items-stretch">
                        <TextInput
                            id="cnpj"
                            v-model="form.cnpj"
                            class="block w-full"
                            placeholder="00.000.000/0001-00"
                            inputmode="numeric"
                            maxlength="18"
                            @input="onCnpjInput"
                        />
                        <SecondaryButton
                            type="button"
                            class="shrink-0 justify-center disabled:!opacity-100 sm:self-auto sm:py-2"
                            :disabled="form.processing || lookupLoading || !canLookupCnpj"
                            @click="fetchCnpjFromReceita"
                        >
                            {{ lookupLoading ? 'Buscando…' : 'Buscar na Receita Federal' }}
                        </SecondaryButton>
                    </div>
                    <p
                        v-if="lookupError"
                        class="mt-2 inline-flex rounded-lg bg-red-50 px-2.5 py-1 text-sm font-semibold text-red-700"
                    >
                        {{ lookupError }}
                    </p>
                    <p
                        v-else-if="lookupSuccess"
                        class="mt-2 inline-flex rounded-lg bg-emerald-50 px-2.5 py-1 text-sm font-semibold text-emerald-700"
                    >
                        {{ lookupSuccess }}
                    </p>
                    <p
                        v-if="cnpjBlocksSave"
                        class="mt-2 text-sm text-red-600"
                    >
                        Não é possível salvar o diagnóstico com um CNPJ inexistente. Corrija o CNPJ e busque novamente.
                    </p>
                    <InputError class="mt-1" :message="form.errors.cnpj" />
                </div>

                <div>
                    <InputLabel for="segment" value="Segmento de Atuação" />
                    <TextInput id="segment" v-model="form.segment" class="mt-1 block w-full" />
                    <InputError class="mt-1" :message="form.errors.segment" />
                </div>

                <div>
                    <InputLabel for="employee_count" value="Quantidade de Colaboradores" />
                    <TextInput id="employee_count" v-model="form.employee_count" class="mt-1 block w-full" />
                    <InputError class="mt-1" :message="form.errors.employee_count" />
                </div>

                <div>
                    <InputLabel for="responsible_name" value="Nome completo do Responsável do CNPJ" />
                    <TextInput
                        id="responsible_name"
                        v-model="form.responsible_name"
                        class="mt-1 block w-full"
                        required
                    />
                    <InputError class="mt-1" :message="form.errors.responsible_name" />
                </div>

                <div>
                    <InputLabel for="email" value="E-mail para envio do Diagnóstico" />
                    <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" required />
                    <InputError class="mt-1" :message="form.errors.email" />
                </div>

                <div>
                    <InputLabel for="phone" value="Contato Whatsapp/Telefone" />
                    <TextInput id="phone" v-model="form.phone" class="mt-1 block w-full" />
                    <InputError class="mt-1" :message="form.errors.phone" />
                </div>

                <div>
                    <InputLabel
                        for="company_history"
                        value="Conte um pouco sobre a empresa. Sua história? Quantos anos de Mercado? Quais produtos e serviços?"
                    />
                    <textarea
                        id="company_history"
                        v-model="form.company_history"
                        rows="4"
                        :class="fieldClass"
                    />
                    <InputError class="mt-1" :message="form.errors.company_history" />
                </div>

                <div>
                    <InputLabel
                        for="biggest_challenge"
                        value="Qual o maior desafio em Gestão de Pessoas dentro da sua empresa hoje?"
                    />
                    <textarea
                        id="biggest_challenge"
                        v-model="form.biggest_challenge"
                        rows="4"
                        :class="fieldClass"
                    />
                    <InputError class="mt-1" :message="form.errors.biggest_challenge" />
                </div>

                <div>
                    <p class="text-sm font-medium text-slate-700">
                        De 0 a 10, como você avalia a maturidade do RH da sua empresa?
                    </p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button
                            v-for="n in 10"
                            :key="n"
                            type="button"
                            class="flex w-11 flex-col items-center gap-1 rounded-xl border px-1 py-2 transition"
                            :class="
                                form.hr_maturity === n
                                    ? 'border-talents-400 bg-talents-50 text-talents-800'
                                    : 'border-slate-200 bg-white text-slate-600 hover:border-talents-200'
                            "
                            :aria-pressed="form.hr_maturity === n"
                            @click="setMaturity(n)"
                        >
                            <span class="text-xs font-semibold">{{ n }}</span>
                            <StarSolid v-if="form.hr_maturity === n" class="h-4 w-4 text-amber-400" />
                            <StarOutline v-else class="h-4 w-4 text-slate-300" />
                        </button>
                    </div>
                    <InputError class="mt-1" :message="form.errors.hr_maturity" />
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-5">
                    <PrimaryButton type="submit" :disabled="!canSubmit">
                        {{ mode === 'create' ? 'Salvar diagnóstico' : 'Atualizar diagnóstico' }}
                    </PrimaryButton>
                    <div class="flex items-center gap-3">
                        <button
                            v-if="mode === 'create'"
                            type="button"
                            class="text-sm font-medium text-slate-500 hover:text-slate-800 hover:underline"
                            @click="clearForm"
                        >
                            Limpar formulário
                        </button>
                        <Link :href="route('admin.diagnostico-empresarial.index')">
                            <SecondaryButton type="button">Cancelar</SecondaryButton>
                        </Link>
                    </div>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
