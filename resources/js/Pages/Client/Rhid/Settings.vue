<script setup>
import FormPageHeader from '@/Components/FormPageHeader.vue';
import PunchScheduleSettingsForm from '@/Components/Rhid/PunchScheduleSettingsForm.vue';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { ref } from 'vue';

const props = defineProps({
    configured: { type: Boolean, required: true },
    settings: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    rhid_base_url: props.settings.rhid_base_url || '',
    rhid_email: props.settings.rhid_email || '',
    rhid_password: '',
    rhid_domain: props.settings.rhid_domain || '',
});

const testing = ref(false);
const testMessage = ref(null);
const testOk = ref(null);

const submit = () => {
    form.put(route('client.rhid.settings.update'), {
        preserveScroll: true,
        onSuccess: () => {
            form.rhid_password = '';
        },
    });
};

const testConnection = async () => {
    testing.value = true;
    testMessage.value = null;
    testOk.value = null;
    try {
        const { data } = await axios.post(route('client.rhid.settings.test'));
        testOk.value = data.ok === true;
        testMessage.value = data.message || (data.ok ? 'OK' : 'Falha');
        if (data.needs_domain && data.domains?.length) {
            testMessage.value += ' — Escolha um domínio e salve nas configurações.';
        }
    } catch (e) {
        testOk.value = false;
        testMessage.value = e.response?.data?.message || e.message || 'Erro ao testar';
    } finally {
        testing.value = false;
    }
};
</script>

<template>
    <Head title="RHID — Configuração" />

    <ClientLayout>
        <template #header>
            <FormPageHeader
                :back-href="route('client.rhid.compliance.index')"
                back-label="Compliance RHID"
                title="Integração RHID (Control iD)"
            />
        </template>

        <div class="mx-auto max-w-2xl space-y-8">
            <div class="rounded-xl border border-talents-200 bg-white p-6 shadow-sm">
                <p class="text-sm text-slate-600">
                    Credenciais da API
                    <code class="rounded bg-slate-100 px-1 text-xs">https://www.rhid.com.br/v2</code>
                    . O token é renovado automaticamente (cache ~3,5h).
                </p>

                <form class="mt-6 space-y-4" @submit.prevent="submit">
                    <div>
                        <InputLabel for="rhid_base_url" value="URL base (opcional)" />
                        <TextInput
                            id="rhid_base_url"
                            v-model="form.rhid_base_url"
                            type="url"
                            class="mt-1 block w-full"
                            placeholder="https://www.rhid.com.br/v2"
                        />
                        <InputError class="mt-1" :message="form.errors.rhid_base_url" />
                    </div>
                    <div>
                        <InputLabel for="rhid_email" value="E-mail RHID" />
                        <TextInput
                            id="rhid_email"
                            v-model="form.rhid_email"
                            type="email"
                            class="mt-1 block w-full"
                            autocomplete="off"
                        />
                        <InputError class="mt-1" :message="form.errors.rhid_email" />
                    </div>
                    <div>
                        <InputLabel for="rhid_password" value="Senha" />
                        <TextInput
                            id="rhid_password"
                            v-model="form.rhid_password"
                            type="password"
                            class="mt-1 block w-full"
                            autocomplete="new-password"
                            placeholder="Deixe em branco para manter a atual"
                        />
                        <p v-if="settings.has_password" class="mt-1 text-xs text-slate-500">Senha já cadastrada.</p>
                        <InputError class="mt-1" :message="form.errors.rhid_password" />
                    </div>
                    <div>
                        <InputLabel for="rhid_domain" value="Domínio (multi-cliente)" />
                        <TextInput
                            id="rhid_domain"
                            v-model="form.rhid_domain"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="ex.: minhaempresa"
                        />
                        <InputError class="mt-1" :message="form.errors.rhid_domain" />
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <PrimaryButton :disabled="form.processing">Salvar</PrimaryButton>
                        <button
                            type="button"
                            class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50"
                            :disabled="testing"
                            @click="testConnection"
                        >
                            {{ testing ? 'Testando...' : 'Testar conexão' }}
                        </button>
                    </div>
                    <p
                        v-if="testMessage"
                        class="text-sm"
                        :class="testOk ? 'text-emerald-700' : 'text-red-700'"
                    >
                        {{ testMessage }}
                    </p>
                </form>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <PunchScheduleSettingsForm :configured="configured" />
            </div>
        </div>
    </ClientLayout>
</template>
