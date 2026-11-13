<script setup>
import FeedbacksLayout from '@/Components/Feedback/FeedbacksLayout.vue';
import FormPageHeader from '@/Components/FormPageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { confirmDialog } from '@/composables/useConfirmDialog';
import { feedbackRoute } from '@/composables/useFeedbackRoutes';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    templates: { type: Array, default: () => [] },
});

const ownTemplates = computed(() => props.templates.filter((template) => template.is_own));
const talentsTemplates = computed(() => props.templates.filter((template) => !template.is_own));

async function destroyTemplate(template) {
    if (!(await confirmDialog(`Excluir o modelo "${template.title}"?`))) {
        return;
    }
    router.delete(feedbackRoute('templates.destroy', template.id), { preserveScroll: true });
}

function usageLabel(count) {
    if (!count) return 'Ainda não usado';
    return count === 1 ? 'Usado em 1 feedback' : `Usado em ${count} feedbacks`;
}
</script>

<template>
    <Head title="Modelos de feedback" />

    <FeedbacksLayout>
        <template #header>
            <FormPageHeader
                :back-href="feedbackRoute('index')"
                back-label="Feedbacks"
                title="Modelos de feedback"
                subtitle="Modelos da sua empresa e modelos da Talents"
            >
                <template #trailing>
                    <Link :href="feedbackRoute('templates.create')">
                        <PrimaryButton type="button">Novo modelo</PrimaryButton>
                    </Link>
                </template>
            </FormPageHeader>
        </template>

        <div
            v-if="$page.props.flash?.success"
            class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-900"
        >
            {{ $page.props.flash.success }}
        </div>
        <div
            v-if="$page.props.flash?.error"
            class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-2 text-sm text-rose-900"
            role="alert"
        >
            {{ $page.props.flash.error }}
        </div>

        <section class="space-y-3" aria-labelledby="own-templates-title">
            <h2 id="own-templates-title" class="text-sm font-semibold text-slate-900">Modelos da empresa</h2>

            <ul v-if="ownTemplates.length" class="space-y-2">
                <li
                    v-for="template in ownTemplates"
                    :key="template.id"
                    class="surface-card flex flex-wrap items-center justify-between gap-3 p-4"
                    data-feedback-template
                >
                    <div class="min-w-0">
                        <p class="font-medium text-slate-900">
                            {{ template.title }}
                            <span
                                v-if="!template.is_active"
                                class="ml-2 rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600"
                            >
                                Inativo
                            </span>
                        </p>
                        <p v-if="template.description" class="mt-0.5 text-sm text-slate-600">{{ template.description }}</p>
                        <p class="mt-1 text-xs text-slate-500">
                            {{ template.sections_count }} {{ template.sections_count === 1 ? 'seção' : 'seções' }} ·
                            {{ usageLabel(template.sessions_count) }}
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-3 text-sm">
                        <Link :href="feedbackRoute('templates.edit', template.id)" class="font-medium text-talents-700 hover:underline">
                            Editar
                        </Link>
                        <button
                            v-if="!template.sessions_count"
                            type="button"
                            class="font-medium text-rose-700 hover:underline"
                            @click="destroyTemplate(template)"
                        >
                            Excluir
                        </button>
                    </div>
                </li>
            </ul>
            <div
                v-else
                class="rounded-2xl border border-dashed border-talents-200 bg-talents-50/40 px-6 py-10 text-center"
            >
                <p class="text-sm font-medium text-talents-900">A empresa ainda não tem modelos próprios</p>
                <p class="mt-1 text-sm text-slate-600">Crie um modelo com as seções e perguntas que fazem sentido para a sua equipe.</p>
                <Link
                    :href="feedbackRoute('templates.create')"
                    class="mt-4 inline-flex rounded-xl bg-talents-700 px-4 py-2 text-sm font-semibold text-white hover:bg-talents-800"
                >
                    Criar primeiro modelo
                </Link>
            </div>
        </section>

        <section v-if="talentsTemplates.length" class="mt-8 space-y-3" aria-labelledby="talents-templates-title">
            <h2 id="talents-templates-title" class="text-sm font-semibold text-slate-900">Modelos da Talents</h2>
            <ul class="space-y-2">
                <li v-for="template in talentsTemplates" :key="template.id" class="surface-card p-4">
                    <p class="font-medium text-slate-900">{{ template.title }}</p>
                    <p v-if="template.description" class="mt-0.5 text-sm text-slate-600">{{ template.description }}</p>
                    <p class="mt-1 text-xs text-slate-500">Disponível para novos feedbacks; não pode ser editado.</p>
                </li>
            </ul>
        </section>
    </FeedbacksLayout>
</template>
