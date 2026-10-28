<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    campaign: { type: Object, required: true },
    sectors: { type: Array, required: true },
    questions: { type: Array, required: true },
    maxAnswerLength: { type: Number, default: 1000 },
    submitUrl: { type: String, required: true },
});

const form = useForm({
    sector: '',
    why_work: '',
    dream: '',
    pain_self: '',
    pain_sector: '',
    pain_company: '',
});

const fieldFor = (key) => key;

const submit = () => {
    form.post(props.submitUrl);
};
</script>

<template>
    <Head :title="campaign.title" />

    <div class="app-shell min-h-screen px-4 py-10 text-slate-900">
        <div class="mx-auto max-w-2xl">
            <div class="text-center">
                <img src="/images/logo.png" alt="Talents" class="mx-auto h-12 w-auto" />
                <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-talents-700">Mapa de Propósito</p>
                <h1 class="mt-2 text-2xl font-bold text-talents-900">{{ campaign.title }}</h1>
                <p v-if="campaign.company_name" class="mt-1 text-sm text-slate-600">{{ campaign.company_name }}</p>
                <p class="mt-3 text-sm text-slate-600">
                    Respostas anónimas. Conte com sinceridade — não pedimos o seu nome.
                </p>
            </div>

            <form class="surface-glass mt-8 space-y-5 px-6 py-8" @submit.prevent="submit">
                <div>
                    <label for="sector" class="text-sm font-semibold text-slate-800">Setor</label>
                    <select
                        id="sector"
                        v-model="form.sector"
                        required
                        class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    >
                        <option value="" disabled>Selecione o seu setor</option>
                        <option v-for="s in sectors" :key="s" :value="s">{{ s }}</option>
                    </select>
                    <p v-if="form.errors.sector" class="mt-1 text-xs text-red-600">{{ form.errors.sector }}</p>
                </div>

                <div v-for="q in questions" :key="q.key">
                    <label :for="q.key" class="text-sm font-semibold text-slate-800">{{ q.label }}</label>
                    <textarea
                        :id="q.key"
                        v-model="form[fieldFor(q.key)]"
                        :maxlength="maxAnswerLength"
                        rows="4"
                        required
                        class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    />
                    <p v-if="form.errors[q.key]" class="mt-1 text-xs text-red-600">{{ form.errors[q.key] }}</p>
                </div>

                <button
                    type="submit"
                    class="w-full rounded-md bg-talents-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-talents-800 disabled:opacity-60"
                    :disabled="form.processing"
                >
                    Enviar respostas
                </button>
            </form>
        </div>
    </div>
</template>
