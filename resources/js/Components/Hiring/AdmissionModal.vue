<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

/**
 * «Registrar admissão» numa candidatura na etapa de contratado: colaborador da empresa da vaga (escolhido ou criado;
 * mesmo e-mail reaproveita o cadastro), cargo e gestor (contato da empresa com o tópico Pós-contratação).
 * Sem data de admissão, o vínculo fica com admissão pendente.
 */
const props = defineProps({
    show: { type: Boolean, default: false },
    application: { type: Object, default: null },
});
const emit = defineEmits(['close']);

const loading = ref(false);
const options = ref(null);
const mode = ref('new');

const form = useForm({
    company_employee_id: '',
    employee_name: '',
    employee_email: '',
    employee_phone: '',
    role_title: '',
    manager_contact_id: '',
    admission_date: '',
    collaborator_email_opt_in: false,
});

watch(
    () => props.show,
    async (open) => {
        if (!open || !props.application?.admission) {
            return;
        }
        form.reset();
        form.clearErrors();
        loading.value = true;
        try {
            const { data } = await window.axios.get(props.application.admission.options_url);
            options.value = data;
            form.role_title = data.suggested_role ?? '';
            form.employee_name = data.candidate?.name ?? '';
            form.employee_email = data.candidate?.email ?? '';
            form.employee_phone = data.candidate?.phone ?? '';
            if (data.same_email_employee) {
                mode.value = 'existing';
                form.company_employee_id = String(data.same_email_employee.id);
            } else {
                mode.value = 'new';
            }
        } finally {
            loading.value = false;
        }
    },
);

const submit = () => {
    form.transform((d) => {
        const payload = {
            role_title: d.role_title || null,
            manager_contact_id: d.manager_contact_id || null,
            admission_date: d.admission_date || null,
            collaborator_email_opt_in: d.collaborator_email_opt_in,
        };
        if (mode.value === 'existing') {
            payload.company_employee_id = d.company_employee_id;
        } else {
            Object.assign(payload, { employee_name: d.employee_name, employee_email: d.employee_email || null, employee_phone: d.employee_phone || null });
        }
        return payload;
    }).post(props.application.admission.store_url, { onSuccess: () => emit('close') });
};

const fieldClass =
    'mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/70';
</script>

<template>
    <Modal :show="show" max-width="xl" @close="emit('close')">
        <form v-if="application" class="space-y-4 p-6" @submit.prevent="submit">
            <div>
                <h3 class="text-lg font-semibold text-talents-900">Registrar admissão</h3>
                <p class="text-sm text-slate-600">{{ application.candidate.name }}</p>
            </div>
            <p v-if="loading" class="text-sm text-slate-500" role="status">Carregando…</p>
            <template v-else-if="options">
                <InputError :message="form.errors.admission" />
                <fieldset>
                    <legend class="text-sm font-medium text-slate-700">Colaborador na empresa</legend>
                    <div class="mt-2 flex gap-4 text-sm">
                        <label class="flex items-center gap-2"><input v-model="mode" type="radio" value="new" class="text-talents-600" /> Novo cadastro</label>
                        <label class="flex items-center gap-2"><input v-model="mode" type="radio" value="existing" class="text-talents-600" /> Já cadastrado</label>
                    </div>
                    <p v-if="options.same_email_employee" class="mt-2 rounded-lg bg-sky-50 px-3 py-2 text-xs text-sky-900">
                        Já existe um colaborador com este e-mail na empresa ({{ options.same_email_employee.name }}); ele será reaproveitado.
                    </p>
                </fieldset>
                <div v-if="mode === 'existing'">
                    <InputLabel for="adm-employee" value="Colaborador" />
                    <select id="adm-employee" v-model="form.company_employee_id" required :class="fieldClass">
                        <option value="" disabled>Selecione</option>
                        <option v-if="options.same_email_employee" :value="String(options.same_email_employee.id)">
                            {{ options.same_email_employee.name }} · {{ options.same_email_employee.email }}
                        </option>
                        <option v-for="e in options.employees.filter((x) => x.id !== options.same_email_employee?.id)" :key="e.id" :value="String(e.id)">
                            {{ e.name }}{{ e.email ? ` · ${e.email}` : '' }}
                        </option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.company_employee_id" />
                </div>
                <div v-else class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <InputLabel for="adm-name" value="Nome" />
                        <input id="adm-name" v-model="form.employee_name" type="text" maxlength="255" required :class="fieldClass" />
                        <InputError class="mt-1" :message="form.errors.employee_name" />
                    </div>
                    <div>
                        <InputLabel for="adm-email" value="E-mail" />
                        <input id="adm-email" v-model="form.employee_email" type="email" :class="fieldClass" />
                        <InputError class="mt-1" :message="form.errors.employee_email" />
                    </div>
                    <div>
                        <InputLabel for="adm-phone" value="Telefone" />
                        <input id="adm-phone" v-model="form.employee_phone" type="tel" :class="fieldClass" />
                    </div>
                </div>

                <div class="rounded-xl border border-talents-200 bg-talents-50/40 p-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="adm-role" value="Cargo" />
                            <input id="adm-role" v-model="form.role_title" type="text" maxlength="255" :class="fieldClass" />
                        </div>
                        <div>
                            <InputLabel for="adm-manager" value="Gestor" />
                            <select id="adm-manager" v-model="form.manager_contact_id" :class="fieldClass">
                                <option value="">Definir depois</option>
                                <option v-for="m in options.managers" :key="m.id" :value="String(m.id)">
                                    {{ m.name }}{{ m.role ? ` · ${m.role}` : '' }}{{ m.reviewed ? '' : ' (não conferido)' }}
                                </option>
                            </select>
                            <p v-if="!options.managers.length" class="mt-1 text-xs text-amber-800">
                                Nenhum contato da empresa com o tópico «Pós-contratação». Cadastre-o na aba Contatos da empresa.
                            </p>
                            <InputError class="mt-1" :message="form.errors.manager_contact_id" />
                        </div>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="adm-date" value="Data de admissão (se já confirmada)" />
                        <input id="adm-date" v-model="form.admission_date" type="date" :class="fieldClass" />
                        <p class="mt-1 text-xs text-slate-500">Sem data, o vínculo fica com admissão pendente. Data antiga gera marcos vencidos para revisão; nada é enviado sozinho.</p>
                        <InputError class="mt-1" :message="form.errors.admission_date" />
                    </div>
                    <label class="flex items-start gap-2 pt-6 text-sm">
                        <input v-model="form.collaborator_email_opt_in" type="checkbox" class="mt-0.5 rounded border-slate-300 text-talents-600" />
                        O colaborador autoriza receber os convites de acompanhamento
                    </label>
                </div>
            </template>

            <div class="flex justify-end gap-2">
                <SecondaryButton type="button" @click="emit('close')">Cancelar</SecondaryButton>
                <PrimaryButton :disabled="form.processing || loading">Registrar admissão</PrimaryButton>
            </div>
        </form>
    </Modal>
</template>
