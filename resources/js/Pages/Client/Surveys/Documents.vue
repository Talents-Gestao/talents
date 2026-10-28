<script setup>
import ReviewHistory from '@/Components/Nr1Documents/ReviewHistory.vue';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    survey: { type: Object, required: true },
    round: { type: Object, default: null },
    canRecord: { type: Boolean, default: false },
    approvalMeaning: { type: String, default: '' },
});

const pgrForm = useForm({ evidence: null, note: '' });

const acknowledge = () => {
    if (!window.confirm('Registrar a ciência desta publicação? O registro vale para os dois documentos e não pode ser desfeito.')) {
        return;
    }
    router.post(route('client.surveys.documents.acknowledge', props.survey.id), {}, { preserveScroll: true });
};

const confirmPgr = () => {
    pgrForm.post(route('client.surveys.documents.pgr-integration', props.survey.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => pgrForm.reset(),
    });
};
</script>

<template>
    <Head :title="`Documentos - ${survey.title}`" />

    <ClientLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-talents-700">Central de documentos</p>
                    <h2 class="text-xl font-semibold leading-tight text-talents-900">Documentos oficiais</h2>
                    <p class="text-sm text-gray-600">{{ survey.title }}</p>
                </div>
                <Link :href="route('client.surveys.psychosocial-dashboard', survey.id)" class="text-sm text-talents-700 hover:underline">
                    Voltar ao painel
                </Link>
            </div>
        </template>

        <div class="space-y-6">
            <div v-if="$page.props.flash?.success" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.flash?.error" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                {{ $page.props.flash.error }}
            </div>

            <p v-if="!round" class="rounded-lg border border-gray-200 bg-white px-4 py-6 text-sm text-gray-600">
                Os documentos oficiais ainda não foram publicados pela Talents.
            </p>

            <template v-else>
                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="published-title">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 id="published-title" class="text-base font-semibold text-talents-900">Publicados</h3>
                        <span class="inline-flex rounded-md border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-800">
                            {{ round.status_label }} · revisão {{ round.number }}
                        </span>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">
                        Publicados em {{ round.published_at }} · protocolo de encaminhamento <strong>{{ round.protocol }}</strong>
                    </p>

                    <ul class="mt-4 divide-y divide-gray-100">
                        <li v-for="doc in round.versions" :key="doc.id" class="flex items-start justify-between gap-3 py-3">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-900">{{ doc.label }}</p>
                                <p class="text-xs text-gray-500">Versão {{ doc.version_number }} · {{ doc.public_identifier }}</p>
                            </div>
                            <a
                                v-if="doc.download_url"
                                :href="doc.download_url"
                                class="shrink-0 rounded-md bg-talents-700 px-3 py-1.5 text-xs font-semibold text-white"
                            >
                                Baixar PDF
                            </a>
                        </li>
                    </ul>
                    <p class="mt-3 text-xs text-gray-500">{{ approvalMeaning }}</p>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="ack-title">
                    <h3 id="ack-title" class="text-base font-semibold text-talents-900">Ciência da empresa</h3>
                    <p class="mt-1 text-sm text-gray-600">
                        Um registro por publicação, para os dois documentos juntos, com usuário, data e o protocolo de encaminhamento.
                    </p>
                    <p v-if="round.acknowledgement" class="mt-3 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-900">
                        Ciência registrada por {{ round.acknowledgement.by }} em {{ round.acknowledgement.at }} (protocolo {{ round.acknowledgement.protocol }}).
                    </p>
                    <template v-else>
                        <p class="mt-3 text-sm text-gray-700">Ainda não registrada.</p>
                        <button
                            v-if="canRecord"
                            type="button"
                            class="mt-2 rounded-md bg-talents-700 px-4 py-2 text-sm font-semibold text-white"
                            @click="acknowledge"
                        >
                            Registrar ciência
                        </button>
                        <p v-else class="mt-2 text-xs text-gray-500">Registrar a ciência exige a permissão de validação em Relatórios.</p>
                    </template>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="pgr-title">
                    <h3 id="pgr-title" class="text-base font-semibold text-talents-900">Integração ao PGR</h3>
                    <p class="mt-1 text-sm text-gray-600">
                        Campo separado da aprovação documental. Só é confirmado com a evidência (documento de SST).
                    </p>
                    <p v-if="round.pgr_integration" class="mt-3 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-900">
                        Confirmada por {{ round.pgr_integration.by }} em {{ round.pgr_integration.at }} · evidência: {{ round.pgr_integration.file_name }}
                    </p>
                    <form v-else-if="canRecord" class="mt-3 grid gap-3 sm:grid-cols-2" @submit.prevent="confirmPgr">
                        <div class="sm:col-span-2">
                            <label for="pgr-evidence" class="text-xs font-semibold text-gray-600">Evidência de SST (arquivo, até 20 MB)</label>
                            <input id="pgr-evidence" type="file" required class="mt-1 block w-full text-sm" @input="pgrForm.evidence = $event.target.files[0]" />
                            <p v-if="pgrForm.errors.evidence" class="mt-1 text-xs text-red-600">{{ pgrForm.errors.evidence }}</p>
                        </div>
                        <div class="sm:col-span-2">
                            <label for="pgr-note" class="text-xs font-semibold text-gray-600">Observação (opcional)</label>
                            <input id="pgr-note" v-model="pgrForm.note" type="text" class="mt-1 w-full rounded-md border-gray-300 text-sm" />
                        </div>
                        <div class="sm:col-span-2">
                            <button type="submit" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-800 disabled:opacity-60" :disabled="pgrForm.processing">
                                Confirmar integração ao PGR
                            </button>
                        </div>
                    </form>
                    <p v-else class="mt-3 text-sm text-gray-600">Não confirmada.</p>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <ReviewHistory :history="round.history" />
                </section>
            </template>
        </div>
    </ClientLayout>
</template>
