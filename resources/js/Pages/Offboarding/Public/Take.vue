<script setup>
import ExitInterviewAccordions from '@/Components/Offboarding/ExitInterviewAccordions.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

const props = defineProps({
    token: { type: String, required: true },
    employeeName: { type: String, default: '' },
    companyName: { type: String, default: '' },
    sections: { type: Array, default: () => [] },
    answers: { type: Object, default: () => ({}) },
});

const fieldClass =
    'mt-1 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/60';

const emptyAnswers = () => {
    const answers = {};
    props.sections.forEach((section) => {
        section.questions.forEach((q) => {
            answers[q.key] = props.answers?.[q.key] ?? '';
        });
    });
    return answers;
};

const form = useForm({
    answers: reactive(emptyAnswers()),
});

const canSubmit = computed(() =>
    Object.values(form.answers).some((v) => String(v ?? '').trim() !== ''),
);

const submit = () => {
    if (!canSubmit.value) {
        return;
    }
    form.post(route('desligamento.public.submit', props.token));
};
</script>

<template>
    <Head title="Pesquisa de Desligamento" />

    <div class="app-shell min-h-screen text-slate-900">
        <header class="sticky top-0 z-10 border-b border-white/40 bg-white/80 px-4 py-4 shadow-sm backdrop-blur-md">
            <div class="mx-auto flex max-w-3xl items-center gap-3">
                <img src="/images/logo.png" alt="Talents" class="h-10 w-auto" />
                <div>
                    <p class="text-xs uppercase tracking-widest text-talents-600">Pesquisa de desligamento</p>
                    <p class="text-lg font-semibold text-slate-900">{{ companyName || 'Talents' }}</p>
                </div>
            </div>
        </header>

        <div class="mx-auto max-w-3xl space-y-4 px-4 py-6">
            <div class="rounded-2xl border border-talents-100 bg-white/90 p-5 shadow-sm">
                <p class="text-sm text-slate-700">
                    Olá{{ employeeName ? `, ${employeeName}` : '' }}. Responda com tranquilidade — suas respostas
                    ajudam a empresa a melhorar. Não é necessário login.
                </p>
            </div>

            <form class="space-y-3" @submit.prevent="submit">
                <ExitInterviewAccordions
                    mode="form"
                    hide-consultant-notes
                    :sections="sections"
                    :answers="form.answers"
                    :field-class="fieldClass"
                />

                <InputError v-if="form.errors.answers" class="text-sm" :message="form.errors.answers" />

                <div class="flex justify-end gap-2 pb-8">
                    <PrimaryButton type="submit" :disabled="!canSubmit || form.processing">
                        Enviar respostas
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </div>
</template>
