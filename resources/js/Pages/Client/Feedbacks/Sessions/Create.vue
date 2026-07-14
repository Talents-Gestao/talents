<script setup>
import FeedbacksLayout from '@/Components/Feedback/FeedbacksLayout.vue';
import FormPageHeader from '@/Components/FormPageHeader.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { feedbackFieldClass } from '@/utils/feedbackStatus';
import { feedbackRoute } from '@/composables/useFeedbackRoutes';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    employees: { type: Array, default: () => [] },
    leaders: { type: Array, default: () => [] },
    templates: { type: Array, default: () => [] },
    rhidReady: { type: Boolean, default: false },
});

const form = useForm({
    rhid_person_id: props.employees[0]?.id ?? '',
    feedback_template_id: props.templates.find((t) => t.is_default)?.id ?? props.templates[0]?.id ?? '',
    leader_user_id: '',
    scheduled_at: '',
    next_alignment_at: '',
    title: '',
});

const canSubmit = computed(
    () => Boolean(String(form.scheduled_at ?? '').trim()) && props.employees.length > 0,
);

const emptyHint = computed(() => {
    if (!props.rhidReady) {
        return 'Configure a integração RHID (Control iD) da empresa para listar colaboradores.';
    }
    return 'Nenhum colaborador ativo encontrado no RHID/Control iD.';
});

const submit = () => {
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
                subtitle="Escolha o colaborador e o modelo de alinhamento"
            />
        </template>

        <form
            class="mx-auto max-w-2xl overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm"
            @submit.prevent="submit"
        >
            <div class="border-b border-slate-100 bg-gradient-to-r from-talents-50/80 to-white px-6 py-5">
                <p class="text-sm text-slate-600">O formulário seguirá o modelo Talents «Contrato de Expectativas».</p>
            </div>

            <div class="space-y-5 p-6">
                <div>
                    <InputLabel value="Colaborador" />
                    <select v-model="form.rhid_person_id" required :disabled="!employees.length" :class="feedbackFieldClass">
                        <option value="" disabled>Selecione</option>
                        <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
                    </select>
                    <InputError :message="form.errors.rhid_person_id" />
                    <p v-if="!employees.length" class="mt-2 text-xs text-amber-700">{{ emptyHint }}</p>
                </div>
                <div>
                    <InputLabel value="Modelo" />
                    <select v-model="form.feedback_template_id" required :class="feedbackFieldClass">
                        <option v-for="t in templates" :key="t.id" :value="t.id">{{ t.title }}</option>
                    </select>
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
