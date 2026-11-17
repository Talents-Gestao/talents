<script setup>
import FeedbackSessionCard from '@/Components/Feedback/FeedbackSessionCard.vue';
import FeedbackSessionDeleteModal from '@/Components/Feedback/FeedbackSessionDeleteModal.vue';
import FeedbacksLayout from '@/Components/Feedback/FeedbacksLayout.vue';
import PaginationBar from '@/Components/PaginationBar.vue';
import FormPageHeader from '@/Components/FormPageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { feedbackRoute } from '@/composables/useFeedbackRoutes';
import { Head, Link } from '@inertiajs/vue3';
import { ChatBubbleLeftRightIcon } from '@heroicons/vue/24/outline';
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
                subtitle="Todos os alinhamentos registrados"
            >
                <template #trailing>
                    <Link :href="feedbackRoute('sessions.create')">
                        <PrimaryButton type="button">Novo feedback</PrimaryButton>
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
        <div
            v-else
            class="rounded-2xl border border-dashed border-talents-200 bg-talents-50/40 px-6 py-12 text-center"
        >
            <ChatBubbleLeftRightIcon class="mx-auto h-10 w-10 text-talents-300" />
            <p class="mt-3 text-sm font-medium text-talents-900">Nenhuma sessão registrada</p>
            <Link
                :href="feedbackRoute('sessions.create')"
                class="mt-4 inline-flex rounded-xl bg-talents-700 px-4 py-2 text-sm font-semibold text-white hover:bg-talents-800"
            >
                Criar primeiro feedback
            </Link>
        </div>

        <FeedbackSessionDeleteModal :session="deletingSession" @close="deletingSession = null" />
    </FeedbacksLayout>
</template>
