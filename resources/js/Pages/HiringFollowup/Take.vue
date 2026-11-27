<script setup>
import InputError from '@/Components/InputError.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    survey: { type: Object, required: true },
});

const form = useForm({
    rating: null,
    comment: '',
    support_requested: false,
    support_note: '',
});

const stars = [1, 2, 3, 4, 5];
const isManager = computed(() => props.survey.audience === 'manager');
const question = computed(() =>
    isManager.value
        ? `Como você avalia a adaptação de ${props.survey.subject_name ?? 'quem foi contratado'} até agora?`
        : `Como está sendo a sua experiência${props.survey.company_name ? ` em ${props.survey.company_name}` : ''} até agora?`,
);

const submit = () => {
    form.post(route('hiring-followup.public.submit', props.survey.token));
};
</script>

<template>
    <Head title="Acompanhamento Talents" />

    <div class="app-shell min-h-screen text-slate-900">
        <header class="border-b border-white/40 bg-white/80 px-4 py-4 shadow-sm backdrop-blur-md">
            <div class="mx-auto flex max-w-2xl items-center gap-3">
                <img src="/images/logo.png" alt="Talents" class="h-10 w-auto" />
                <div>
                    <p class="text-xs uppercase tracking-widest text-talents-600">Acompanhamento {{ survey.moment_label }}</p>
                    <p class="text-lg font-semibold text-slate-900">{{ survey.company_name }}</p>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-2xl px-4 py-8">
            <form class="surface-glass space-y-6 p-6 sm:p-8" @submit.prevent="submit">
                <div>
                    <p class="text-lg font-semibold text-slate-900">
                        {{ survey.greeting_name ? `Olá, ${survey.greeting_name}!` : 'Olá!' }}
                    </p>
                    <p class="mt-1 text-sm text-slate-600">
                        <template v-if="isManager">A Talents quer saber como está a chegada da pessoa contratada. Leva menos de um minuto.</template>
                        <template v-else>
                            A Talents quer saber como você está. Leva menos de um minuto e a sua resposta é confidencial: não é
                            compartilhada com o seu gestor nem com a empresa.
                        </template>
                    </p>
                </div>

                <fieldset>
                    <legend class="text-sm font-semibold text-slate-800">{{ question }}</legend>
                    <div class="mt-3 flex gap-2" role="radiogroup" aria-label="Nota de 1 a 5 estrelas">
                        <button
                            v-for="n in stars"
                            :key="n"
                            type="button"
                            role="radio"
                            :aria-checked="form.rating === n"
                            :aria-label="`${n} ${n === 1 ? 'estrela' : 'estrelas'}`"
                            class="flex h-12 w-12 items-center justify-center rounded-xl border text-2xl transition"
                            :class="form.rating !== null && n <= form.rating ? 'border-amber-400 bg-amber-50 text-amber-500' : 'border-slate-200 bg-white text-slate-300 hover:border-amber-300'"
                            @click="form.rating = n"
                        >
                            ★
                        </button>
                    </div>
                    <InputError class="mt-2" :message="form.errors.rating" />
                </fieldset>

                <div>
                    <label for="comment" class="text-sm font-semibold text-slate-800">Comentário (opcional)</label>
                    <textarea
                        id="comment"
                        v-model="form.comment"
                        rows="4"
                        maxlength="2000"
                        class="mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/70"
                    />
                    <p class="mt-1 text-xs text-slate-500">{{ form.comment.length }}/2.000</p>
                    <InputError class="mt-1" :message="form.errors.comment" />
                </div>

                <div class="rounded-xl border border-slate-200 bg-white/70 p-4">
                    <label class="flex items-start gap-2 text-sm font-semibold text-slate-800">
                        <input v-model="form.support_requested" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-talents-600" />
                        Preciso de apoio da Talents
                    </label>
                    <div v-if="form.support_requested" class="mt-3">
                        <label for="support_note" class="text-xs font-semibold text-slate-700">Conte, se quiser, em que podemos ajudar (opcional)</label>
                        <textarea
                            id="support_note"
                            v-model="form.support_note"
                            rows="3"
                            maxlength="2000"
                            class="mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/70"
                        />
                        <InputError class="mt-1" :message="form.errors.support_note" />
                    </div>
                </div>

                <button
                    type="submit"
                    class="w-full rounded-xl bg-talents-600 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-talents-700 disabled:opacity-50"
                    :disabled="form.processing || form.rating === null"
                >
                    Enviar resposta
                </button>
            </form>
        </main>
    </div>
</template>
