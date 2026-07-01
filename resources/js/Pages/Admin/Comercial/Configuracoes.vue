<script setup>
import FormPageHeader from '@/Components/FormPageHeader.vue';
import CommercialModuleNav from '@/Components/Comercial/CommercialModuleNav.vue';
import CommercialPricingShortcuts from '@/Components/Comercial/CommercialPricingShortcuts.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import CommercialProductsManager from '@/Pages/Admin/Comercial/CommercialProductsManager.vue';
import ContractTemplatesManager from '@/Pages/Admin/Comercial/ContractTemplatesManager.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
    settings: { type: Object, required: true },
    users: { type: Array, default: () => [] },
    contractTemplates: { type: Array, default: () => [] },
    commercialProducts: { type: Array, default: () => [] },
    pricingTypeLabels: { type: Object, default: () => ({}) },
});

const tab = ref('produtos');

const validTabs = ['produtos', 'pdf', 'vendedores', 'empresa', 'contratos'];

const setTab = (id) => {
    if (!validTabs.includes(id)) return;
    tab.value = id;
    const url = new URL(window.location.href);
    url.searchParams.set('tab', id);
    window.history.replaceState({}, '', url);
};

onMounted(() => {
    const q = new URLSearchParams(window.location.search).get('tab');
    const legacyTabs = ['faixas', 'fixos'];
    if (q && legacyTabs.includes(q)) {
        setTab('produtos');
    } else if (q && validTabs.includes(q)) {
        tab.value = q;
    }
});

const buildPdfDescriptions = () => {
    const existing = { ...(props.settings.pdf_descricoes_servicos ?? {}) };
    props.commercialProducts.forEach((product) => {
        if (!(product.slug in existing)) {
            existing[product.slug] = product.description ?? '';
        }
    });
    return existing;
};

const form = useForm({
    default_commission_percent: props.settings.default_commission_percent ?? 0,
    pdf_validade_dias: props.settings.pdf_validade_dias,
    pdf_aceite_texto: props.settings.pdf_aceite_texto ?? '',
    pdf_descricoes_servicos: buildPdfDescriptions(),
    pdf_condicoes_pagamento: props.settings.pdf_condicoes_pagamento ?? '',
    pdf_texto_encerramento: props.settings.pdf_texto_encerramento ?? '',
    zapsign_api_token: '',
    zapsign_api_base_url: props.settings.zapsign_api_base_url ?? 'https://api.zapsign.com.br/api/v1',
    zapsign_send_automatic_email: props.settings.zapsign_send_automatic_email !== false,
});

const submit = () => {
    form.put(route('admin.comercial.settings.update'), { preserveScroll: true });
};

const tabs = [
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

const pdfProductLabels = computed(() =>
    props.commercialProducts
        .filter((p) => p.is_active !== false)
        .map((p) => ({ key: p.slug, label: p.name })),
);
</script>

<template>
    <Head title="Comercial — Configurações" />

    <AdminLayout>
        <template #header>
            <FormPageHeader
                :back-href="route('admin.comercial.dashboard')"
                back-label="Comercial"
                title="Valores e contratos"
                subtitle="Tabelas de preço, PDF da proposta e modelos usados na geração de contratos."
            />
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
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Texto de aceite</label>
                            <textarea
                                v-model="form.pdf_aceite_texto"
                                rows="3"
                                class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            />
                        </div>
                        <div>
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Condições de pagamento (PDF)</label>
                            <textarea
                                v-model="form.pdf_condicoes_pagamento"
                                rows="3"
                                placeholder="Ex.: • Parcelamento em até 5x no cartão de crédito;"
                                class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            />
                        </div>
                        <div>
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Texto de encerramento (PDF)</label>
                            <textarea
                                v-model="form.pdf_texto_encerramento"
                                rows="4"
                                placeholder="Parágrafo final da proposta comercial..."
                                class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            />
                        </div>
                    </div>
                </section>

                <section class="surface-card p-6">
                    <h3 class="text-lg font-semibold text-slate-900">Descrições padrão dos produtos (PDF)</h3>
                    <p class="mt-1 text-xs text-slate-500">
                        Textos usados automaticamente em cada produto da proposta. Podem ser personalizados por proposta.
                    </p>
                    <div v-if="pdfProductLabels.length" class="mt-4 space-y-4">
                        <div v-for="svc in pdfProductLabels" :key="svc.key">
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ svc.label }}</label>
                            <textarea
                                v-model="form.pdf_descricoes_servicos[svc.key]"
                                rows="6"
                                class="mt-1 w-full rounded-xl border-slate-300 font-mono text-xs shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                placeholder="O que contempla, bullets (• ou -) e Objetivo..."
                            />
                        </div>
                    </div>
                    <p v-else class="mt-4 text-sm text-slate-500">
                        Cadastre produtos na aba Produtos para configurar as descrições do PDF.
                    </p>
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
