<script setup>
import FormPageHeader from '@/Components/FormPageHeader.vue';
import OrientationCard from '@/Components/Nr1Campaign/OrientationCard.vue';
import ParticipationCard from '@/Components/Nr1Campaign/ParticipationCard.vue';
import SurveyStatusBadge from '@/Components/SurveyStatusBadge.vue';
import TrainingCards from '@/Components/Nr1Campaign/TrainingCards.vue';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import { formatDateTime } from '@/utils/dateOnly';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    survey: Object,
    publicUrl: String,
    audienceLinks: { type: Array, default: () => [] },
    stats: Object,
    resultsAvailable: { type: Boolean, default: true },
    campaign: { type: Object, default: null },
    canRequestProposal: { type: Boolean, default: false },
});

const requestUrl = (modality, audience) => route('client.surveys.trainings.request', [props.survey.id, modality, audience]);

const drafts = reactive(
    Object.fromEntries(
        props.audienceLinks.map((l) => [l.id, { enabled: l.enabled, opens_at: l.opens_at ?? '', closes_at: l.closes_at ?? '' }]),
    ),
);

const createAudienceLinks = () => router.post(route('client.surveys.audiences.store', props.survey.id), {}, { preserveScroll: true });

const saveAudienceLink = (link) =>
    router.patch(
        route('client.surveys.audiences.update', [props.survey.id, link.id]),
        {
            enabled: drafts[link.id].enabled,
            opens_at: drafts[link.id].opens_at || null,
            closes_at: drafts[link.id].closes_at || null,
        },
        { preserveScroll: true },
    );
</script>

<template>
    <Head :title="survey.title" />

    <ClientLayout>
        <template #header>
            <FormPageHeader
                :back-href="route('client.surveys.index')"
                back-label="Campanhas"
                :title="survey.title"
                :prefer-history="true"
            >
                <template #trailing>
                    <Link
                        :href="route('client.surveys.edit', survey.id)"
                        class="text-sm font-semibold text-talents-700 hover:underline"
                    >
                        Editar
                    </Link>
                </template>
            </FormPageHeader>
        </template>

        <div v-if="campaign" class="mb-6 space-y-4">
            <div v-if="$page.props.flash?.success" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ $page.props.flash.success }}</div>
            <div v-if="$page.props.flash?.error" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">{{ $page.props.flash.error }}</div>
            <div class="grid gap-4 lg:grid-cols-3">
                <div class="lg:col-span-2"><OrientationCard :orientation="campaign.orientation" /></div>
                <ParticipationCard :participation="campaign.participation" />
            </div>
            <TrainingCards :cards="campaign.trainings" :can-request="canRequestProposal" :request-url="requestUrl" />
        </div>

        <div class="surface-card p-6">
            <p class="flex flex-wrap items-center gap-2 text-sm text-gray-600">
                <span>Status:</span>
                <SurveyStatusBadge :status="survey.status" />
            </p>
            <p class="mt-2 text-sm text-gray-600">
                Período: {{ formatDateTime(survey.starts_at) }} — {{ formatDateTime(survey.ends_at) }}
            </p>
            <div v-if="publicUrl" class="mt-4">
                <p class="text-sm font-medium text-gray-800">Link anônimo para colaboradores</p>
                <p class="mt-2 break-all rounded bg-gray-50 p-3 text-sm font-mono text-talents-800">{{ publicUrl }}</p>
                <button
                    type="button"
                    class="mt-3 rounded-md border border-talents-300 px-3 py-2 text-sm font-semibold text-talents-900"
                    @click="createAudienceLinks"
                >
                    Criar links separados para Time e Líderes
                </button>
            </div>

            <section v-if="audienceLinks.length" class="mt-4" aria-labelledby="audience-links-title">
                <h2 id="audience-links-title" class="text-sm font-medium text-gray-800">Links anônimos por público</h2>
                <p class="mt-1 text-xs text-gray-500">
                    Cada público tem link e QR code próprios. O link só abre dentro do período definido e enquanto estiver habilitado.
                </p>
                <div class="mt-3 grid gap-4 md:grid-cols-2">
                    <div v-for="link in audienceLinks" :key="link.id" class="rounded-lg border border-gray-200 p-4">
                        <p class="text-sm font-semibold text-talents-900">{{ link.label }}</p>
                        <p v-if="!link.open" class="mt-2 rounded bg-gray-50 p-2 text-xs text-gray-600">
                            O link e o QR code ficam visíveis enquanto a campanha e o link estiverem abertos.
                        </p>
                        <p v-if="link.open" class="mt-2 break-all rounded bg-gray-50 p-2 font-mono text-xs text-talents-800">{{ link.url }}</p>
                        <img
                            v-if="link.open"
                            :src="link.qr_url"
                            :alt="'QR code do link do público ' + link.label"
                            class="mt-3 h-32 w-32 rounded border border-gray-200 bg-white p-1"
                            width="128"
                            height="128"
                        />
                        <div class="mt-3 space-y-2 text-sm">
                            <label class="flex items-center gap-2">
                                <input v-model="drafts[link.id].enabled" type="checkbox" class="rounded border-gray-300 text-talents-600" />
                                <span>Link habilitado</span>
                            </label>
                            <label class="block text-xs text-gray-600">
                                Abre em
                                <input v-model="drafts[link.id].opens_at" type="datetime-local" class="mt-1 block w-full rounded-md border border-gray-300 px-2 py-1 text-sm" />
                            </label>
                            <label class="block text-xs text-gray-600">
                                Fecha em
                                <input v-model="drafts[link.id].closes_at" type="datetime-local" class="mt-1 block w-full rounded-md border border-gray-300 px-2 py-1 text-sm" />
                            </label>
                            <button
                                type="button"
                                class="rounded-md bg-talents-700 px-3 py-2 text-sm font-semibold text-white"
                                @click="saveAudienceLink(link)"
                            >
                                Salvar
                            </button>
                        </div>
                    </div>
                </div>
            </section>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div class="rounded-lg bg-talents-50 p-4">
                    <p class="text-xs text-talents-700">Iniciaram</p>
                    <p class="text-2xl font-bold text-talents-900">{{ stats.started }}</p>
                </div>
                <div class="rounded-lg bg-talents-50 p-4">
                    <p class="text-xs text-talents-700">Concluíram</p>
                    <p class="text-2xl font-bold text-talents-900">{{ stats.completed }}</p>
                </div>
            </div>
            <p v-if="!resultsAvailable" class="mt-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                Resultados, plano de ação e documentos ficam disponíveis depois que a Talents publicar a campanha.
            </p>
            <div v-else class="mt-6 flex flex-wrap gap-3">
                <Link
                    :href="route('client.surveys.results', survey.id)"
                    class="rounded-md bg-talents-700 px-4 py-2 text-sm font-semibold text-white"
                >
                    Resultados e gráficos
                </Link>
                <Link
                    :href="route('client.surveys.action-plan', survey.id)"
                    class="rounded-md border border-talents-300 px-4 py-2 text-sm font-semibold text-talents-900"
                >
                    Plano de ação
                </Link>
            </div>
        </div>
    </ClientLayout>
</template>
