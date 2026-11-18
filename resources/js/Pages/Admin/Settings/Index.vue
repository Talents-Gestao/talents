<script setup>
import FormPageHeader from '@/Components/FormPageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import MoneyInput from '@/Components/MoneyInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { formatDateTime } from '@/utils/dateOnly';

const props = defineProps({
    tab: { type: String, default: 'ia' },
    aiSettings: Object,
    mailSettings: Object,
    solidesSettings: Object,
    feedbackAi: { type: Object, default: null },
});

// ── Feedbacks: provedor aprovado (trava de conformidade) e limite mensal por empresa ──
const feedbackApproveForm = useForm({
    provider: props.feedbackAi?.current_provider ?? '',
    confirmed: false,
});
const approveFeedbackProvider = () => {
    feedbackApproveForm.put(route('admin.settings.feedback-ai.approve'), {
        preserveScroll: true,
        onSuccess: () => feedbackApproveForm.reset('confirmed'),
    });
};
const revokeFeedbackProvider = () => {
    router.delete(route('admin.settings.feedback-ai.revoke'), { preserveScroll: true });
};
const centsToReais = (cents) => (cents == null ? '' : (cents / 100).toFixed(2));
const feedbackLimits = ref(Object.fromEntries((props.feedbackAi?.companies ?? []).map((c) => [c.id, centsToReais(c.limit_cents)])));
const saveFeedbackLimit = (company) => {
    const raw = feedbackLimits.value[company.id];
    const cents = raw === '' || raw == null ? null : Math.round(parseFloat(raw) * 100);
    router.put(route('admin.settings.feedback-ai.limit', company.id), { limit_cents: cents }, { preserveScroll: true });
};
const formatCents = (cents) => (Number(cents ?? 0) / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const feedbackStatusTitle = computed(() =>
    props.feedbackAi?.status?.allowed
        ? 'Liberado: ' + props.feedbackAi.status.provider_label + ' · ' + props.feedbackAi.status.model
        : 'Processamento por IA ainda não liberado',
);

const tabQuery = computed(() => (['ia', 'mail', 'solides'].includes(props.tab) ? props.tab : 'ia'));

const aiForm = useForm({
    provider: props.aiSettings.provider,
    model: props.aiSettings.model,
    api_key: '',
    openai_transcription_api_key: '',
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

const solidesForm = useForm({
    base_url: props.solidesSettings.base_url ?? 'https://app.solides.com',
    locale: props.solidesSettings.locale ?? 'pt-BR',
    api_token: '',
    is_enabled: props.solidesSettings.is_enabled ?? false,
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

const testTranscription = () => {
    router.post(
        route('admin.settings.ai.test-transcription'),
        {
            provider: aiForm.provider,
            api_key: aiForm.api_key || null,
            openai_transcription_api_key: aiForm.openai_transcription_api_key || null,
        },
        { preserveScroll: true },
    );
};

const sendTestMail = () => {
    testMailForm.post(route('admin.settings.mail.test'), { preserveScroll: true });
};

const submitSolides = () => {
    solidesForm.put(route('admin.settings.solides.update'));
};

const testSolidesConnection = () => {
    router.post(
        route('admin.settings.solides.test'),
        {
            base_url: solidesForm.base_url,
            locale: solidesForm.locale,
            api_token: solidesForm.api_token || null,
        },
        { preserveScroll: true },
    );
};

const setTab = (name) => {
    router.get(route('admin.settings.edit'), { tab: name }, { preserveState: true, replace: true });
};
</script>

<template>
    <Head title="Configurações" />

    <AdminLayout>
        <template #header>
            <FormPageHeader
                :back-href="route('admin.dashboard')"
                title="Configurações"
                subtitle="Mia (IA) para análises NR-1 e envio de e-mails (SMTP) da plataforma."
            />
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

        <Link
            v-if="$page.props.auth?.user?.can_commercial_settings"
            :href="route('admin.comercial.settings.edit')"
            class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-talents-200 bg-talents-50 px-4 py-3 text-sm transition hover:border-talents-300 hover:bg-talents-100/80"
        >
            <div>
                <p class="font-semibold text-talents-900">Comercial — valores e contratos</p>
                <p class="mt-0.5 text-talents-800/90">
                    Edite faixas de preço, valores fixos e modelos de contrato usados nas propostas.
                </p>
            </div>
            <span class="shrink-0 font-semibold text-talents-700">Abrir →</span>
        </Link>

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
            <button
                type="button"
                :class="
                    tabQuery === 'solides'
                        ? 'rounded-md bg-talents-100 px-4 py-2 text-sm font-medium text-talents-900'
                        : 'rounded-md px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100'
                "
                @click="setTab('solides')"
            >
                Sólides
            </button>
        </div>

        <form
            v-show="tabQuery === 'ia'"
            class="surface-card max-w-2xl space-y-4 p-6 text-slate-900"
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
            <div v-if="aiForm.provider === 'anthropic'">
                <InputLabel for="openai_transcription_api_key" value="Chave OpenAI para transcrição (Whisper)" />
                <TextInput
                    id="openai_transcription_api_key"
                    v-model="aiForm.openai_transcription_api_key"
                    type="password"
                    class="mt-1 block w-full"
                    autocomplete="off"
                    :placeholder="
                        aiSettings.openai_transcription_api_key_set
                            ? 'Deixe em branco para manter a chave atual'
                            : 'Obrigatória para transcrever entrevistas em áudio'
                    "
                />
                <p class="mt-1 text-xs text-gray-500">
                    A Anthropic não oferece transcrição de áudio. Use uma chave OpenAI dedicada ao Whisper.
                </p>
            </div>
            <div v-else class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                Com provedor OpenAI, a mesma chave da API acima é usada para transcrição (Whisper) nas entrevistas.
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
                <SecondaryButton type="button" :disabled="aiForm.processing" @click="testTranscription">
                    Testar transcrição
                </SecondaryButton>
            </div>
        </form>

        <section
            v-if="feedbackAi"
            v-show="tabQuery === 'ia'"
            class="surface-card mt-6 max-w-2xl space-y-4 p-6 text-slate-900"
            data-settings-feedback-ai
        >
            <h3 class="text-lg font-semibold text-gray-900">Provedor aprovado para dados de Feedbacks</h3>
            <p class="text-sm text-gray-600">
                Transcrições de feedback só vão para IA depois desta aprovação, feita por quem confirmou contrato, retenção, região dos dados e não uso para
                treinamento. Se o provedor da Mia mudar, a aprovação deixa de valer. Não é um interruptor de produto: sem ela, o servidor recusa toda
                chamada de IA de Feedbacks.
            </p>

            <div
                class="rounded-lg border px-4 py-3 text-sm"
                :class="feedbackAi.status.allowed ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-slate-300 bg-slate-100 text-slate-800'"
                role="status"
            >
                <p class="font-semibold">{{ feedbackStatusTitle }}</p>
                <p v-if="!feedbackAi.status.allowed && feedbackAi.status.message" class="mt-1">{{ feedbackAi.status.message }}</p>
                <p v-if="feedbackAi.approved_at" class="mt-1 text-xs opacity-80">
                    <template v-if="feedbackAi.approved_provider">Aprovado: {{ feedbackAi.approved_provider_label }}</template>
                    <template v-else>Aprovação revogada</template>
                    em {{ formatDateTime(feedbackAi.approved_at) }}<span v-if="feedbackAi.approved_by"> por {{ feedbackAi.approved_by }}</span>.
                </p>
            </div>

            <form class="space-y-3" @submit.prevent="approveFeedbackProvider">
                <div>
                    <InputLabel for="feedback_ai_provider" value="Provedor que vai receber os dados" />
                    <select
                        id="feedback_ai_provider"
                        v-model="feedbackApproveForm.provider"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    >
                        <option v-if="feedbackAi.current_provider" :value="feedbackAi.current_provider">
                            {{ feedbackAi.current_provider_label }} · {{ feedbackAi.current_model }} (provedor atual da Mia)
                        </option>
                        <option v-if="feedbackAi.demo_available" value="demo">Demonstração local (sem IA externa; só neste ambiente)</option>
                    </select>
                    <p v-if="feedbackApproveForm.errors.provider" class="mt-1 text-sm text-red-700">{{ feedbackApproveForm.errors.provider }}</p>
                </div>
                <label class="flex items-start gap-2 text-sm">
                    <input v-model="feedbackApproveForm.confirmed" type="checkbox" class="mt-0.5 rounded border-gray-300 text-talents-600 focus:ring-talents-500" />
                    <span>Confirmei contrato, retenção, região dos dados e não uso para treinamento deste provedor.</span>
                </label>
                <p v-if="feedbackApproveForm.errors.confirmed" class="text-sm text-red-700">{{ feedbackApproveForm.errors.confirmed }}</p>
                <div class="flex flex-wrap gap-3">
                    <PrimaryButton :disabled="feedbackApproveForm.processing || !feedbackApproveForm.confirmed || !feedbackApproveForm.provider">
                        Aprovar provedor
                    </PrimaryButton>
                    <SecondaryButton v-if="feedbackAi.approved_provider" type="button" @click="revokeFeedbackProvider">Revogar aprovação</SecondaryButton>
                </div>
            </form>

            <div class="border-t border-gray-100 pt-4">
                <h4 class="text-sm font-semibold text-gray-900">Limite mensal de IA por empresa</h4>
                <p class="mt-1 text-xs text-gray-600">
                    Custo estimado no mês (calendário de São Paulo). Em branco, vale o padrão de {{ formatCents(feedbackAi.default_limit_cents) }}; zero bloqueia.
                    Ao atingir, novos processamentos são recusados e a Talents recebe um aviso.
                </p>
                <p v-if="!feedbackAi.companies.length" class="mt-2 text-sm text-gray-500">Nenhuma empresa ativa com Feedbacks.</p>
                <ul v-else class="mt-3 divide-y divide-gray-100">
                    <li v-for="company in feedbackAi.companies" :key="company.id" class="flex flex-wrap items-center gap-3 py-2 text-sm">
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-gray-900">{{ company.name }}</p>
                            <p class="text-xs text-gray-500">Usado no mês: {{ formatCents(company.used_cents) }} de {{ formatCents(company.effective_limit_cents) }}</p>
                        </div>
                        <MoneyInput v-model="feedbackLimits[company.id]" class="w-32" placeholder="padrão" />
                        <SecondaryButton type="button" @click="saveFeedbackLimit(company)">Salvar</SecondaryButton>
                    </li>
                </ul>
            </div>
        </section>

        <form
            v-show="tabQuery === 'mail'"
            class="surface-card max-w-2xl space-y-4 p-6 text-slate-900"
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

        <div v-show="tabQuery === 'mail'" class="mt-6 max-w-2xl surface-card p-6 text-slate-900">
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

        <form
            v-show="tabQuery === 'solides'"
            class="surface-card mt-6 max-w-2xl space-y-4 p-6 text-slate-900"
            @submit.prevent="submitSolides"
        >
            <h3 class="text-lg font-semibold text-gray-900">Sólides (Integração)</h3>
            <p class="text-sm text-gray-600">Configuração global da API Sólides no admin para habilitar a conexão inicial.</p>

            <label class="flex items-center gap-2 text-sm">
                <input v-model="solidesForm.is_enabled" type="checkbox" class="rounded border-gray-300 text-talents-600 focus:ring-talents-500" />
                Integração Sólides habilitada globalmente
            </label>

            <div>
                <InputLabel for="solides_base_url" value="Base URL" />
                <TextInput id="solides_base_url" v-model="solidesForm.base_url" class="mt-1 block w-full" required />
                <p class="mt-1 text-xs text-gray-500">Ex.: https://app.solides.com</p>
            </div>

            <div>
                <InputLabel for="solides_locale" value="Locale" />
                <select
                    id="solides_locale"
                    v-model="solidesForm.locale"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                >
                    <option value="pt-BR">pt-BR</option>
                    <option value="es">es</option>
                    <option value="en">en</option>
                </select>
            </div>

            <div>
                <InputLabel for="solides_api_token" value="Token da API" />
                <TextInput
                    id="solides_api_token"
                    v-model="solidesForm.api_token"
                    type="password"
                    class="mt-1 block w-full"
                    autocomplete="off"
                    :placeholder="solidesSettings.api_token_set ? 'Deixe em branco para manter o token atual' : 'Cole o token da Sólides'"
                />
                <p v-if="solidesSettings.api_token_set" class="mt-1 text-xs text-gray-500">Um token já está salvo (criptografado).</p>
                <p v-if="solidesSettings.api_token_set && solidesSettings.api_token_readable === false" class="mt-1 text-xs text-amber-700">
                    O token salvo está ilegível com a APP_KEY atual. Salve novamente para corrigir.
                </p>
            </div>

            <div class="flex flex-wrap gap-3">
                <PrimaryButton :disabled="solidesForm.processing">Salvar Sólides</PrimaryButton>
                <SecondaryButton type="button" :disabled="solidesForm.processing" @click="testSolidesConnection">
                    Testar conexão
                </SecondaryButton>
            </div>

            <div
                v-if="solidesSettings.last_tested_at || solidesSettings.last_test_message"
                class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700"
            >
                <p>
                    <span class="font-medium">Último teste:</span>
                    {{ solidesSettings.last_tested_at ? formatDateTime(solidesSettings.last_tested_at) : 'não realizado' }}
                </p>
                <p>
                    <span class="font-medium">Status:</span>
                    {{ solidesSettings.last_test_status === 'ok' ? 'ok' : 'falha' }}
                </p>
                <p v-if="solidesSettings.last_test_message">
                    <span class="font-medium">Mensagem:</span>
                    {{ solidesSettings.last_test_message }}
                </p>
            </div>
        </form>

    </AdminLayout>
</template>
