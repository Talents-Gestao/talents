<script setup>
import { router } from '@inertiajs/vue3';

const props = defineProps({
    cards: { type: Array, default: () => [] },
    canRequest: { type: Boolean, default: false },
    requestUrl: { type: Function, default: null },
});

const request = (modality, audience) => {
    router.post(props.requestUrl(modality, audience), {}, { preserveScroll: true });
};
</script>

<template>
    <div class="grid gap-4 lg:grid-cols-2">
        <section v-for="card in cards" :key="card.modality" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm" :aria-labelledby="`training-${card.modality}`">
            <h2 :id="`training-${card.modality}`" class="text-base font-semibold text-talents-900">{{ card.label }}</h2>
            <p class="mt-1 text-xs text-gray-500">Por público. Só a Talents marca como contratado.</p>

            <ul class="mt-4 space-y-3">
                <li
                    v-for="row in card.rows"
                    :key="row.audience"
                    class="rounded-lg p-3"
                    :class="row.state === 'contracted' ? 'border-2 border-emerald-300 bg-emerald-50' : 'border border-dashed border-gray-300 bg-gray-50'"
                >
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-sm font-semibold" :class="row.state === 'contracted' ? 'text-emerald-900' : 'text-gray-700'">{{ row.audience_label }}</p>
                        <span v-if="row.state === 'contracted'" class="rounded-full bg-emerald-600 px-2.5 py-0.5 text-xs font-semibold text-white">Contratado</span>
                        <span v-else-if="row.state === 'requested'" class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-900">Proposta solicitada</span>
                        <span v-else class="text-xs text-gray-500">Não contratado</span>
                    </div>

                    <dl v-if="row.state === 'contracted' && row.training" class="mt-2 grid gap-1 text-sm text-gray-800 sm:grid-cols-2">
                        <div v-if="row.training.scope" class="sm:col-span-2"><dt class="sr-only">Escopo</dt><dd>{{ row.training.scope }}</dd></div>
                        <div v-if="row.training.starts_on || row.training.ends_on">
                            <dt class="text-xs text-gray-500">Período</dt>
                            <dd>{{ row.training.starts_on ?? '—' }} a {{ row.training.ends_on ?? '—' }}</dd>
                        </div>
                        <div v-if="row.training.responsible"><dt class="text-xs text-gray-500">Responsável</dt><dd>{{ row.training.responsible }}</dd></div>
                        <div v-if="row.training.location"><dt class="text-xs text-gray-500">Local</dt><dd>{{ row.training.location }}</dd></div>
                        <div v-if="row.training.event_at"><dt class="text-xs text-gray-500">Data e hora</dt><dd>{{ row.training.event_at }}</dd></div>
                        <div v-if="row.training.facilitator"><dt class="text-xs text-gray-500">Facilitador</dt><dd>{{ row.training.facilitator }}</dd></div>
                        <div v-if="row.training.capacity"><dt class="text-xs text-gray-500">Capacidade</dt><dd>{{ row.training.capacity }} pessoas</dd></div>
                        <div v-if="row.training.attendance !== null && row.training.attendance !== undefined"><dt class="text-xs text-gray-500">Presença</dt><dd>{{ row.training.attendance }}</dd></div>
                        <div v-if="row.training.link_url" class="sm:col-span-2"><dt class="text-xs text-gray-500">Link</dt><dd class="break-all">{{ row.training.link_url }}</dd></div>
                        <div v-if="row.training.material_name" class="sm:col-span-2"><dt class="text-xs text-gray-500">Arquivo</dt><dd>{{ row.training.material_name }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-xs text-gray-500">Disponibilidade</dt><dd>{{ row.training.availability }}</dd></div>
                    </dl>

                    <p v-else-if="row.state === 'requested'" class="mt-1 text-xs text-gray-600">Pedido feito em {{ row.requested_at }}. A Talents entrará em contato.</p>

                    <div v-if="row.state === 'not_contracted' && canRequest && requestUrl" class="mt-2">
                        <button
                            type="button"
                            class="rounded-md border border-gray-300 bg-white px-3 py-1 text-xs font-semibold text-gray-800 hover:bg-gray-100"
                            @click="request(card.modality, row.audience)"
                        >
                            Solicitar proposta
                        </button>
                    </div>
                </li>
            </ul>
        </section>
    </div>
</template>
