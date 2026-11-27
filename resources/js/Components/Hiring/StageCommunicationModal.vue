<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { contactsForChannel, fitsDailyLimit, reviewBlockers } from '@/utils/hiringCommunication';
import { Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

/**
 * «Comunicar etapa»: escolher etapa, canal e contatos elegíveis (tópico vagas), editar o texto-modelo
 * (vaga, etapa e quantidade de candidatos — nunca dado de candidato), revisar e enviar.
 */
const props = defineProps({
    show: { type: Boolean, default: false },
    vacancyId: { type: Number, required: true },
    stages: { type: Array, required: true },
    audience: { type: Object, required: true },
});
const emit = defineEmits(['close']);

const step = ref('compose');
const stageId = ref('');
const channel = ref('email');
const selected = ref([]);
const subject = ref('');
const body = ref('');
const resend = ref(false);
const rows = ref([]);
const remaining = ref(props.audience.emails_remaining_today);
const loading = ref(false);
const errors = ref({});

const available = computed(() => contactsForChannel(props.audience.contacts, channel.value));
const blockers = computed(() => reviewBlockers(rows.value, resend.value));
const hasDuplicates = computed(() => rows.value.some((r) => r.already_done));
const overLimit = computed(() => channel.value === 'email' && !fitsDailyLimit(selected.value.length, remaining.value));

const reset = () => {
    step.value = 'compose';
    stageId.value = props.stages[0] ? String(props.stages[0].id) : '';
    channel.value = props.audience.smtp_configured ? 'email' : 'whatsapp';
    selected.value = [];
    resend.value = false;
    rows.value = [];
    errors.value = {};
    loadTemplate();
};

watch(() => props.show, (open) => open && reset());
watch(channel, () => {
    const allowed = new Set(available.value.map((c) => c.id));
    selected.value = selected.value.filter((id) => allowed.has(id));
});

const loadTemplate = async () => {
    if (!stageId.value) {
        return;
    }
    try {
        const { data } = await window.axios.get(route('admin.contratacao.vagas.communications.template', props.vacancyId), {
            params: { stage_id: stageId.value },
        });
        subject.value = data.subject;
        body.value = data.body;
    } catch {
        errors.value = { body: 'Não foi possível carregar o texto-modelo.' };
    }
};

const payload = () => ({
    stage_id: Number(stageId.value),
    channel: channel.value,
    contact_ids: selected.value,
    subject: channel.value === 'email' ? subject.value : null,
    body: body.value,
    resend: resend.value,
});

const goReview = async () => {
    errors.value = {};
    loading.value = true;
    try {
        const { data } = await window.axios.post(route('admin.contratacao.vagas.communications.review', props.vacancyId), payload());
        rows.value = data.rows;
        remaining.value = data.emails_remaining_today;
        step.value = 'review';
    } catch (e) {
        errors.value = Object.fromEntries(
            Object.entries(e?.response?.data?.errors ?? { body: ['Não foi possível revisar a comunicação.'] }).map(([k, v]) => [k, v[0]]),
        );
    } finally {
        loading.value = false;
    }
};

const send = () => {
    loading.value = true;
    router.post(route('admin.contratacao.vagas.communications.store', props.vacancyId), payload(), {
        preserveScroll: true,
        onSuccess: () => emit('close'),
        onError: (e) => {
            errors.value = e;
            step.value = 'compose';
        },
        onFinish: () => (loading.value = false),
    });
};

const fieldClass =
    'mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/70';
const errorList = computed(() => Object.values(errors.value).filter(Boolean));
</script>

<template>
    <Modal :show="show" max-width="2xl" @close="emit('close')">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-talents-900">Comunicar etapa</h3>
            <p class="mt-1 text-sm text-slate-600">
                Só contatos habilitados, conferidos e que aceitam o tópico «Vagas» no canal escolhido. O texto não pode citar
                nome, e-mail ou telefone de candidato.
            </p>

            <div v-if="errorList.length" class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="alert">
                <p v-for="(msg, i) in errorList" :key="i">{{ msg }}</p>
            </div>

            <template v-if="step === 'compose'">
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="comm-stage" value="Etapa" />
                        <select id="comm-stage" v-model="stageId" :class="fieldClass" @change="loadTemplate">
                            <option v-for="s in stages" :key="s.id" :value="String(s.id)">{{ s.name }}</option>
                        </select>
                    </div>
                    <fieldset>
                        <legend class="text-sm font-medium text-slate-700">Canal</legend>
                        <div class="mt-2 flex gap-4 text-sm">
                            <label class="flex items-center gap-2">
                                <input v-model="channel" type="radio" value="email" :disabled="!audience.smtp_configured" class="text-talents-600" />
                                E-mail
                            </label>
                            <label class="flex items-center gap-2">
                                <input v-model="channel" type="radio" value="whatsapp" class="text-talents-600" />
                                WhatsApp (copiar texto)
                            </label>
                        </div>
                        <p v-if="!audience.smtp_configured" class="mt-1 text-xs text-amber-800">E-mail indisponível: o SMTP do painel não está ligado.</p>
                        <p v-else-if="channel === 'email'" class="mt-1 text-xs text-slate-500">
                            Restam {{ remaining }} de {{ audience.daily_limit }} e-mails de comunicação hoje.
                        </p>
                    </fieldset>
                </div>

                <fieldset class="mt-4">
                    <legend class="text-sm font-medium text-slate-700">Contatos</legend>
                    <ul v-if="available.length" class="mt-2 space-y-1">
                        <li v-for="c in available" :key="c.id">
                            <label class="flex items-center gap-2 text-sm text-slate-800">
                                <input v-model="selected" type="checkbox" :value="c.id" class="rounded border-slate-300 text-talents-600" />
                                <span class="font-semibold">{{ c.name }}</span>
                                <span class="text-xs text-slate-500">{{ [c.role, channel === 'email' ? c.email : c.phone].filter(Boolean).join(' · ') }}</span>
                                <span v-if="c.is_primary" class="rounded-full bg-talents-50 px-2 text-xs font-semibold text-talents-800">Principal</span>
                            </label>
                        </li>
                    </ul>
                    <p v-else class="mt-2 text-sm text-slate-500">Nenhum contato elegível para este canal.</p>
                    <p v-if="audience.ineligible_count" class="mt-2 text-xs text-slate-500">
                        {{ audience.ineligible_count }} contato(s) da empresa não podem receber comunicação de vagas.
                        <Link v-if="audience.contacts_url" :href="audience.contacts_url" class="font-semibold text-talents-700 underline">Revisar contatos</Link>
                    </p>
                </fieldset>

                <div v-if="channel === 'email'" class="mt-4">
                    <InputLabel for="comm-subject" value="Assunto" />
                    <input id="comm-subject" v-model="subject" type="text" maxlength="200" :class="fieldClass" />
                </div>
                <div class="mt-4">
                    <InputLabel for="comm-body" value="Texto" />
                    <textarea id="comm-body" v-model="body" rows="8" maxlength="5000" :class="fieldClass" />
                    <InputError class="mt-1" :message="errors.body" />
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <SecondaryButton type="button" @click="emit('close')">Cancelar</SecondaryButton>
                    <PrimaryButton type="button" :disabled="loading || !selected.length || !body.trim()" @click="goReview">Revisar</PrimaryButton>
                </div>
            </template>

            <template v-else>
                <div class="mt-4 space-y-3">
                    <p class="text-sm font-semibold text-slate-800">Revise antes de {{ channel === 'email' ? 'enviar' : 'gerar o texto' }}</p>
                    <ul class="divide-y divide-slate-100 rounded-xl border border-slate-200">
                        <li v-for="r in rows" :key="r.contact_id" class="px-3 py-2 text-sm">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span><span class="font-semibold">{{ r.name }}</span> <span class="text-slate-500">{{ r.recipient }}</span></span>
                                <span v-if="r.already_done" class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-800">
                                    Mesmo texto já registrado: {{ r.previous_status }}
                                </span>
                            </div>
                            <p v-for="(reason, i) in r.reasons" :key="i" class="text-xs text-red-700">{{ reason }}</p>
                        </li>
                    </ul>
                    <div class="rounded-xl bg-slate-50 px-3 py-2">
                        <p v-if="channel === 'email'" class="text-xs font-semibold text-slate-700">{{ subject }}</p>
                        <p class="mt-1 whitespace-pre-wrap text-sm text-slate-700">{{ body }}</p>
                    </div>
                    <label v-if="hasDuplicates" class="flex items-start gap-2 text-sm text-amber-900">
                        <input v-model="resend" type="checkbox" class="mt-0.5 rounded border-amber-300 text-talents-600" />
                        Enviar novamente: confirmo que este mesmo texto deve sair de novo (gera um novo registro).
                    </label>
                    <p v-if="overLimit" class="text-sm text-red-700">
                        Acima do limite diário: restam {{ remaining }} e-mails de comunicação hoje.
                    </p>
                    <p v-if="channel === 'whatsapp'" class="text-xs text-slate-500">
                        O sistema não envia pelo WhatsApp: ele gera o texto e você usa «Copiar» no histórico. Fica registrado como «Texto copiado».
                    </p>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <SecondaryButton type="button" @click="step = 'compose'">Voltar e editar</SecondaryButton>
                    <PrimaryButton type="button" :disabled="loading || blockers.length > 0 || overLimit" @click="send">
                        {{ channel === 'email' ? 'Enviar e-mail' : 'Gerar texto do WhatsApp' }}
                    </PrimaryButton>
                </div>
            </template>
        </div>
    </Modal>
</template>
