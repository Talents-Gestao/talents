<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    survey: Object,
});

const allQuestions = computed(() => {
    const list = [];
    for (const s of props.survey.template.sections) {
        for (const q of s.questions) {
            list.push(q);
        }
    }
    return list;
});

const buildAnswers = () => {
    const o = {};
    for (const q of allQuestions.value) {
        o[q.id] = q.type === 'scale' ? null : '';
    }
    return o;
};

const form = useForm({
    email: '',
    department_id: null,
    answers: buildAnswers(),
});

const steps = computed(() => {
    const list = [];
    if (props.survey.collect_email) {
        list.push({ type: 'email' });
    }
    for (const s of props.survey.template.sections) {
        list.push({ type: 'section', section: s });
    }
    return list;
});

const currentStep = ref(0);

const currentStepData = computed(() => steps.value[currentStep.value]);

const totalAnswerable = computed(() => allQuestions.value.length);

const progress = computed(() => {
    const vals = Object.values(form.answers);
    const done = vals.filter((v) => v !== null && v !== '').length;
    return totalAnswerable.value ? Math.round((done / totalAnswerable.value) * 100) : 0;
});

const canGoNext = computed(() => {
    const step = currentStepData.value;
    if (!step) {
        return false;
    }
    if (step.type === 'email') {
        if (!props.survey.collect_email) {
            return true;
        }
        return Boolean(form.email && String(form.email).includes('@'));
    }
    const qs = step.section.questions;
    for (const q of qs) {
        const v = form.answers[q.id];
        if (q.is_required && (v === null || v === '')) {
            return false;
        }
    }
    return true;
});

const validateAll = computed(() => {
    if (props.survey.collect_email && (!form.email || !String(form.email).includes('@'))) {
        return false;
    }
    for (const q of allQuestions.value) {
        const v = form.answers[q.id];
        if (q.is_required && (v === null || v === '')) {
            return false;
        }
    }
    return true;
});

const next = () => {
    if (!canGoNext.value) {
        return;
    }
    if (currentStep.value < steps.value.length - 1) {
        currentStep.value += 1;
    }
};

const prev = () => {
    if (currentStep.value > 0) {
        currentStep.value -= 1;
    }
};

const submit = () => {
    if (!validateAll.value) {
        return;
    }
    form.post(route('methodology.public.submit', props.survey.public_token));
};

const isLastStep = computed(() => currentStep.value === steps.value.length - 1);
</script>

<template>
    <Head :title="survey.title" />

    <div class="min-h-screen bg-gradient-to-b from-talents-50 to-white text-gray-900">
        <div class="fixed left-0 right-0 top-0 z-10 h-1.5 bg-talents-100">
            <div class="h-full bg-talents-600 transition-all duration-300" :style="{ width: progress + '%' }" />
        </div>

        <header class="border-b border-talents-100 bg-white/90 px-4 py-6 shadow-sm backdrop-blur">
            <div class="mx-auto flex max-w-2xl flex-col items-center text-center">
                <img src="/images/logo.png" alt="Talents" class="h-12 w-auto" />
                <p class="mt-3 text-xs font-semibold uppercase tracking-widest text-talents-600">Pesquisa de satisfação</p>
                <h1 class="mt-2 text-xl font-bold text-talents-900 sm:text-2xl">{{ survey.title }}</h1>
            </div>
        </header>

        <div class="mx-auto max-w-2xl px-4 py-6">
            <p class="text-center text-sm text-gray-600">Suas respostas são confidenciais e ajudam a melhorar o ambiente de trabalho.</p>
            <p class="mt-2 text-center text-xs text-gray-500">{{ progress }}% concluído</p>

            <form class="mt-8 space-y-8" @submit.prevent="isLastStep ? submit() : next()">
                <div v-if="currentStepData?.type === 'email'" class="rounded-2xl border border-talents-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-talents-900">Identificação</h2>
                    <p class="mt-1 text-sm text-gray-600">Informe seu e-mail para esta pesquisa.</p>
                    <label class="mt-4 block text-sm font-medium text-gray-700">E-mail</label>
                    <input
                        v-model="form.email"
                        type="email"
                        required
                        class="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        autocomplete="email"
                    />
                    <div v-if="survey.company?.departments?.length" class="mt-4">
                        <label class="block text-sm font-medium text-gray-700">Setor (opcional)</label>
                        <select
                            v-model="form.department_id"
                            class="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        >
                            <option :value="null">Prefiro não informar</option>
                            <option v-for="d in survey.company.departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                        </select>
                    </div>
                </div>

                <div v-else-if="currentStepData?.type === 'section'" class="rounded-2xl border border-talents-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-talents-900">{{ currentStepData.section.title }}</h2>
                    <p v-if="currentStepData.section.description" class="mt-1 text-sm text-gray-600">{{ currentStepData.section.description }}</p>

                    <div v-if="!survey.collect_email && currentStep === 0 && survey.company?.departments?.length" class="mt-4">
                        <label class="block text-sm font-medium text-gray-700">Setor (opcional)</label>
                        <select
                            v-model="form.department_id"
                            class="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        >
                            <option :value="null">Prefiro não informar</option>
                            <option v-for="d in survey.company.departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                        </select>
                    </div>

                    <div class="mt-6 space-y-8">
                        <div v-for="q in currentStepData.section.questions" :key="q.id" class="border-b border-gray-100 pb-6 last:border-0">
                            <p class="text-sm font-medium text-gray-900">
                                {{ q.body }}
                                <span v-if="q.is_required" class="text-red-500">*</span>
                            </p>
                            <div v-if="q.type === 'scale'" class="mt-3 flex flex-wrap gap-2">
                                <button
                                    v-for="n in q.scale_max - q.scale_min + 1"
                                    :key="q.id + '-' + n"
                                    type="button"
                                    class="flex h-11 w-11 items-center justify-center rounded-full border-2 text-sm font-semibold transition"
                                    :class="
                                        form.answers[q.id] === q.scale_min + n - 1
                                            ? 'border-talents-600 bg-talents-600 text-white shadow-md'
                                            : 'border-talents-200 bg-white text-talents-800 hover:border-talents-400'
                                    "
                                    @click="form.answers[q.id] = q.scale_min + n - 1"
                                >
                                    {{ q.scale_min + n - 1 }}
                                </button>
                            </div>
                            <textarea
                                v-else
                                v-model="form.answers[q.id]"
                                rows="4"
                                class="mt-3 w-full rounded-lg border-gray-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                :required="q.is_required"
                            />
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <button
                        v-if="currentStep > 0"
                        type="button"
                        class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        @click="prev"
                    >
                        Anterior
                    </button>
                    <span v-else />

                    <button
                        v-if="!isLastStep"
                        type="submit"
                        :disabled="!canGoNext || form.processing"
                        class="rounded-lg bg-talents-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-talents-700 disabled:opacity-50"
                    >
                        Próximo
                    </button>
                    <button
                        v-else
                        type="button"
                        :disabled="!validateAll || form.processing"
                        class="rounded-lg bg-talents-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-talents-700 disabled:opacity-50"
                        @click="submit"
                    >
                        Enviar respostas
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
