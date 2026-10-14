import axios from 'axios';
import { computed, ref } from 'vue';
import { extractListItems } from '@/utils/rhidDate';

export const PUNCH_DAY_ORDER = [
    { key: 'seg', label: 'Segunda-feira' },
    { key: 'ter', label: 'Terça-feira' },
    { key: 'qua', label: 'Quarta-feira' },
    { key: 'qui', label: 'Quinta-feira' },
    { key: 'sex', label: 'Sexta-feira' },
    { key: 'sab', label: 'Sábado' },
    { key: 'dom', label: 'Domingo' },
];

export const defaultScheduleForm = () => {
    const dias = {};
    for (const { key } of PUNCH_DAY_ORDER) {
        dias[key] = {
            ativo: false,
            entrada: null,
            saida_almoco: null,
            volta_almoco: null,
            saida: null,
            almoco2_inicio: null,
            almoco2_fim: null,
            trabalho2_entrada: null,
            trabalho2_saida: null,
        };
    }
    return {
        segundo_trabalho: false,
        segundo_almoco: false,
        tolerancia_minutos: 10,
        dias,
    };
};

const parseIdList = (raw) => {
    const t = String(raw ?? '').trim();
    if (!t) {
        return null;
    }
    const parts = t.split(/[\s,;]+/);
    const ids = [];
    for (const p of parts) {
        const n = parseInt(p.trim(), 10);
        if (!Number.isNaN(n)) {
            ids.push(n);
        }
    }
    return ids.length ? ids : null;
};

/**
 * @param {{ value: boolean }} configuredRef — ref ou objeto com .value indicando se RHID está configurado
 * @param {{ value: string|null }} errRef — opcional, para mensagens de erro (ex.: err do pai)
 * @param {{
 *   show?: () => string,
 *   update?: () => string,
 *   people?: () => string,
 *   batchPrefs?: () => string,
 * } | null} routeMap — rotas Admin; omissão usa as rotas do painel Cliente
 */
export function useRhidPunchSchedule(configuredRef, errRef = null, routeMap = null) {
    const punchScheduleShowUrl = () =>
        routeMap?.show?.() ?? route('client.rhid.api.punch-schedule-settings.show');
    const punchScheduleUpdateUrl = () =>
        routeMap?.update?.() ?? route('client.rhid.api.punch-schedule-settings.update');
    const peopleIndexUrl = () => routeMap?.people?.() ?? route('client.rhid.api.people.index');
    const schedulePrefsBatchUrl = () =>
        routeMap?.batchPrefs?.() ?? route('client.rhid.api.people.schedule-preferences.batch');
    const scheduleForm = ref(defaultScheduleForm());
    const scheduleLoading = ref(false);
    const scheduleSaving = ref(false);
    const schedulePrefBatchIds = ref('');
    const schedulePrefBatchSecond = ref(false);
    const schedulePrefBatchSaving = ref(false);
    const schedulePrefBatchMsg = ref(null);
    const schedulePrefListLoading = ref(false);
    const schedulePrefPeopleFilter = ref('');
    const schedulePrefBatchPicked = ref([]);
    const schedulePeopleList = ref(null);

    const personDisplayName = (row) =>
        row?.name ?? row?.nome ?? row?.strName ?? row?.personName ?? (row?.id != null ? `Colaborador #${row.id}` : '—');

    const rhidPersonId = (row) => {
        const raw = row?.id != null && row?.id !== '' ? row.id : row?.idPerson;
        if (raw == null || raw === '') {
            return null;
        }
        const n = Number(raw);
        return Number.isFinite(n) ? n : null;
    };

    const peopleRows = computed(() => extractListItems(schedulePeopleList.value));

    const schedulePrefPeopleFiltered = computed(() => {
        const q = String(schedulePrefPeopleFilter.value || '')
            .trim()
            .toLowerCase();
        const out = [];
        for (const row of peopleRows.value) {
            const id = rhidPersonId(row);
            if (id == null) {
                continue;
            }
            const name = String(personDisplayName(row) || '').trim() || `ID ${id}`;
            if (q && !name.toLowerCase().includes(q)) {
                continue;
            }
            out.push({ id, name, row });
        }
        out.sort((a, b) => a.name.localeCompare(b.name, 'pt-BR'));
        return out;
    });

    const schedulePrefPickedSet = computed(() => new Set(schedulePrefBatchPicked.value));

    const setErr = (msg) => {
        if (errRef) {
            errRef.value = msg;
        }
    };

    const clearErr = () => {
        if (errRef) {
            errRef.value = null;
        }
    };

    const loadPunchScheduleSettings = async () => {
        if (!configuredRef.value) {
            return;
        }
        scheduleLoading.value = true;
        clearErr();
        try {
            const { data } = await axios.get(punchScheduleShowUrl());
            const s = data?.settings;
            if (s && typeof s === 'object') {
                const base = defaultScheduleForm();
                base.segundo_trabalho = Boolean(s.segundo_trabalho);
                base.segundo_almoco = Boolean(s.segundo_almoco);
                const tm = Number(s.tolerancia_minutos);
                base.tolerancia_minutos = Number.isFinite(tm) ? Math.min(120, Math.max(0, Math.round(tm))) : 10;
                for (const { key } of PUNCH_DAY_ORDER) {
                    const incoming = s.dias?.[key];
                    if (incoming && typeof incoming === 'object') {
                        base.dias[key] = { ...base.dias[key], ...incoming };
                    }
                }
                scheduleForm.value = base;
            } else {
                scheduleForm.value = defaultScheduleForm();
            }
        } catch (e) {
            setErr(e.response?.data?.message || e.message || 'Erro na requisicao');
            scheduleForm.value = defaultScheduleForm();
        } finally {
            scheduleLoading.value = false;
        }
    };

    const savePunchScheduleSettings = async () => {
        if (!configuredRef.value || !scheduleForm.value) {
            return;
        }
        scheduleSaving.value = true;
        clearErr();
        try {
            const { data } = await axios.put(punchScheduleUpdateUrl(), scheduleForm.value);
            const s = data?.settings;
            if (s && typeof s === 'object') {
                const base = defaultScheduleForm();
                base.segundo_trabalho = Boolean(s.segundo_trabalho);
                base.segundo_almoco = Boolean(s.segundo_almoco);
                const tm = Number(s.tolerancia_minutos);
                base.tolerancia_minutos = Number.isFinite(tm) ? Math.min(120, Math.max(0, Math.round(tm))) : 10;
                for (const { key } of PUNCH_DAY_ORDER) {
                    const incoming = s.dias?.[key];
                    if (incoming && typeof incoming === 'object') {
                        base.dias[key] = { ...base.dias[key], ...incoming };
                    }
                }
                scheduleForm.value = base;
            }
        } catch (e) {
            setErr(e.response?.data?.message || e.message || 'Erro na requisicao');
        } finally {
            scheduleSaving.value = false;
        }
    };

    const loadPeopleForScheduleBatch = async () => {
        if (!configuredRef.value) {
            return;
        }
        schedulePrefListLoading.value = true;
        schedulePrefBatchMsg.value = null;
        clearErr();
        try {
            const { data } = await axios.get(peopleIndexUrl(), {
                params: { page: 0, maxSize: 500 },
            });
            schedulePeopleList.value = data;
            schedulePrefBatchMsg.value = 'Lista carregada. Marque os colaboradores abaixo e aplique.';
        } catch (e) {
            setErr(e.response?.data?.message || e.message || 'Erro na requisicao');
        } finally {
            schedulePrefListLoading.value = false;
        }
    };

    const submitSchedulePreferenceBatch = async () => {
        if (!configuredRef.value) {
            return;
        }
        const fromNames = [...schedulePrefBatchPicked.value];
        const fromText = parseIdList(schedulePrefBatchIds.value) ?? [];
        const merged = [...new Set([...fromNames, ...fromText])];
        if (!merged.length) {
            schedulePrefBatchMsg.value =
                'Selecione ao menos um colaborador na lista (por nome) ou informe IDs no campo opcional.';
            return;
        }
        schedulePrefBatchSaving.value = true;
        schedulePrefBatchMsg.value = null;
        clearErr();
        try {
            const { data } = await axios.post(schedulePrefsBatchUrl(), {
                id_people: merged,
                use_second_lunch_interval: schedulePrefBatchSecond.value,
            });
            schedulePrefBatchMsg.value = `Atualizado: ${data.updated} colaborador(es).`;
        } catch (e) {
            setErr(e.response?.data?.message || e.message || 'Erro na requisicao');
        } finally {
            schedulePrefBatchSaving.value = false;
        }
    };

    const restorePunchScheduleSettings = () => {
        loadPunchScheduleSettings();
    };

    const toggleSchedulePrefPick = (id) => {
        const cur = [...schedulePrefBatchPicked.value];
        const i = cur.indexOf(id);
        if (i >= 0) {
            cur.splice(i, 1);
        } else {
            cur.push(id);
        }
        schedulePrefBatchPicked.value = cur;
    };

    const selectAllSchedulePrefVisible = () => {
        const ids = schedulePrefPeopleFiltered.value.map((p) => p.id);
        schedulePrefBatchPicked.value = [...new Set([...schedulePrefBatchPicked.value, ...ids])];
    };

    const clearSchedulePrefPicks = () => {
        schedulePrefBatchPicked.value = [];
    };

    return {
        PUNCH_DAY_ORDER,
        scheduleForm,
        scheduleLoading,
        scheduleSaving,
        schedulePrefBatchIds,
        schedulePrefBatchSecond,
        schedulePrefBatchSaving,
        schedulePrefBatchMsg,
        schedulePrefListLoading,
        schedulePrefPeopleFilter,
        schedulePrefBatchPicked,
        schedulePeopleList,
        peopleRows,
        schedulePrefPeopleFiltered,
        schedulePrefPickedSet,
        personDisplayName,
        rhidPersonId,
        loadPunchScheduleSettings,
        savePunchScheduleSettings,
        loadPeopleForScheduleBatch,
        submitSchedulePreferenceBatch,
        restorePunchScheduleSettings,
        toggleSchedulePrefPick,
        selectAllSchedulePrefVisible,
        clearSchedulePrefPicks,
    };
}
