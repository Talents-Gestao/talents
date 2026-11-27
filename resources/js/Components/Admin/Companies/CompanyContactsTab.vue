<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { confirmDialog } from '@/composables/useConfirmDialog';
import { formatDateTime } from '@/utils/dateOnly';
import { contactLosesReview, emptyContact } from '@/utils/hiringCommunication';
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

/**
 * Aba «Contatos» do detalhe da empresa. Ver: companies:view; criar, editar, conferir e arquivar: companies:edit.
 * A coluna «Pode receber» usa a mesma regra dos envios (servidor: CompanyContactEligibility).
 */
const props = defineProps({
    companyId: { type: Number, required: true },
    contacts: { type: Array, default: () => [] },
    options: { type: Object, default: () => ({ topics: [], responsibles: [] }) },
    canEdit: { type: Boolean, default: false },
});

const showArchived = ref(false);
const visible = computed(() => props.contacts.filter((c) => showArchived.value || !c.archived));
const archivedCount = computed(() => props.contacts.filter((c) => c.archived).length);

const showForm = ref(false);
const editing = ref(null);
const form = useForm(emptyContact());

const openCreate = () => {
    editing.value = null;
    form.defaults(emptyContact());
    form.reset();
    form.clearErrors();
    showForm.value = true;
};

const openEdit = (contact) => {
    editing.value = contact;
    const values = { ...emptyContact() };
    for (const key of Object.keys(values)) {
        values[key] = contact[key] ?? values[key];
    }
    values.responsible_user_id = contact.responsible_user_id ? String(contact.responsible_user_id) : '';
    form.defaults(values);
    form.reset();
    form.clearErrors();
    showForm.value = true;
};

const losesReview = computed(() => editing.value?.reviewed_at && contactLosesReview(editing.value, form));

const submit = () => {
    form.transform((data) => ({ ...data, responsible_user_id: data.responsible_user_id || null }));
    const options = { preserveScroll: true, onSuccess: () => (showForm.value = false) };
    if (editing.value) {
        form.patch(route('admin.companies.contacts.update', [props.companyId, editing.value.id]), options);
    } else {
        form.post(route('admin.companies.contacts.store', props.companyId), options);
    }
};

const review = (contact) =>
    router.post(route('admin.companies.contacts.review', [props.companyId, contact.id]), {}, { preserveScroll: true });

const archive = async (contact) => {
    if (await confirmDialog({ title: `Arquivar o contato ${contact.name}?`, message: 'Ele deixa de receber comunicações. O histórico fica preservado.', confirmLabel: 'Arquivar' })) {
        router.post(route('admin.companies.contacts.archive', [props.companyId, contact.id]), {}, { preserveScroll: true });
    }
};

const restore = (contact) =>
    router.post(route('admin.companies.contacts.restore', [props.companyId, contact.id]), {}, { preserveScroll: true });

const channelLabel = (value) => ({ email: 'e-mail', whatsapp: 'WhatsApp' })[value] ?? value;

const fieldClass =
    'mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/70';
const checkClass = 'h-4 w-4 rounded border-slate-300 text-talents-600 focus:ring-talents-500';
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-slate-600">
                Quem a Talents pode procurar na empresa. Só recebe comunicação o contato habilitado, conferido, com o tópico e o canal certos.
            </p>
            <div class="flex items-center gap-3">
                <label v-if="archivedCount" class="flex items-center gap-2 text-xs text-slate-600">
                    <input v-model="showArchived" type="checkbox" :class="checkClass" />
                    Mostrar arquivados ({{ archivedCount }})
                </label>
                <PrimaryButton v-if="canEdit" type="button" @click="openCreate">Novo contato</PrimaryButton>
            </div>
        </div>

        <ul v-if="visible.length" class="space-y-3">
            <li v-for="c in visible" :key="c.id" class="rounded-2xl border bg-white p-4" :class="c.archived ? 'border-slate-200 opacity-70' : 'border-slate-200'">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-900">
                            {{ c.name }}
                            <span v-if="c.role" class="font-normal text-slate-500">· {{ c.role }}</span>
                        </p>
                        <p class="text-xs text-slate-500">{{ [c.email, c.phone].filter(Boolean).join(' · ') || 'Sem e-mail ou telefone' }}</p>
                        <div class="mt-1 flex flex-wrap gap-1 text-xs font-semibold">
                            <span v-if="c.is_primary" class="rounded-full bg-talents-50 px-2 py-0.5 text-talents-800">Principal</span>
                            <span v-if="c.archived" class="rounded-full bg-slate-200 px-2 py-0.5 text-slate-700">Arquivado</span>
                            <span v-else-if="!c.is_enabled" class="rounded-full bg-slate-100 px-2 py-0.5 text-slate-700">Desabilitado</span>
                            <span
                                class="rounded-full px-2 py-0.5"
                                :class="c.reviewed_at ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-800'"
                            >
                                {{ c.reviewed_at ? `Conferido em ${formatDateTime(c.reviewed_at)}` : 'Não conferido' }}
                            </span>
                        </div>
                        <p v-if="c.responsible_name" class="mt-1 text-xs text-slate-500">Responsável Talents: {{ c.responsible_name }}</p>
                    </div>
                    <div v-if="canEdit" class="flex flex-wrap gap-2">
                        <template v-if="!c.archived">
                            <SecondaryButton v-if="!c.reviewed_at" type="button" @click="review(c)">Conferir</SecondaryButton>
                            <SecondaryButton type="button" @click="openEdit(c)">Editar</SecondaryButton>
                            <button type="button" class="text-sm font-medium text-red-600 hover:underline" @click="archive(c)">Arquivar</button>
                        </template>
                        <SecondaryButton v-else type="button" @click="restore(c)">Restaurar</SecondaryButton>
                    </div>
                </div>

                <div class="mt-3 grid gap-2 sm:grid-cols-3">
                    <div v-for="e in c.eligibility" :key="e.topic" class="rounded-xl bg-slate-50 px-3 py-2 text-xs">
                        <p class="font-semibold text-slate-700">{{ e.label }}</p>
                        <p v-if="e.channels.length" class="text-emerald-700">Pode receber por {{ e.channels.map(channelLabel).join(' e ') }}</p>
                        <p v-else class="text-slate-500">Não recebe</p>
                    </div>
                </div>
                <p v-if="c.notes" class="mt-2 whitespace-pre-wrap text-xs text-slate-600">{{ c.notes }}</p>

                <details v-if="c.events.length" class="mt-2">
                    <summary class="cursor-pointer text-xs font-semibold text-talents-700">Histórico ({{ c.events.length }})</summary>
                    <ol class="mt-1 space-y-1">
                        <li v-for="ev in c.events" :key="ev.id" class="text-xs text-slate-600">
                            <span class="font-semibold text-slate-800">{{ ev.label }}</span>
                            <template v-if="ev.fields.length"> ({{ ev.fields.join(', ') }})</template>
                            <template v-if="ev.user_name"> · {{ ev.user_name }}</template>
                            · {{ formatDateTime(ev.created_at) }}
                        </li>
                    </ol>
                </details>
            </li>
        </ul>
        <p v-else class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/80 px-4 py-8 text-center text-sm text-slate-500">
            Nenhum contato cadastrado.
        </p>

        <Modal :show="showForm" max-width="2xl" @close="showForm = false">
            <form class="space-y-4 p-6" @submit.prevent="submit">
                <h3 class="text-lg font-semibold text-talents-900">{{ editing ? 'Editar contato' : 'Novo contato' }}</h3>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="contact-name" value="Nome" />
                        <input id="contact-name" v-model="form.name" type="text" maxlength="255" required :class="fieldClass" />
                        <InputError class="mt-1" :message="form.errors.name" />
                    </div>
                    <div>
                        <InputLabel for="contact-role" value="Função" />
                        <input id="contact-role" v-model="form.role" type="text" maxlength="120" :class="fieldClass" placeholder="Ex.: RH, diretoria" />
                        <InputError class="mt-1" :message="form.errors.role" />
                    </div>
                    <div>
                        <InputLabel for="contact-email" value="E-mail" />
                        <input id="contact-email" v-model="form.email" type="email" maxlength="255" :class="fieldClass" />
                        <InputError class="mt-1" :message="form.errors.email" />
                    </div>
                    <div>
                        <InputLabel for="contact-phone" value="Telefone (internacional, com +)" />
                        <input id="contact-phone" v-model="form.phone" type="tel" maxlength="40" :class="fieldClass" placeholder="+55 11 91234-5678" />
                        <InputError class="mt-1" :message="form.errors.phone" />
                    </div>
                </div>
                <p v-if="losesReview" class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                    Mudar o e-mail ou o telefone desfaz a conferência: o contato precisará ser conferido de novo.
                </p>

                <fieldset>
                    <legend class="text-sm font-medium text-slate-700">Canais autorizados</legend>
                    <div class="mt-2 flex flex-wrap gap-4 text-sm">
                        <label class="flex items-center gap-2"><input v-model="form.channel_email" type="checkbox" :class="checkClass" /> E-mail</label>
                        <label class="flex items-center gap-2"><input v-model="form.channel_whatsapp" type="checkbox" :class="checkClass" /> WhatsApp</label>
                    </div>
                </fieldset>
                <fieldset>
                    <legend class="text-sm font-medium text-slate-700">Tópicos que aceita receber</legend>
                    <div class="mt-2 flex flex-wrap gap-4 text-sm">
                        <label v-for="t in options.topics" :key="t.value" class="flex items-center gap-2">
                            <input v-model="form.topics" type="checkbox" :value="t.value" :class="checkClass" /> {{ t.label }}
                        </label>
                    </div>
                    <InputError class="mt-1" :message="form.errors.topics" />
                </fieldset>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="contact-responsible" value="Responsável Talents" />
                        <select id="contact-responsible" v-model="form.responsible_user_id" :class="fieldClass">
                            <option value="">Nenhum</option>
                            <option v-for="u in options.responsibles" :key="u.id" :value="String(u.id)">{{ u.name }}</option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.responsible_user_id" />
                    </div>
                    <div class="flex flex-col justify-end gap-2 text-sm">
                        <label class="flex items-center gap-2"><input v-model="form.is_enabled" type="checkbox" :class="checkClass" /> Habilitado</label>
                        <label class="flex items-center gap-2"><input v-model="form.is_primary" type="checkbox" :class="checkClass" /> Contato principal (só um por empresa)</label>
                        <InputError :message="form.errors.is_primary" />
                    </div>
                </div>
                <div>
                    <InputLabel for="contact-notes" value="Observações" />
                    <textarea id="contact-notes" v-model="form.notes" rows="3" maxlength="2000" :class="fieldClass" />
                    <InputError class="mt-1" :message="form.errors.notes" />
                </div>

                <div class="flex justify-end gap-2">
                    <SecondaryButton type="button" @click="showForm = false">Cancelar</SecondaryButton>
                    <PrimaryButton :disabled="form.processing">{{ editing ? 'Salvar contato' : 'Criar contato' }}</PrimaryButton>
                </div>
            </form>
        </Modal>
    </div>
</template>
