<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
    products: { type: Array, default: () => [] },
    pricingTypeLabels: { type: Object, default: () => ({}) },
});

const modalOpen = ref(false);
const editing = ref(null);

const emptyConfig = (type) => {
    switch (type) {
        case 'fixed':
            return { amount_cents: 0 };
        case 'per_employee':
            return { cents_per_employee: 0 };
        case 'tiered_per_employee':
            return {
                tier1_max: 5,
                tier1_cents: 0,
                tier2_max: 10,
                tier2_cents: 0,
                tier3_max: 20,
                tier3_cents: 0,
                tier4_cents: 0,
            };
        case 'fixed_modality':
            return { modalities: [{ key: 'padrao', label: 'Padrão', cents: 0 }] };
        case 'threshold_multiplier':
            return { base_cents: 0, threshold_employees: 30, multiplier: 2 };
        default:
            return {};
    }
};

const form = useForm({
    name: '',
    description: '',
    pricing_type: 'fixed',
    pricing_config: emptyConfig('fixed'),
    sort_order: 0,
    is_active: true,
});

const reaisFields = reactive({});

const syncReaisFromCents = (key, cents) => {
    reaisFields[key] = ((Number(cents) || 0) / 100).toFixed(2).replace('.', ',');
};

const centsFromReais = (key) => {
    const numeric = Number(String(reaisFields[key] ?? '').replace(/\./g, '').replace(',', '.'));
    return Number.isFinite(numeric) ? Math.max(0, Math.round(numeric * 100)) : 0;
};

const openCreate = () => {
    editing.value = null;
    form.reset();
    form.pricing_type = 'fixed';
    form.pricing_config = emptyConfig('fixed');
    form.sort_order = props.products.length;
    form.is_active = true;
    syncReaisFromCents('amount', 0);
    modalOpen.value = true;
};

const openEdit = (product) => {
    editing.value = product;
    form.name = product.name;
    form.description = product.description ?? '';
    form.pricing_type = product.pricing_type;
    form.pricing_config = JSON.parse(JSON.stringify(product.pricing_config ?? emptyConfig(product.pricing_type)));
    form.sort_order = product.sort_order ?? 0;
    form.is_active = product.is_active !== false;
    hydrateReais(product);
    modalOpen.value = true;
};

const hydrateReais = (product) => {
    const cfg = product.pricing_config || {};
    if (product.pricing_type === 'fixed') syncReaisFromCents('amount', cfg.amount_cents);
    if (product.pricing_type === 'per_employee') syncReaisFromCents('per_emp', cfg.cents_per_employee);
    if (product.pricing_type === 'tiered_per_employee') {
        ['tier1', 'tier2', 'tier3', 'tier4'].forEach((t) => syncReaisFromCents(t, cfg[`${t}_cents`]));
    }
    if (product.pricing_type === 'threshold_multiplier') syncReaisFromCents('base', cfg.base_cents);
    if (product.pricing_type === 'fixed_modality') {
        (cfg.modalities || []).forEach((m, i) => syncReaisFromCents(`mod_${i}`, m.cents));
    }
};

const onTypeChange = () => {
    form.pricing_config = emptyConfig(form.pricing_type);
    hydrateReais({ pricing_type: form.pricing_type, pricing_config: form.pricing_config });
};

const applyReaisToConfig = () => {
    const cfg = { ...form.pricing_config };
    switch (form.pricing_type) {
        case 'fixed':
            cfg.amount_cents = centsFromReais('amount');
            break;
        case 'per_employee':
            cfg.cents_per_employee = centsFromReais('per_emp');
            break;
        case 'tiered_per_employee':
            cfg.tier1_cents = centsFromReais('tier1');
            cfg.tier2_cents = centsFromReais('tier2');
            cfg.tier3_cents = centsFromReais('tier3');
            cfg.tier4_cents = centsFromReais('tier4');
            break;
        case 'fixed_modality':
            cfg.modalities = (cfg.modalities || []).map((m, i) => ({
                ...m,
                cents: centsFromReais(`mod_${i}`),
            }));
            break;
        case 'threshold_multiplier':
            cfg.base_cents = centsFromReais('base');
            break;
        default:
            break;
    }
    form.pricing_config = cfg;
};

const addModality = () => {
    const modalities = [...(form.pricing_config.modalities || [])];
    modalities.push({ key: `opcao_${modalities.length + 1}`, label: 'Nova opção', cents: 0 });
    form.pricing_config = { ...form.pricing_config, modalities };
    syncReaisFromCents(`mod_${modalities.length - 1}`, 0);
};

const removeModality = (index) => {
    const modalities = [...(form.pricing_config.modalities || [])];
    modalities.splice(index, 1);
    form.pricing_config = { ...form.pricing_config, modalities };
};

const submit = () => {
    applyReaisToConfig();
    const opts = { preserveScroll: true, onSuccess: () => { modalOpen.value = false; } };
    if (editing.value) {
        form.put(route('admin.comercial.products.update', editing.value.id), opts);
    } else {
        form.post(route('admin.comercial.products.store'), opts);
    }
};

const destroy = (product) => {
    if (!confirm(`Remover o produto "${product.name}"?`)) return;
    router.delete(route('admin.comercial.products.destroy', product.id), { preserveScroll: true });
};

const sortedProducts = computed(() =>
    [...props.products].sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0) || a.name.localeCompare(b.name)),
);

const typeLabel = (type) => props.pricingTypeLabels[type] ?? type;
</script>

<template>
    <section class="surface-card p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-lg font-semibold text-slate-900">Produtos do catálogo</h3>
                <p class="mt-1 text-xs text-slate-500">
                    Cadastre produtos extras que aparecem na proposta, no PDF e nos contratos (placeholders
                    <code class="rounded bg-slate-100 px-1">svc_*_slug</code>).
                </p>
            </div>
            <button
                type="button"
                class="inline-flex items-center rounded-xl bg-talents-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-talents-700"
                @click="openCreate"
            >
                Novo produto
            </button>
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium">Nome</th>
                        <th class="px-4 py-3 text-left font-medium">Slug / contrato</th>
                        <th class="px-4 py-3 text-left font-medium">Precificação</th>
                        <th class="px-4 py-3 text-left font-medium">Status</th>
                        <th class="px-4 py-3 text-right font-medium">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <tr v-for="p in sortedProducts" :key="p.id">
                        <td class="px-4 py-3 font-medium text-slate-900">{{ p.name }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ p.slug }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ typeLabel(p.pricing_type) }}</td>
                        <td class="px-4 py-3">
                            <span
                                class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="p.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600'"
                            >
                                {{ p.is_active ? 'Ativo' : 'Inativo' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button
                                type="button"
                                class="mr-2 text-xs font-semibold text-talents-700 hover:underline"
                                @click="openEdit(p)"
                            >
                                Editar
                            </button>
                            <button
                                type="button"
                                class="text-xs font-semibold text-rose-600 hover:underline"
                                @click="destroy(p)"
                            >
                                Excluir
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!sortedProducts.length">
                        <td colspan="5" class="px-4 py-10 text-center text-slate-500">
                            Nenhum produto cadastrado. Os serviços padrão (Profiler, NR-1, etc.) continuam nas faixas acima.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="modalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            @click.self="modalOpen = false"
        >
            <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
                <h4 class="text-lg font-semibold text-slate-900">
                    {{ editing ? 'Editar produto' : 'Novo produto' }}
                </h4>

                <form class="mt-4 space-y-4" @submit.prevent="submit">
                    <div>
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Nome *</label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        />
                    </div>
                    <div>
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Descrição</label>
                        <textarea
                            v-model="form.description"
                            rows="2"
                            class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Tipo de preço *</label>
                            <select
                                v-model="form.pricing_type"
                                class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                @change="onTypeChange"
                            >
                                <option v-for="(label, key) in pricingTypeLabels" :key="key" :value="key">
                                    {{ label }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Ordem</label>
                            <input
                                v-model.number="form.sort_order"
                                type="number"
                                min="0"
                                class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            />
                        </div>
                    </div>

                    <div v-if="form.pricing_type === 'fixed'">
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Valor fixo (R$)</label>
                        <input v-model="reaisFields.amount" type="text" class="mt-1 w-full rounded-xl border-slate-300 shadow-sm" />
                    </div>

                    <div v-if="form.pricing_type === 'per_employee'">
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Valor por funcionário (R$)</label>
                        <input v-model="reaisFields.per_emp" type="text" class="mt-1 w-full rounded-xl border-slate-300 shadow-sm" />
                    </div>

                    <div v-if="form.pricing_type === 'tiered_per_employee'" class="space-y-3">
                        <p class="text-xs text-slate-500">Valor por funcionário em cada faixa (R$).</p>
                        <div v-for="tier in ['tier1', 'tier2', 'tier3', 'tier4']" :key="tier" class="grid grid-cols-2 gap-2">
                            <input
                                v-model.number="form.pricing_config[`${tier}_max`]"
                                type="number"
                                min="1"
                                :placeholder="tier === 'tier4' ? 'Acima' : 'Máx. func.'"
                                :disabled="tier === 'tier4'"
                                class="rounded-xl border-slate-300 text-sm shadow-sm"
                            />
                            <input
                                v-model="reaisFields[tier]"
                                type="text"
                                placeholder="R$"
                                class="rounded-xl border-slate-300 text-sm shadow-sm"
                            />
                        </div>
                    </div>

                    <div v-if="form.pricing_type === 'fixed_modality'" class="space-y-2">
                        <div
                            v-for="(mod, idx) in form.pricing_config.modalities || []"
                            :key="idx"
                            class="rounded-xl border border-slate-200 p-3"
                        >
                            <input v-model="mod.label" type="text" placeholder="Nome da opção" class="mb-2 w-full rounded-lg border-slate-300 text-sm" />
                            <input v-model="mod.key" type="text" placeholder="chave (slug)" class="mb-2 w-full rounded-lg border-slate-300 font-mono text-xs" />
                            <input v-model="reaisFields[`mod_${idx}`]" type="text" placeholder="Valor R$" class="w-full rounded-lg border-slate-300 text-sm" />
                            <button type="button" class="mt-2 text-xs text-rose-600" @click="removeModality(idx)">Remover</button>
                        </div>
                        <button type="button" class="text-xs font-semibold text-talents-700" @click="addModality">+ Modalidade</button>
                    </div>

                    <div v-if="form.pricing_type === 'salary_times_employees'" class="rounded-xl bg-slate-50 p-3 text-xs text-slate-600">
                        O salário base será informado em cada proposta (como em Contratação / Recrutamento).
                    </div>

                    <div v-if="form.pricing_type === 'threshold_multiplier'" class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="text-xs font-medium uppercase text-slate-500">Valor base (R$)</label>
                            <input v-model="reaisFields.base" type="text" class="mt-1 w-full rounded-xl border-slate-300 shadow-sm" />
                        </div>
                        <div>
                            <label class="text-xs font-medium uppercase text-slate-500">Acima de (func.)</label>
                            <input
                                v-model.number="form.pricing_config.threshold_employees"
                                type="number"
                                min="0"
                                class="mt-1 w-full rounded-xl border-slate-300 shadow-sm"
                            />
                        </div>
                        <div>
                            <label class="text-xs font-medium uppercase text-slate-500">Multiplicador</label>
                            <input
                                v-model.number="form.pricing_config.multiplier"
                                type="number"
                                min="1"
                                class="mt-1 w-full rounded-xl border-slate-300 shadow-sm"
                            />
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300 text-talents-600" />
                        Produto ativo (visível nas propostas)
                    </label>

                    <div class="flex justify-end gap-2 pt-2">
                        <button
                            type="button"
                            class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700"
                            @click="modalOpen = false"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-xl bg-talents-600 px-4 py-2 text-sm font-semibold text-white hover:bg-talents-700 disabled:opacity-60"
                        >
                            Salvar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>
</template>
