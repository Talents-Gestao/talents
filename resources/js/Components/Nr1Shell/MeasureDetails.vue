<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Nr1Button from './Nr1Button.vue';
import { useNr1Toast } from './useNr1Toast';

/**
 * Execução da medida no plano de ação: objetivo, evidências, eficácia e histórico; a empresa registra evidência, ajusta,
 * replaneja e verifica a eficácia pelas rotas do plano (a URL só vem preenchida quando a ação é permitida).
 */
const props = defineProps({
    measure: { type: Object, required: true },
    users: { type: Array, default: () => [] },
    effectivenessResults: { type: Object, default: () => ({}) },
});

const { push } = useNr1Toast();
const fileInput = ref(null);

const adjustForm = useForm({
    responsible_user_id: props.measure.confirmed?.responsible_user_id ?? '',
    responsible_text: props.measure.confirmed?.responsible_text ?? '',
    due_date: props.measure.confirmed?.due_date ?? '',
    reason: '',
});
const replanForm = useForm({ due_date: '', reason: '' });
const evidenceForm = useForm({ description: '', file: null });
const effectivenessForm = useForm({
    note: props.measure.effectiveness.note ?? '',
    result: props.measure.effectiveness.result ?? '',
    checked_at: '',
});

const submit = (form, url, message, after) => {
    form.transform((data) => ('responsible_user_id' in data ? { ...data, responsible_user_id: data.responsible_user_id || null } : data)).post(url, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            push(message);
            after?.();
        },
    });
};
const saveEvidence = () => submit(evidenceForm, props.measure.urls.evidence, 'Evidência registrada.', () => {
    evidenceForm.reset();
    if (fileInput.value) fileInput.value.value = '';
});
</script>

<template>
    <details class="n1-measure-more">
        <summary>Execução, evidências e histórico</summary>

        <dl v-if="measure.objective || measure.expected_result || measure.indicator" class="n1-measure-facts">
            <div v-if="measure.objective"><dt>Objetivo</dt><dd>{{ measure.objective }}</dd></div>
            <div v-if="measure.expected_result"><dt>Resultado esperado</dt><dd>{{ measure.expected_result }}</dd></div>
            <div v-if="measure.indicator"><dt>Indicador</dt><dd>{{ measure.indicator }}</dd></div>
        </dl>

        <h4>Evidências de execução</h4>
        <ul v-if="measure.evidences.length" class="n1-history">
            <li v-for="evidence in measure.evidences" :key="evidence.id">
                <time>{{ evidence.created_at }}</time>
                <span>
                    {{ evidence.description }}
                    <a v-if="evidence.download_url" :href="evidence.download_url">{{ evidence.file_name }}</a>
                    <template v-else-if="evidence.file_name"> ({{ evidence.file_name }})</template>
                    · {{ evidence.author }}
                </span>
            </li>
        </ul>
        <p v-else class="n1-tiny">Nenhuma evidência registrada.</p>

        <form v-if="measure.urls.evidence" class="n1-measure-form no-print" @submit.prevent="saveEvidence">
            <label class="n1-label" :for="'ev-text-' + measure.id">Registrar evidência</label>
            <input :id="'ev-text-' + measure.id" v-model="evidenceForm.description" type="text" required class="n1-field" placeholder="O que foi feito" />
            <label class="n1-label" :for="'ev-file-' + measure.id">Arquivo (opcional, até 20 MB)</label>
            <input :id="'ev-file-' + measure.id" ref="fileInput" type="file" class="n1-field" @input="evidenceForm.file = $event.target.files[0]" />
            <p v-if="evidenceForm.errors.description || evidenceForm.errors.file" class="n1-tiny" role="alert">{{ evidenceForm.errors.description || evidenceForm.errors.file }}</p>
            <Nr1Button type="submit" size="sm" :loading="evidenceForm.processing">Registrar evidência</Nr1Button>
        </form>

        <template v-if="measure.effectiveness.result_label || measure.urls.effectiveness">
            <h4>Verificação de eficácia</h4>
            <p v-if="measure.effectiveness.result_label" class="n1-tiny">
                <b>{{ measure.effectiveness.result_label }}</b> · {{ measure.effectiveness.checked_at }} — {{ measure.effectiveness.note }}
            </p>
            <form v-if="measure.urls.effectiveness" class="n1-measure-form no-print" @submit.prevent="submit(effectivenessForm, measure.urls.effectiveness, 'Verificação de eficácia registrada.')">
                <label class="n1-label" :for="'ef-result-' + measure.id">Resultado</label>
                <select :id="'ef-result-' + measure.id" v-model="effectivenessForm.result" required class="n1-field">
                    <option value="" disabled>Selecione</option>
                    <option v-for="(label, value) in effectivenessResults" :key="value" :value="value">{{ label }}</option>
                </select>
                <label class="n1-label" :for="'ef-date-' + measure.id">Data da verificação</label>
                <input :id="'ef-date-' + measure.id" v-model="effectivenessForm.checked_at" type="date" class="n1-field" />
                <label class="n1-label" :for="'ef-note-' + measure.id">Descrição</label>
                <textarea :id="'ef-note-' + measure.id" v-model="effectivenessForm.note" rows="2" required class="n1-field"></textarea>
                <p v-if="effectivenessForm.errors.note || effectivenessForm.errors.result" class="n1-tiny" role="alert">{{ effectivenessForm.errors.note || effectivenessForm.errors.result }}</p>
                <Nr1Button type="submit" size="sm" :loading="effectivenessForm.processing">Salvar verificação</Nr1Button>
            </form>
        </template>

        <form v-if="measure.urls.adjust" class="n1-measure-form no-print" @submit.prevent="submit(adjustForm, measure.urls.adjust, 'Ajuste registrado no histórico.')">
            <h4>Ajustar responsável ou prazo</h4>
            <label class="n1-label" :for="'aj-user-' + measure.id">Usuário responsável</label>
            <select :id="'aj-user-' + measure.id" v-model="adjustForm.responsible_user_id" class="n1-field">
                <option value="">Outro (informar abaixo)</option>
                <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
            </select>
            <label class="n1-label" :for="'aj-text-' + measure.id">Nome, função e contato</label>
            <input :id="'aj-text-' + measure.id" v-model="adjustForm.responsible_text" type="text" class="n1-field" />
            <label class="n1-label" :for="'aj-date-' + measure.id">Prazo</label>
            <input :id="'aj-date-' + measure.id" v-model="adjustForm.due_date" type="date" required class="n1-field" />
            <label class="n1-label" :for="'aj-why-' + measure.id">Justificativa (obrigatória)</label>
            <input :id="'aj-why-' + measure.id" v-model="adjustForm.reason" type="text" required class="n1-field" />
            <Nr1Button type="submit" size="sm" :loading="adjustForm.processing">Registrar ajuste</Nr1Button>
        </form>

        <form v-if="measure.urls.replan" class="n1-measure-form no-print" @submit.prevent="submit(replanForm, measure.urls.replan, 'Medida replanejada.', () => replanForm.reset())">
            <h4>Replanejar</h4>
            <label class="n1-label" :for="'rp-date-' + measure.id">Novo prazo</label>
            <input :id="'rp-date-' + measure.id" v-model="replanForm.due_date" type="date" required class="n1-field" />
            <label class="n1-label" :for="'rp-why-' + measure.id">Justificativa (obrigatória)</label>
            <input :id="'rp-why-' + measure.id" v-model="replanForm.reason" type="text" required class="n1-field" />
            <Nr1Button type="submit" size="sm" :loading="replanForm.processing">Replanejar</Nr1Button>
        </form>

        <template v-if="measure.history.length">
            <h4>Histórico</h4>
            <ul class="n1-history">
                <li v-for="row in measure.history" :key="row.id">
                    <time>{{ row.at }}</time>
                    <span>{{ row.field_label }}: {{ row.old ?? '—' }} → {{ row.new ?? '—' }} · {{ row.author }}<template v-if="row.reason"> · {{ row.reason }}</template></span>
                </li>
            </ul>
        </template>
    </details>
</template>
