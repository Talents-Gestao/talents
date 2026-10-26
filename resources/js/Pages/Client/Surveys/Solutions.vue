<script setup>
import ClientLayout from '@/Layouts/ClientLayout.vue';
import { bandBadgeClass } from '@/utils/nr1Bands';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    survey: { type: Object, required: true },
    cards: { type: Array, default: () => [] },
    recommendations: { type: Array, default: () => [] },
    canRequest: { type: Boolean, default: false },
});

const toneClass = {
    ok: 'bg-emerald-50 text-emerald-800 border-emerald-200',
    info: 'bg-sky-50 text-sky-800 border-sky-200',
    warn: 'bg-amber-50 text-amber-800 border-amber-200',
};

const request = (recommendation) => {
    router.post(route('client.surveys.solutions.request', [props.survey.id, recommendation.id]), {}, { preserveScroll: true });
};
</script>

<template>
    <Head :title="`Soluções Talents - ${survey.title}`" />

    <ClientLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-talents-700">Talents para sua empresa</p>
                    <h2 class="text-xl font-semibold leading-tight text-talents-900">Soluções conectadas às necessidades.</h2>
                    <p class="text-sm text-gray-600">Serviços que podem apoiar o plano de ação, a partir dos fatores sinalizados na pesquisa · {{ survey.title }}</p>
                </div>
                <Link :href="route('client.surveys.psychosocial-dashboard', survey.id)" class="text-sm text-talents-700 hover:underline">Voltar ao painel</Link>
            </div>
        </template>

        <div class="space-y-6">
            <div v-if="$page.props.flash?.success" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ $page.props.flash.success }}</div>
            <div v-if="$page.props.flash?.error" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">{{ $page.props.flash.error }}</div>

            <div class="grid gap-4 lg:grid-cols-3">
                <section v-for="card in cards" :key="card.number" class="flex flex-col gap-2 rounded-xl border border-gray-200 bg-white p-5 shadow-sm" :aria-labelledby="`service-${card.number}`">
                    <span class="text-xs font-bold text-gray-400">{{ card.number }}</span>
                    <h3 :id="`service-${card.number}`" class="text-base font-semibold text-talents-900">{{ card.title }}</h3>
                    <span class="inline-flex self-start rounded-md border px-2 py-0.5 text-xs font-semibold" :class="toneClass[card.tone]">{{ card.status }}</span>
                    <p class="text-sm text-gray-700">{{ card.text }}</p>
                </section>
            </div>

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="recommendations-title">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h3 id="recommendations-title" class="text-base font-semibold text-talents-900">Recomendações relacionadas aos resultados</h3>
                    <p v-if="recommendations.length" class="text-xs text-gray-500">{{ recommendations.length }} {{ recommendations.length === 1 ? 'frente de atenção' : 'frentes de atenção' }}</p>
                </div>

                <p v-if="!recommendations.length" class="mt-4 text-sm text-gray-600">
                    Ainda não há recomendações para esta campanha. Elas aparecem quando uma dimensão fica em atenção e a Talents libera o serviço correspondente.
                </p>

                <div v-else class="mt-4 grid gap-4 lg:grid-cols-2">
                    <article v-for="item in recommendations" :key="item.id" class="rounded-lg border border-gray-100 p-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex rounded-md border px-2 py-0.5 text-xs font-semibold" :class="bandBadgeClass(item.risk_level)">
                                {{ item.factor }} · {{ item.score_label }}/5
                            </span>
                            <span class="text-xs text-gray-500">Confiança da recomendação: {{ item.confidence_label }}</span>
                        </div>
                        <h4 class="mt-2 text-sm font-semibold text-gray-900">{{ item.service_name }}</h4>
                        <p class="mt-1 text-sm text-gray-700">{{ item.text }}</p>
                        <p class="mt-2 text-sm font-semibold text-gray-900">{{ item.eligible_services }}</p>
                        <p class="mt-2 text-xs text-gray-500">Sugestão consultiva a partir de uma faixa do questionário. Não é diagnóstico nem classificação de risco do PGR.</p>
                        <div class="mt-3">
                            <span v-if="item.requested" class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-900">Conversa solicitada</span>
                            <button
                                v-else-if="canRequest"
                                type="button"
                                class="rounded-md border border-talents-300 px-3 py-1.5 text-sm font-semibold text-talents-900 hover:bg-talents-50"
                                @click="request(item)"
                            >
                                Solicitar conversa →
                            </button>
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </ClientLayout>
</template>
