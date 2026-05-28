<script setup>
import CommercialModuleNav from '@/Components/Comercial/CommercialModuleNav.vue';
import CommercialPricingShortcuts from '@/Components/Comercial/CommercialPricingShortcuts.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import CommercialProductsManager from '@/Pages/Admin/Comercial/CommercialProductsManager.vue';
import ContractTemplatesManager from '@/Pages/Admin/Comercial/ContractTemplatesManager.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps({
    settings: { type: Object, required: true },
    users: { type: Array, default: () => [] },
    contractTemplates: { type: Array, default: () => [] },
    commercialProducts: { type: Array, default: () => [] },
    pricingTypeLabels: { type: Object, default: () => ({}) },
});

const tab = ref('faixas');

const validTabs = ['faixas', 'fixos', 'produtos', 'pdf', 'vendedores', 'empresa', 'contratos'];

const setTab = (id) => {
    if (!validTabs.includes(id)) return;
    tab.value = id;
    const url = new URL(window.location.href);
    url.searchParams.set('tab', id);
    window.history.replaceState({}, '', url);
};

onMounted(() => {
    const q = new URLSearchParams(window.location.search).get('tab');
    if (q && validTabs.includes(q)) {
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
    default_commission_percent: props.settings.default_commission_percent ?? 0,

    pdf_validade_dias: props.settings.pdf_validade_dias,
    pdf_observacoes: props.settings.pdf_observacoes ?? '',
    pdf_aceite_texto: props.settings.pdf_aceite_texto ?? '',

    zapsign_api_token: '',
    zapsign_api_base_url: props.settings.zapsign_api_base_url ?? 'https://api.zapsign.com.br/api/v1',
    zapsign_send_automatic_email: props.settings.zapsign_send_automatic_email !== false,
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
    { id: 'produtos', label: 'Produtos' },
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
                    Valores e contratos
                </h2>
                <p class="mt-1 text-sm text-slate-600">
                    Tabelas de preço, PDF da proposta e modelos usados na geração de contratos.
                </p>
            </div>
        </template>

        <CommercialModuleNav />
        <CommercialPricingShortcuts />

        <div class="surface-card p-1">
            <div class="flex flex-wrap gap-1 p-2">
                <button
                    v-for="t in tabs"
                    :key="t.id"
                    type="button"
                    class="rounded-lg px-3 py-2 text-sm font-medium transition"
                    :class="tab === t.id ? 'bg-talents-600 text-white' : 'text-slate-600 hover:bg-slate-50'"
                    @click="setTab(t.id)"
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

                <section class="surface-card p-6">
                    <h3 class="text-lg font-semibold text-slate-900">ZapSign — assinatura eletrônica</h3>
                    <p class="mt-1 text-xs text-slate-500">
                        Token de API para enviar contratos gerados à ZapSign. Documentação:
                        <a
                            href="https://docs.zapsign.com.br/documentos/criar-documento"
                            class="font-medium text-talents-700 underline hover:text-talents-800"
                            target="_blank"
                            rel="noopener noreferrer"
                        >criar documento</a>.
                    </p>

                    <div class="mt-4 grid gap-4 max-w-xl">
                        <div>
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Token da API</label>
                            <input
                                v-model="form.zapsign_api_token"
                                type="password"
                                autocomplete="new-password"
                                class="mt-1 w-full rounded-xl border-slate-300 font-mono text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                :placeholder="settings.zapsign_api_token_set ? 'Deixe em branco para manter o token atual' : 'Cole o token ZapSign'"
                            />
                            <p v-if="settings.zapsign_api_token_set" class="mt-1 text-xs text-emerald-700">
                                Token já configurado (oculto). Preencha apenas para substituir.
                            </p>
                        </div>
                        <div>
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">URL base da API</label>
                            <input
                                v-model="form.zapsign_api_base_url"
                                type="url"
                                class="mt-1 w-full rounded-xl border-slate-300 font-mono text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                placeholder="https://api.zapsign.com.br/api/v1"
                            />
                        </div>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                            <input
                                v-model="form.zapsign_send_automatic_email"
                                type="checkbox"
                                class="rounded border-slate-300 text-talents-600 focus:ring-talents-500"
                            />
                            Enviar e-mail automático aos signatários (quando suportado pela ZapSign)
                        </label>
                    </div>
                </section>
            </template>

            <!-- Tab: Empresa (contratos / placeholders Talents) -->
            <template v-if="tab === 'empresa'">
                <section class="surface-card p-6">
                    <h3 class="text-lg font-semibold text-slate-900">Dados da empresa Talents (CONTRATADA)</h3>
                    <p class="mt-1 text-xs text-slate-500">
                        Razão social, CNPJ, endereço, representação legal, foro e termos padrão — usados nos PDFs de contrato e proposta.
                        A edição foi movida para uma página dedicada no menu lateral.
                    </p>
                    <dl class="mt-6 grid gap-3 text-sm sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Razão social</dt>
                            <dd class="mt-1 font-medium text-slate-900">{{ settings.company_name || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">CNPJ</dt>
                            <dd class="mt-1 text-slate-800">{{ settings.company_cnpj || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">E-mail</dt>
                            <dd class="mt-1 text-slate-800">{{ settings.company_email || '—' }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Endereço</dt>
                            <dd class="mt-1 text-slate-800">{{ settings.company_address || '—' }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Representação legal</dt>
                            <dd class="mt-1 text-slate-700">{{ settings.company_representative_line || '—' }}</dd>
                        </div>
                    </dl>
                    <div class="mt-8">
                        <Link
                            :href="route('admin.empresa-talents.edit')"
                            class="inline-flex items-center rounded-xl bg-talents-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-talents-700"
                        >
                            Editar dados da Talents (Empresa)
                        </Link>
                    </div>
                </section>
            </template>

            <template v-if="tab === 'vendedores'">
                <section class="surface-card p-6">
                    <h3 class="text-lg font-semibold text-slate-900">Comissão de vendedores</h3>
                    <p class="mt-1 text-xs text-slate-500">
                        Percentual aplicado automaticamente em todas as propostas. Uso interno — não aparece no formulário
                        nem no PDF enviado ao cliente.
                    </p>
                    <div class="mt-4 max-w-xs">
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-500">
                            Comissão padrão (%)
                        </label>
                        <input
                            v-model.number="form.default_commission_percent"
                            type="number"
                            min="0"
                            max="100"
                            step="0.01"
                            class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        />
                    </div>
                </section>
            </template>

            <div v-if="tab !== 'contratos' && tab !== 'empresa' && tab !== 'produtos'" class="flex justify-end">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex items-center rounded-xl bg-talents-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-talents-700 disabled:opacity-60"
                >
                    Salvar parâmetros
                </button>
            </div>
        </form>

        <div v-if="tab === 'produtos'" class="mt-6">
            <CommercialProductsManager
                :products="commercialProducts"
                :pricing-type-labels="pricingTypeLabels"
            />
        </div>

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
