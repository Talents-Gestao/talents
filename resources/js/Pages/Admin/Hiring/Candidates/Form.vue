<script setup>
import FormPageHeader from '@/Components/FormPageHeader.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps({
    candidate: { type: Object, default: null },
    contact_preferences: { type: Array, required: true },
});

const isEdit = computed(() => props.candidate !== null);

const form = useForm({
    name: props.candidate?.name ?? '',
    email: props.candidate?.email ?? '',
    phone: props.candidate?.phone ?? '',
    email_reviewed: props.candidate?.email_reviewed ?? false,
    phone_reviewed: props.candidate?.phone_reviewed ?? false,
    email_opt_in: props.candidate?.email_opt_in ?? false,
    contact_preference: props.candidate?.contact_preference ?? '',
    source: props.candidate?.source ?? '',
    current_role: props.candidate?.current_role ?? '',
    current_company: props.candidate?.current_company ?? '',
    notes: props.candidate?.notes ?? '',
});

// Contato alterado perde a conferência e a autorização (o servidor aplica a mesma regra).
const originalEmail = (props.candidate?.email ?? '').trim().toLowerCase();
const originalPhone = (props.candidate?.phone ?? '').trim();
watch(
    () => form.email,
    (value) => {
        if (isEdit.value && (value ?? '').trim().toLowerCase() !== originalEmail) {
            form.email_reviewed = false;
            form.email_opt_in = false;
        }
    },
);
watch(
    () => form.phone,
    (value) => {
        if (isEdit.value && (value ?? '').trim() !== originalPhone) {
            form.phone_reviewed = false;
        }
    },
);

const existingUrl = computed(() => form.errors.existing_candidate_url ?? null);

const submit = () => {
    form.transform((data) => ({ ...data, contact_preference: data.contact_preference || null }));
    if (isEdit.value) {
        form.patch(route('admin.contratacao.candidatos.update', props.candidate.id), { preserveScroll: true });
    } else {
        form.post(route('admin.contratacao.candidatos.store'), { preserveScroll: true });
    }
};

const fieldClass =
    'mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/70';
const checkClass = 'h-4 w-4 rounded border-slate-300 text-talents-600 focus:ring-talents-500';
</script>

<template>
    <Head :title="isEdit ? `Editar ${candidate.name}` : 'Novo candidato'" />

    <AdminLayout>
        <template #header>
            <FormPageHeader
                :back-href="isEdit ? route('admin.contratacao.candidatos.show', candidate.id) : route('admin.contratacao.candidatos.index')"
                :back-label="isEdit ? 'Ficha' : 'Candidatos'"
                :title="isEdit ? 'Editar candidato' : 'Novo candidato'"
                :subtitle="isEdit ? candidate.name : 'Se o e-mail já estiver cadastrado, você será levado ao cadastro existente em vez de duplicar.'"
            />
        </template>

        <form class="surface-card space-y-6 p-5 sm:p-6" @submit.prevent="submit">
            <div
                v-if="existingUrl"
                class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
                role="alert"
            >
                <p>{{ form.errors.email || form.errors.solides_id }}</p>
                <Link :href="existingUrl" class="mt-1 inline-block font-semibold text-amber-900 underline">
                    Abrir o cadastro existente
                </Link>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <InputLabel for="name" value="Nome" />
                    <TextInput id="name" v-model="form.name" :class="fieldClass" maxlength="255" required autocomplete="off" />
                    <InputError class="mt-1" :message="form.errors.name" />
                </div>

                <div>
                    <InputLabel for="email" value="E-mail" />
                    <TextInput id="email" v-model="form.email" type="email" :class="fieldClass" maxlength="255" autocomplete="off" />
                    <InputError v-if="!existingUrl" class="mt-1" :message="form.errors.email" />
                    <div class="mt-2 space-y-1.5">
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input v-model="form.email_reviewed" type="checkbox" :class="checkClass" :disabled="!form.email" />
                            E-mail conferido
                        </label>
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input v-model="form.email_opt_in" type="checkbox" :class="checkClass" :disabled="!form.email" />
                            Autoriza convites por e-mail
                        </label>
                    </div>
                </div>

                <div>
                    <InputLabel for="phone" value="Telefone (internacional, com +)" />
                    <TextInput
                        id="phone"
                        v-model="form.phone"
                        type="tel"
                        :class="fieldClass"
                        maxlength="40"
                        placeholder="+55 11 91234-5678"
                        autocomplete="off"
                    />
                    <InputError class="mt-1" :message="form.errors.phone" />
                    <label class="mt-2 flex items-center gap-2 text-sm text-slate-700">
                        <input v-model="form.phone_reviewed" type="checkbox" :class="checkClass" :disabled="!form.phone" />
                        Telefone conferido
                    </label>
                </div>

                <p v-if="isEdit" class="sm:col-span-2 -mt-2 text-xs text-slate-500">
                    Mudar o e-mail desmarca a conferência e a autorização do e-mail; mudar o telefone desmarca a conferência do
                    telefone.
                </p>

                <div>
                    <InputLabel for="contact_preference" value="Preferência de contato" />
                    <select id="contact_preference" v-model="form.contact_preference" :class="fieldClass">
                        <option value="">Não informada</option>
                        <option v-for="p in contact_preferences" :key="p.value" :value="p.value">{{ p.label }}</option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.contact_preference" />
                </div>

                <div>
                    <InputLabel for="source" value="Origem" />
                    <TextInput id="source" v-model="form.source" :class="fieldClass" maxlength="120" placeholder="Ex.: indicação, LinkedIn, Sólides" />
                    <InputError class="mt-1" :message="form.errors.source" />
                </div>

                <div>
                    <InputLabel for="current_role" value="Cargo atual" />
                    <TextInput id="current_role" v-model="form.current_role" :class="fieldClass" maxlength="255" />
                    <InputError class="mt-1" :message="form.errors.current_role" />
                </div>

                <div>
                    <InputLabel for="current_company" value="Empresa atual" />
                    <TextInput id="current_company" v-model="form.current_company" :class="fieldClass" maxlength="255" />
                    <InputError class="mt-1" :message="form.errors.current_company" />
                </div>

                <div class="sm:col-span-2">
                    <InputLabel for="notes" value="Observações" />
                    <textarea id="notes" v-model="form.notes" rows="4" maxlength="5000" :class="fieldClass" />
                    <InputError class="mt-1" :message="form.errors.notes" />
                </div>
            </div>

            <div class="flex justify-end">
                <PrimaryButton :disabled="form.processing">{{ isEdit ? 'Salvar alterações' : 'Cadastrar candidato' }}</PrimaryButton>
            </div>
        </form>
    </AdminLayout>
</template>
