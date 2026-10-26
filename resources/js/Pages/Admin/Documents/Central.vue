<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ReviewHistory from '@/Components/Nr1Documents/ReviewHistory.vue';
import ReviewStatusPill from '@/Components/Nr1Documents/ReviewStatusPill.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    company: { type: Object, required: true },
    survey: { type: Object, required: true },
    externalReviewEnabled: { type: Boolean, default: false },
    round: { type: Object, default: null },
    publishedRound: { type: Object, default: null },
    canStart: { type: Boolean, default: false },
    reviewers: { type: Array, default: () => [] },
    approvalMeaning: { type: String, default: '' },
});

const sendForm = useForm({ reviewer_id: '', share_days: 30 });

const helpText = computed(() => {
    switch (props.round?.status) {
        case 'published':
            return 'Documentos liberados para a empresa, para consulta e impressão.';
        case 'in_review':
            return 'Os documentos estão com o revisor externo (PASSEG) para revisão documental.';
        case 'changes_requested':
            return 'A PASSEG pediu ajustes. Corrija os dados, gere novas versões e reenvie: a mudança exige nova versão.';
        case 'approved':
            return 'Revisão documental aprovada. A Talents pode publicar os documentos.';
        default:
            return 'Documentos em edição pela Talents. Gere as versões, confira as pendências e envie à PASSEG.';
    }
});

const post = (name, params, data = {}) => router.post(route(`admin.companies.surveys.documents.${name}`, params), data, { preserveScroll: true });
const base = computed(() => [props.company.id, props.survey.id]);

const send = () => {
    sendForm.post(route('admin.companies.surveys.documents.send', [props.company.id, props.survey.id, props.round.id]), { preserveScroll: true });
};
</script>

<template>
    <Head :title="`Documentos — ${survey.title}`" />

    <AdminLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-talents-700">Central de documentos</p>
                    <h2 class="text-xl font-semibold leading-tight text-gray-900">Documentos oficiais</h2>
                    <p class="text-sm text-gray-600">{{ company.name }} · {{ survey.title }}</p>
                </div>
                <Link :href="route('admin.companies.surveys.action-plan.edit', [company.id, survey.id])" class="text-sm text-talents-700 underline">
                    Voltar ao parecer
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
            <p v-if="!externalReviewEnabled" class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                A revisão externa está desligada neste ambiente: não é possível enviar à PASSEG nem, portanto, publicar.
                Ela só deve ser ligada depois do MFA.
            </p>

            <div class="grid gap-6 lg:grid-cols-2">
                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="docs-title">
                    <h3 id="docs-title" class="text-base font-semibold text-talents-900">Documentos da revisão atual</h3>
                    <p class="mt-1 text-xs text-gray-500">
                        Dois documentos finais, revisados e publicados juntos. Cada geração congela o snapshot e o PDF, com hash.
                    </p>

                    <p v-if="!round" class="mt-4 text-sm text-gray-600">Nenhuma revisão iniciada para esta campanha.</p>
                    <ul v-else class="mt-4 divide-y divide-gray-100">
                        <li v-if="!round.versions.length" class="py-3 text-sm text-gray-600">Documentos ainda não gerados.</li>
                        <li v-for="doc in round.versions" :key="doc.id" class="flex items-start justify-between gap-3 py-3">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-900">{{ doc.label }}</p>
                                <p class="text-xs text-gray-500">
                                    Versão {{ doc.version_number }} · {{ doc.public_identifier }} · gerado em {{ doc.generated_at }}
                                </p>
                                <p v-if="doc.pdf_sha256" class="break-all text-[11px] text-gray-400">SHA-256 do PDF: {{ doc.pdf_sha256 }}</p>
                            </div>
                            <a
                                v-if="doc.download_url"
                                :href="doc.download_url"
                                class="shrink-0 rounded-md border border-gray-300 px-3 py-1 text-xs font-semibold text-gray-800"
                            >
                                Baixar PDF
                            </a>
                            <span v-else class="shrink-0 text-xs text-red-700">PDF não gerado</span>
                        </li>
                    </ul>

                    <div v-if="publishedRound" class="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900">
                        <p class="font-medium">A empresa continua vendo a revisão {{ publishedRound.number }} (publicada em {{ publishedRound.published_at }}).</p>
                        <p class="text-xs">Protocolo {{ publishedRound.protocol }}</p>
                    </div>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="review-title">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 id="review-title" class="text-base font-semibold text-talents-900">Revisão do relatório técnico e plano de ação</h3>
                        <ReviewStatusPill v-if="round" :status="round.status" :label="round.status_label" />
                    </div>

                    <template v-if="round">
                        <p class="mt-1 text-xs text-gray-500">Revisão {{ round.number }}</p>
                        <p class="mt-2 text-sm text-gray-700">{{ helpText }}</p>

                        <div v-if="round.pending.length" class="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-3">
                            <p class="text-sm font-semibold text-amber-900">Pendências antes de enviar ou publicar</p>
                            <ul class="mt-1 list-disc space-y-0.5 pl-5 text-sm text-amber-900">
                                <li v-for="item in round.pending" :key="item.field">
                                    <span class="font-medium">{{ item.label }}</span>: {{ item.problem }} ({{ item.where }})
                                </li>
                            </ul>
                        </div>
                        <p v-else-if="round.actions.send || round.actions.publish" class="mt-4 text-sm text-emerald-800">Sem pendências nos documentos gerados.</p>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-semibold text-gray-800 disabled:opacity-50"
                                :disabled="!round.actions.regenerate"
                                @click="post('regenerate', [...base, round.id])"
                            >
                                {{ round.versions.length ? 'Gerar novas versões' : 'Gerar documentos' }}
                            </button>
                            <button
                                type="button"
                                class="rounded-md bg-talents-700 px-3 py-1.5 text-sm font-semibold text-white disabled:opacity-50"
                                :disabled="!round.actions.publish"
                                @click="post('publish', [...base, round.id])"
                            >
                                Publicar
                            </button>
                        </div>

                        <form v-if="round.actions.send" class="mt-4 grid gap-3 rounded-lg border border-gray-100 bg-gray-50 p-3 sm:grid-cols-3" @submit.prevent="send">
                            <div class="sm:col-span-2">
                                <label for="reviewer" class="text-xs font-semibold text-gray-600">Revisor externo (PASSEG)</label>
                                <select id="reviewer" v-model="sendForm.reviewer_id" required class="mt-1 w-full rounded-md border-gray-300 text-sm">
                                    <option value="" disabled>Selecione</option>
                                    <option v-for="reviewer in reviewers" :key="reviewer.id" :value="reviewer.id">
                                        {{ reviewer.name }}{{ reviewer.organization ? ` · ${reviewer.organization}` : '' }}
                                    </option>
                                </select>
                                <p v-if="sendForm.errors.reviewer_id" class="mt-1 text-xs text-red-600">{{ sendForm.errors.reviewer_id }}</p>
                            </div>
                            <div>
                                <label for="share-days" class="text-xs font-semibold text-gray-600">Acesso por (dias)</label>
                                <input id="share-days" v-model="sendForm.share_days" type="number" min="1" max="90" class="mt-1 w-full rounded-md border-gray-300 text-sm" />
                            </div>
                            <div class="sm:col-span-3">
                                <button type="submit" class="rounded-md bg-talents-700 px-3 py-1.5 text-sm font-semibold text-white disabled:opacity-60" :disabled="sendForm.processing">
                                    Enviar à PASSEG
                                </button>
                            </div>
                        </form>

                        <dl v-if="round.published_at" class="mt-4 grid gap-2 text-sm sm:grid-cols-2">
                            <div><dt class="text-xs text-gray-500">Publicado em</dt><dd>{{ round.published_at }} por {{ round.published_by }}</dd></div>
                            <div><dt class="text-xs text-gray-500">Protocolo de encaminhamento</dt><dd>{{ round.protocol }}</dd></div>
                            <div>
                                <dt class="text-xs text-gray-500">Ciência da empresa</dt>
                                <dd v-if="round.acknowledgement">{{ round.acknowledgement.by }} em {{ round.acknowledgement.at }}</dd>
                                <dd v-else class="text-gray-600">Ainda não registrada</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500">Integração ao PGR</dt>
                                <dd v-if="round.pgr_integration">Confirmada por {{ round.pgr_integration.by }} em {{ round.pgr_integration.at }}</dd>
                                <dd v-else class="text-gray-600">Não confirmada (exige evidência de SST)</dd>
                            </div>
                        </dl>
                        <p class="mt-3 text-xs text-gray-500">{{ approvalMeaning }}</p>
                    </template>

                    <div v-if="canStart" class="mt-4">
                        <button type="button" class="rounded-md border border-talents-300 bg-white px-3 py-1.5 text-sm font-semibold text-talents-900" @click="post('start', base)">
                            {{ round ? 'Iniciar nova revisão' : 'Iniciar revisão' }}
                        </button>
                    </div>

                    <ReviewHistory v-if="round" :history="round.history" />
                </section>
            </div>
        </div>
    </AdminLayout>
</template>
