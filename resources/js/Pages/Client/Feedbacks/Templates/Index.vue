<script setup>
import FeedbackEmptyState from '@/Components/Feedback/FeedbackEmptyState.vue';
import FeedbackFlash from '@/Components/Feedback/FeedbackFlash.vue';
import FeedbackTemplateDeleteModal from '@/Components/Feedback/FeedbackTemplateDeleteModal.vue';
import FeedbacksLayout from '@/Components/Feedback/FeedbacksLayout.vue';
import FormPageHeader from '@/Components/FormPageHeader.vue';
import { feedbackRoute } from '@/composables/useFeedbackRoutes';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    DocumentDuplicateIcon,
    EyeIcon,
    LockClosedIcon,
    PencilSquareIcon,
    PlusIcon,
    TrashIcon,
} from '@heroicons/vue/24/outline';
import { computed, ref } from 'vue';

const props = defineProps({
    templates: { type: Array, default: () => [] },
    permissions: { type: Object, default: () => ({ create: true, edit: true, delete: true }) },
});

const duplicatingId = ref(null);
function duplicateTemplate(template) {
    router.post(
        feedbackRoute('templates.duplicate', template.id),
        {},
        { onStart: () => (duplicatingId.value = template.id), onFinish: () => (duplicatingId.value = null) },
    );
}

const ownTemplates = computed(() => props.templates.filter((template) => template.is_own));
const talentsTemplates = computed(() => props.templates.filter((template) => !template.is_own));

// Modelo usado também pode ser excluído: o alerta mostra quantos feedbacks saem junto (calculado no servidor).
const deletingTemplate = ref(null);

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
                <template v-if="permissions.create" #trailing>
                    <Link
                        :href="feedbackRoute('templates.create')"
                        class="inline-flex min-h-[40px] items-center gap-2 rounded-xl bg-talents-700 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-talents-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500 focus-visible:ring-offset-2"
                    >
                        <PlusIcon class="h-4 w-4" aria-hidden="true" />
                        Novo modelo
                    </Link>
                </template>
            </FormPageHeader>
        </template>

        <FeedbackFlash class="mb-6" />

        <section class="space-y-3" aria-labelledby="own-templates-title">
            <div>
                <h2 id="own-templates-title" class="text-base font-semibold text-slate-900">Modelos da empresa</h2>
                <p class="mt-0.5 text-sm text-slate-600">Criados pela sua empresa; aparecem em «Novo feedback» enquanto estiverem ativos.</p>
            </div>

            <ul v-if="ownTemplates.length" class="space-y-3">
                <li
                    v-for="template in ownTemplates"
                    :key="template.id"
                    class="flex flex-col gap-4 rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm transition hover:border-talents-200 sm:flex-row sm:items-center sm:justify-between"
                    data-feedback-template
                >
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-semibold text-slate-900">{{ template.title }}</p>
                            <span
                                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ring-inset"
                                :class="template.is_active ? 'bg-emerald-50 text-emerald-800 ring-emerald-200' : 'bg-slate-100 text-slate-600 ring-slate-200'"
                            >
                                {{ template.is_active ? 'Ativo' : 'Inativo' }}
                            </span>
                            <span
                                class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ring-inset"
                                :class="template.sessions_count ? 'bg-amber-50 text-amber-800 ring-amber-200' : 'bg-slate-50 text-slate-600 ring-slate-200'"
                            >
                                <LockClosedIcon v-if="template.sessions_count" class="h-3 w-3" aria-hidden="true" />
                                {{ usageLabel(template.sessions_count) }}
                            </span>
                        </div>
                        <p v-if="template.description" class="mt-1 line-clamp-2 max-w-prose text-sm text-slate-600">{{ template.description }}</p>
                        <p class="mt-1 text-xs text-slate-500">
                            {{ template.sections_count }} {{ template.sections_count === 1 ? 'seção' : 'seções' }}
                        </p>
                    </div>
                    <div class="flex shrink-0 flex-wrap items-center gap-2 text-sm">
                        <Link
                            :href="feedbackRoute('templates.edit', template.id)"
                            class="inline-flex min-h-[36px] items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500/50"
                        >
                            <component :is="permissions.edit ? PencilSquareIcon : EyeIcon" class="h-4 w-4" aria-hidden="true" />
                            {{ permissions.edit ? 'Editar' : 'Ver' }}
                        </Link>
                        <button
                            v-if="permissions.create"
                            type="button"
                            class="inline-flex min-h-[36px] items-center gap-1.5 rounded-lg px-3 font-medium text-slate-700 transition hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500/50 disabled:opacity-60"
                            :disabled="duplicatingId !== null"
                            @click="duplicateTemplate(template)"
                        >
                            <DocumentDuplicateIcon class="h-4 w-4" aria-hidden="true" />
                            {{ duplicatingId === template.id ? 'Duplicando…' : 'Duplicar' }}
                        </button>
                        <button
                            v-if="permissions.delete"
                            type="button"
                            class="inline-flex min-h-[36px] items-center gap-1.5 rounded-lg px-3 font-medium text-slate-500 transition hover:bg-rose-50 hover:text-rose-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-400/60 sm:ml-2 sm:border-l sm:border-slate-200 sm:pl-3"
                            @click="deletingTemplate = template"
                        >
                            <TrashIcon class="h-4 w-4" aria-hidden="true" />
                            Excluir
                        </button>
                    </div>
                </li>
            </ul>
            <FeedbackEmptyState
                v-else
                :icon="DocumentDuplicateIcon"
                title="A empresa ainda não tem modelos próprios"
                description="Crie um modelo com as seções e perguntas que fazem sentido para a sua equipe."
            >
                <Link
                    v-if="permissions.create"
                    :href="feedbackRoute('templates.create')"
                    class="inline-flex min-h-[40px] items-center gap-2 rounded-xl bg-talents-700 px-4 py-2 text-sm font-semibold text-white hover:bg-talents-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500 focus-visible:ring-offset-2"
                >
                    <PlusIcon class="h-4 w-4" aria-hidden="true" />
                    Criar primeiro modelo
                </Link>
            </FeedbackEmptyState>
        </section>

        <section v-if="talentsTemplates.length" class="mt-10 space-y-3" aria-labelledby="talents-templates-title">
            <div>
                <h2 id="talents-templates-title" class="text-base font-semibold text-slate-900">Modelos da Talents</h2>
                <p class="mt-0.5 text-sm text-slate-600">
                    Disponível para novos feedbacks; não pode ser editado.
                    <template v-if="permissions.create">Duplique para criar uma versão da empresa.</template>
                </p>
            </div>
            <ul class="grid gap-3 md:grid-cols-2">
                <li
                    v-for="template in talentsTemplates"
                    :key="template.id"
                    class="flex flex-col gap-3 rounded-2xl border border-talents-100 bg-talents-50/30 p-5"
                    data-talents-template
                >
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-semibold text-slate-900">{{ template.title }}</p>
                            <span class="inline-flex items-center rounded-full bg-talents-100 px-2 py-0.5 text-xs font-semibold text-talents-800">
                                Talents
                            </span>
                        </div>
                        <p v-if="template.description" class="mt-1 line-clamp-3 text-sm text-slate-600">{{ template.description }}</p>
                    </div>
                    <button
                        v-if="permissions.create"
                        type="button"
                        class="mt-auto inline-flex min-h-[36px] items-center gap-1.5 self-start rounded-lg border border-talents-200 bg-white px-3 text-sm font-semibold text-talents-800 transition hover:bg-talents-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500/50 disabled:opacity-60"
                        :disabled="duplicatingId !== null"
                        @click="duplicateTemplate(template)"
                    >
                        <DocumentDuplicateIcon class="h-4 w-4" aria-hidden="true" />
                        {{ duplicatingId === template.id ? 'Duplicando…' : 'Duplicar' }}
                    </button>
                </li>
            </ul>
        </section>

        <FeedbackTemplateDeleteModal :template="deletingTemplate" @close="deletingTemplate = null" />
    </FeedbacksLayout>
</template>
