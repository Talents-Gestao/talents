<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

const props = defineProps({
    survey: Object,
    entryToken: String,
    audience: { type: String, default: null },
    audienceLabel: { type: String, default: null },
    invitation: { type: String, default: null },
    likertLabels: Object,
    agreementLabels: Object,
    ageRanges: Object,
    tenureRanges: Object,
    collectDemographics: { type: Boolean, default: true },
    clientDraft: { type: Boolean, default: false },
});

const sections = props.survey.template.sections.filter((s) => s.questions?.length);
const hasDepartments = Boolean(props.survey.company?.departments?.length);

// 0 = início, 1..N = uma dimensão por etapa, N+1 = revisão e envio.
const reviewStep = sections.length + 1;
const step = ref(0);
const confirmed = ref(false);
const stepError = ref('');
const heading = ref(null);

const labelsForQuestion = (q) => {
    if (q.response_scale === 'agreement' && props.agreementLabels) {
        return props.agreementLabels;
    }
    return props.likertLabels;
};

const buildAnswers = () => {
    const o = {};
    for (const s of sections) {
        for (const q of s.questions) {
            o[q.id] = null;
        }
    }
    return o;
};

const form = useForm({
    answers: buildAnswers(),
    department_id: null,
    age_range: null,
    tenure_range: null,
    invitation: props.invitation,
});

const draftKey = `nr1-draft-${props.entryToken}`;

const readDraft = () => {
    if (!props.clientDraft) return;
    try {
        const raw = window.localStorage.getItem(draftKey);
        if (!raw) return;
        const saved = JSON.parse(raw);
        for (const id of Object.keys(form.answers)) {
            if (saved.answers && saved.answers[id] >= 1 && saved.answers[id] <= 5) {
                form.answers[id] = saved.answers[id];
            }
        }
        if (saved.department_id) form.department_id = saved.department_id;
    } catch {
        // Armazenamento indisponível: o questionário funciona sem rascunho.
    }
};

const writeDraft = () => {
    if (!props.clientDraft) return;
    try {
        window.localStorage.setItem(draftKey, JSON.stringify({ answers: form.answers, department_id: form.department_id }));
    } catch {
        // sem armazenamento: segue sem rascunho
    }
};

const clearDraft = () => {
    try {
        window.localStorage.removeItem(draftKey);
    } catch {
        // nada a limpar
    }
};

onMounted(readDraft);
watch(() => [form.answers, form.department_id], writeDraft, { deep: true });

const answeredCount = computed(() => Object.values(form.answers).filter((v) => v !== null && v !== '').length);
const totalQuestions = computed(() => Object.keys(form.answers).length);
const progress = computed(() => (totalQuestions.value ? Math.round((answeredCount.value / totalQuestions.value) * 100) : 0));

const stepTitle = computed(() => {
    if (step.value === 0) return 'Antes de começar';
    if (step.value === reviewStep) return 'Revisar e enviar';
    return sections[step.value - 1].title;
});

const unansweredIn = (section) => section.questions.filter((q) => form.answers[q.id] === null || form.answers[q.id] === '');

const stepIsValid = (index) => {
    if (index === 0) {
        return !hasDepartments || (form.department_id !== null && form.department_id !== '');
    }
    if (index === reviewStep) return true;
    return unansweredIn(sections[index - 1]).length === 0;
};

const errorFor = (index) => {
    if (index === 0) return 'Selecione o seu setor para continuar.';
    return 'Responda todas as afirmações desta etapa para continuar.';
};

const goTo = async (index) => {
    step.value = index;
    stepError.value = '';
    await nextTick();
    heading.value?.focus();
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

const next = () => {
    if (!stepIsValid(step.value)) {
        stepError.value = errorFor(step.value);
        return;
    }
    goTo(step.value + 1);
};

const previous = () => goTo(Math.max(0, step.value - 1));

const submit = () => {
    for (let i = 0; i < reviewStep; i++) {
        if (!stepIsValid(i)) {
            goTo(i).then(() => {
                stepError.value = errorFor(i);
            });
            return;
        }
    }
    if (!confirmed.value) {
        stepError.value = 'Confirme o envio para concluir.';
        return;
    }

    form
        .transform((data) => ({
            ...data,
            department_id: data.department_id != null && data.department_id !== '' ? Number(data.department_id) : null,
        }))
        .post(route('survey.public.submit', props.entryToken), {
            onSuccess: clearDraft,
        });
};
</script>

<template>
    <Head :title="survey.title" />

    <div class="app-shell min-h-screen text-slate-900">
        <header class="sticky top-0 z-10 border-b border-white/40 bg-white/80 px-4 py-4 shadow-sm backdrop-blur-md">
            <div class="mx-auto flex max-w-3xl items-center gap-3">
                <img src="/images/logo.png" alt="Talents" class="h-10 w-auto" />
                <div>
                    <p class="text-xs uppercase tracking-widest text-talents-600">Pesquisa anônima NR-1</p>
                    <p class="text-lg font-semibold text-slate-900">{{ survey.title }}</p>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-4 py-6">
            <div
                class="h-2 w-full overflow-hidden rounded-full bg-slate-200/80"
                role="progressbar"
                aria-label="Progresso do questionário"
                aria-valuemin="0"
                aria-valuemax="100"
                :aria-valuenow="progress"
                :aria-valuetext="progress + '% respondido'"
            >
                <div class="h-full bg-highlight transition-all" :style="{ width: progress + '%' }" />
            </div>
            <p class="mt-2 text-xs text-slate-500">
                {{ progress }}% respondido · Etapa {{ step + 1 }} de {{ reviewStep + 1 }}
            </p>

            <form class="mt-6" @submit.prevent="submit">
                <section class="surface-card p-5 sm:p-6" :aria-labelledby="'step-title'">
                    <h1 id="step-title" ref="heading" tabindex="-1" class="text-lg font-semibold text-talents-700 focus:outline-none">
                        {{ stepTitle }}
                    </h1>

                    <!-- Início -->
                    <div v-if="step === 0" class="mt-4 space-y-5">
                        <p v-if="audienceLabel" class="inline-flex rounded-full bg-talents-50 px-3 py-1 text-xs font-semibold text-talents-800">
                            Você está respondendo pelo grupo: {{ audienceLabel }}
                        </p>

                        <div>
                            <h2 class="text-sm font-semibold text-slate-900">Objetivo</h2>
                            <p class="mt-1 text-sm text-slate-600">
                                Esta pesquisa busca entender como é o dia a dia de trabalho, para apoiar ações de prevenção e melhoria da
                                organização do trabalho. Não existem respostas certas ou erradas.
                            </p>
                        </div>
                        <div>
                            <h2 class="text-sm font-semibold text-slate-900">Sigilo</h2>
                            <p class="mt-1 text-sm text-slate-600">
                                Suas respostas são confidenciais e só aparecem agrupadas. Não coletamos nome, e-mail nem outra identificação pessoal.
                            </p>
                        </div>
                        <div>
                            <h2 class="text-sm font-semibold text-slate-900">Como responder</h2>
                            <p class="mt-1 text-sm text-slate-600">
                                São {{ totalQuestions }} afirmações em {{ sections.length }} etapas. Escolha a opção que melhor descreve a
                                sua experiência, de <strong>Nunca</strong> a <strong>Sempre</strong>. Use os botões
                                <strong>Anterior</strong> e <strong>Próxima</strong> para navegar; no fim você confirma o envio.
                            </p>
                        </div>
                        <div class="rounded-lg border border-talents-100 bg-talents-50/60 p-4">
                            <h2 class="text-sm font-semibold text-talents-800">Orientação NR-1</h2>
                            <p class="mt-1 text-sm text-slate-700">
                                A NR-1 prevê a gestão dos fatores psicossociais relacionados ao trabalho. Esta pesquisa faz parte desse
                                cuidado e não avalia pessoas: ela olha para as condições de trabalho do grupo.
                            </p>
                        </div>

                        <div v-if="hasDepartments || collectDemographics" class="grid gap-4 sm:grid-cols-2">
                            <div v-if="hasDepartments">
                                <label for="department" class="text-xs text-slate-600">Setor <span class="text-red-600">*</span></label>
                                <select
                                    id="department"
                                    v-model="form.department_id"
                                    class="mt-1 w-full rounded-lg border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                    required
                                >
                                    <option :value="null" disabled>Selecione</option>
                                    <option v-for="d in survey.company.departments" :key="d.id" :value="Number(d.id)">{{ d.name }}</option>
                                </select>
                                <p v-if="form.errors.department_id" class="mt-1 text-xs text-red-700">{{ form.errors.department_id }}</p>
                            </div>
                            <div v-if="collectDemographics">
                                <label for="age" class="text-xs text-slate-600">Faixa etária (opcional)</label>
                                <select
                                    id="age"
                                    v-model="form.age_range"
                                    class="mt-1 w-full rounded-lg border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                >
                                    <option :value="null">Prefiro não informar</option>
                                    <option v-for="(label, key) in ageRanges" :key="key" :value="key">{{ label }}</option>
                                </select>
                            </div>
                            <div v-if="collectDemographics">
                                <label for="tenure" class="text-xs text-slate-600">Tempo na empresa (opcional)</label>
                                <select
                                    id="tenure"
                                    v-model="form.tenure_range"
                                    class="mt-1 w-full rounded-lg border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                >
                                    <option :value="null">Prefiro não informar</option>
                                    <option v-for="(label, key) in tenureRanges" :key="key" :value="key">{{ label }}</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Uma dimensão por etapa -->
                    <div v-for="(section, index) in sections" v-show="step === index + 1" :key="section.id" class="mt-2">
                        <p v-if="section.description" class="mt-2 text-sm text-slate-600">{{ section.description }}</p>

                        <fieldset
                            v-for="q in section.questions"
                            :key="q.id"
                            class="mt-6 border-t border-slate-200/80 pt-4 first:border-t-0 first:pt-0"
                        >
                            <legend class="text-sm font-medium text-slate-900">{{ q.body }}</legend>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ q.response_scale === 'agreement' ? 'Escala de concordância (1 a 5)' : 'Escala de frequência (1 a 5)' }}
                            </p>
                            <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-5">
                                <label
                                    v-for="val in [1, 2, 3, 4, 5]"
                                    :key="val"
                                    class="flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 bg-slate-50/80 px-3 py-2 text-xs transition hover:border-talents-400 focus-within:border-talents-600 focus-within:ring-2 focus-within:ring-talents-300"
                                >
                                    <input
                                        v-model="form.answers[q.id]"
                                        type="radio"
                                        :name="'q-' + q.id"
                                        class="text-talents-600 focus:ring-talents-500"
                                        :value="val"
                                    />
                                    <span>{{ labelsForQuestion(q)[val] }}</span>
                                </label>
                            </div>
                        </fieldset>
                    </div>

                    <!-- Revisão -->
                    <div v-if="step === reviewStep" class="mt-4 space-y-4">
                        <p class="text-sm text-slate-600">
                            Você respondeu {{ answeredCount }} de {{ totalQuestions }} afirmações. Depois do envio não é possível alterar as respostas.
                        </p>
                        <ul class="space-y-1 text-sm text-slate-700">
                            <li v-for="(section, index) in sections" :key="section.id" class="flex items-center justify-between gap-3">
                                <span>{{ section.title }}</span>
                                <button type="button" class="text-xs font-semibold text-talents-700 underline" @click="goTo(index + 1)">Rever</button>
                            </li>
                        </ul>
                        <label class="flex items-start gap-2 text-sm text-slate-800">
                            <input v-model="confirmed" type="checkbox" class="mt-0.5 rounded border-slate-300 text-talents-600 focus:ring-talents-500" />
                            <span>Confirmo que revisei e quero enviar as minhas respostas.</span>
                        </label>
                    </div>

                    <p v-if="stepError" class="mt-4 text-sm font-medium text-red-700" role="alert">{{ stepError }}</p>
                </section>

                <div class="mt-6 flex items-center justify-between gap-3">
                    <button
                        v-if="step > 0"
                        type="button"
                        class="rounded-full border border-slate-300 bg-white px-5 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        @click="previous"
                    >
                        Anterior
                    </button>
                    <span v-else />

                    <button
                        v-if="step < reviewStep"
                        type="button"
                        class="rounded-full bg-talents-600 px-6 py-2 text-sm font-bold text-white shadow transition hover:bg-talents-700"
                        @click="next"
                    >
                        {{ step === 0 ? 'Começar' : 'Próxima' }}
                    </button>
                    <button
                        v-else
                        type="submit"
                        class="rounded-full bg-talents-600 px-6 py-2 text-sm font-bold text-white shadow transition hover:bg-talents-700 disabled:opacity-60"
                        :disabled="form.processing"
                    >
                        Enviar respostas
                    </button>
                </div>
            </form>
        </main>
    </div>
</template>
