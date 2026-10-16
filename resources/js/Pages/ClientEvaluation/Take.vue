<script setup>
import InputError from '@/Components/InputError.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    evaluation: { type: Object, required: true },
});

const form = useForm({
    nps_score: null,
    service_score: null,
    delivery_score: null,
    communication_score: null,
    comment: '',
    answered_name: props.evaluation.respondent_name ?? '',
    answered_role: '',
});

const dimensions = [
    {
        key: 'service_score',
        label: 'Atendimento',
        hint: 'Como foi o contato com o time Talents?',
    },
    {
        key: 'delivery_score',
        label: 'Qualidade da entrega',
        hint: 'O resultado atendeu ao que foi combinado?',
    },
    {
        key: 'communication_score',
        label: 'Comunicação',
        hint: 'A comunicação foi clara e no tempo certo?',
    },
];

const npsScale = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
const stars = [1, 2, 3, 4, 5];

const submit = () => {
    form.post(route('client-evaluation.public.submit', props.evaluation.public_token));
};

const scoreButtonClass = (active) =>
    active
        ? 'border-talents-600 bg-talents-600 text-white'
        : 'border-slate-200 bg-white text-slate-700 hover:border-talents-300';
</script>

<template>
    <Head title="Avaliação Talents" />

    <div class="app-shell min-h-screen text-slate-900">
        <header class="border-b border-white/40 bg-white/80 px-4 py-4 shadow-sm backdrop-blur-md">
            <div class="mx-auto flex max-w-2xl items-center gap-3">
                <img src="/images/logo.png" alt="Talents" class="h-10 w-auto" />
                <div>
                    <p class="text-xs uppercase tracking-widest text-talents-600">Avaliação</p>
                    <p class="text-lg font-semibold text-slate-900">{{ evaluation.company_name }}</p>
                </div>
            </div>
        </header>

        <form class="mx-auto max-w-2xl space-y-6 px-4 py-8" @submit.prevent="submit">
            <p class="text-sm text-slate-600">
                Sua opinião sobre o trabalho da Talents no ciclo
                <span class="font-semibold text-slate-900">{{ evaluation.cycle_label }}</span>.
                Leva cerca de 2 minutos e este link aceita uma resposta.
            </p>

            <fieldset class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <legend class="px-1 text-sm font-semibold text-slate-900">
                    De 0 a 10, o quanto você recomendaria a Talents?
                </legend>
                <div class="mt-4 grid grid-cols-6 gap-2 sm:grid-cols-11" role="group" aria-label="Nota de recomendação">
                    <button
                        v-for="score in npsScale"
                        :key="score"
                        type="button"
                        class="rounded-xl border px-2 py-2 text-sm font-semibold transition"
                        :class="scoreButtonClass(form.nps_score === score)"
                        :aria-pressed="form.nps_score === score"
                        @click="form.nps_score = score"
                    >
                        {{ score }}
                    </button>
                </div>
                <div class="mt-2 flex justify-between text-xs text-slate-500">
                    <span>Não recomendaria</span>
                    <span>Recomendaria muito</span>
                </div>
                <InputError class="mt-2" :message="form.errors.nps_score" />
            </fieldset>

            <fieldset
                v-for="dimension in dimensions"
                :key="dimension.key"
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
            >
                <legend class="px-1 text-sm font-semibold text-slate-900">{{ dimension.label }}</legend>
                <p class="mt-1 text-sm text-slate-500">{{ dimension.hint }}</p>
                <div class="mt-4 grid grid-cols-5 gap-2" role="group" :aria-label="dimension.label">
                    <button
                        v-for="score in stars"
                        :key="score"
                        type="button"
                        class="rounded-xl border px-2 py-2 text-sm font-semibold transition"
                        :class="scoreButtonClass(form[dimension.key] === score)"
                        :aria-pressed="form[dimension.key] === score"
                        @click="form[dimension.key] = score"
                    >
                        {{ score }}
                    </button>
                </div>
                <InputError class="mt-2" :message="form.errors[dimension.key]" />
            </fieldset>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <label for="comment" class="text-sm font-semibold text-slate-900">Comentário</label>
                <textarea
                    id="comment"
                    v-model="form.comment"
                    rows="4"
                    maxlength="2000"
                    placeholder="O que funcionou bem e o que podemos melhorar?"
                    class="mt-3 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                />
                <InputError class="mt-2" :message="form.errors.comment" />
            </div>

            <div class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:grid-cols-2">
                <div>
                    <label for="answered_name" class="text-sm font-semibold text-slate-900">Seu nome</label>
                    <input
                        id="answered_name"
                        v-model="form.answered_name"
                        type="text"
                        required
                        maxlength="120"
                        class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    />
                    <InputError class="mt-2" :message="form.errors.answered_name" />
                </div>
                <div>
                    <label for="answered_role" class="text-sm font-semibold text-slate-900">Cargo</label>
                    <input
                        id="answered_role"
                        v-model="form.answered_role"
                        type="text"
                        maxlength="120"
                        class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    />
                    <InputError class="mt-2" :message="form.errors.answered_role" />
                </div>
            </div>

            <button
                type="submit"
                class="inline-flex w-full items-center justify-center rounded-xl bg-talents-600 px-4 py-3 text-sm font-semibold text-white hover:bg-talents-700 disabled:opacity-60 sm:w-auto"
                :disabled="form.processing"
            >
                {{ form.processing ? 'Enviando…' : 'Enviar avaliação' }}
            </button>
        </form>
    </div>
</template>
