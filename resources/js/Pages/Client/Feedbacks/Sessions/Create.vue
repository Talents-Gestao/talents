<script setup>
import FeedbackPanel from '@/Components/Feedback/FeedbackPanel.vue';
import FeedbacksLayout from '@/Components/Feedback/FeedbacksLayout.vue';
import FormPageHeader from '@/Components/FormPageHeader.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { feedbackFieldClass } from '@/utils/feedbackStatus';
import { feedbackRoute } from '@/composables/useFeedbackRoutes';
import { Head, Link, useForm } from '@inertiajs/vue3';
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
        form.clearErrors('feedback_template_id');
        return;
    }

    const partial = props.templates.find((t) =>
        String(t.title ?? '').trim().toLowerCase().includes(typed),
    );
    if (partial) {
        form.feedback_template_id = partial.id;
        templateLabel.value = partial.title;
        form.clearErrors('feedback_template_id');
        return;
    }

    // Texto sem modelo correspondente: avisa em vez de trocar pelo padrão sem o usuário perceber.
    form.feedback_template_id = '';
    form.setError('feedback_template_id', 'Modelo não encontrado. Escolha um modelo da lista.');
};

const submit = () => {
    syncTemplateFromLabel();
    if (!canSubmit.value) {
        if (!props.templates.length) {
            form.setError('feedback_template_id', 'Nenhum modelo disponível. Fale com o suporte.');
        }
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

        <form class="mx-auto max-w-3xl space-y-6" @submit.prevent="submit">
            <FeedbackPanel title="Colaborador" description="Quem recebe o feedback.">
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <InputLabel for="feedback_employee_name" value="Nome do colaborador" />
                        <input
                            id="feedback_employee_name"
                            v-model="form.employee_name"
                            type="text"
                            list="feedback-employee-suggestions"
                            required
                            maxlength="255"
                            :class="feedbackFieldClass"
                            placeholder="Ex.: Maria Silva"
                            autocomplete="name"
                            aria-describedby="feedback_employee_name_help"
                            @change="onEmployeeNameInput"
                        />
                        <datalist id="feedback-employee-suggestions">
                            <option v-for="e in employees" :key="e.id" :value="e.name">
                                {{ e.email || 'Sem e-mail' }}
                            </option>
                        </datalist>
                        <InputError :message="form.errors.employee_name" />
                        <p id="feedback_employee_name_help" class="mt-1.5 text-xs text-slate-500">
                            Digite o nome. Se já existir na empresa, pode escolher na lista de sugestões.
                        </p>
                    </div>
                    <div>
                        <InputLabel for="feedback_employee_email" value="E-mail do colaborador (opcional)" />
                        <input
                            id="feedback_employee_email"
                            v-model="form.employee_email"
                            type="email"
                            maxlength="255"
                            :class="feedbackFieldClass"
                            placeholder="Para convite de assinatura digital"
                            autocomplete="email"
                            aria-describedby="feedback_employee_email_help"
                        />
                        <InputError :message="form.errors.employee_email" />
                        <p id="feedback_employee_email_help" class="mt-1.5 text-xs text-slate-500">
                            Necessário apenas se for enviar o convite de assinatura por e-mail.
                        </p>
                    </div>
                </div>
            </FeedbackPanel>

            <FeedbackPanel
                title="Alinhamento"
                description="Escolha o modelo de alinhamento (sugestões abaixo) ou mantenha o padrão Talents."
            >
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <InputLabel for="feedback_template" value="Modelo" />
                        <input
                            id="feedback_template"
                            v-model="templateLabel"
                            type="text"
                            list="feedback-template-suggestions"
                            required
                            maxlength="255"
                            :class="feedbackFieldClass"
                            placeholder="Digite ou escolha o modelo de alinhamento"
                            autocomplete="off"
                            aria-describedby="feedback_template_help"
                            @change="syncTemplateFromLabel"
                            @blur="syncTemplateFromLabel"
                        />
                        <datalist id="feedback-template-suggestions">
                            <option v-for="t in templates" :key="t.id" :value="t.title" />
                        </datalist>
                        <InputError :message="form.errors.feedback_template_id" />
                        <p id="feedback_template_help" class="mt-1.5 text-xs text-slate-500">
                            Digite parte do nome ou escolha nas sugestões. Em branco, usa-se o modelo padrão.
                        </p>
                    </div>
                    <div>
                        <InputLabel for="feedback_leader" value="Líder responsável" />
                        <select
                            id="feedback_leader"
                            v-model="form.leader_user_id"
                            required
                            :class="feedbackFieldClass"
                            aria-describedby="feedback_leader_help"
                        >
                            <option value="">Selecione</option>
                            <option v-for="l in leaders" :key="l.id" :value="l.id">{{ l.label ?? l.name }}</option>
                        </select>
                        <InputError :message="form.errors.leader_user_id" />
                        <p id="feedback_leader_help" class="mt-1.5 text-xs text-slate-500">Pessoas com acesso ativo ao sistema nesta empresa.</p>
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel for="feedback_title" value="Título (opcional)" />
                        <input id="feedback_title" v-model="form.title" :class="feedbackFieldClass" placeholder="Ex.: Feedback — Q2/2026" />
                    </div>
                    <div>
                        <InputLabel for="feedback_scheduled_at" value="Data do alinhamento" />
                        <input
                            id="feedback_scheduled_at"
                            v-model="form.scheduled_at"
                            type="datetime-local"
                            required
                            :class="feedbackFieldClass"
                        />
                        <InputError :message="form.errors.scheduled_at" />
                    </div>
                    <div>
                        <InputLabel for="feedback_next_alignment_at" value="Próximo alinhamento" />
                        <input id="feedback_next_alignment_at" v-model="form.next_alignment_at" type="date" :class="feedbackFieldClass" />
                    </div>
                </div>
            </FeedbackPanel>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-end">
                <Link
                    :href="feedbackRoute('index')"
                    class="inline-flex min-h-[40px] items-center justify-center rounded-xl px-4 text-sm font-semibold text-slate-600 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500/50"
                >
                    Cancelar
                </Link>
                <PrimaryButton type="submit" :disabled="form.processing || !canSubmit" class="disabled:cursor-not-allowed disabled:opacity-50">
                    Iniciar preenchimento
                </PrimaryButton>
            </div>
        </form>
    </FeedbacksLayout>
</template>
