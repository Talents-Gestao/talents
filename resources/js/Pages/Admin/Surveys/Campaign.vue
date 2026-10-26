<script setup>
import OrientationCard from '@/Components/Nr1Campaign/OrientationCard.vue';
import ParticipationCard from '@/Components/Nr1Campaign/ParticipationCard.vue';
import TrainingCards from '@/Components/Nr1Campaign/TrainingCards.vue';
import TrainingContractForm from '@/Components/Nr1Campaign/TrainingContractForm.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    company: { type: Object, required: true },
    survey: { type: Object, required: true },
    campaign: { type: Object, required: true },
    audienceLinks: { type: Array, default: () => [] },
    eligible: { type: Object, required: true },
    urls: { type: Object, required: true },
});

const eligibleForm = useForm({
    eligible_count: props.eligible.count ?? '',
    eligible_source: props.eligible.source ?? '',
});

const saveEligible = () => eligibleForm.post(props.urls.eligible, { preserveScroll: true });
</script>

<template>
    <Head :title="`Campanha NR-1 — ${survey.title}`" />

    <AdminLayout>
        <template #header>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-talents-700">Campanha NR-1 · consultoria</p>
                <h2 class="text-xl font-semibold leading-tight text-gray-900">{{ survey.title }}</h2>
                <p class="text-sm text-gray-600">{{ company.name }} · {{ campaign.collection.label }}</p>
            </div>
        </template>

        <div class="space-y-6">
            <div v-if="$page.props.flash?.success" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ $page.props.flash.success }}</div>
            <div v-if="$page.props.flash?.error" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">{{ $page.props.flash.error }}</div>

            <div class="grid gap-4 lg:grid-cols-3">
                <div class="lg:col-span-2"><OrientationCard :orientation="campaign.orientation" /></div>
                <div class="space-y-4">
                    <ParticipationCard :participation="campaign.participation" />
                    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="eligible-title">
                        <h2 id="eligible-title" class="text-base font-semibold text-talents-900">Elegíveis cadastrados</h2>
                        <form class="mt-3 space-y-3" @submit.prevent="saveEligible">
                            <div>
                                <label for="eligible-count" class="text-xs font-semibold text-gray-600">Total (mínimo {{ Math.max(1, eligible.min) }})</label>
                                <input id="eligible-count" v-model="eligibleForm.eligible_count" type="number" :min="Math.max(1, eligible.min)" required class="mt-1 w-full rounded-md border-gray-300 text-sm" />
                                <p v-if="eligibleForm.errors.eligible_count" class="text-xs text-red-600">{{ eligibleForm.errors.eligible_count }}</p>
                            </div>
                            <div>
                                <label for="eligible-source" class="text-xs font-semibold text-gray-600">Fonte do número</label>
                                <input id="eligible-source" v-model="eligibleForm.eligible_source" type="text" required class="mt-1 w-full rounded-md border-gray-300 text-sm" />
                                <p v-if="eligibleForm.errors.eligible_source" class="text-xs text-red-600">{{ eligibleForm.errors.eligible_source }}</p>
                            </div>
                            <button type="submit" class="rounded-md bg-talents-700 px-3 py-1.5 text-sm font-semibold text-white disabled:opacity-60" :disabled="eligibleForm.processing">Atualizar</button>
                            <Link :href="urls.registration" class="ml-3 text-xs text-talents-700 underline">Cadastro técnico completo</Link>
                        </form>
                    </section>
                </div>
            </div>

            <section v-if="audienceLinks.length" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="links-title">
                <h2 id="links-title" class="text-base font-semibold text-talents-900">Links por público</h2>
                <ul class="mt-2 space-y-1 text-sm">
                    <li v-for="link in audienceLinks" :key="link.id">
                        <span class="font-medium">{{ link.label }}:</span>
                        <span v-if="link.open" class="break-all font-mono text-xs text-talents-800"> {{ link.url }}</span>
                        <span v-else class="text-gray-600"> fechado (visível só com a campanha aberta)</span>
                    </li>
                </ul>
            </section>

            <TrainingCards :cards="campaign.trainings" />

            <section class="space-y-4" aria-labelledby="contract-title">
                <h2 id="contract-title" class="text-base font-semibold text-talents-900">Marcar treinamentos contratados</h2>
                <div v-for="card in campaign.trainings" :key="card.modality" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <h3 class="text-sm font-semibold text-gray-900">{{ card.label }}</h3>
                    <div v-for="row in card.rows" :key="row.audience" class="mt-3">
                        <p class="text-sm font-medium text-gray-800">{{ row.audience_label }}</p>
                        <TrainingContractForm
                            :modality="card.modality"
                            :audience="row.audience"
                            :contracted="row.state === 'contracted'"
                            :training="row.training"
                            :contract-url="urls.contract"
                            :uncontract-url="urls.uncontract"
                        />
                    </div>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>
