<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatBRL, useCommercialPricing } from '@/composables/useCommercialPricing';
import axios from 'axios';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    mode: { type: String, default: 'create' }, // 'create' | 'edit'
    proposal: { type: Object, default: null },
    sellers: { type: Array, default: () => [] },
    settings: { type: Object, required: true },
});

const settingsRef = ref({ ...props.settings });

const formInitial = props.proposal
    ? {
          client_name: props.proposal.client_name ?? '',
          client_cnpj: props.proposal.client_cnpj ?? '',
          client_email: props.proposal.client_email ?? '',
          client_phone: props.proposal.client_phone ?? '',
          indication: props.proposal.indication ?? '',
          employee_count: props.proposal.employee_count ?? 0,
          seller_id: props.proposal.seller_id ?? '',
          svc_pesquisas: !!props.proposal.svc_pesquisas,
          svc_profiler: !!props.proposal.svc_profiler,
          svc_devolutiva: props.proposal.svc_devolutiva ?? '',
          svc_nr1: !!props.proposal.svc_nr1,
          svc_nr1_implantacao_modo: props.proposal.svc_nr1_implantacao_modo ?? '',
          svc_contratacao: !!props.proposal.svc_contratacao,
          svc_contratacao_salario_cents: props.proposal.svc_contratacao_salario_cents ?? 0,
          svc_direcionamento: !!props.proposal.svc_direcionamento,
          svc_palestras: !!props.proposal.svc_palestras,
          commission_percent: props.proposal.commission_percent ?? 0,
          is_closed: !!props.proposal.is_closed,
          notes: props.proposal.notes ?? '',
      }
    : {
          client_name: '',
          client_cnpj: '',
          client_email: '',
          client_phone: '',
          indication: '',
          employee_count: 0,
          seller_id: '',
          svc_pesquisas: false,
          svc_profiler: false,
          svc_devolutiva: '',
          svc_nr1: false,
          svc_nr1_implantacao_modo: '',
          svc_contratacao: false,
          svc_contratacao_salario_cents: 0,
          svc_direcionamento: false,
          svc_palestras: false,
          commission_percent: 0,
          is_closed: false,
          notes: '',
      };

const form = useForm(formInitial);

// Helpers de input em reais (string) -> centavos para o salário base de Contratação
const salaryReais = ref(((Number(formInitial.svc_contratacao_salario_cents) || 0) / 100).toFixed(2).replace('.', ','));
watch(salaryReais, (val) => {
    const numeric = Number(String(val ?? '').replace(/\./g, '').replace(',', '.'));
    form.svc_contratacao_salario_cents = Number.isFinite(numeric) ? Math.max(0, Math.round(numeric * 100)) : 0;
});

const formRef = computed(() => form);
const { breakdownCents, totalFinalCents, commissionCents } = useCommercialPricing(formRef, settingsRef);

// Consulta CNPJ (Receita Federal) — reaproveita o endpoint já existente.
const cnpjLookupLoading = ref(false);
const cnpjLookupError = ref('');
const cnpjLookupSuccess = ref('');
const cnpjDigitCount = computed(() => (String(form.client_cnpj || '').match(/\d/g) || []).length);
const canLookupCnpj = computed(() => cnpjDigitCount.value === 14);

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
            params: { cnpj: form.client_cnpj },
        });
        form.client_cnpj = data.cnpj ?? form.client_cnpj;
        const fantasiaOuRazao = data.name || data.legal_name;
        if (fantasiaOuRazao) {
            form.client_name = fantasiaOuRazao;
        }
        if (data.contact_email) {
            form.client_email = data.contact_email;
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
    if (props.mode === 'edit') {
        form.put(route('admin.comercial.propostas.update', props.proposal.id), { preserveScroll: true });
    } else {
        form.post(route('admin.comercial.propostas.store'), { preserveScroll: true });
    }
};

const downloadPdf = () => {
    if (!props.proposal?.id) return;
    window.open(route('admin.comercial.propostas.pdf', props.proposal.id), '_blank');
};

const openContractPdf = (contractId) => {
    window.open(route('admin.comercial.contratos.pdf', contractId), '_blank');
};

const formatContractDate = (iso) => (iso ? new Date(iso).toLocaleString('pt-BR') : '—');

const isEdit = computed(() => props.mode === 'edit');
const titleText = computed(() => (isEdit.value ? `Proposta ${props.proposal?.code}` : 'Nova proposta'));

const services = computed(() => [
    { label: 'Pesquisas e Organograma', cents: breakdownCents.value.total_pesquisas_cents, on: form.svc_pesquisas },
    { label: 'Profiler', cents: breakdownCents.value.total_profiler_cents, on: form.svc_profiler },
    { label: 'Devolutiva', cents: breakdownCents.value.total_devolutiva_cents, on: !!form.svc_devolutiva },
    { label: 'NR-1 Mapeamento', cents: breakdownCents.value.total_nr1_cents, on: form.svc_nr1 },
    { label: 'NR-1 Implantação', cents: breakdownCents.value.total_nr1_implantacao_cents, on: !!form.svc_nr1_implantacao_modo && form.svc_nr1 },
    { label: 'Contratação', cents: breakdownCents.value.total_contratacao_cents, on: form.svc_contratacao },
    { label: 'Direcionamento Estratégico', cents: breakdownCents.value.total_direcionamento_cents, on: form.svc_direcionamento },
    { label: 'Palestras e Treinamentos', cents: breakdownCents.value.total_palestras_cents, on: form.svc_palestras },
]);
</script>

<template>
    <Head :title="`Comercial — ${titleText}`" />

    <AdminLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-sm text-slate-500">
                        <Link :href="route('admin.comercial.propostas.index')" class="hover:underline">Propostas</Link>
                        / {{ isEdit ? 'Editar' : 'Nova' }}
                    </p>
                    <h2 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">{{ titleText }}</h2>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-if="isEdit"
                        type="button"
                        class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                        @click="downloadPdf"
                    >
                        Gerar PDF
                    </button>
                </div>
            </div>
        </template>

        <form class="grid gap-8 lg:grid-cols-3" @submit.prevent="submit">
            <div class="space-y-6 lg:col-span-2">
                <!-- Cliente -->
                <section class="surface-card p-6">
                    <h3 class="text-lg font-semibold text-slate-900">Dados do cliente</h3>
                    <p class="mt-1 text-xs text-slate-500">Lead / prospect — não vinculado a empresas cadastradas.</p>

                    <div class="mt-4 space-y-4">
                        <div>
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">CNPJ</label>
                            <p class="mt-0.5 text-xs text-slate-500">
                                Informe o CNPJ e busque na Receita Federal para preencher nome e e-mail automaticamente.
                            </p>
                            <div class="mt-2 flex flex-col gap-2 sm:flex-row sm:items-stretch">
                                <input
                                    v-model="form.client_cnpj"
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

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Nome / Razão social *</label>
                                <input
                                    v-model="form.client_name"
                                    type="text"
                                    required
                                    class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                />
                                <p v-if="form.errors.client_name" class="mt-1 text-xs text-rose-600">{{ form.errors.client_name }}</p>
                            </div>
                            <div>
                                <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Telefone</label>
                                <input
                                    v-model="form.client_phone"
                                    type="text"
                                    class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                />
                            </div>
                            <div>
                                <label class="text-xs font-medium uppercase tracking-wide text-slate-500">E-mail</label>
                                <input
                                    v-model="form.client_email"
                                    type="email"
                                    class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                />
                            </div>
                            <div>
                                <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Indicação</label>
                                <input
                                    v-model="form.indication"
                                    type="text"
                                    class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                />
                            </div>
                            <div>
                                <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Nº de funcionários *</label>
                                <input
                                    v-model.number="form.employee_count"
                                    type="number"
                                    min="0"
                                    required
                                    class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                />
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Serviços -->
                <section class="surface-card p-6">
                    <h3 class="text-lg font-semibold text-slate-900">Serviços contratados</h3>
                    <p class="mt-1 text-xs text-slate-500">Marque os serviços e o cálculo aparece no resumo ao lado.</p>

                    <div class="mt-4 space-y-4">
                        <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-3 hover:bg-slate-50">
                            <input v-model="form.svc_pesquisas" type="checkbox" class="mt-1 h-4 w-4 rounded border-slate-300 text-talents-600 focus:ring-talents-500" />
                            <div class="flex-1">
                                <div class="font-medium text-slate-900">Pesquisas e Organograma</div>
                                <p class="text-xs text-slate-500">Por funcionário, conforme faixa.</p>
                            </div>
                            <div class="text-right text-sm tabular-nums text-slate-700">
                                {{ formatBRL(breakdownCents.total_pesquisas_cents) }}
                            </div>
                        </label>

                        <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-3 hover:bg-slate-50">
                            <input v-model="form.svc_profiler" type="checkbox" class="mt-1 h-4 w-4 rounded border-slate-300 text-talents-600 focus:ring-talents-500" />
                            <div class="flex-1">
                                <div class="font-medium text-slate-900">Profiler — Diagnóstico Comportamental</div>
                                <p class="text-xs text-slate-500">Por funcionário, conforme faixa.</p>
                            </div>
                            <div class="text-right text-sm tabular-nums text-slate-700">
                                {{ formatBRL(breakdownCents.total_profiler_cents) }}
                            </div>
                        </label>

                        <div class="rounded-xl border border-slate-200 p-3">
                            <div class="flex items-center justify-between">
                                <div class="font-medium text-slate-900">Devolutiva e Diagnóstico</div>
                                <div class="text-right text-sm tabular-nums text-slate-700">
                                    {{ formatBRL(breakdownCents.total_devolutiva_cents) }}
                                </div>
                            </div>
                            <select
                                v-model="form.svc_devolutiva"
                                class="mt-2 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            >
                                <option value="">— Não contratado —</option>
                                <option value="individual">Individual ({{ formatBRL(settings.devolutiva_individual_cents) }})</option>
                                <option value="grupo">Grupo ({{ formatBRL(settings.devolutiva_grupo_cents) }})</option>
                            </select>
                        </div>

                        <div class="rounded-xl border border-slate-200 p-3">
                            <label class="flex items-start gap-3">
                                <input v-model="form.svc_nr1" type="checkbox" class="mt-1 h-4 w-4 rounded border-slate-300 text-talents-600 focus:ring-talents-500" />
                                <div class="flex-1">
                                    <div class="font-medium text-slate-900">NR-1 — Mapeamento (12 parcelas)</div>
                                    <p class="text-xs text-slate-500">Por funcionário, conforme faixa.</p>
                                </div>
                                <div class="text-right text-sm tabular-nums text-slate-700">
                                    {{ formatBRL(breakdownCents.total_nr1_cents) }}
                                </div>
                            </label>
                            <div v-if="form.svc_nr1" class="mt-3 grid gap-2 sm:grid-cols-[1fr_auto] sm:items-center">
                                <select
                                    v-model="form.svc_nr1_implantacao_modo"
                                    class="w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                >
                                    <option value="">— Sem implantação —</option>
                                    <option value="online">Implantação On-line ({{ formatBRL(settings.nr1_implantacao_online_cents) }} / func.)</option>
                                    <option value="presencial">Implantação Presencial ({{ formatBRL(settings.nr1_implantacao_presencial_cents) }} fixo)</option>
                                </select>
                                <span class="text-right text-sm tabular-nums text-slate-700">
                                    Implantação: {{ formatBRL(breakdownCents.total_nr1_implantacao_cents) }}
                                </span>
                            </div>
                        </div>

                        <div class="rounded-xl border border-slate-200 p-3">
                            <label class="flex items-start gap-3">
                                <input v-model="form.svc_contratacao" type="checkbox" class="mt-1 h-4 w-4 rounded border-slate-300 text-talents-600 focus:ring-talents-500" />
                                <div class="flex-1">
                                    <div class="font-medium text-slate-900">Contratação / Recrutamento</div>
                                    <p class="text-xs text-slate-500">Salário base × nº de funcionários.</p>
                                </div>
                                <div class="text-right text-sm tabular-nums text-slate-700">
                                    {{ formatBRL(breakdownCents.total_contratacao_cents) }}
                                </div>
                            </label>
                            <div v-if="form.svc_contratacao" class="mt-3">
                                <label class="text-xs font-medium uppercase tracking-wide text-slate-500">
                                    Salário base por funcionário (R$)
                                </label>
                                <input
                                    v-model="salaryReais"
                                    type="text"
                                    placeholder="0,00"
                                    class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                />
                            </div>
                        </div>

                        <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-3 hover:bg-slate-50">
                            <input v-model="form.svc_direcionamento" type="checkbox" class="mt-1 h-4 w-4 rounded border-slate-300 text-talents-600 focus:ring-talents-500" />
                            <div class="flex-1">
                                <div class="font-medium text-slate-900">Direcionamento Estratégico</div>
                                <p class="text-xs text-slate-500">Por funcionário, conforme faixa.</p>
                            </div>
                            <div class="text-right text-sm tabular-nums text-slate-700">
                                {{ formatBRL(breakdownCents.total_direcionamento_cents) }}
                            </div>
                        </label>

                        <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-3 hover:bg-slate-50">
                            <input v-model="form.svc_palestras" type="checkbox" class="mt-1 h-4 w-4 rounded border-slate-300 text-talents-600 focus:ring-talents-500" />
                            <div class="flex-1">
                                <div class="font-medium text-slate-900">Palestras e Treinamentos</div>
                                <p class="text-xs text-slate-500">
                                    Base {{ formatBRL(settings.palestras_base_cents) }};
                                    multiplicado por {{ settings.palestras_multiplier }}× acima de {{ settings.palestras_threshold_funcionarios }} func.
                                </p>
                            </div>
                            <div class="text-right text-sm tabular-nums text-slate-700">
                                {{ formatBRL(breakdownCents.total_palestras_cents) }}
                            </div>
                        </label>
                    </div>
                </section>

                <!-- Comercial -->
                <section class="surface-card p-6">
                    <h3 class="text-lg font-semibold text-slate-900">Informações comerciais</h3>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Vendedor</label>
                            <select
                                v-model="form.seller_id"
                                class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            >
                                <option value="">— Sem vendedor atribuído —</option>
                                <option v-for="s in sellers" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                            <p v-if="!sellers.length" class="mt-1 text-xs text-amber-700">
                                Nenhum vendedor marcado como Comercial. Marque usuários como "Comercial" no cadastro de usuários.
                            </p>
                        </div>
                        <div>
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Comissão (%)</label>
                            <input
                                v-model.number="form.commission_percent"
                                type="number"
                                min="0"
                                max="100"
                                step="0.01"
                                class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            />
                            <p class="mt-1 text-xs text-slate-500">
                                Comissão calculada: <strong>{{ formatBRL(commissionCents) }}</strong>
                            </p>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Observações</label>
                            <textarea
                                v-model="form.notes"
                                rows="3"
                                class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            />
                        </div>
                        <label class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm sm:col-span-2">
                            <input v-model="form.is_closed" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                            <span class="font-medium text-slate-900">Marcar como fechada</span>
                            <span class="text-xs text-slate-500">— a data de fechamento será registrada agora.</span>
                        </label>
                    </div>
                </section>

                <section v-if="isEdit" class="surface-card p-6">
                    <h3 class="text-lg font-semibold text-slate-900">Contratos gerados</h3>
                    <p class="mt-1 text-xs text-slate-500">Histórico de contratos PDF gerados a partir desta proposta.</p>
                    <ul
                        v-if="proposal?.contracts?.length"
                        class="mt-4 divide-y divide-slate-100 rounded-xl border border-slate-200"
                    >
                        <li
                            v-for="c in proposal.contracts"
                            :key="c.id"
                            class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm"
                        >
                            <div>
                                <div class="font-mono text-xs font-semibold text-slate-800">{{ c.code }}</div>
                                <div class="text-xs text-slate-500">{{ c.template_name_snapshot }} · {{ formatContractDate(c.generated_at) }}</div>
                            </div>
                            <button
                                type="button"
                                class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-talents-700 hover:bg-talents-50"
                                @click="openContractPdf(c.id)"
                            >
                                PDF
                            </button>
                        </li>
                    </ul>
                    <p v-else class="mt-4 text-sm text-slate-500">Nenhum contrato gerado ainda. Use a listagem de propostas para gerar.</p>
                </section>
            </div>

            <!-- Resumo lateral sticky -->
            <aside class="lg:sticky lg:top-24 lg:self-start">
                <div class="surface-card p-6">
                    <h3 class="text-lg font-semibold text-slate-900">Resumo</h3>
                    <p class="mt-1 text-xs text-slate-500">Cálculo em tempo real conforme você preenche.</p>

                    <ul class="mt-4 space-y-2 text-sm">
                        <li
                            v-for="svc in services"
                            :key="svc.label"
                            class="flex items-center justify-between gap-2"
                            :class="!svc.on ? 'text-slate-400' : 'text-slate-700'"
                        >
                            <span class="truncate">{{ svc.label }}</span>
                            <span class="tabular-nums">{{ formatBRL(svc.cents) }}</span>
                        </li>
                    </ul>

                    <div class="mt-4 border-t border-slate-200 pt-4">
                        <div class="flex items-center justify-between text-sm text-slate-600">
                            <span>Comissão ({{ form.commission_percent || 0 }}%)</span>
                            <span class="tabular-nums">{{ formatBRL(commissionCents) }}</span>
                        </div>
                        <div class="mt-2 flex items-center justify-between text-base font-semibold text-talents-700">
                            <span>Honorário Final</span>
                            <span class="tabular-nums">{{ formatBRL(totalFinalCents) }}</span>
                        </div>
                    </div>

                    <div class="mt-6 space-y-2">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex w-full items-center justify-center rounded-xl bg-talents-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-talents-700 disabled:opacity-60"
                        >
                            {{ isEdit ? 'Salvar alterações' : 'Salvar proposta' }}
                        </button>
                        <button
                            v-if="isEdit"
                            type="button"
                            class="inline-flex w-full items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                            @click="downloadPdf"
                        >
                            Gerar PDF da proposta
                        </button>
                    </div>
                </div>
            </aside>
        </form>
    </AdminLayout>
</template>
