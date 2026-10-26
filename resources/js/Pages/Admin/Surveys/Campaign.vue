<script setup>
import OrientationCard from '@/Components/Nr1Campaign/OrientationCard.vue';
import ParticipationCard from '@/Components/Nr1Campaign/ParticipationCard.vue';
import TrainingCards from '@/Components/Nr1Campaign/TrainingCards.vue';
import TrainingContractForm from '@/Components/Nr1Campaign/TrainingContractForm.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    company: { type: Object, required: true },
    survey: { type: Object, required: true },
    campaign: { type: Object, required: true },
    audienceLinks: { type: Array, default: () => [] },
    eligible: { type: Object, required: true },
    invitations: { type: Object, default: () => ({ enabled: false, stats: [], generated: [] }) },
    urls: { type: Object, required: true },
});

const invitationForm = useForm({ audience: props.audienceLinks.length ? 'time' : '', count: 10 });
const toggleInvitations = (event) =>
    router.post(props.urls.invitation_option, { enabled: event.target.checked }, { preserveScroll: true });
const generateInvitations = () => invitationForm.post(props.urls.invitations, { preserveScroll: true });
const downloadInvitations = () => {
    const blob = new Blob([props.invitations.generated.join('\n')], { type: 'text/plain;charset=utf-8' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'convites.txt';
    link.click();
    URL.revokeObjectURL(link.href);
};

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

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="invitations-title">
                <h2 id="invitations-title" class="text-base font-semibold text-talents-900">Convite único</h2>
                <p class="mt-1 text-xs text-gray-600">
                    Com a opção ligada, só abre e envia o questionário quem tem um convite de uso único. O convite não guarda quem
                    respondeu nem o que foi respondido: só o hash do código e o mês em que foi usado. Desligada, vale o padrão da
                    plataforma (sem convite).
                </p>
                <label class="mt-3 flex items-center gap-2 text-sm">
                    <input type="checkbox" class="h-4 w-4 rounded border-gray-300" :checked="invitations.enabled" @change="toggleInvitations" />
                    <span>Exigir convite único nesta campanha</span>
                </label>

                <ul v-if="invitations.stats.length" class="mt-3 text-sm text-gray-700">
                    <li v-for="row in invitations.stats" :key="row.audience ?? 'todos'">
                        {{ row.label }}: {{ row.total }} gerados, {{ row.used }} usados, {{ row.available }} disponíveis
                    </li>
                </ul>

                <form class="mt-3 flex flex-wrap items-end gap-3" @submit.prevent="generateInvitations">
                    <div v-if="audienceLinks.length">
                        <label for="invite-audience" class="text-xs font-semibold text-gray-600">Público</label>
                        <select id="invite-audience" v-model="invitationForm.audience" class="mt-1 rounded-md border-gray-300 text-sm">
                            <option value="time">Time</option>
                            <option value="lideres">Líderes</option>
                        </select>
                    </div>
                    <div>
                        <label for="invite-count" class="text-xs font-semibold text-gray-600">Quantidade</label>
                        <input id="invite-count" v-model="invitationForm.count" type="number" min="1" max="5000" required class="mt-1 w-28 rounded-md border-gray-300 text-sm" />
                    </div>
                    <button type="submit" class="rounded-md bg-talents-700 px-3 py-1.5 text-sm font-semibold text-white disabled:opacity-60" :disabled="invitationForm.processing">Gerar convites</button>
                </form>
                <p v-if="invitationForm.errors.count" class="mt-1 text-xs text-red-600">{{ invitationForm.errors.count }}</p>

                <div v-if="invitations.generated.length" class="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-3" role="status">
                    <p class="text-sm font-semibold text-amber-900">Convites gerados: copie ou baixe agora, eles não ficam guardados.</p>
                    <textarea readonly rows="6" class="mt-2 w-full rounded-md border-gray-300 font-mono text-xs" :value="invitations.generated.join('\n')" aria-label="Convites gerados" />
                    <button type="button" class="mt-2 rounded-md border border-gray-300 bg-white px-3 py-1 text-xs font-semibold text-gray-800" @click="downloadInvitations">Baixar .txt</button>
                </div>
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
