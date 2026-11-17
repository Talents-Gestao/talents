<script setup>
import FeedbackStatusBadge from '@/Components/Feedback/FeedbackStatusBadge.vue';
import { feedbackRoute } from '@/composables/useFeedbackRoutes';
import { formatDateTime } from '@/utils/dateOnly';
import { Link } from '@inertiajs/vue3';
import { CalendarDaysIcon, TrashIcon, UserCircleIcon, UserIcon } from '@heroicons/vue/24/outline';

defineProps({
    session: { type: Object, required: true },
    showEmployee: { type: Boolean, default: true },
    showCompany: { type: Boolean, default: false },
    canDelete: { type: Boolean, default: false },
});

defineEmits(['delete']);
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
                    class="block rounded text-pretty font-semibold leading-snug text-talents-900 transition group-hover:text-talents-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500/50"
                >
                    {{ session.title }}
                </Link>
                <p v-if="showCompany && session.company?.name" class="mt-1 truncate text-xs text-slate-500">
                    {{ session.company.name }}
                </p>
                <p v-if="session.template?.title" class="mt-1 truncate text-xs text-slate-500">
                    Modelo: {{ session.template.title }}
                </p>
            </div>
            <FeedbackStatusBadge
                class="shrink-0 self-start"
                :status="session.status"
                :label="session.status_label"
            />
        </div>

        <dl class="mb-4 mt-4 grid min-w-0 gap-1.5 text-sm text-slate-700">
            <div v-if="showEmployee && session.employee?.name" class="flex min-w-0 items-center gap-2">
                <dt class="shrink-0"><UserIcon class="h-4 w-4 text-talents-500" aria-hidden="true" /><span class="sr-only">Colaborador</span></dt>
                <dd class="truncate font-medium text-slate-800">{{ session.employee.name }}</dd>
            </div>
            <div v-if="session.leader?.name" class="flex min-w-0 items-center gap-2">
                <dt class="shrink-0"><UserCircleIcon class="h-4 w-4 text-slate-400" aria-hidden="true" /><span class="sr-only">Líder</span></dt>
                <dd class="truncate">Líder: {{ session.leader.name }}</dd>
            </div>
            <div v-if="session.scheduled_at" class="flex min-w-0 items-center gap-2">
                <dt class="shrink-0"><CalendarDaysIcon class="h-4 w-4 text-slate-400" aria-hidden="true" /><span class="sr-only">Data do alinhamento</span></dt>
                <dd class="truncate">{{ formatDateTime(session.scheduled_at) }}</dd>
            </div>
        </dl>

        <div class="mt-auto flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-slate-100 pt-4">
            <Link
                :href="feedbackRoute('sessions.show', session.id)"
                class="rounded text-sm font-semibold text-talents-700 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500/50"
            >
                Ver detalhes
            </Link>
            <Link
                v-if="session.status !== 'completed' && session.status !== 'cancelled'"
                :href="feedbackRoute('sessions.edit', session.id)"
                class="rounded text-sm font-medium text-slate-600 hover:text-talents-700 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500/50"
            >
                Continuar
            </Link>
            <button
                v-if="canDelete"
                type="button"
                class="ml-auto inline-flex min-h-[32px] items-center gap-1 rounded-lg px-2 text-sm font-medium text-slate-500 transition hover:bg-rose-50 hover:text-rose-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-400/60"
                @click="$emit('delete')"
            >
                <TrashIcon class="h-4 w-4" aria-hidden="true" />
                Excluir
            </button>
        </div>
    </article>
</template>
