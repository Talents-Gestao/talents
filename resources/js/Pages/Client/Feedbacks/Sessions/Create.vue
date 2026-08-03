<script setup>
import FeedbacksLayout from '@/Components/Feedback/FeedbacksLayout.vue';
import FormPageHeader from '@/Components/FormPageHeader.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { feedbackFieldClass } from '@/utils/feedbackStatus';
import { feedbackRoute } from '@/composables/useFeedbackRoutes';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    leaders: { type: Array, default: () => [] },
    templates: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
});

const defaultTemplate =
    props.templates.find((t) => t.is_default) ?? props.templates[0] ?? null;

const form = useForm({
    employee_name: '',
    employee_email: '',
    feedback_template_id: defaultTemplate?.id ?? '',
    leader_user_id: '',
    scheduled_at: '',
    next_alignment_at: '',
    title: '',
});

const templateLabel = ref(defaultTemplate?.title ?? '');

const canSubmit = computed(
    () =>
        Boolean(String(form.employee_name ?? '').trim()) &&
        Boolean(String(form.scheduled_at ?? '').trim()) &&
        Boolean(form.feedback_template_id),
);

const onEmployeeNameInput = () => {
    const name = String(form.employee_name ?? '').trim().toLowerCase();
    if (!name) {
        return;
    }
    const match = props.employees.find((e) => String(e.name ?? '').trim().toLowerCase() === name);
    if (match?.email && !String(form.employee_email ?? '').trim()) {
        form.employee_email = match.email;
    }
};

const syncTemplateFromLabel = () => {
    const typed = String(templateLabel.value ?? '').trim().toLowerCase();
    if (!typed) {
        form.feedback_template_id = defaultTemplate?.id ?? '';
        templateLabel.value = defaultTemplate?.title ?? '';
        return;
    }

    const exact = props.templates.find((t) => String(t.title ?? '').trim().toLowerCase() === typed);
    if (exact) {
        form.feedback_template_id = exact.id;
        templateLabel.value = exact.title;
        return;
    }

    const partial = props.templates.find((t) =>
        String(t.title ?? '').trim().toLowerCase().includes(typed),
    );
    if (partial) {
        form.feedback_template_id = partial.id;
        templateLabel.value = partial.title;
        return;
    }

    form.feedback_template_id = defaultTemplate?.id ?? '';
    templateLabel.value = defaultTemplate?.title ?? templateLabel.value;
};

const submit = () => {
    syncTemplateFromLabel();
    if (!canSubmit.value) {
        return;
    }

    form.post(feedbackRoute('sessions.store'));
};
</script>

<template>
    <Head title="Novo feedback" />

    <FeedbacksLayout>
        <template #header>
            <FormPageHeader
                :back-href="feedbackRoute('index')"
                back-label="Feedbacks"
                title="Novo feedback"
                subtitle="Informe o colaborador e o modelo de alinhamento"
            />
        </template>

        <form
            class="mx-auto max-w-2xl overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm"
            @submit.prevent="submit"
        >
            <div class="border-b border-slate-100 bg-gradient-to-r from-talents-50/80 to-white px-6 py-5">
                <p class="text-sm text-slate-600">
                    Escolha o modelo de alinhamento (sugestões abaixo) ou mantenha o padrão Talents.
                </p>
            </div>

            <div class="space-y-5 p-6">
                <div>
                    <InputLabel value="Nome do colaborador" />
                    <input
                        v-model="form.employee_name"
                        type="text"
                        list="feedback-employee-suggestions"
                        required
                        maxlength="255"
                        :class="feedbackFieldClass"
                        placeholder="Ex.: Maria Silva"
                        autocomplete="name"
                        @change="onEmployeeNameInput"
                    />
                    <datalist id="feedback-employee-suggestions">
                        <option v-for="e in employees" :key="e.id" :value="e.name">
                            {{ e.email || 'Sem e-mail' }}
                        </option>
                    </datalist>
                    <InputError :message="form.errors.employee_name" />
                    <p class="mt-1.5 text-xs text-slate-500">
                        Digite o nome. Se já existir na empresa, pode escolher na lista de sugestões.
                    </p>
                </div>
                <div>
                    <InputLabel value="E-mail do colaborador (opcional)" />
                    <input
                        v-model="form.employee_email"
                        type="email"
                        maxlength="255"
                        :class="feedbackFieldClass"
                        placeholder="Para convite de assinatura digital"
                        autocomplete="email"
                    />
                    <InputError :message="form.errors.employee_email" />
                    <p class="mt-1.5 text-xs text-slate-500">
                        Necessário apenas se for enviar o convite de assinatura por e-mail.
                    </p>
                </div>
                <div>
                    <InputLabel value="Modelo" />
                    <input
                        v-model="templateLabel"
                        type="text"
                        list="feedback-template-suggestions"
                        required
                        maxlength="255"
                        :class="feedbackFieldClass"
                        placeholder="Digite ou escolha o modelo de alinhamento"
                        autocomplete="off"
                        @change="syncTemplateFromLabel"
                        @blur="syncTemplateFromLabel"
                    />
                    <datalist id="feedback-template-suggestions">
                        <option v-for="t in templates" :key="t.id" :value="t.title" />
                    </datalist>
                    <InputError :message="form.errors.feedback_template_id" />
                    <p class="mt-1.5 text-xs text-slate-500">
                        Campo aberto com sugestões. Se o texto não bater com um modelo, usa-se o padrão Talents.
                    </p>
                </div>
                <div>
                    <InputLabel value="Líder responsável" />
                    <select v-model="form.leader_user_id" required :class="feedbackFieldClass">
                        <option value="">Selecione</option>
                        <option v-for="l in leaders" :key="l.id" :value="l.id">{{ l.name }}</option>
                    </select>
                </div>
                <div>
                    <InputLabel value="Título (opcional)" />
                    <input v-model="form.title" :class="feedbackFieldClass" placeholder="Ex.: Feedback — Q2/2026" />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel value="Data do alinhamento" />
                        <input
                            v-model="form.scheduled_at"
                            type="datetime-local"
                            required
                            :class="feedbackFieldClass"
                        />
                        <InputError :message="form.errors.scheduled_at" />
                    </div>
                    <div>
                        <InputLabel value="Próximo alinhamento" />
                        <input v-model="form.next_alignment_at" type="date" :class="feedbackFieldClass" />
                    </div>
                </div>
            </div>

            <div class="border-t border-slate-100 bg-slate-50/50 px-6 py-4">
                <PrimaryButton type="submit" :disabled="form.processing || !canSubmit">
                    Iniciar preenchimento
                </PrimaryButton>
            </div>
        </form>
    </FeedbacksLayout>
</template>
