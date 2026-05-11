<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ContractTemplatesManager from '@/Pages/Admin/Comercial/ContractTemplatesManager.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps({
    settings: { type: Object, required: true },
    users: { type: Array, default: () => [] },
    contractTemplates: { type: Array, default: () => [] },
});

const tab = ref('faixas');

onMounted(() => {
    const q = new URLSearchParams(window.location.search).get('tab');
    if (q && ['faixas', 'fixos', 'pdf', 'vendedores', 'empresa', 'contratos'].includes(q)) {
        tab.value = q;
    }
});

const form = useForm({
    profiler_tier1_max: props.settings.profiler_tier1_max,
    profiler_tier1_cents: props.settings.profiler_tier1_cents,
    profiler_tier2_max: props.settings.profiler_tier2_max,
    profiler_tier2_cents: props.settings.profiler_tier2_cents,
    profiler_tier3_max: props.settings.profiler_tier3_max,
    profiler_tier3_cents: props.settings.profiler_tier3_cents,
    profiler_tier4_cents: props.settings.profiler_tier4_cents,

    pesquisas_tier1_max: props.settings.pesquisas_tier1_max,
    pesquisas_tier1_cents: props.settings.pesquisas_tier1_cents,
    pesquisas_tier2_max: props.settings.pesquisas_tier2_max,
    pesquisas_tier2_cents: props.settings.pesquisas_tier2_cents,
    pesquisas_tier3_max: props.settings.pesquisas_tier3_max,
    pesquisas_tier3_cents: props.settings.pesquisas_tier3_cents,
    pesquisas_tier4_cents: props.settings.pesquisas_tier4_cents,

    direcionamento_tier1_max: props.settings.direcionamento_tier1_max,
    direcionamento_tier1_cents: props.settings.direcionamento_tier1_cents,
    direcionamento_tier2_max: props.settings.direcionamento_tier2_max,
    direcionamento_tier2_cents: props.settings.direcionamento_tier2_cents,
    direcionamento_tier3_max: props.settings.direcionamento_tier3_max,
    direcionamento_tier3_cents: props.settings.direcionamento_tier3_cents,
    direcionamento_tier4_cents: props.settings.direcionamento_tier4_cents,

    nr1_tier1_max: props.settings.nr1_tier1_max,
    nr1_tier1_cents: props.settings.nr1_tier1_cents,
    nr1_tier2_max: props.settings.nr1_tier2_max,
    nr1_tier2_cents: props.settings.nr1_tier2_cents,
    nr1_tier3_max: props.settings.nr1_tier3_max,
    nr1_tier3_cents: props.settings.nr1_tier3_cents,
    nr1_tier4_cents: props.settings.nr1_tier4_cents,

    devolutiva_individual_cents: props.settings.devolutiva_individual_cents,
    devolutiva_grupo_cents: props.settings.devolutiva_grupo_cents,

    nr1_implantacao_online_cents: props.settings.nr1_implantacao_online_cents,
    nr1_implantacao_presencial_cents: props.settings.nr1_implantacao_presencial_cents,

    palestras_base_cents: props.settings.palestras_base_cents,
    palestras_threshold_funcionarios: props.settings.palestras_threshold_funcionarios,
    palestras_multiplier: props.settings.palestras_multiplier,

    pdf_validade_dias: props.settings.pdf_validade_dias,
    pdf_observacoes: props.settings.pdf_observacoes ?? '',
    pdf_aceite_texto: props.settings.pdf_aceite_texto ?? '',

    company_name: props.settings.company_name ?? '',
    company_cnpj: props.settings.company_cnpj ?? '',
    company_address: props.settings.company_address ?? '',
    company_city_state: props.settings.company_city_state ?? '',
    company_phone: props.settings.company_phone ?? '',
    company_email: props.settings.company_email ?? '',
    company_representative_line: props.settings.company_representative_line ?? '',
    company_forum_city_state: props.settings.company_forum_city_state ?? '',
    default_payment_terms: props.settings.default_payment_terms ?? '',
    default_prazo_dias: props.settings.default_prazo_dias ?? '',
});

const submit = () => {
    form.put(route('admin.comercial.settings.update'), { preserveScroll: true });
};

// Helpers para edição em reais
const moneyKeys = [
    'profiler_tier1_cents', 'profiler_tier2_cents', 'profiler_tier3_cents', 'profiler_tier4_cents',
    'pesquisas_tier1_cents', 'pesquisas_tier2_cents', 'pesquisas_tier3_cents', 'pesquisas_tier4_cents',
    'direcionamento_tier1_cents', 'direcionamento_tier2_cents', 'direcionamento_tier3_cents', 'direcionamento_tier4_cents',
    'nr1_tier1_cents', 'nr1_tier2_cents', 'nr1_tier3_cents', 'nr1_tier4_cents',
    'devolutiva_individual_cents', 'devolutiva_grupo_cents',
    'nr1_implantacao_online_cents', 'nr1_implantacao_presencial_cents',
    'palestras_base_cents',
];
const reaisProxy = {};
moneyKeys.forEach((key) => {
    reaisProxy[key] = ref(((Number(form[key]) || 0) / 100).toFixed(2).replace('.', ','));
    watch(reaisProxy[key], (val) => {
        const numeric = Number(String(val ?? '').replace(/\./g, '').replace(',', '.'));
        form[key] = Number.isFinite(numeric) ? Math.max(0, Math.round(numeric * 100)) : 0;
    });
});

const tabs = [
    { id: 'faixas', label: 'Faixas por funcionários' },
    { id: 'fixos', label: 'Valores fixos' },
    { id: 'pdf', label: 'PDF' },
    { id: 'empresa', label: 'Empresa' },
    { id: 'contratos', label: 'Contratos' },
    { id: 'vendedores', label: 'Vendedores' },
];

const toggleSeller = (user) => {
    router.patch(
        route('admin.comercial.settings.sellers.toggle', user.id),
        { is_commercial: !user.is_commercial },
        { preserveScroll: true },
    );
};

const tableConfig = computed(() => [
    {
        title: 'Profiler — Diagnóstico Comportamental',
        prefix: 'profiler',
        defaultMaxes: [5, 10, 20],
        helpRow4: 'Acima de 20 funcionários',
    },
    {
        title: 'Pesquisas e Organograma',
        prefix: 'pesquisas',
        defaultMaxes: [10, 20, 30],
        helpRow4: 'Acima de 30 funcionários',
    },
    {
        title: 'Direcionamento Estratégico',
        prefix: 'direcionamento',
        defaultMaxes: [5, 10, 20],
        helpRow4: 'Acima de 20 funcionários',
    },
    {
        title: 'NR-1 — Mapeamento de Risco Psicossocial',
        prefix: 'nr1',
        defaultMaxes: [5, 10, 20],
        helpRow4: 'Acima de 20 funcionários',
    },
]);
</script>

<template>
    <Head title="Comercial — Configurações" />

    <AdminLayout>
        <template #header>
            <div>
                <p class="text-sm text-slate-500">Comercial</p>
                <h2 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">
                    Parâmetros de precificação
                </h2>
            </div>
        </template>

        <div class="surface-card p-1">
            <div class="flex flex-wrap gap-1 p-2">
                <button
                    v-for="t in tabs"
                    :key="t.id"
                    type="button"
                    class="rounded-lg px-3 py-2 text-sm font-medium transition"
                    :class="tab === t.id ? 'bg-talents-600 text-white' : 'text-slate-600 hover:bg-slate-50'"
                    @click="tab = t.id"
                >
                    {{ t.label }}
                </button>
            </div>
        </div>

        <form class="mt-6 space-y-6" @submit.prevent="submit">
            <!-- Tab: Faixas -->
            <template v-if="tab === 'faixas'">
                <section v-for="tbl in tableConfig" :key="tbl.prefix" class="surface-card p-6">
                    <h3 class="text-lg font-semibold text-slate-900">{{ tbl.title }}</h3>
                    <p class="mt-1 text-xs text-slate-500">Faixas por número de funcionários e valor por funcionário (em R$).</p>

                    <div class="mt-4 grid gap-4 sm:grid-cols-3">
                        <div v-for="i in 3" :key="i">
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">
                                Faixa {{ i }} — até X funcionários
                            </label>
                            <input
                                v-model.number="form[`${tbl.prefix}_tier${i}_max`]"
                                type="number"
                                min="1"
                                class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            />
                            <label class="mt-2 text-xs font-medium uppercase tracking-wide text-slate-500">
                                Valor por funcionário (R$)
                            </label>
                            <input
                                v-model="reaisProxy[`${tbl.prefix}_tier${i}_cents`].value"
                                type="text"
                                class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            />
                        </div>
                        <div class="sm:col-span-3">
                            <div class="rounded-xl bg-slate-50 p-3">
                                <label class="text-xs font-medium uppercase tracking-wide text-slate-500">
                                    Faixa final — {{ tbl.helpRow4 }}
                                </label>
                                <input
                                    v-model="reaisProxy[`${tbl.prefix}_tier4_cents`].value"
                                    type="text"
                                    class="mt-1 w-full max-w-xs rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                />
                            </div>
                        </div>
                    </div>
                </section>
            </template>

            <!-- Tab: Valores fixos -->
            <template v-if="tab === 'fixos'">
                <section class="surface-card p-6">
                    <h3 class="text-lg font-semibold text-slate-900">Devolutiva e Diagnóstico</h3>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Individual (R$)</label>
                            <input
                                v-model="reaisProxy.devolutiva_individual_cents.value"
                                type="text"
                                class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            />
                        </div>
                        <div>
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Grupo (R$)</label>
                            <input
                                v-model="reaisProxy.devolutiva_grupo_cents.value"
                                type="text"
                                class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            />
                        </div>
                    </div>
                </section>

                <section class="surface-card p-6">
                    <h3 class="text-lg font-semibold text-slate-900">NR-1 — Implantação</h3>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">On-line (R$ / func.)</label>
                            <input
                                v-model="reaisProxy.nr1_implantacao_online_cents.value"
                                type="text"
                                class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            />
                        </div>
                        <div>
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Presencial (R$ fixo)</label>
                            <input
                                v-model="reaisProxy.nr1_implantacao_presencial_cents.value"
                                type="text"
                                class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            />
                        </div>
                    </div>
                </section>

                <section class="surface-card p-6">
                    <h3 class="text-lg font-semibold text-slate-900">Palestras e Treinamentos</h3>
                    <div class="mt-4 grid gap-4 sm:grid-cols-3">
                        <div>
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Valor base (R$)</label>
                            <input
                                v-model="reaisProxy.palestras_base_cents.value"
                                type="text"
                                class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            />
                        </div>
                        <div>
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Limite de funcionários</label>
                            <input
                                v-model.number="form.palestras_threshold_funcionarios"
                                type="number"
                                min="0"
                                class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            />
                        </div>
                        <div>
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Multiplicador acima do limite</label>
                            <input
                                v-model.number="form.palestras_multiplier"
                                type="number"
                                min="1"
                                max="10"
                                class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            />
                        </div>
                    </div>
                </section>
            </template>

            <!-- Tab: PDF -->
            <template v-if="tab === 'pdf'">
                <section class="surface-card p-6">
                    <h3 class="text-lg font-semibold text-slate-900">Configurações do PDF</h3>

                    <div class="mt-4 grid gap-4">
                        <div class="max-w-xs">
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Validade da proposta (dias)</label>
                            <input
                                v-model.number="form.pdf_validade_dias"
                                type="number"
                                min="1"
                                max="365"
                                class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            />
                        </div>
                        <div>
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Observações padrão (impressas no PDF)</label>
                            <textarea
                                v-model="form.pdf_observacoes"
                                rows="4"
                                class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            />
                        </div>
                        <div>
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Texto de aceite</label>
                            <textarea
                                v-model="form.pdf_aceite_texto"
                                rows="3"
                                class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            />
                        </div>
                    </div>
                </section>
            </template>

            <!-- Tab: Empresa (contratos / placeholders Talents) -->
            <template v-if="tab === 'empresa'">
                <section class="surface-card p-6">
                    <h3 class="text-lg font-semibold text-slate-900">Dados da empresa (Talents)</h3>
                    <p class="mt-1 text-xs text-slate-500">
                        Usados nos placeholders de contrato (empresa_nome, forma_pagamento, prazo_dias, etc.).
                    </p>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Razão social / Nome</label>
                            <input
                                v-model="form.company_name"
                                type="text"
                                class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            />
                        </div>
                        <div>
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">CNPJ</label>
                            <input
                                v-model="form.company_cnpj"
                                type="text"
                                class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            />
                        </div>
                        <div>
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Telefone</label>
                            <input
                                v-model="form.company_phone"
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
                                placeholder="São Paulo — SP"
                                class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Representação legal no contrato (Contratada)</label>
                            <textarea
                                v-model="form.company_representative_line"
                                rows="3"
                                placeholder='Ex.: neste ato representada por Fulana de Tal, CPF nº 000.000.000-00, conforme contrato social.'
                                class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Foro (comarca)</label>
                            <input
                                v-model="form.company_forum_city_state"
                                type="text"
                                placeholder="Várzea Paulista – SP (deixe em branco para usar Cidade/UF ou o padrão legal)"
                                class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            />
                        </div>
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
            </template>

            <div v-if="tab !== 'vendedores' && tab !== 'contratos'" class="flex justify-end">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex items-center rounded-xl bg-talents-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-talents-700 disabled:opacity-60"
                >
                    Salvar parâmetros
                </button>
            </div>
        </form>

        <div v-if="tab === 'contratos'" class="mt-6">
            <ContractTemplatesManager :templates="contractTemplates" />
        </div>

        <section v-if="tab === 'vendedores'" class="surface-card mt-6 p-6">
            <h3 class="text-lg font-semibold text-slate-900">Vendedores comerciais</h3>
            <p class="mt-1 text-xs text-slate-500">
                Marque os usuários administrativos que devem aparecer como vendedores nas propostas.
            </p>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">Nome</th>
                            <th class="px-4 py-3 text-left font-medium">E-mail</th>
                            <th class="px-4 py-3 text-left font-medium">Status</th>
                            <th class="px-4 py-3 text-right font-medium">Vendedor?</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <tr v-for="user in users" :key="user.id">
                            <td class="px-4 py-3 font-medium">{{ user.name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ user.email }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="user.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                                >
                                    {{ user.is_active ? 'Ativo' : 'Inativo' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-xl border px-3 py-1.5 text-xs font-semibold transition"
                                    :class="user.is_commercial
                                        ? 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100'
                                        : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
                                    @click="toggleSeller(user)"
                                >
                                    {{ user.is_commercial ? 'Vendedor' : 'Não é vendedor' }}
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!users.length">
                            <td colspan="4" class="px-4 py-10 text-center text-slate-500">
                                Nenhum usuário administrativo encontrado.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </AdminLayout>
</template>
