<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    token: String,
    companyName: String,
});

const form = useForm({
    protocol: '',
});

const submit = () => {
    form.post(route('denuncia.track.lookup', props.token));
};
</script>

<template>
    <Head title="Acompanhar denúncia" />

    <div class="app-shell min-h-screen px-4 py-12 text-slate-900">
        <div class="surface-card mx-auto max-w-md p-8">
            <h1 class="text-lg font-semibold text-slate-900">Acompanhar denúncia</h1>
            <p class="mt-1 text-sm text-slate-600">{{ companyName }}</p>

            <form class="mt-6 space-y-4" @submit.prevent="submit">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Número de protocolo</label>
                    <input
                        v-model="form.protocol"
                        type="text"
                        required
                        autocomplete="off"
                        class="mt-1 w-full rounded-lg border-slate-200 text-sm tracking-wide shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        placeholder="DEN-2026-00001"
                    />
                    <p v-if="form.errors.protocol" class="mt-1 text-sm text-red-600">{{ form.errors.protocol }}</p>
                </div>
                <button
                    type="submit"
                    class="w-full rounded-full bg-talents-700 py-2.5 text-sm font-semibold text-white shadow-md hover:bg-talents-800"
                    :disabled="form.processing"
                >
                    Consultar
                </button>
            </form>

            <p class="mt-6 text-center text-sm">
                <Link :href="route('denuncia.create', token)" class="text-talents-700 hover:underline">Fazer uma denúncia</Link>
            </p>
        </div>
    </div>
</template>
