<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import Nr1Button from './Nr1Button.vue';
import { useNr1Toast } from './useNr1Toast';

/**
 * Cartão de oportunidade da fila comercial: serviço sugerido, empresa, estágio, histórico de contato e próxima ação.
 * Assumir, mudar de estágio e registrar a próxima ação usam as rotas que já existem (permissão de edição no módulo).
 */
const props = defineProps({
    row: { type: Object, required: true },
    canWork: { type: Boolean, default: false },
    compact: { type: Boolean, default: false },
});

const { push } = useNr1Toast();
const back = () => ({ _nr1_return: window.location.pathname + window.location.search });

const TONE = { new: 'gray', to_qualify: 'warn', in_contact: 'blue', proposal_sent: 'purple', won: 'ok', lost: 'gray' };
const tone = computed(() => TONE[props.row.status] ?? 'gray');

const statusForm = useForm({ status: '', note: '' });
const needsReason = computed(() => statusForm.status === 'lost');
const changeStatus = () => {
    statusForm.transform((data) => ({ ...data, ...back() })).post(props.row.urls.status, {
        preserveScroll: true,
        onSuccess: () => {
            push('Estado atualizado.');
            statusForm.reset();
        },
    });
};
const actionForm = useForm({ next_action: props.row.next_action ?? '', next_action_at: '' });
const saveNextAction = () => {
    actionForm.transform((data) => ({ ...data, ...back() })).post(props.row.urls.nextAction, { preserveScroll: true, onSuccess: () => push('Próxima ação registrada.') });
};
const assume = () => router.post(props.row.urls.assume, back(), { preserveScroll: true, onSuccess: () => push('Oportunidade assumida.') });
</script>

<template>
    <article class="n1-opp n1-lift" :class="[tone, { compact }]">
        <div class="n1-row" style="align-items: flex-start">
            <div>
                <span class="n1-cap">{{ row.origin_label }}</span>
                <h3 class="n1-opp-title">{{ row.service }}</h3>
                <p class="n1-tiny">{{ row.company_name }} · {{ row.survey_title }}</p>
            </div>
            <span class="n1-pill n1-stage" :class="tone">{{ row.status_label }}</span>
        </div>

        <dl v-if="!compact" class="n1-docmeta">
            <div><dt class="n1-cap">Solicitante</dt><dd>{{ row.requester ?? 'Sugestão automática' }}</dd></div>
            <div><dt class="n1-cap">Responsável</dt><dd>{{ row.assignee ?? 'Sem responsável' }}</dd></div>
            <div><dt class="n1-cap">Última atividade</dt><dd>{{ row.last_activity_at }}</dd></div>
            <div><dt class="n1-cap">Próxima ação</dt><dd>{{ row.next_action ?? '—' }}<template v-if="row.next_action_at"> · {{ row.next_action_at }}</template></dd></div>
        </dl>
        <p v-else class="n1-tiny">{{ row.assignee ?? 'Sem responsável' }}</p>

        <ul v-if="!compact && row.history.length" class="n1-opp-history" aria-label="Histórico de contato">
            <li v-for="(event, index) in row.history" :key="index"><i aria-hidden="true"></i><span>{{ event.body }} <small>{{ event.author }} · {{ event.at }}</small></span></li>
        </ul>

        <div v-if="canWork && row.open && !compact" class="n1-opp-actions no-print">
            <Nr1Button v-if="!row.assignee" size="sm" variant="primary" @click="assume">Assumir oportunidade</Nr1Button>

            <form v-if="row.transitions.length" class="n1-opp-form" @submit.prevent="changeStatus">
                <label class="sr-only" :for="'st-' + row.id">Mudar estágio</label>
                <select :id="'st-' + row.id" v-model="statusForm.status" class="n1-field" required>
                    <option value="">Mudar estágio…</option>
                    <option v-for="t in row.transitions" :key="t.value" :value="t.value">{{ t.label }}</option>
                </select>
                <input v-if="statusForm.status" v-model="statusForm.note" type="text" class="n1-field" :placeholder="needsReason ? 'Motivo da recusa (mín. 5 caracteres)' : 'Nota (opcional)'" :required="needsReason" :aria-label="needsReason ? 'Motivo da recusa' : 'Nota'" />
                <Nr1Button type="submit" size="sm" :disabled="!statusForm.status" :loading="statusForm.processing">Aplicar</Nr1Button>
            </form>
            <p v-if="statusForm.errors.status || statusForm.errors.note" class="n1-tiny" style="color: #a6334b">{{ statusForm.errors.status ?? statusForm.errors.note }}</p>

            <form class="n1-opp-form" @submit.prevent="saveNextAction">
                <label class="sr-only" :for="'na-' + row.id">Próxima ação</label>
                <input :id="'na-' + row.id" v-model="actionForm.next_action" type="text" class="n1-field" placeholder="Próxima ação comercial" required />
                <input v-model="actionForm.next_action_at" type="date" class="n1-field" aria-label="Data da próxima ação" style="max-width: 150px" />
                <Nr1Button type="submit" size="sm" :loading="actionForm.processing">Registrar</Nr1Button>
            </form>
        </div>
    </article>
</template>
