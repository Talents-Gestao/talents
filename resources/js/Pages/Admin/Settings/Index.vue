<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    tab: { type: String, default: 'ia' },
    aiSettings: Object,
    mailSettings: Object,
});

const tabQuery = computed(() => (props.tab === 'mail' ? 'mail' : 'ia'));

const aiForm = useForm({
    provider: props.aiSettings.provider,
    model: props.aiSettings.model,
    api_key: '',
    is_enabled: props.aiSettings.is_enabled,
    max_tokens: props.aiSettings.max_tokens,
    temperature: props.aiSettings.temperature,
});

const mailForm = useForm({
    host: props.mailSettings.host ?? '',
    port: props.mailSettings.port ?? 587,
    encryption: props.mailSettings.encryption ?? '',
    username: props.mailSettings.username ?? '',
    password: '',
    from_address: props.mailSettings.from_address ?? '',
    from_name: props.mailSettings.from_name ?? '',
    is_enabled: props.mailSettings.is_enabled,
});

const testMailForm = useForm({
    test_to: '',
});

const submitAi = () => {
    aiForm.put(route('admin.settings.ai.update'));
};

const submitMail = () => {
    mailForm.put(route('admin.settings.mail.update'));
};

const testConnection = () => {
    router.post(
        route('admin.settings.ai.test'),
        {
            provider: aiForm.provider,
            model: aiForm.model,
            api_key: aiForm.api_key || null,
            max_tokens: aiForm.max_tokens,
            temperature: aiForm.temperature,
        },
        { preserveScroll: true },
    );
};

const sendTestMail = () => {
    testMailForm.post(route('admin.settings.mail.test'), { preserveScroll: true });
};

const setTab = (name) => {
    router.get(route('admin.settings.edit'), { tab: name }, { preserveState: true, replace: true });
};
</script>

<template>
    <Head title="Configurações" />

    <AdminLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-900">Configurações</h2>
            <p class="mt-1 text-sm text-gray-600">Mia (IA) para análises NR-1 e envio de e-mails (SMTP) da plataforma.</p>
        </template>

        <div
            v-if="$page.props.flash?.success"
            class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
        >
            {{ $page.props.flash.success }}
        </div>
        <div
            v-if="$page.props.flash?.error"
            class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900"
        >
            {{ $page.props.flash.error }}
        </div>

        <div
            v-if="
                (aiSettings.api_key_set && aiSettings.api_key_readable === false) ||
                (mailSettings.password_set && mailSettings.password_readable === false)
            "
            class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950"
        >
            <p class="font-medium">Credenciais criptografadas ilegíveis</p>
            <p class="mt-1">
                A chave da API (Mia) ou a senha SMTP foi salva com uma <code class="rounded bg-amber-100/80 px-1 text-xs">APP_KEY</code>
                diferente da atual e não pode ser lida. Salve novamente a chave ou a senha em cada aba para corrigir.
            </p>
        </div>

        <div class="mb-6 flex flex-wrap gap-2 border-b border-gray-200 pb-4">
            <button
                type="button"
                :class="
                    tabQuery === 'ia'
                        ? 'rounded-md bg-talents-100 px-4 py-2 text-sm font-medium text-talents-900'
                        : 'rounded-md px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100'
                "
                @click="setTab('ia')"
            >
                Mia (IA)
            </button>
            <button
                type="button"
                :class="
                    tabQuery === 'mail'
                        ? 'rounded-md bg-talents-100 px-4 py-2 text-sm font-medium text-talents-900'
                        : 'rounded-md px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100'
                "
                @click="setTab('mail')"
            >
                E-mail (SMTP)
            </button>
        </div>

        <form
            v-show="tabQuery === 'ia'"
            class="max-w-2xl space-y-4 rounded-xl border border-gray-200 bg-white p-6 text-gray-900 shadow-sm"
            @submit.prevent="submitAi"
        >
            <h3 class="text-lg font-semibold text-gray-900">Mia (IA)</h3>
            <p class="text-sm text-gray-600">Chave e modelo centralizados. Empresas clientes usam quando a IA estiver habilitada.</p>
            <div>
                <InputLabel for="provider" value="Provedor" />
                <select
                    id="provider"
                    v-model="aiForm.provider"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                >
                    <option value="openai">OpenAI</option>
                    <option value="anthropic">Anthropic</option>
                </select>
            </div>
            <div>
                <InputLabel for="model" value="Modelo" />
                <TextInput id="model" v-model="aiForm.model" class="mt-1 block w-full" required placeholder="ex.: gpt-4o-mini ou claude-3-5-haiku-20241022" />
            </div>
            <div>
                <InputLabel for="api_key" value="Chave da API" />
                <TextInput
                    id="api_key"
                    v-model="aiForm.api_key"
                    type="password"
                    class="mt-1 block w-full"
                    autocomplete="off"
                    :placeholder="aiSettings.api_key_set ? 'Deixe em branco para manter a chave atual' : 'Cole a chave secreta'"
                />
                <p v-if="aiSettings.api_key_set" class="mt-1 text-xs text-gray-500">Uma chave já está salva (criptografada).</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel for="max_tokens" value="Máx. tokens (resposta)" />
                    <TextInput id="max_tokens" v-model="aiForm.max_tokens" type="number" class="mt-1 block w-full" required min="100" max="16000" />
                </div>
                <div>
                    <InputLabel for="temperature" value="Temperatura (0–2)" />
                    <TextInput
                        id="temperature"
                        v-model="aiForm.temperature"
                        type="number"
                        step="0.01"
                        min="0"
                        max="2"
                        class="mt-1 block w-full"
                        required
                    />
                </div>
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input v-model="aiForm.is_enabled" type="checkbox" class="rounded border-gray-300 text-talents-600 focus:ring-talents-500" />
                IA habilitada globalmente para clientes
            </label>
            <div class="flex flex-wrap gap-3">
                <PrimaryButton :disabled="aiForm.processing">Salvar Mia</PrimaryButton>
                <SecondaryButton type="button" :disabled="aiForm.processing" @click="testConnection">Testar conexão</SecondaryButton>
            </div>
        </form>

        <form
            v-show="tabQuery === 'mail'"
            class="max-w-2xl space-y-4 rounded-xl border border-gray-200 bg-white p-6 text-gray-900 shadow-sm"
            @submit.prevent="submitMail"
        >
            <h3 class="text-lg font-semibold text-gray-900">E-mail (SMTP)</h3>
            <p class="text-sm text-gray-600">
                Quando habilitado, estes dados substituem o <code class="rounded bg-gray-100 px-1 text-xs">MAIL_*</code> do
                <code class="rounded bg-gray-100 px-1 text-xs">.env</code> após o boot da aplicação (útil no Coolify).
            </p>
            <label class="flex items-center gap-2 text-sm">
                <input v-model="mailForm.is_enabled" type="checkbox" class="rounded border-gray-300 text-talents-600 focus:ring-talents-500" />
                Usar SMTP configurado aqui
            </label>
            <div>
                <InputLabel for="mail_host" value="Host" />
                <TextInput id="mail_host" v-model="mailForm.host" class="mt-1 block w-full" placeholder="smtp.exemplo.com" />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel for="mail_port" value="Porta" />
                    <TextInput id="mail_port" v-model="mailForm.port" type="number" class="mt-1 block w-full" />
                </div>
                <div>
                    <InputLabel for="mail_encryption" value="Criptografia" />
                    <select
                        id="mail_encryption"
                        v-model="mailForm.encryption"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    >
                        <option value="">Nenhuma</option>
                        <option value="tls">TLS</option>
                        <option value="ssl">SSL</option>
                    </select>
                </div>
            </div>
            <div>
                <InputLabel for="mail_username" value="Usuário" />
                <TextInput id="mail_username" v-model="mailForm.username" class="mt-1 block w-full" autocomplete="off" />
            </div>
            <div>
                <InputLabel for="mail_password" value="Senha" />
                <TextInput
                    id="mail_password"
                    v-model="mailForm.password"
                    type="password"
                    class="mt-1 block w-full"
                    autocomplete="new-password"
                    :placeholder="mailSettings.password_set ? 'Deixe em branco para manter' : 'Senha SMTP'"
                />
            </div>
            <div>
                <InputLabel for="from_address" value="E-mail remetente (from)" />
                <TextInput id="from_address" v-model="mailForm.from_address" type="email" class="mt-1 block w-full" />
            </div>
            <div>
                <InputLabel for="from_name" value="Nome remetente" />
                <TextInput id="from_name" v-model="mailForm.from_name" class="mt-1 block w-full" />
            </div>
            <div class="flex flex-wrap gap-3">
                <PrimaryButton :disabled="mailForm.processing">Salvar SMTP</PrimaryButton>
            </div>
        </form>

        <div v-show="tabQuery === 'mail'" class="mt-6 max-w-2xl rounded-xl border border-gray-200 bg-white p-6 text-gray-900 shadow-sm">
            <h4 class="text-sm font-semibold text-gray-900">Testar envio</h4>
            <p class="mt-1 text-sm text-gray-600">Envia um e-mail simples para verificar o SMTP.</p>
            <form class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end" @submit.prevent="sendTestMail">
                <div class="flex-1">
                    <InputLabel for="test_to" value="Enviar para" />
                    <TextInput id="test_to" v-model="testMailForm.test_to" type="email" class="mt-1 block w-full" required placeholder="seu@email.com" />
                </div>
                <SecondaryButton type="submit" :disabled="testMailForm.processing">Enviar e-mail de teste</SecondaryButton>
            </form>
        </div>

    </AdminLayout>
</template>
