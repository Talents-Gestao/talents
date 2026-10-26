<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    opportunity: { type: Object, required: true },
    transitions: { type: Array, default: () => [] },
    canWork: { type: Boolean, default: false },
    me: { type: Number, default: null },
});

const statusForm = useForm({ status: '', note: '' });
const nextForm = useForm({ next_action: props.opportunity.next_action ?? '', next_action_at: '' });

const assume = () => router.post(route('admin.nr1-opportunities.assume', props.opportunity.id), {}, { preserveScroll: true });
const changeStatus = () => statusForm.post(route('admin.nr1-opportunities.status', props.opportunity.id), { preserveScroll: true, onSuccess: () => statusForm.reset() });
const saveNext = () => nextForm.post(route('admin.nr1-opportunities.next-action', props.opportunity.id), { preserveScroll: true });
</script>

<template>
    <Head :title="`Oportunidade — ${opportunity.company_name}`" />

    <AdminLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-talents-700">{{ opportunity.origin_label }}</p>
                    <h2 class="text-xl font-semibold leading-tight text-gray-900">{{ opportunity.company_name }} · {{ opportunity.service }}</h2>
                    <p class="text-sm text-gray-600">{{ opportunity.survey_title }} · {{ opportunity.status_label }}</p>
                </div>
                <Link :href="route('admin.nr1-opportunities.index')" class="text-sm text-talents-700 underline">Voltar à fila</Link>
            </div>
        </template>

        <div class="space-y-6">
            <div v-if="$page.props.flash?.success" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ $page.props.flash.success }}</div>
            <div v-if="$page.props.flash?.error" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">{{ $page.props.flash.error }}</div>

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <dl class="grid gap-3 text-sm sm:grid-cols-3">
                    <div><dt class="text-xs text-gray-500">Solicitante</dt><dd>{{ opportunity.requester ?? 'Sugestão automática' }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Criada em</dt><dd>{{ opportunity.created_at }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Responsável</dt><dd>{{ opportunity.assignee ?? 'Sem responsável' }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Última atividade</dt><dd>{{ opportunity.last_activity_at }}</dd></div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs text-gray-500">Próxima ação</dt>
                        <dd>{{ opportunity.next_action ?? '—' }}<span v-if="opportunity.next_action_at"> · {{ opportunity.next_action_at }}</span></dd>
                    </div>
                    <div v-if="opportunity.need" class="sm:col-span-3"><dt class="text-xs text-gray-500">Necessidade</dt><dd>{{ opportunity.need }}</dd></div>
                    <div v-if="opportunity.lost_reason" class="sm:col-span-3"><dt class="text-xs text-gray-500">Motivo da recusa</dt><dd>{{ opportunity.lost_reason }}</dd></div>
                </dl>

                <div v-if="canWork && opportunity.open" class="mt-4 flex flex-wrap gap-3">
                    <button v-if="!opportunity.assigned_to" type="button" class="rounded-md bg-talents-700 px-3 py-1.5 text-sm font-semibold text-white" @click="assume">Assumir oportunidade</button>
                </div>
            </section>

            <div v-if="canWork && opportunity.open" class="grid gap-6 lg:grid-cols-2">
                <form class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm" @submit.prevent="changeStatus">
                    <h3 class="text-base font-semibold text-talents-900">Mudar estado</h3>
                    <label for="opp-status" class="mt-3 block text-xs font-semibold text-gray-600">Novo estado</label>
                    <select id="opp-status" v-model="statusForm.status" required class="mt-1 w-full rounded-md border-gray-300 text-sm">
                        <option value="" disabled>Selecione</option>
                        <option v-for="item in transitions" :key="item.value" :value="item.value">{{ item.label }}</option>
                    </select>
                    <p v-if="statusForm.errors.status" class="text-xs text-red-600">{{ statusForm.errors.status }}</p>
                    <label for="opp-note" class="mt-3 block text-xs font-semibold text-gray-600">Nota (obrigatória para Perdido: motivo da recusa)</label>
                    <textarea id="opp-note" v-model="statusForm.note" rows="3" class="mt-1 w-full rounded-md border-gray-300 text-sm" />
                    <button type="submit" class="mt-3 rounded-md bg-talents-700 px-3 py-1.5 text-sm font-semibold text-white disabled:opacity-60" :disabled="statusForm.processing">Salvar estado</button>
                </form>

                <form class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm" @submit.prevent="saveNext">
                    <h3 class="text-base font-semibold text-talents-900">Próxima ação</h3>
                    <label for="opp-next" class="mt-3 block text-xs font-semibold text-gray-600">O que fazer</label>
                    <textarea id="opp-next" v-model="nextForm.next_action" rows="3" required class="mt-1 w-full rounded-md border-gray-300 text-sm" />
                    <label for="opp-next-at" class="mt-3 block text-xs font-semibold text-gray-600">Quando</label>
                    <input id="opp-next-at" v-model="nextForm.next_action_at" type="date" class="mt-1 w-full rounded-md border-gray-300 text-sm" />
                    <button type="submit" class="mt-3 rounded-md bg-talents-700 px-3 py-1.5 text-sm font-semibold text-white disabled:opacity-60" :disabled="nextForm.processing">Salvar próxima ação</button>
                </form>
            </div>

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="opp-history">
                <h3 id="opp-history" class="text-base font-semibold text-talents-900">Histórico</h3>
                <ol class="mt-3 space-y-2">
                    <li v-for="event in opportunity.history" :key="event.id" class="rounded-lg border border-gray-100 p-3 text-sm">
                        <p class="font-medium text-gray-900">
                            <span v-if="event.from && event.to && event.from !== event.to">{{ event.from }} → {{ event.to }}</span>
                            <span v-else>{{ event.to ?? event.action }}</span>
                        </p>
                        <p v-if="event.body" class="mt-0.5 whitespace-pre-line text-gray-700">{{ event.body }}</p>
                        <p class="mt-0.5 text-xs text-gray-500">{{ event.author }} · {{ event.at }}</p>
                    </li>
                </ol>
            </section>

            <section v-if="opportunity.aggregate" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="opp-aggregate">
                <h3 id="opp-aggregate" class="text-base font-semibold text-talents-900">Resultado agregado da campanha publicada</h3>
                <p class="mt-1 text-xs text-gray-500">Só agregados, respeitando o grupo mínimo de {{ opportunity.aggregate.min_group_size }} respondentes.</p>
                <p class="mt-2 text-sm">{{ opportunity.aggregate.respondents }} respostas · {{ opportunity.aggregate.method_label }}</p>
                <ul class="mt-2 space-y-1 text-sm">
                    <li v-for="dimension in opportunity.aggregate.dimensions" :key="dimension.title" class="flex flex-wrap justify-between gap-2">
                        <span>{{ dimension.title }}</span>
                        <span class="font-semibold">{{ dimension.score }} · {{ dimension.band_label }}</span>
                    </li>
                </ul>
            </section>
        </div>
    </AdminLayout>
</template>
