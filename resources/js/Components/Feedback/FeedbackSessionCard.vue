<script setup>
import FeedbackStatusBadge from '@/Components/Feedback/FeedbackStatusBadge.vue';
import { feedbackRoute } from '@/composables/useFeedbackRoutes';
import { Link } from '@inertiajs/vue3';
import { UserIcon } from '@heroicons/vue/24/outline';

defineProps({
    session: { type: Object, required: true },
    showEmployee: { type: Boolean, default: true },
    showCompany: { type: Boolean, default: false },
});
</script>

<template>
    <article
        class="group flex flex-col rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm transition hover:border-talents-200 hover:shadow-md"
    >
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0 flex-1">
                <Link
                    :href="feedbackRoute('sessions.show', session.id)"
                    :title="session.title"
                    class="block text-pretty font-semibold leading-snug text-talents-900 transition group-hover:text-talents-700"
                >
                    {{ session.title }}
                </Link>
                <p v-if="showCompany && session.company?.name" class="mt-1 truncate text-xs text-slate-500">
                    {{ session.company.name }}
                </p>
            </div>
            <FeedbackStatusBadge
                class="shrink-0 self-start"
                :status="session.status"
                :label="session.status_label"
            />
        </div>

        <div class="mt-4 flex min-w-0 flex-col gap-1 text-xs text-slate-600 sm:flex-row sm:flex-wrap sm:gap-x-4 sm:gap-y-1">
            <span v-if="showEmployee && session.employee?.name" class="inline-flex min-w-0 items-center gap-1">
                <UserIcon class="h-3.5 w-3.5 shrink-0 text-talents-500" />
                <span class="truncate">{{ session.employee.name }}</span>
            </span>
            <span v-if="session.leader?.name" class="inline-flex min-w-0 items-center gap-1">
                <span class="shrink-0">Líder:</span>
                <span class="truncate">{{ session.leader.name }}</span>
            </span>
        </div>

        <div class="mt-auto flex flex-wrap gap-x-3 gap-y-1 pt-4">
            <Link :href="feedbackRoute('sessions.show', session.id)" class="text-sm font-medium text-talents-700 hover:underline">
                Ver detalhes
            </Link>
            <Link
                v-if="session.status !== 'completed' && session.status !== 'cancelled'"
                :href="feedbackRoute('sessions.edit', session.id)"
                class="text-sm font-medium text-slate-600 hover:text-talents-700 hover:underline"
            >
                Continuar
            </Link>
        </div>
    </article>
</template>
