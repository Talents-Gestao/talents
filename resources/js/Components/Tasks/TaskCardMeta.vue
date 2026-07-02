<script setup>
import {
    attachmentsCount,
    avatarColor,
    avatarInitials,
    calendarIconClass,
    cardDueAlert,
    checklistTotals,
    commentsCount,
    descriptionPresent,
    dueAlertClass,
} from '@/utils/taskCardMeta';
import {
    CalendarDaysIcon,
    ChatBubbleOvalLeftEllipsisIcon,
    CheckCircleIcon,
    ClipboardDocumentListIcon,
    DocumentTextIcon,
    ArrowPathIcon,
    PaperClipIcon,
} from '@heroicons/vue/24/outline';
import { computed } from 'vue';

const props = defineProps({
    card: { type: Object, required: true },
});

const checklist = computed(() => checklistTotals(props.card));
const comments = computed(() => commentsCount(props.card));
const attachments = computed(() => attachmentsCount(props.card));
const dueAlert = computed(() => cardDueAlert(props.card));

const checklistTitle = computed(() => {
    if (!checklist.value) return '';
    if (checklist.value.empty) return 'Checklist sem etapas';
    const base = `Checklist ${checklist.value.done}/${checklist.value.total}`;
    if (dueAlert.value?.hasChecklistDue && dueAlert.value.urgency === 'soon') {
        return `${base} · etapa vence em breve`;
    }
    if (dueAlert.value?.hasChecklistDue && dueAlert.value.urgency === 'overdue') {
        return `${base} · etapa atrasada`;
    }
    return base;
});
</script>

<template>
    <div class="mt-2 flex items-end justify-between gap-2">
        <div class="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1 text-[11px] text-slate-500">
            <span
                v-if="card.recurrence_label"
                class="inline-flex items-center gap-0.5 rounded bg-violet-50 px-1.5 py-0.5 font-medium text-violet-700"
                :title="`Repete ${card.recurrence_label.toLowerCase()}`"
            >
                <ArrowPathIcon class="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
                {{ card.recurrence_label }}
            </span>

            <span
                v-if="dueAlert.show"
                class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 font-medium"
                :class="dueAlertClass(dueAlert)"
                :title="dueAlert.absoluteDate ? `${dueAlert.title} · ${dueAlert.absoluteDate}` : dueAlert.title"
            >
                <CheckCircleIcon
                    v-if="dueAlert.urgency === 'completed'"
                    class="h-3.5 w-3.5 shrink-0"
                    aria-hidden="true"
                />
                <CalendarDaysIcon
                    v-else
                    class="h-3.5 w-3.5 shrink-0"
                    :class="calendarIconClass(dueAlert)"
                    aria-hidden="true"
                />
                {{ dueAlert.label }}
            </span>

            <span
                v-if="descriptionPresent(card)"
                class="inline-flex items-center gap-0.5"
                title="Descrição / observações"
            >
                <DocumentTextIcon class="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
            </span>

            <span
                v-if="checklist"
                class="inline-flex items-center gap-0.5 font-medium"
                :class="
                    checklist.empty
                        ? 'text-slate-400'
                        : dueAlert.hasChecklistDue && dueAlert.urgency === 'overdue'
                          ? 'text-rose-700'
                          : dueAlert.hasChecklistDue && dueAlert.urgency === 'soon'
                            ? 'text-amber-700'
                            : checklist.complete
                              ? 'text-emerald-700'
                              : 'text-slate-500'
                "
                :title="checklistTitle"
            >
                <ClipboardDocumentListIcon class="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
                <template v-if="!checklist.empty">{{ checklist.done }}/{{ checklist.total }}</template>
                <CalendarDaysIcon
                    v-if="!checklist.empty && dueAlert.hasChecklistDue && dueAlert.urgency !== 'completed'"
                    class="h-3 w-3 shrink-0"
                    :class="calendarIconClass(dueAlert)"
                    aria-hidden="true"
                />
            </span>

            <span v-if="comments" class="inline-flex items-center gap-0.5" :title="`${comments} comentário(s)`">
                <ChatBubbleOvalLeftEllipsisIcon class="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
                {{ comments }}
            </span>

            <span v-if="attachments" class="inline-flex items-center gap-0.5" :title="`${attachments} anexo(s)`">
                <PaperClipIcon class="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
                {{ attachments }}
            </span>
        </div>

        <div v-if="card.members?.length" class="flex shrink-0 -space-x-1.5">
            <span
                v-for="m in card.members.slice(0, 3)"
                :key="m.id"
                :title="m.name"
                class="inline-flex h-6 w-6 items-center justify-center rounded-full text-[10px] font-semibold text-white ring-2 ring-white"
                :class="avatarColor(m.id)"
            >
                {{ avatarInitials(m.name) }}
            </span>
            <span
                v-if="card.members.length > 3"
                class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-slate-300 text-[10px] font-semibold text-slate-700 ring-2 ring-white"
                :title="`+${card.members.length - 3} membros`"
            >
                +{{ card.members.length - 3 }}
            </span>
        </div>
    </div>
</template>
