<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import {
    buildJustificationTypeMapFromPayload,
    justificationTypeLabel,
} from '@/utils/rhidJustificationsAnalytics';
import {
    extractListItems,
    formatRhidDotNetDateRange,
    fromRhidYmd,
    monthRangeHtmlDates,
    toRhidYmd,
    toRhidYmdHm,
} from '@/utils/rhidDate';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    companyId: { type: [Number, String], required: true },
});

const range = monthRangeHtmlDates();
const iniDate = ref(range.first);
const fimDate = ref(range.last);
const search = ref('');

const loading = ref(false);
const saving = ref(false);
const error = ref(null);
const success = ref(null);

const typeMap = ref({});
const typeOptions = ref([]);
const peopleOptions = ref([]);
const rows = ref([]);

const showForm = ref(false);
const editingId = ref(null);
const form = ref(emptyForm());

const filteredRows = computed(() => {
    const term = search.value.trim().toLowerCase();
    if (!term) {
        return rows.value;
    }
    return rows.value.filter((row) =>
        [row.personName, row.typeLabel, row.justificativa, row.periodLabel]
            .filter(Boolean)
            .some((v) => String(v).toLowerCase().includes(term)),
    );
});

function emptyForm() {
    return {
        idPerson: '',
        idJustificationType: '',
        justificativa: '',
        startDate: range.first,
        startTime: '08:00',
        endDate: range.first,
        endTime: '18:00',
    };
}

const rowId = (row) => row?.id ?? row?.Id ?? row?.idJustification ?? null;

const personNameFromRow = (row) => {
    for (const key of ['personName', 'strPersonName', 'name', 'nome']) {
        if (row?.[key] != null && String(row[key]).trim() !== '') {
            return String(row[key]).trim();
        }
    }
    const nest = row?.person ?? row?.Person;
    if (nest && typeof nest === 'object') {
        for (const key of ['name', 'nome', 'personName']) {
            if (nest[key] != null && String(nest[key]).trim() !== '') {
                return String(nest[key]).trim();
            }
        }
    }
    const id = row?.idPerson ?? row?.IdPerson;
    return id != null ? `#${id}` : '—';
};

const normalizeRows = (payload) => {
    const list = Array.isArray(payload?.data) ? payload.data : extractListItems(payload);
    return list.map((row) => {
        const id = rowId(row);
        const typeId = row?.idJustificationType ?? row?.IdJustificationType;
        return {
            raw: row,
            id,
            idPerson: row?.idPerson ?? row?.IdPerson ?? null,
            idJustificationType: typeId,
            personName: personNameFromRow(row),
            typeLabel: justificationTypeLabel(typeId, typeMap.value),
            justificativa: String(row?.justificativa ?? row?.description ?? row?.descricao ?? ''),
            periodLabel: formatRhidDotNetDateRange(row?.start ?? row?.inicio ?? row?.ini, row?.end ?? row?.fim),
            startRaw: row?.start ?? row?.inicio ?? row?.ini ?? null,
            endRaw: row?.end ?? row?.fim ?? null,
        };
    });
};

const loadMeta = async () => {
    const [typesRes, peopleRes] = await Promise.all([
        axios.get(route('admin.ponto.companies.justification-types', props.companyId), { timeout: 60000 }),
        axios.get(route('admin.ponto.companies.people', props.companyId), {
            params: { page: 0, maxSize: 500, status: 1 },
            timeout: 60000,
        }),
    ]);
    typeMap.value = buildJustificationTypeMapFromPayload(typesRes.data);
    typeOptions.value = Object.entries(typeMap.value)
        .map(([id, t]) => ({
            value: Number(id),
            label: justificationTypeLabel(id, typeMap.value),
        }))
        .sort((a, b) => a.label.localeCompare(b.label, 'pt-BR'));

    peopleOptions.value = extractListItems(peopleRes.data)
        .map((p) => ({
            value: Number(p.id ?? p.Id ?? p.idPerson),
            label: String(p.name ?? p.nome ?? p.strName ?? `#${p.id}`),
        }))
        .filter((p) => Number.isFinite(p.value) && p.value > 0)
        .sort((a, b) => a.label.localeCompare(b.label, 'pt-BR'));
};

const loadList = async () => {
    const ini = toRhidYmd(iniDate.value);
    const fim = toRhidYmd(fimDate.value);
    if (!/^\d{8}$/.test(ini) || !/^\d{8}$/.test(fim)) {
        error.value = 'Informe um período válido.';
        return;
    }

    loading.value = true;
    error.value = null;
    success.value = null;
    try {
        await loadMeta();
        const { data } = await axios.post(
            route('admin.ponto.companies.justifications.list', props.companyId),
            { ini, fim, page: 0, maxSize: 200 },
            { timeout: 90000 },
        );
        rows.value = normalizeRows(data);
    } catch (e) {
        rows.value = [];
        error.value = e?.response?.data?.message || 'Falha ao listar justificativas na RHID.';
    } finally {
        loading.value = false;
    }
};

const openCreate = () => {
    editingId.value = null;
    form.value = emptyForm();
    showForm.value = true;
    success.value = null;
    error.value = null;
};

const openEdit = (row) => {
    editingId.value = row.id;
    const startDigits = String(row.startRaw ?? '').replace(/\D/g, '');
    const endDigits = String(row.endRaw ?? '').replace(/\D/g, '');
    form.value = {
        idPerson: row.idPerson != null ? String(row.idPerson) : '',
        idJustificationType: row.idJustificationType != null ? String(row.idJustificationType) : '',
        justificativa: row.justificativa,
        startDate: fromRhidYmd(startDigits.slice(0, 8)) || iniDate.value,
        startTime: startDigits.length >= 12 ? `${startDigits.slice(8, 10)}:${startDigits.slice(10, 12)}` : '08:00',
        endDate: fromRhidYmd(endDigits.slice(0, 8)) || fimDate.value,
        endTime: endDigits.length >= 12 ? `${endDigits.slice(8, 10)}:${endDigits.slice(10, 12)}` : '18:00',
    };
    showForm.value = true;
    success.value = null;
    error.value = null;
};

const cancelForm = () => {
    showForm.value = false;
    editingId.value = null;
    form.value = emptyForm();
};

const buildPayload = () => ({
    idPerson: Number(form.value.idPerson),
    idJustificationType: Number(form.value.idJustificationType),
    justificativa: String(form.value.justificativa || '').trim(),
    inicio: toRhidYmdHm(form.value.startDate, form.value.startTime),
    fim: toRhidYmdHm(form.value.endDate, form.value.endTime),
});

const submitForm = async () => {
    const payload = buildPayload();
    if (!payload.idPerson || !payload.idJustificationType || !payload.justificativa || !payload.inicio || !payload.fim) {
        error.value = 'Preencha colaborador, tipo, descrição e período.';
        return;
    }

    saving.value = true;
    error.value = null;
    success.value = null;
    try {
        if (editingId.value) {
            await axios.put(
                route('admin.ponto.companies.justifications.update', [props.companyId, editingId.value]),
                payload,
                { timeout: 60000 },
            );
            success.value = 'Justificativa atualizada na RHID.';
        } else {
            await axios.post(route('admin.ponto.companies.justifications.store', props.companyId), payload, {
                timeout: 60000,
            });
            success.value = 'Justificativa criada na RHID.';
        }
        showForm.value = false;
        editingId.value = null;
        form.value = emptyForm();
        await loadList();
    } catch (e) {
        error.value = e?.response?.data?.message || 'Falha ao gravar justificativa na RHID.';
    } finally {
        saving.value = false;
    }
};

const destroyRow = async (row) => {
    if (!row?.id) {
        error.value = 'Esta justificativa não tem ID para remoção.';
        return;
    }
    if (!confirm(`Remover a justificativa de ${row.personName}? Esta ação aplica-se na RHID.`)) {
        return;
    }
    saving.value = true;
    error.value = null;
    success.value = null;
    try {
        await axios.delete(route('admin.ponto.companies.justifications.destroy', [props.companyId, row.id]), {
            timeout: 60000,
        });
        success.value = 'Justificativa removida na RHID.';
        await loadList();
    } catch (e) {
        error.value = e?.response?.data?.message || 'Falha ao remover justificativa na RHID.';
    } finally {
        saving.value = false;
    }
};

watch(
    () => props.companyId,
    async () => {
        cancelForm();
        rows.value = [];
        await loadList();
    },
    { immediate: true },
);
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div class="flex flex-wrap gap-3">
                <label class="text-sm text-slate-600">
                    De
                    <input
                        v-model="iniDate"
                        type="date"
                        class="mt-1 block rounded-lg border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    >
                </label>
                <label class="text-sm text-slate-600">
                    Até
                    <input
                        v-model="fimDate"
                        type="date"
                        class="mt-1 block rounded-lg border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    >
                </label>
                <input
                    v-model="search"
                    type="search"
                    placeholder="Filtrar na lista..."
                    class="mt-6 w-full max-w-xs rounded-lg border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500 sm:mt-0"
                >
            </div>
            <div class="flex flex-wrap gap-2">
                <SecondaryButton type="button" :disabled="loading" @click="loadList">
                    {{ loading ? 'Carregando…' : 'Atualizar lista' }}
                </SecondaryButton>
                <PrimaryButton type="button" @click="openCreate">Nova justificativa</PrimaryButton>
            </div>
        </div>

        <p v-if="error" class="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-800">{{ error }}</p>
        <p v-if="success" class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ success }}</p>

        <div v-if="showForm" class="rounded-xl border border-talents-200 bg-talents-50/40 p-4">
            <h4 class="text-sm font-semibold text-talents-900">
                {{ editingId ? 'Editar justificativa' : 'Nova justificativa' }}
            </h4>
            <p class="mt-1 text-xs text-slate-500">
                Os dados são gravados diretamente na API RHID (Control iD) da empresa selecionada.
            </p>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <label class="text-sm text-slate-700 sm:col-span-2">
                    Colaborador
                    <select
                        v-model="form.idPerson"
                        class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    >
                        <option value="">Selecione…</option>
                        <option v-for="p in peopleOptions" :key="p.value" :value="String(p.value)">
                            {{ p.label }}
                        </option>
                    </select>
                </label>
                <label class="text-sm text-slate-700 sm:col-span-2">
                    Tipo
                    <select
                        v-model="form.idJustificationType"
                        class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    >
                        <option value="">Selecione…</option>
                        <option v-for="t in typeOptions" :key="t.value" :value="String(t.value)">
                            {{ t.label }}
                        </option>
                    </select>
                </label>
                <label class="text-sm text-slate-700">
                    Início (data)
                    <input
                        v-model="form.startDate"
                        type="date"
                        class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    >
                </label>
                <label class="text-sm text-slate-700">
                    Início (hora)
                    <input
                        v-model="form.startTime"
                        type="time"
                        class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    >
                </label>
                <label class="text-sm text-slate-700">
                    Fim (data)
                    <input
                        v-model="form.endDate"
                        type="date"
                        class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    >
                </label>
                <label class="text-sm text-slate-700">
                    Fim (hora)
                    <input
                        v-model="form.endTime"
                        type="time"
                        class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    >
                </label>
                <label class="text-sm text-slate-700 sm:col-span-2">
                    Descrição
                    <textarea
                        v-model="form.justificativa"
                        rows="3"
                        maxlength="2000"
                        class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        placeholder="Motivo / observação da justificativa"
                    />
                </label>
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                <PrimaryButton type="button" :disabled="saving" @click="submitForm">
                    {{ saving ? 'A gravar…' : editingId ? 'Guardar alterações' : 'Criar na RHID' }}
                </PrimaryButton>
                <SecondaryButton type="button" :disabled="saving" @click="cancelForm">Cancelar</SecondaryButton>
            </div>
        </div>

        <div class="max-h-[28rem] overflow-auto rounded-xl ring-1 ring-slate-100">
            <table class="min-w-full text-sm">
                <thead class="sticky top-0 bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-2">Colaborador</th>
                        <th class="px-4 py-2">Tipo</th>
                        <th class="px-4 py-2">Período</th>
                        <th class="px-4 py-2">Descrição</th>
                        <th class="px-4 py-2 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in filteredRows" :key="`just-${row.id ?? row.personName}`" class="border-t border-slate-50">
                        <td class="px-4 py-2 font-medium text-slate-800">{{ row.personName }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ row.typeLabel }}</td>
                        <td class="px-4 py-2 text-xs text-slate-600">{{ row.periodLabel || '—' }}</td>
                        <td class="max-w-xs px-4 py-2 text-slate-600">
                            <span class="line-clamp-2">{{ row.justificativa || '—' }}</span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-2 text-right">
                            <button
                                type="button"
                                class="mr-3 font-medium text-talents-700 hover:underline disabled:opacity-40"
                                :disabled="!row.id || saving"
                                @click="openEdit(row)"
                            >
                                Editar
                            </button>
                            <button
                                type="button"
                                class="font-medium text-rose-600 hover:underline disabled:opacity-40"
                                :disabled="!row.id || saving"
                                @click="destroyRow(row)"
                            >
                                Remover
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!filteredRows.length && !loading">
                        <td colspan="5" class="px-4 py-8 text-center text-slate-500">
                            Nenhuma justificativa no período. Ajuste as datas ou crie uma nova.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
