<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import DimensionBadge from './DimensionBadge.vue';
import LevelPill from './LevelPill.vue';
import MeasureDetails from './MeasureDetails.vue';
import Nr1Button from './Nr1Button.vue';
import { useNr1Toast } from './useNr1Toast';

/**
 * Cartão de medida do plano de ação: sugestão completa editável in-card (Talents),
 * confirmação pela empresa, status, evidências e histórico.
 */
const props = defineProps({
    measure: { type: Object, required: true },
    users: { type: Array, default: () => [] },
    effectivenessResults: { type: Object, default: () => ({}) },
    dueOrigins: {
        type: Object,
        default: () => ({
            talents: 'Sugerido pela Talents',
            legal: 'Prazo legal ou contratual',
            company: 'Pedido da empresa',
        }),
    },
    /** Labels de status admin (parecer). Quando presente, o select de status fica no card. */
    statuses: { type: Object, default: null },
    companyId: { type: Number, default: null },
    surveyId: { type: Number, default: null },
});

const { push } = useNr1Toast();

const confirmed = computed(() => props.measure.confirmed !== null);
const editableSuggestion = computed(() => props.measure.urls.suggestion !== null);
const canConfirm = computed(() => props.measure.urls.confirm !== null);
const canUpdateStatus = computed(
    () => props.statuses !== null && props.companyId !== null && props.surveyId !== null,
);

const suggestion = useForm({
    factor_label: props.measure.factor_label ?? '',
    measure_description: props.measure.measure_description ?? '',
    objective: props.measure.objective ?? '',
    expected_result: props.measure.expected_result ?? '',
    indicator: props.measure.indicator ?? '',
    responsible_text: props.measure.suggested?.responsible ?? '',
    suggested_due_date: props.measure.suggested?.due_date ?? '',
    suggested_due_origin: props.measure.suggested?.due_origin ?? '',
    suggested_due_note: props.measure.suggested?.due_note ?? '',
    reason: '',
});

watch(
    () => props.measure,
    (measure) => {
        suggestion.factor_label = measure.factor_label ?? '';
        suggestion.measure_description = measure.measure_description ?? '';
        suggestion.objective = measure.objective ?? '';
        suggestion.expected_result = measure.expected_result ?? '';
        suggestion.indicator = measure.indicator ?? '';
        suggestion.responsible_text = measure.suggested?.responsible ?? '';
        suggestion.suggested_due_date = measure.suggested?.due_date ?? '';
        suggestion.suggested_due_origin = measure.suggested?.due_origin ?? '';
        suggestion.suggested_due_note = measure.suggested?.due_note ?? '';
    },
    { deep: true },
);

const saveSuggestion = () => {
    suggestion
        .transform((data) => ({
            factor_label: data.factor_label,
            measure_description: data.measure_description,
            objective: data.objective || null,
            expected_result: data.expected_result || null,
            indicator: data.indicator || null,
            responsible_text: data.responsible_text || null,
            suggested_due_date: data.suggested_due_date || null,
            suggested_due_origin: data.suggested_due_origin || null,
            suggested_due_note: data.suggested_due_note || null,
            reason: data.reason || null,
        }))
        .patch(props.measure.urls.suggestion, {
            preserveScroll: true,
            onSuccess: () => {
                suggestion.reset('reason');
                push('Sugestão atualizada.');
            },
        });
};

const confirmForm = useForm({
    responsible_user_id: '',
    responsible_text: '',
    due_date: props.measure.suggested?.due_date ?? '',
    reason: '',
});
const confirmAction = () => {
    confirmForm
        .transform((data) => ({
            responsible_user_id: data.responsible_user_id || null,
            responsible_text: data.responsible_user_id ? null : data.responsible_text || null,
            due_date: data.due_date,
            reason: data.reason || null,
        }))
        .post(props.measure.urls.confirm, {
            preserveScroll: true,
            onSuccess: () => push('Responsável e prazo confirmados.'),
        });
};

const selectedStatus = useForm({ status: props.measure.status });
watch(
    () => props.measure.status,
    (status) => {
        selectedStatus.status = status;
    },
);
const saveStatus = () => {
    if (!canUpdateStatus.value) {
        return;
    }
    selectedStatus.patch(
        route('admin.companies.surveys.measures.status', [props.companyId, props.surveyId, props.measure.id]),
        {
            preserveScroll: true,
            onSuccess: () => push('Status atualizado.'),
        },
    );
};

const step = (url, message) => router.post(url, {}, { preserveScroll: true, onSuccess: () => push(message) });
const dateBr = (iso) => (iso ? iso.split('-').reverse().join('/') : '—');
</script>

<template>
    <article class="n1-action n1-lift" :aria-labelledby="'measure-' + measure.id">
        <div class="n1-action-head">
            <div>
                <span class="n1-cap">
                    <template v-if="measure.score_label">Índice {{ measure.score_label }} / 5</template>
                    <template v-else>Medida do plano</template>
                </span>
                <h3 :id="'measure-' + measure.id" class="n1-dimname" style="font-size: 15px; margin: 4px 0 6px">
                    <DimensionBadge :title="measure.factor_label" :size="26" />{{ measure.factor_label }}
                </h3>
                <p v-if="!editableSuggestion">{{ measure.measure_description }}</p>
                <p v-if="measure.departments?.length" class="n1-tiny" style="margin-top: 6px">
                    <b>Setores apontados:</b>
                    <template v-for="(department, index) in measure.departments" :key="department.name">
                        <span v-if="index"> · </span>{{ department.name }}<template v-if="department.band_label"> ({{ department.band_label }}<template v-if="department.score_label"> · {{ department.score_label }}</template>)</template>
                    </template>
                </p>
            </div>
            <div class="n1-action-chips">
                <LevelPill v-if="measure.risk_level" :risk="measure.risk_level" :label="measure.band_label" />
                <span class="n1-chip" :class="{ done: confirmed }">{{ confirmed ? '✓ Confirmada pela empresa' : 'Sugestão Talents' }}</span>
                <span class="n1-chip n1-chip-status" :data-status="measure.status">{{ measure.status_label }}</span>
            </div>
        </div>

        <!-- Talents: todos os campos da sugestão no próprio card -->
        <form v-if="editableSuggestion" class="n1-measure-form n1-fields n1-measure-form--suggestion" @submit.prevent="saveSuggestion">
            <p class="n1-tiny" style="grid-column: 1 / -1">
                Editar a sugestão não confirma nada pela empresa. A confirmação de responsável e prazo é feita pela empresa depois de «Publicado».
            </p>
            <div>
                <label class="n1-label" :for="'factor-' + measure.id">Fator</label>
                <input :id="'factor-' + measure.id" v-model="suggestion.factor_label" type="text" required class="n1-field" />
            </div>
            <div>
                <label class="n1-label" :for="'ind-' + measure.id">Indicador</label>
                <input :id="'ind-' + measure.id" v-model="suggestion.indicator" type="text" class="n1-field" />
            </div>
            <div style="grid-column: 1 / -1">
                <label class="n1-label" :for="'desc-' + measure.id">Ação proposta</label>
                <textarea :id="'desc-' + measure.id" v-model="suggestion.measure_description" rows="3" required class="n1-field" />
            </div>
            <div>
                <label class="n1-label" :for="'obj-' + measure.id">Objetivo</label>
                <textarea :id="'obj-' + measure.id" v-model="suggestion.objective" rows="2" class="n1-field" />
            </div>
            <div>
                <label class="n1-label" :for="'exp-' + measure.id">Resultado esperado</label>
                <textarea :id="'exp-' + measure.id" v-model="suggestion.expected_result" rows="2" class="n1-field" />
            </div>
            <div>
                <label class="n1-label" :for="'owner-' + measure.id">Responsável sugerido</label>
                <input :id="'owner-' + measure.id" v-model="suggestion.responsible_text" type="text" class="n1-field" placeholder="Área ou função" />
            </div>
            <div>
                <label class="n1-label" :for="'due-' + measure.id">Prazo sugerido</label>
                <input :id="'due-' + measure.id" v-model="suggestion.suggested_due_date" type="date" class="n1-field" />
            </div>
            <div>
                <label class="n1-label" :for="'orig-' + measure.id">Origem do prazo</label>
                <select :id="'orig-' + measure.id" v-model="suggestion.suggested_due_origin" class="n1-field">
                    <option value="">Não informada</option>
                    <option v-for="(label, value) in dueOrigins" :key="value" :value="value">{{ label }}</option>
                </select>
            </div>
            <div>
                <label class="n1-label" :for="'note-' + measure.id">Justificativa do prazo sugerido</label>
                <input :id="'note-' + measure.id" v-model="suggestion.suggested_due_note" type="text" class="n1-field" />
            </div>
            <div style="grid-column: 1 / -1">
                <label class="n1-label" :for="'why-' + measure.id">Justificativa da alteração (obrigatória ao trocar um valor já registrado)</label>
                <input :id="'why-' + measure.id" v-model="suggestion.reason" type="text" class="n1-field" />
            </div>
            <div v-if="canUpdateStatus" style="grid-column: 1 / -1" class="n1-row" :style="{ gap: '10px', flexWrap: 'wrap', alignItems: 'flex-end' }">
                <div style="min-width: 180px">
                    <label class="n1-label" :for="'status-' + measure.id">Status</label>
                    <select :id="'status-' + measure.id" v-model="selectedStatus.status" class="n1-field">
                        <option v-for="(label, value) in statuses" :key="value" :value="value">{{ label }}</option>
                    </select>
                </div>
                <Nr1Button type="button" size="sm" :loading="selectedStatus.processing" @click="saveStatus">Atualizar status</Nr1Button>
            </div>
            <div v-else class="n1-row" style="grid-column: 1 / -1; gap: 10px; align-items: center">
                <span class="n1-label" style="margin: 0">Andamento</span>
                <div class="n1-readonly" style="width: auto">{{ measure.status_label }}</div>
            </div>
            <div style="grid-column: 1 / -1" class="n1-row" :style="{ gap: '10px', flexWrap: 'wrap', alignItems: 'center' }">
                <p v-if="suggestion.errors.measure_description || suggestion.errors.factor_label || suggestion.errors.reason" class="n1-tiny" role="alert">
                    {{ suggestion.errors.measure_description || suggestion.errors.factor_label || suggestion.errors.reason }}
                </p>
                <Nr1Button type="submit" variant="primary" size="sm" :loading="suggestion.processing">Salvar sugestão</Nr1Button>
            </div>
        </form>

        <!-- Empresa / leitura: colunas resumidas -->
        <template v-else>
            <div class="n1-action-cols">
                <div>
                    <label class="n1-label" :for="'owner-' + measure.id">Responsável {{ confirmed ? 'confirmado' : 'sugerido' }}</label>
                    <template v-if="confirmed">
                        <div class="n1-readonly">{{ measure.confirmed.responsible }}</div>
                        <p class="n1-tiny">Sugerido pela Talents: {{ measure.suggested.responsible ?? '—' }}</p>
                    </template>
                    <template v-else-if="canConfirm">
                        <select :id="'owner-' + measure.id" v-model="confirmForm.responsible_user_id" class="n1-field">
                            <option value="">{{ measure.suggested.responsible ?? 'Escolha o responsável' }} (sugerido)</option>
                            <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                        </select>
                        <input v-if="!confirmForm.responsible_user_id" v-model="confirmForm.responsible_text" type="text" class="n1-field" style="margin-top: 7px" placeholder="Ou digite o responsável" aria-label="Responsável (texto)" />
                    </template>
                    <div v-else class="n1-readonly">{{ measure.suggested.responsible ?? 'A definir' }}</div>
                </div>

                <div>
                    <label class="n1-label" :for="'due-' + measure.id">Prazo {{ confirmed ? 'confirmado' : 'sugerido' }}</label>
                    <div v-if="confirmed" class="n1-readonly">{{ dateBr(measure.confirmed.due_date) }}</div>
                    <input v-else-if="canConfirm" :id="'due-' + measure.id" v-model="confirmForm.due_date" type="date" class="n1-field" />
                    <div v-else class="n1-readonly">{{ dateBr(measure.suggested.due_date) }}</div>
                    <p class="n1-tiny">{{ measure.suggested.due_origin_label ?? 'Contado a partir da aprovação do plano' }}</p>
                </div>

                <div>
                    <span class="n1-label">Andamento</span>
                    <div class="n1-readonly">{{ measure.status_label }}</div>
                    <div v-if="measure.urls.start || measure.urls.complete" style="margin-top: 7px">
                        <Nr1Button v-if="measure.urls.start" size="sm" @click="step(measure.urls.start, 'Medida em andamento.')">Iniciar</Nr1Button>
                        <Nr1Button v-if="measure.urls.complete" size="sm" @click="step(measure.urls.complete, 'Medida concluída.')">Concluir</Nr1Button>
                    </div>
                </div>
            </div>

            <div class="n1-row" style="gap: 10px; flex-wrap: wrap; align-items: flex-end">
                <div class="n1-tiny">
                    <b>Evidência e aferição:</b> {{ measure.indicator ?? measure.expected_result ?? 'definidos com a empresa' }}.
                    <b>Verificação:</b> comparar a situação após a implementação e registrar o resultado.
                    <template v-if="measure.effectiveness.result_label"> <b>Eficácia:</b> {{ measure.effectiveness.result_label }}.</template>
                </div>
                <input v-if="canConfirm" v-model="confirmForm.reason" type="text" class="n1-field" style="max-width: 260px" placeholder="Observação (opcional)" aria-label="Observação da confirmação" />
                <Nr1Button v-if="canConfirm" variant="primary" size="sm" :loading="confirmForm.processing" :disabled="!confirmForm.due_date" @click="confirmAction">Confirmar ação</Nr1Button>
            </div>
        </template>

        <div
            v-if="canUpdateStatus && !editableSuggestion"
            class="n1-row"
            style="gap: 10px; flex-wrap: wrap; align-items: flex-end; margin-top: 12px"
        >
            <div style="min-width: 180px">
                <label class="n1-label" :for="'status-ro-' + measure.id">Status</label>
                <select :id="'status-ro-' + measure.id" v-model="selectedStatus.status" class="n1-field">
                    <option v-for="(label, value) in statuses" :key="value" :value="value">{{ label }}</option>
                </select>
            </div>
            <Nr1Button type="button" size="sm" :loading="selectedStatus.processing" @click="saveStatus">Atualizar status</Nr1Button>
        </div>

        <MeasureDetails :measure="measure" :users="users" :effectiveness-results="effectivenessResults" />
    </article>
</template>
