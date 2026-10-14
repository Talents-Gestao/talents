<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import axios from 'axios';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    companyId: { type: Number, required: true },
    settings: {
        type: Object,
        default: () => ({
            rhid_base_url: '',
            rhid_email: '',
            rhid_domain: '',
            has_password: false,
        }),
    },
    embedded: { type: Boolean, default: false },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success ?? null);

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
    form.put(route('admin.ponto.companies.settings.update', props.companyId), {
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
        const { data } = await axios.post(route('admin.ponto.companies.settings.test', props.companyId));
        testOk.value = data.ok === true;
        testMessage.value = data.message || (data.ok ? 'OK' : 'Falha');
        if (data.needs_domain && data.domains?.length) {
            testMessage.value += ' — Escolha um domínio e salve nas credenciais.';
        }
    } catch (e) {
        testOk.value = false;
        const data = e?.response?.data;
        testMessage.value = data?.message || 'Não foi possível testar a conexão.';
        if (data?.needs_domain && data?.domains?.length) {
            testMessage.value += ' — Escolha um domínio e salve nas credenciais.';
        }
    } finally {
        testing.value = false;
    }
};
</script>

<template>
    <div :class="embedded ? 'p-5' : 'rounded-xl border border-slate-200 bg-white p-5'">
        <template v-if="!embedded">
            <h3 class="text-sm font-semibold text-slate-900">Credenciais da API RHID</h3>
            <p class="mt-1 text-sm text-slate-500">
                E-mail e senha da conta Control iD desta empresa. A senha não é exibida depois de gravada.
            </p>
        </template>

        <p v-if="flashSuccess" class="mt-3 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
            {{ flashSuccess }}
        </p>

        <form class="mt-4 space-y-4" @submit.prevent="submit">
            <div>
                <InputLabel for="admin_rhid_base_url" value="URL base (opcional)" />
                <TextInput
                    id="admin_rhid_base_url"
                    v-model="form.rhid_base_url"
                    type="url"
                    class="mt-1 block w-full"
                    placeholder="https://www.rhid.com.br/v2"
                    autocomplete="off"
                />
                <InputError class="mt-1" :message="form.errors.rhid_base_url" />
            </div>
            <div>
                <InputLabel for="admin_rhid_email" value="E-mail RHID" />
                <TextInput
                    id="admin_rhid_email"
                    v-model="form.rhid_email"
                    type="email"
                    class="mt-1 block w-full"
                    autocomplete="off"
                />
                <InputError class="mt-1" :message="form.errors.rhid_email" />
            </div>
            <div>
                <InputLabel for="admin_rhid_password" value="Senha" />
                <TextInput
                    id="admin_rhid_password"
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
                <InputLabel for="admin_rhid_domain" value="Domínio (multi-cliente)" />
                <TextInput
                    id="admin_rhid_domain"
                    v-model="form.rhid_domain"
                    type="text"
                    class="mt-1 block w-full"
                    placeholder="ex.: minhaempresa"
                    autocomplete="off"
                />
                <InputError class="mt-1" :message="form.errors.rhid_domain" />
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <PrimaryButton :disabled="form.processing">Salvar credenciais</PrimaryButton>
                <button
                    type="button"
                    class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50"
                    :disabled="testing"
                    @click="testConnection"
                >
                    {{ testing ? 'Testando…' : 'Testar conexão' }}
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
</template>
