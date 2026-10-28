<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import DimensionBadge from './DimensionBadge.vue';
import LevelPill from './LevelPill.vue';
import Nr1Button from './Nr1Button.vue';
import { useNr1Toast } from './useNr1Toast';

/**
 * Cartão de medida do plano de ação: sugerido pela Talents × confirmado pela empresa, prazo sugerido editável (Talents,
 * até a publicação), status em chip, linha do tempo simples e as ações do ciclo pelas rotas que já existem.
 */
const props = defineProps({
    measure: { type: Object, required: true },
    users: { type: Array, default: () => [] },
});

const { push } = useNr1Toast();
const OWNER_OPTIONS = ['RH', 'Gestão de Operações', 'Diretoria', 'Liderança do setor', 'Gestor de Pessoas'];
const OTHER = 'Outro responsável';

const confirmed = computed(() => props.measure.confirmed !== null);
const editableSuggestion = computed(() => props.measure.urls.suggestion !== null);
const canConfirm = computed(() => props.measure.urls.confirm !== null);

// Talents: sugestão editável (responsável e prazo sugeridos).
const suggestionKnown = OWNER_OPTIONS.includes(props.measure.suggested.responsible);
const suggestion = useForm({
    owner: suggestionKnown || !props.measure.suggested.responsible ? props.measure.suggested.responsible ?? OWNER_OPTIONS[0] : OTHER,
    custom: suggestionKnown ? '' : props.measure.suggested.responsible ?? '',
    suggested_due_date: props.measure.suggested.due_date ?? '',
});
const saveSuggestion = () => {
    const responsible = suggestion.owner === OTHER ? suggestion.custom.trim() : suggestion.owner;
    suggestion.transform(() => ({ responsible_text: responsible, suggested_due_date: suggestion.suggested_due_date || null })).patch(props.measure.urls.suggestion, {
        preserveScroll: true,
        onSuccess: () => push('Sugestão de responsável e prazo atualizada.'),
    });
};

// Empresa: confirmar responsável (usuário da empresa ou texto) e prazo.
const confirmForm = useForm({ responsible_user_id: '', responsible_text: '', due_date: props.measure.suggested.due_date ?? '' });
const confirmAction = () => {
    confirmForm
        .transform((data) => ({ responsible_user_id: data.responsible_user_id || null, responsible_text: data.responsible_user_id ? null : data.responsible_text || null, due_date: data.due_date }))
        .post(props.measure.urls.confirm, { preserveScroll: true, onSuccess: () => push('Responsável e prazo confirmados.') });
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
                <p>{{ measure.measure_description }}</p>
            </div>
            <div class="n1-action-chips">
                <LevelPill v-if="measure.risk_level" :risk="measure.risk_level" :label="measure.band_label" />
                <span class="n1-chip" :class="{ done: confirmed }">{{ confirmed ? '✓ Confirmada pela empresa' : 'Sugestão Talents' }}</span>
                <span class="n1-chip n1-chip-status" :data-status="measure.status">{{ measure.status_label }}</span>
            </div>
        </div>

        <ol class="n1-timeline" aria-label="Andamento da medida">
            <li v-for="point in measure.timeline" :key="point.label" :class="point.state" :aria-current="point.state === 'current' ? 'step' : undefined">
                <i aria-hidden="true"></i>{{ point.label }}
            </li>
        </ol>

        <div class="n1-action-cols">
            <div>
                <label class="n1-label" :for="'owner-' + measure.id">Responsável {{ confirmed ? 'confirmado' : 'sugerido' }}</label>
                <template v-if="confirmed">
                    <div class="n1-readonly">{{ measure.confirmed.responsible }}</div>
                    <p class="n1-tiny">Sugerido pela Talents: {{ measure.suggested.responsible ?? '—' }}</p>
                </template>
                <template v-else-if="editableSuggestion">
                    <select :id="'owner-' + measure.id" v-model="suggestion.owner" class="n1-field">
                        <option v-for="option in [...OWNER_OPTIONS, OTHER]" :key="option">{{ option }}</option>
                    </select>
                    <input v-if="suggestion.owner === OTHER" v-model="suggestion.custom" type="text" class="n1-field" style="margin-top: 7px" placeholder="Nome ou área responsável" aria-label="Nome ou área responsável" />
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
                <input v-else-if="editableSuggestion" :id="'due-' + measure.id" v-model="suggestion.suggested_due_date" type="date" class="n1-field" />
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
            <Nr1Button v-if="editableSuggestion" variant="primary" size="sm" :loading="suggestion.processing" @click="saveSuggestion">Salvar sugestão</Nr1Button>
            <Nr1Button v-else-if="canConfirm" variant="primary" size="sm" :loading="confirmForm.processing" :disabled="!confirmForm.due_date" @click="confirmAction">Confirmar ação</Nr1Button>
        </div>
    </article>
</template>
