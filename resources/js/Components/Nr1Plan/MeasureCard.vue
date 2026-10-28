<script setup>
import MeasureHistory from '@/Components/Nr1Plan/MeasureHistory.vue';
import { router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    surveyId: { type: Number, required: true },
    measure: { type: Object, required: true },
    users: { type: Array, default: () => [] },
    canEdit: { type: Boolean, default: false },
    transitions: { type: Object, default: () => ({}) },
    effectivenessResults: { type: Object, default: () => ({}) },
});

const STATUS_BADGE = {
    to_approve: 'bg-gray-100 text-gray-800 border-gray-300',
    pending: 'bg-sky-50 text-sky-800 border-sky-200',
    in_progress: 'bg-amber-50 text-amber-800 border-amber-200',
    implemented: 'bg-emerald-50 text-emerald-800 border-emerald-200',
    replanned: 'bg-violet-50 text-violet-800 border-violet-200',
};

const url = (name) => route(`client.surveys.measures.${name}`, [props.surveyId, props.measure.id]);

const status = computed(() => props.measure.status);
const confirmed = computed(() => props.measure.confirmed);
const allowed = computed(() => props.transitions[status.value] ?? []);
const canStart = computed(() => allowed.value.includes('in_progress'));
const canComplete = computed(() => allowed.value.includes('implemented'));
const canReplan = computed(() => allowed.value.includes('replanned'));
const canConfirm = computed(() => status.value === 'to_approve');
const canAdjust = computed(() => confirmed.value !== null && status.value !== 'implemented');
const canEvidence = computed(() => status.value !== 'to_approve');
const canEffectiveness = computed(() => status.value === 'implemented');

const confirmForm = useForm({
    responsible_user_id: '',
    responsible_text: '',
    due_date: props.measure.suggested.due_date ?? '',
    reason: '',
});
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

const submit = (form, name, after) => {
    form.transform((data) => ({ ...data, responsible_user_id: data.responsible_user_id || null }))
        .post(url(name), { preserveScroll: true, forceFormData: true, onSuccess: () => after?.() });
};
const act = (name) => router.post(url(name), {}, { preserveScroll: true });
</script>

<template>
    <article class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm" :aria-labelledby="`measure-${measure.id}`">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ measure.factor_label }}</p>
                <h3 :id="`measure-${measure.id}`" class="mt-1 text-base font-semibold text-talents-900">{{ measure.measure_description }}</h3>
            </div>
            <span class="inline-flex shrink-0 rounded-md border px-2.5 py-1 text-xs font-semibold" :class="STATUS_BADGE[status]">
                {{ measure.status_label }}
            </span>
        </div>

        <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
            <div v-if="measure.objective"><dt class="text-xs text-gray-500">Objetivo</dt><dd>{{ measure.objective }}</dd></div>
            <div v-if="measure.expected_result"><dt class="text-xs text-gray-500">Resultado esperado</dt><dd>{{ measure.expected_result }}</dd></div>
            <div v-if="measure.indicator"><dt class="text-xs text-gray-500">Indicador</dt><dd>{{ measure.indicator }}</dd></div>
            <div>
                <dt class="text-xs text-gray-500">Sugerido pela Talents</dt>
                <dd>
                    {{ measure.suggested.responsible ?? 'Responsável não sugerido' }}
                    <template v-if="measure.suggested.due_date"> · prazo {{ measure.suggested.due_date }}</template>
                    <span v-if="measure.suggested.due_origin_label" class="block text-xs text-gray-500">{{ measure.suggested.due_origin_label }}</span>
                    <span v-if="measure.suggested.due_note" class="block text-xs text-gray-500">{{ measure.suggested.due_note }}</span>
                </dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500">Confirmado pela empresa</dt>
                <dd v-if="confirmed">
                    {{ confirmed.responsible }} · prazo {{ confirmed.due_date }}
                    <span class="block text-xs text-gray-500">Aprovado em {{ confirmed.approved_at }} por {{ confirmed.approved_by }}</span>
                </dd>
                <dd v-else class="text-gray-600">Ainda não confirmado. A sugestão da Talents não equivale a aceitação.</dd>
            </div>
        </dl>

        <div v-if="measure.evidences.length" class="mt-3 text-sm">
            <p class="text-xs text-gray-500">Evidências de execução</p>
            <ul class="mt-1 space-y-1">
                <li v-for="evidence in measure.evidences" :key="evidence.id">
                    {{ evidence.description }}
                    <a v-if="evidence.download_url" :href="evidence.download_url" class="ml-1 text-talents-700 underline">{{ evidence.file_name }}</a>
                    <span class="text-xs text-gray-500"> · {{ evidence.author }} · {{ evidence.created_at }}</span>
                </li>
            </ul>
        </div>

        <div v-if="measure.effectiveness.note" class="mt-3 text-sm">
            <p class="text-xs text-gray-500">Verificação de eficácia</p>
            <p>
                <span class="font-medium">{{ measure.effectiveness.result_label }}</span>
                · {{ measure.effectiveness.checked_at }} — {{ measure.effectiveness.note }}
            </p>
        </div>

        <div v-if="canEdit" class="mt-4 space-y-3 border-t border-gray-100 pt-4 text-sm">
            <form v-if="canConfirm" class="grid gap-3 sm:grid-cols-2" @submit.prevent="submit(confirmForm, 'confirm')">
                <p class="font-medium text-gray-900 sm:col-span-2">Confirmar responsável e prazo</p>
                <div>
                    <label :for="`cu-${measure.id}`" class="text-xs text-gray-600">Usuário responsável</label>
                    <select :id="`cu-${measure.id}`" v-model="confirmForm.responsible_user_id" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                        <option value="">Outro (informar abaixo)</option>
                        <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                    </select>
                </div>
                <div>
                    <label :for="`ct-${measure.id}`" class="text-xs text-gray-600">Nome, função e contato (se não for usuário)</label>
                    <input :id="`ct-${measure.id}`" v-model="confirmForm.responsible_text" type="text" class="mt-1 w-full rounded-md border-gray-300 text-sm" />
                </div>
                <div>
                    <label :for="`cd-${measure.id}`" class="text-xs text-gray-600">Prazo confirmado</label>
                    <input :id="`cd-${measure.id}`" v-model="confirmForm.due_date" type="date" required class="mt-1 w-full rounded-md border-gray-300 text-sm" />
                </div>
                <div>
                    <label :for="`cr-${measure.id}`" class="text-xs text-gray-600">Observação (opcional)</label>
                    <input :id="`cr-${measure.id}`" v-model="confirmForm.reason" type="text" class="mt-1 w-full rounded-md border-gray-300 text-sm" />
                </div>
                <p v-if="confirmForm.errors.due_date" class="text-xs text-red-600 sm:col-span-2">{{ confirmForm.errors.due_date }}</p>
                <div class="sm:col-span-2">
                    <button type="submit" class="rounded-md bg-talents-700 px-3 py-1.5 font-semibold text-white disabled:opacity-60" :disabled="confirmForm.processing">Confirmar</button>
                </div>
            </form>

            <div class="flex flex-wrap gap-2">
                <button v-if="canStart" type="button" class="rounded-md bg-talents-700 px-3 py-1.5 font-semibold text-white" @click="act('start')">Iniciar execução</button>
                <button v-if="canComplete" type="button" class="rounded-md bg-emerald-700 px-3 py-1.5 font-semibold text-white" @click="act('complete')">Concluir</button>
            </div>
            <p v-if="canComplete" class="text-xs text-gray-500">Para concluir é preciso ao menos uma evidência de execução.</p>

            <form v-if="canEvidence" class="grid gap-3 sm:grid-cols-2" @submit.prevent="submit(evidenceForm, 'evidences.store', () => evidenceForm.reset())">
                <p class="font-medium text-gray-900 sm:col-span-2">Registrar evidência de execução</p>
                <div class="sm:col-span-2">
                    <label :for="`ed-${measure.id}`" class="text-xs text-gray-600">Descrição</label>
                    <input :id="`ed-${measure.id}`" v-model="evidenceForm.description" type="text" required class="mt-1 w-full rounded-md border-gray-300 text-sm" />
                </div>
                <div class="sm:col-span-2">
                    <label :for="`ef-${measure.id}`" class="text-xs text-gray-600">Arquivo (opcional, até 20 MB)</label>
                    <input :id="`ef-${measure.id}`" type="file" class="mt-1 block w-full text-sm" @input="evidenceForm.file = $event.target.files[0]" />
                </div>
                <p v-if="evidenceForm.errors.description || evidenceForm.errors.file" class="text-xs text-red-600 sm:col-span-2">
                    {{ evidenceForm.errors.description || evidenceForm.errors.file }}
                </p>
                <div class="sm:col-span-2">
                    <button type="submit" class="rounded-md border border-gray-300 px-3 py-1.5 font-semibold text-gray-800 disabled:opacity-60" :disabled="evidenceForm.processing">Salvar evidência</button>
                </div>
            </form>

            <details v-if="canAdjust" class="rounded-md border border-gray-100 p-3">
                <summary class="cursor-pointer font-medium text-gray-900">Ajustar responsável ou prazo</summary>
                <form class="mt-3 grid gap-3 sm:grid-cols-2" @submit.prevent="submit(adjustForm, 'adjust')">
                    <div>
                        <label :for="`au-${measure.id}`" class="text-xs text-gray-600">Usuário responsável</label>
                        <select :id="`au-${measure.id}`" v-model="adjustForm.responsible_user_id" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                            <option value="">Outro (informar ao lado)</option>
                            <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label :for="`at-${measure.id}`" class="text-xs text-gray-600">Nome, função e contato</label>
                        <input :id="`at-${measure.id}`" v-model="adjustForm.responsible_text" type="text" class="mt-1 w-full rounded-md border-gray-300 text-sm" />
                    </div>
                    <div>
                        <label :for="`ad-${measure.id}`" class="text-xs text-gray-600">Prazo</label>
                        <input :id="`ad-${measure.id}`" v-model="adjustForm.due_date" type="date" required class="mt-1 w-full rounded-md border-gray-300 text-sm" />
                    </div>
                    <div>
                        <label :for="`ar-${measure.id}`" class="text-xs text-gray-600">Justificativa (obrigatória)</label>
                        <input :id="`ar-${measure.id}`" v-model="adjustForm.reason" type="text" required class="mt-1 w-full rounded-md border-gray-300 text-sm" />
                    </div>
                    <div class="sm:col-span-2">
                        <button type="submit" class="rounded-md border border-gray-300 px-3 py-1.5 font-semibold text-gray-800" :disabled="adjustForm.processing">Registrar ajuste</button>
                    </div>
                </form>
            </details>

            <details v-if="canReplan" class="rounded-md border border-gray-100 p-3">
                <summary class="cursor-pointer font-medium text-gray-900">Replanejar</summary>
                <form class="mt-3 grid gap-3 sm:grid-cols-2" @submit.prevent="submit(replanForm, 'replan', () => replanForm.reset())">
                    <div>
                        <label :for="`rd-${measure.id}`" class="text-xs text-gray-600">Novo prazo</label>
                        <input :id="`rd-${measure.id}`" v-model="replanForm.due_date" type="date" required class="mt-1 w-full rounded-md border-gray-300 text-sm" />
                    </div>
                    <div>
                        <label :for="`rr-${measure.id}`" class="text-xs text-gray-600">Justificativa (obrigatória)</label>
                        <input :id="`rr-${measure.id}`" v-model="replanForm.reason" type="text" required class="mt-1 w-full rounded-md border-gray-300 text-sm" />
                    </div>
                    <div class="sm:col-span-2">
                        <button type="submit" class="rounded-md border border-gray-300 px-3 py-1.5 font-semibold text-gray-800" :disabled="replanForm.processing">Replanejar</button>
                    </div>
                </form>
            </details>

            <form v-if="canEffectiveness" class="grid gap-3 sm:grid-cols-2" @submit.prevent="submit(effectivenessForm, 'effectiveness')">
                <p class="font-medium text-gray-900 sm:col-span-2">Verificação de eficácia</p>
                <div>
                    <label :for="`er-${measure.id}`" class="text-xs text-gray-600">Resultado</label>
                    <select :id="`er-${measure.id}`" v-model="effectivenessForm.result" required class="mt-1 w-full rounded-md border-gray-300 text-sm">
                        <option value="" disabled>Selecione</option>
                        <option v-for="(label, value) in effectivenessResults" :key="value" :value="value">{{ label }}</option>
                    </select>
                </div>
                <div>
                    <label :for="`ec-${measure.id}`" class="text-xs text-gray-600">Data da verificação</label>
                    <input :id="`ec-${measure.id}`" v-model="effectivenessForm.checked_at" type="date" class="mt-1 w-full rounded-md border-gray-300 text-sm" />
                </div>
                <div class="sm:col-span-2">
                    <label :for="`en-${measure.id}`" class="text-xs text-gray-600">Descrição da verificação</label>
                    <textarea :id="`en-${measure.id}`" v-model="effectivenessForm.note" rows="2" required class="mt-1 w-full rounded-md border-gray-300 text-sm" />
                </div>
                <div class="sm:col-span-2">
                    <button type="submit" class="rounded-md border border-gray-300 px-3 py-1.5 font-semibold text-gray-800" :disabled="effectivenessForm.processing">Salvar verificação</button>
                </div>
            </form>
        </div>

        <MeasureHistory :history="measure.history" />
    </article>
</template>
