<script setup>
import FormPageHeader from '@/Components/FormPageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import axios from 'axios';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    settings: { type: Object, required: true },
});

const page = usePage();

const form = useForm({
    company_name: props.settings.company_name ?? '',
    company_cnpj: props.settings.company_cnpj ?? '',
    company_address: props.settings.company_address ?? '',
    company_city_state: props.settings.company_city_state ?? '',
    company_phone: props.settings.company_phone ?? '',
    company_email: props.settings.company_email ?? '',
    company_representative_line: props.settings.company_representative_line ?? '',
    company_forum_city_state: props.settings.company_forum_city_state ?? '',
    company_contract_signatory_name: props.settings.company_contract_signatory_name ?? '',
    company_contract_signatory_cpf: props.settings.company_contract_signatory_cpf ?? '',
    default_payment_terms: props.settings.default_payment_terms ?? '',
    default_prazo_dias: props.settings.default_prazo_dias ?? '',
});

const cnpjLookupLoading = ref(false);
const cnpjLookupError = ref('');
const cnpjLookupSuccess = ref('');
const cnpjDigitCount = computed(() => (String(form.company_cnpj || '').match(/\d/g) || []).length);
const canLookupCnpj = computed(() => cnpjDigitCount.value === 14);

const showContractGapWarning = computed(() => {
    const n = (v) => String(v ?? '').trim();
    return !n(form.company_name) || !n(form.company_cnpj) || !n(form.company_representative_line);
});

const fetchCnpjFromReceita = async () => {
    cnpjLookupError.value = '';
    cnpjLookupSuccess.value = '';
    if (!canLookupCnpj.value) {
        cnpjLookupError.value = 'Informe um CNPJ com 14 dígitos.';
        return;
    }
    cnpjLookupLoading.value = true;
    try {
        const { data } = await axios.get(route('admin.companies.lookup-cnpj'), {
            params: { cnpj: form.company_cnpj },
        });
        form.company_cnpj = data.cnpj ?? form.company_cnpj;
        const nome = data.name || data.legal_name;
        if (nome) {
            form.company_name = nome;
        }
        if (data.contact_email) {
            form.company_email = data.contact_email;
        }
        const street = data.address_street ?? '';
        const bairro = data.address_neighborhood ?? '';
        const cidade = data.address_city ?? '';
        const uf = data.address_state ?? '';
        const cep = data.address_zip ?? '';
        const linha1 = [street, bairro].filter(Boolean).join(' — ');
        const linha2 = [cidade && uf ? `${cidade} — ${uf}` : cidade || uf, cep ? `CEP ${cep}` : '']
            .filter(Boolean)
            .join(', ');
        const endereco = [linha1, linha2].filter(Boolean).join(', ');
        if (endereco) {
            form.company_address = endereco;
        }
        if (cidade && uf) {
            form.company_city_state = `${cidade} — ${uf}`;
        }
        cnpjLookupSuccess.value = 'Dados preenchidos a partir da Receita Federal.';
    } catch (e) {
        const d = e.response?.data;
        cnpjLookupError.value =
            typeof d?.message === 'string'
                ? d.message
                : d?.errors?.cnpj?.[0] ?? 'Não foi possível consultar o CNPJ.';
    } finally {
        cnpjLookupLoading.value = false;
    }
};

const submit = () => {
    form.put(route('admin.empresa-talents.update'), { preserveScroll: true });
};
</script>

<template>
    <Head title="Empresa Talents (CONTRATADA)" />

    <AdminLayout>
        <template #header>
            <FormPageHeader
                :back-href="`${route('admin.comercial.settings.edit')}?tab=empresa`"
                back-label="Configurações comerciais"
                title="Empresa Talents — dados institucionais (CONTRATADA)"
                subtitle="Estes dados alimentam os placeholders dos contratos comerciais (empresa_nome, empresa_cnpj, empresa_representacao, etc.)."
            />
        </template>

        <div v-if="page.props.flash?.success" class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
            {{ page.props.flash.success }}
        </div>

        <div
            v-if="showContractGapWarning"
            class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950"
        >
            <strong class="font-semibold">Atenção:</strong> razão social, CNPJ ou representação legal estão em branco.
            O PDF do contrato pode sair com lacunas no bloco da CONTRATADA até você completar e salvar.
        </div>

        <form class="space-y-8" @submit.prevent="submit">
            <section class="surface-card p-6">
                <h3 class="text-lg font-semibold text-slate-900">Identificação</h3>
                <p class="mt-1 text-xs text-slate-500">
                    Informe o CNPJ e use a Receita Federal para pré-preencher nome, e-mail e endereço quando a API estiver configurada.
                </p>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-500">CNPJ</label>
                        <div class="mt-2 flex flex-col gap-2 sm:flex-row sm:items-stretch">
                            <input
                                v-model="form.company_cnpj"
                                type="text"
                                placeholder="00.000.000/0001-00"
                                class="block w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500 sm:max-w-md"
                            />
                            <button
                                type="button"
                                class="inline-flex shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:opacity-60"
                                :disabled="form.processing || cnpjLookupLoading || !canLookupCnpj"
                                @click="fetchCnpjFromReceita"
                            >
                                {{ cnpjLookupLoading ? 'Buscando…' : 'Buscar na Receita Federal' }}
                            </button>
                        </div>
                        <p v-if="cnpjLookupError" class="mt-2 text-sm text-rose-600">{{ cnpjLookupError }}</p>
                        <p v-else-if="cnpjLookupSuccess" class="mt-2 text-sm text-emerald-700">{{ cnpjLookupSuccess }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Razão social / Nome</label>
                        <input
                            v-model="form.company_name"
                            type="text"
                            class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        />
                    </div>
                    <div>
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-500">E-mail</label>
                        <input
                            v-model="form.company_email"
                            type="email"
                            class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        />
                    </div>
                    <div>
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-500">
                            Celular / WhatsApp (empresa — ZapSign 2º signatário)
                        </label>
                        <input
                            v-model="form.company_phone"
                            type="text"
                            placeholder="DDD + número da Talents para receber o link por WhatsApp"
                            class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        />
                        <p class="mt-1 text-xs text-slate-500">
                            Se não usar e-mail institucional no ZapSign, cadastre o celular com DDD (mesmo número pode ser usado para o signatário da CONTRATADA).
                        </p>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Endereço</label>
                        <input
                            v-model="form.company_address"
                            type="text"
                            class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Cidade / UF</label>
                        <input
                            v-model="form.company_city_state"
                            type="text"
                            placeholder="Várzea Paulista — SP"
                            class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        />
                    </div>
                </div>
            </section>

            <section class="surface-card p-6">
                <h3 class="text-lg font-semibold text-slate-900">Representação legal nos contratos</h3>
                <p class="mt-1 text-xs text-slate-500">
                    O texto abaixo compõe o placeholder <code class="rounded bg-slate-100 px-1">empresa_representacao</code> (frase completa após o endereço).
                </p>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Representação (Contratada)</label>
                        <textarea
                            v-model="form.company_representative_line"
                            rows="3"
                            placeholder='Ex.: neste ato representada por Fulana de Tal, CPF nº 000.000.000-00, conforme contrato social.'
                            class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        />
                    </div>
                    <div>
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-500">
                            Signatário(a) CONTRATADA — nome (ex.: Suzane)
                        </label>
                        <input
                            v-model="form.company_contract_signatory_name"
                            type="text"
                            class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        />
                    </div>
                    <div>
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Signatário(a) — CPF</label>
                        <input
                            v-model="form.company_contract_signatory_cpf"
                            type="text"
                            class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        />
                    </div>
                </div>
            </section>

            <section class="surface-card p-6">
                <h3 class="text-lg font-semibold text-slate-900">Foro padrão</h3>
                <p class="mt-1 text-xs text-slate-500">
                    Usado em <code class="rounded bg-slate-100 px-1">foro_comarca</code>. Se vazio, o sistema pode usar Cidade/UF ou o padrão legal.
                </p>
                <div class="mt-4">
                    <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Comarca (foro)</label>
                    <input
                        v-model="form.company_forum_city_state"
                        type="text"
                        placeholder="Várzea Paulista – SP"
                        class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    />
                </div>
            </section>

            <section class="surface-card p-6">
                <h3 class="text-lg font-semibold text-slate-900">Termos comerciais padrão</h3>
                <p class="mt-1 text-xs text-slate-500">Referência para <code class="rounded bg-slate-100 px-1">forma_pagamento</code> e prazo nos contratos.</p>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Forma de pagamento padrão</label>
                        <textarea
                            v-model="form.default_payment_terms"
                            rows="4"
                            class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        />
                    </div>
                    <div class="max-w-xs">
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Prazo padrão (dias)</label>
                        <input
                            v-model.number="form.default_prazo_dias"
                            type="number"
                            min="0"
                            class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        />
                    </div>
                </div>
            </section>

            <div class="flex flex-wrap items-center justify-between gap-4">
                <Link
                    :href="`${route('admin.comercial.settings.edit')}?tab=empresa`"
                    class="text-sm font-semibold text-talents-700 hover:underline"
                >
                    Voltar para Comercial → Configurações
                </Link>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex items-center rounded-xl bg-talents-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-talents-700 disabled:opacity-60"
                >
                    Salvar dados da Talents
                </button>
            </div>
        </form>
    </AdminLayout>
</template>
