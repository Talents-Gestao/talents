<script setup>
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { canSendAny, copyText, plannedAudiences, sendOutcomeLabel } from '@/utils/hiringFollowup';
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

/**
 * «Enviar acompanhamento» de um marco: destino Colaborador, Gestor ou Ambos; canal E-mail ou Copiar link e mensagem.
 * Destino sem elegibilidade é pulado com o motivo e o outro segue (envio parcial explícito). A tela nunca trava
 * pedindo contato da empresa: mostra o motivo e deixa enviar ao colaborador.
 */
const props = defineProps({
    show: { type: Boolean, default: false },
    milestone: { type: Object, default: null },
    employeeName: { type: String, default: '' },
    emailsRemaining: { type: Number, default: 0 },
});
const emit = defineEmits(['close']);

const target = ref('both');
const channel = ref('email');
const results = ref([]);
const errors = ref([]);
const sending = ref(false);
const copied = ref({});

const labels = { collaborator: 'Colaborador', manager: 'Gestor' };
const planned = computed(() => (props.milestone ? plannedAudiences(props.milestone.eligibility, target.value, channel.value) : []));
const sendable = computed(() => props.milestone && canSendAny(props.milestone.eligibility, target.value, channel.value));

watch(
    () => props.show,
    (open) => {
        if (open) {
            target.value = 'both';
            channel.value = 'email';
            results.value = [];
            errors.value = [];
            copied.value = {};
        }
    },
);

const send = async () => {
    sending.value = true;
    errors.value = [];
    try {
        const { data } = await window.axios.post(props.milestone.send_url, { target: target.value, channel: channel.value });
        results.value = data.results;
        if (channel.value === 'email') {
            router.reload({ preserveScroll: true });
        }
    } catch (e) {
        errors.value = Object.values(e?.response?.data?.errors ?? { x: ['Não foi possível enviar o acompanhamento.'] }).map((v) => v[0]);
    } finally {
        sending.value = false;
    }
};

const copy = async (result) => {
    try {
        await navigator.clipboard.writeText(copyText(result));
    } catch {
        errors.value = ['Não foi possível copiar automaticamente. Selecione o texto e copie manualmente.'];
        return;
    }
    try {
        await window.axios.post(result.copy_url);
        copied.value = { ...copied.value, [result.audience]: true };
        router.reload({ preserveScroll: true });
    } catch {
        errors.value = ['O texto foi copiado, mas a cópia não foi registrada. Tente de novo.'];
    }
};

const outcomeClass = (outcome) =>
    ({
        emailed: 'text-emerald-700',
        failed: 'text-red-700',
        skipped: 'text-amber-800',
        ready_to_copy: 'text-sky-800',
        already_answered: 'text-talents-800',
    })[outcome] ?? 'text-slate-700';
</script>

<template>
    <Modal :show="show" max-width="xl" @close="emit('close')">
        <div v-if="milestone" class="space-y-4 p-6">
            <div>
                <h3 class="text-lg font-semibold text-talents-900">Enviar acompanhamento {{ milestone.moment_label }}</h3>
                <p class="text-sm text-slate-600">{{ employeeName }} · {{ milestone.status_label }}</p>
            </div>

            <template v-if="!results.length">
                <fieldset>
                    <legend class="text-sm font-medium text-slate-700">Para quem</legend>
                    <div class="mt-2 flex flex-wrap gap-2 text-sm">
                        <label v-for="opt in [['collaborator', 'Colaborador'], ['manager', 'Gestor'], ['both', 'Ambos']]" :key="opt[0]"
                            class="flex cursor-pointer items-center gap-2 rounded-xl border px-3 py-2"
                            :class="target === opt[0] ? 'border-talents-400 bg-talents-50' : 'border-slate-200'"
                        >
                            <input v-model="target" type="radio" :value="opt[0]" class="text-talents-600" /> {{ opt[1] }}
                        </label>
                    </div>
                </fieldset>
                <fieldset>
                    <legend class="text-sm font-medium text-slate-700">Como</legend>
                    <div class="mt-2 flex flex-wrap gap-2 text-sm">
                        <label class="flex cursor-pointer items-center gap-2 rounded-xl border px-3 py-2" :class="channel === 'email' ? 'border-talents-400 bg-talents-50' : 'border-slate-200'">
                            <input v-model="channel" type="radio" value="email" class="text-talents-600" /> E-mail
                        </label>
                        <label class="flex cursor-pointer items-center gap-2 rounded-xl border px-3 py-2" :class="channel === 'copy' ? 'border-talents-400 bg-talents-50' : 'border-slate-200'">
                            <input v-model="channel" type="radio" value="copy" class="text-talents-600" /> Copiar link e mensagem
                        </label>
                    </div>
                    <p v-if="channel === 'email'" class="mt-1 text-xs text-slate-500">Restam {{ emailsRemaining }} e-mails de Contratação hoje.</p>
                </fieldset>

                <ul class="space-y-1 rounded-xl bg-slate-50 px-3 py-2 text-sm">
                    <li v-for="p in planned" :key="p.audience">
                        <span class="font-semibold">{{ labels[p.audience] }}:</span>
                        <span v-if="p.reason === null" class="text-emerald-700"> pode receber</span>
                        <span v-else class="text-amber-800"> será pulado — {{ p.reason }}</span>
                    </li>
                </ul>
            </template>

            <ul v-else class="space-y-3">
                <li v-for="r in results" :key="r.audience" class="rounded-xl border border-slate-200 p-3 text-sm">
                    <p>
                        <span class="font-semibold">{{ r.audience_label }}:</span>
                        <span :class="outcomeClass(r.outcome)"> {{ sendOutcomeLabel(r) }}</span>
                        <span v-if="r.reason" class="text-slate-600"> — {{ r.reason }}</span>
                    </p>
                    <template v-if="r.outcome === 'ready_to_copy'">
                        <pre class="mt-2 max-h-40 overflow-auto whitespace-pre-wrap rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-700">{{ r.message }}</pre>
                        <div class="mt-2 flex items-center gap-2">
                            <SecondaryButton type="button" :aria-label="`Copiar link e mensagem para ${r.audience_label}`" @click="copy(r)">Copiar</SecondaryButton>
                            <span v-if="copied[r.audience]" class="text-xs text-sky-800">Link copiado (registrado). Copiar não é entregar: envie você mesmo.</span>
                        </div>
                    </template>
                </li>
            </ul>

            <div v-if="errors.length" class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900" role="alert">
                <p v-for="(e, i) in errors" :key="i">{{ e }}</p>
            </div>

            <div class="flex justify-end gap-2">
                <SecondaryButton type="button" @click="emit('close')">{{ results.length ? 'Fechar' : 'Cancelar' }}</SecondaryButton>
                <PrimaryButton v-if="!results.length" type="button" :disabled="sending || !sendable" @click="send">
                    {{ channel === 'email' ? 'Enviar' : 'Gerar link e mensagem' }}
                </PrimaryButton>
            </div>
        </div>
    </Modal>
</template>
