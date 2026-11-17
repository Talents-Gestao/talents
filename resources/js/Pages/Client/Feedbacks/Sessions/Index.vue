<script setup>
import FeedbackEmptyState from '@/Components/Feedback/FeedbackEmptyState.vue';
import FeedbackFlash from '@/Components/Feedback/FeedbackFlash.vue';
import FeedbackSessionCard from '@/Components/Feedback/FeedbackSessionCard.vue';
import FeedbackSessionDeleteModal from '@/Components/Feedback/FeedbackSessionDeleteModal.vue';
import FeedbacksLayout from '@/Components/Feedback/FeedbacksLayout.vue';
import PaginationBar from '@/Components/PaginationBar.vue';
import FormPageHeader from '@/Components/FormPageHeader.vue';
import { feedbackRoute } from '@/composables/useFeedbackRoutes';
import { Head, Link } from '@inertiajs/vue3';
import { ChatBubbleLeftRightIcon, PlusIcon } from '@heroicons/vue/24/outline';
import { ref } from 'vue';

defineProps({
    sessions: Object,
    canDelete: { type: Boolean, default: false },
});

const deletingSession = ref(null);
</script>

<template>
    <Head title="Sessões de feedback" />

    <FeedbacksLayout>
        <template #header>
            <FormPageHeader
                :back-href="feedbackRoute('index')"
                back-label="Feedbacks"
                title="Sessões"
                subtitle="Todos os alinhamentos registrados, do mais recente ao mais antigo"
            >
                <template #trailing>
                    <Link
                        :href="feedbackRoute('sessions.create')"
                        class="inline-flex min-h-[40px] items-center gap-2 rounded-xl bg-talents-700 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-talents-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500 focus-visible:ring-offset-2"
                    >
                        <PlusIcon class="h-4 w-4" aria-hidden="true" />
                        Novo feedback
                    </Link>
                </template>
            </FormPageHeader>
        </template>

        <FeedbackFlash class="mb-4" />

        <div v-if="sessions.data?.length" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <FeedbackSessionCard
                v-for="s in sessions.data"
                :key="s.id"
                :session="s"
                :can-delete="canDelete"
                @delete="deletingSession = s"
            />
        </div>
        <PaginationBar v-if="sessions.data?.length" :paginator="sessions" />
        <FeedbackEmptyState
            v-else
            :icon="ChatBubbleLeftRightIcon"
            title="Nenhuma sessão registrada"
            description="Cada feedback aberto aparece aqui, com o status, o líder e a data do alinhamento."
        >
            <Link
                :href="feedbackRoute('sessions.create')"
                class="inline-flex min-h-[40px] items-center gap-2 rounded-xl bg-talents-700 px-4 py-2 text-sm font-semibold text-white hover:bg-talents-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500 focus-visible:ring-offset-2"
            >
                <PlusIcon class="h-4 w-4" aria-hidden="true" />
                Criar primeiro feedback
            </Link>
        </FeedbackEmptyState>

        <FeedbackSessionDeleteModal :session="deletingSession" @close="deletingSession = null" />
    </FeedbacksLayout>
</template>
