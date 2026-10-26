<script setup>
import { router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    modality: { type: String, required: true },
    audience: { type: String, required: true },
    contracted: { type: Boolean, default: false },
    training: { type: Object, default: null },
    contractUrl: { type: String, required: true },
    uncontractUrl: { type: String, required: true },
});

const t = props.training ?? {};
const form = useForm({
    modality: props.modality,
    audience: props.audience,
    scope: t.scope ?? '',
    price_cents: t.price_cents ?? '',
    starts_on: t.starts_on_raw ?? '',
    ends_on: t.ends_on_raw ?? '',
    link_url: t.link_url ?? '',
    material: null,
    responsible: t.responsible ?? '',
    location: t.location ?? '',
    event_at: t.event_at_raw ?? '',
    facilitator: t.facilitator ?? '',
    capacity: t.capacity ?? '',
    attendance: t.attendance ?? '',
    released: t.released ?? false,
});

const id = (field) => `${props.modality}-${props.audience}-${field}`;
const inPerson = props.modality === 'presencial';

const submit = () => {
    form.post(props.contractUrl, { preserveScroll: true, forceFormData: true });
};

const remove = () => {
    router.post(props.uncontractUrl, { modality: props.modality, audience: props.audience }, { preserveScroll: true });
};
</script>

<template>
    <details class="mt-2 rounded-lg border border-gray-200 bg-white p-3" :open="contracted">
        <summary class="cursor-pointer text-sm font-semibold text-talents-900">{{ contracted ? 'Editar contratação' : 'Marcar contratado' }}</summary>

        <form class="mt-3 grid gap-3 sm:grid-cols-2" @submit.prevent="submit">
            <div class="sm:col-span-2">
                <label :for="id('scope')" class="text-xs font-semibold text-gray-600">Escopo e preço contratados</label>
                <textarea :id="id('scope')" v-model="form.scope" rows="2" required class="mt-1 w-full rounded-md border-gray-300 text-sm" />
                <p v-if="form.errors.scope" class="text-xs text-red-600">{{ form.errors.scope }}</p>
            </div>
            <div>
                <label :for="id('price')" class="text-xs font-semibold text-gray-600">Preço (centavos)</label>
                <input :id="id('price')" v-model="form.price_cents" type="number" min="0" class="mt-1 w-full rounded-md border-gray-300 text-sm" />
            </div>
            <div>
                <label :for="id('responsible')" class="text-xs font-semibold text-gray-600">Responsável</label>
                <input :id="id('responsible')" v-model="form.responsible" type="text" class="mt-1 w-full rounded-md border-gray-300 text-sm" />
            </div>
            <div>
                <label :for="id('start')" class="text-xs font-semibold text-gray-600">Início</label>
                <input :id="id('start')" v-model="form.starts_on" type="date" class="mt-1 w-full rounded-md border-gray-300 text-sm" />
            </div>
            <div>
                <label :for="id('end')" class="text-xs font-semibold text-gray-600">Fim</label>
                <input :id="id('end')" v-model="form.ends_on" type="date" class="mt-1 w-full rounded-md border-gray-300 text-sm" />
                <p v-if="form.errors.ends_on" class="text-xs text-red-600">{{ form.errors.ends_on }}</p>
            </div>

            <template v-if="inPerson">
                <div>
                    <label :for="id('location')" class="text-xs font-semibold text-gray-600">Local</label>
                    <input :id="id('location')" v-model="form.location" type="text" class="mt-1 w-full rounded-md border-gray-300 text-sm" />
                </div>
                <div>
                    <label :for="id('event')" class="text-xs font-semibold text-gray-600">Data e hora</label>
                    <input :id="id('event')" v-model="form.event_at" type="datetime-local" class="mt-1 w-full rounded-md border-gray-300 text-sm" />
                </div>
                <div>
                    <label :for="id('facilitator')" class="text-xs font-semibold text-gray-600">Facilitador</label>
                    <input :id="id('facilitator')" v-model="form.facilitator" type="text" class="mt-1 w-full rounded-md border-gray-300 text-sm" />
                </div>
                <div>
                    <label :for="id('capacity')" class="text-xs font-semibold text-gray-600">Capacidade</label>
                    <input :id="id('capacity')" v-model="form.capacity" type="number" min="1" class="mt-1 w-full rounded-md border-gray-300 text-sm" />
                </div>
                <div>
                    <label :for="id('attendance')" class="text-xs font-semibold text-gray-600">Presença</label>
                    <input :id="id('attendance')" v-model="form.attendance" type="number" min="0" class="mt-1 w-full rounded-md border-gray-300 text-sm" />
                </div>
            </template>

            <div>
                <label :for="id('link')" class="text-xs font-semibold text-gray-600">Link{{ inPerson ? ' (opcional)' : '' }}</label>
                <input :id="id('link')" v-model="form.link_url" type="url" class="mt-1 w-full rounded-md border-gray-300 text-sm" />
                <p v-if="form.errors.link_url" class="text-xs text-red-600">{{ form.errors.link_url }}</p>
            </div>
            <div>
                <label :for="id('material')" class="text-xs font-semibold text-gray-600">Arquivo</label>
                <input :id="id('material')" type="file" class="mt-1 w-full text-sm" @input="form.material = $event.target.files[0]" />
                <p v-if="form.errors.material" class="text-xs text-red-600">{{ form.errors.material }}</p>
            </div>

            <label class="flex items-center gap-2 text-sm sm:col-span-2">
                <input v-model="form.released" type="checkbox" class="h-4 w-4 rounded border-gray-300" />
                <span>Liberar ao público do colaborador (aparece em «Minha jornada»)</span>
            </label>

            <div class="flex flex-wrap gap-2 sm:col-span-2">
                <button type="submit" class="rounded-md bg-talents-700 px-3 py-1.5 text-sm font-semibold text-white disabled:opacity-60" :disabled="form.processing">
                    {{ contracted ? 'Salvar contratação' : 'Marcar contratado' }}
                </button>
                <button v-if="contracted" type="button" class="rounded-md border border-red-300 px-3 py-1.5 text-sm font-semibold text-red-800" @click="remove">
                    Remover contratação
                </button>
            </div>
        </form>
    </details>
</template>
