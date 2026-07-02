import { daysFromToday, formatDateNumeric, formatRelativeDate } from '@/utils/dateOnly';

export function descriptionPresent(card) {
    const d = card?.description;
    return typeof d === 'string' ? d.trim().length > 0 : Boolean(d);
}

export function checklistTotals(card) {
    if (!card) return null;

    const explicitTotal = Number(card.checklist_total);
    const explicitDone = Number(card.checklist_done) || 0;
    const hasExplicit = Number.isFinite(explicitTotal);

    const checklists = Array.isArray(card.checklists) ? card.checklists : [];
    const hasAnyChecklist = hasExplicit
        ? explicitTotal > 0 || checklists.length > 0
        : checklists.length > 0;

    if (!hasAnyChecklist) return null;

    let total = hasExplicit ? explicitTotal : 0;
    let done = hasExplicit ? explicitDone : 0;

    if (!hasExplicit) {
        checklists.forEach((cl) => {
            cl.items?.forEach((it) => {
                total += 1;
                if (it.is_completed) done += 1;
            });
        });
    }

    return {
        done,
        total,
        complete: total > 0 && done === total,
        empty: total === 0,
    };
}

export function commentsCount(card) {
    if (Array.isArray(card?.comments)) return card.comments.length;
    return Number(card?.comments_count) || 0;
}

export function attachmentsCount(card) {
    if (Array.isArray(card?.attachments)) return card.attachments.length;
    return Number(card?.attachments_count) || 0;
}

/** Dias até o vencimento (negativo = atrasado), ancorado em São Paulo. */
export function dueDaysDiff(dateStr) {
    return daysFromToday(dateStr);
}

/** overdue = atrasado; soon = vence em até 2 dias; ok = data futura. */
export function dueUrgency(dateStr, isCompleted = false) {
    if (!dateStr || isCompleted) return null;
    const diff = dueDaysDiff(dateStr);
    if (diff === null) return null;
    if (diff < 0) return 'overdue';
    if (diff <= 2) return 'soon';
    return 'ok';
}

export function pendingChecklistDueDates(card) {
    const dates = [];
    card?.checklists?.forEach((cl) => {
        cl.items?.forEach((it) => {
            if (!it.is_completed && it.due_date) {
                dates.push({ date: it.due_date, text: it.text });
            }
        });
    });
    return dates;
}

/**
 * Alerta de vencimento unificado: tarefa + etapas do checklist pendentes.
 */
export function cardDueAlert(card) {
    if (card?.completed_at) {
        return {
            show: true,
            urgency: 'completed',
            date: card.due_date,
            label: 'Concluído',
            title: 'Tarefa concluída',
            fromChecklist: false,
        };
    }

    const candidates = [];

    if (card?.due_date) {
        candidates.push({
            date: card.due_date,
            urgency: dueUrgency(card.due_date),
            source: 'card',
            title: 'Vencimento da tarefa',
        });
    }

    pendingChecklistDueDates(card).forEach((item) => {
        candidates.push({
            date: item.date,
            urgency: dueUrgency(item.date),
            source: 'checklist',
            title: item.text ? `Etapa: ${item.text}` : 'Vencimento de etapa do checklist',
        });
    });

    const active = candidates.filter((c) => c.urgency);
    if (!active.length) {
        return { show: false };
    }

    const worstRank = { overdue: 3, soon: 2, ok: 1 };
    const worst = active.reduce((a, b) =>
        (worstRank[b.urgency] ?? 0) > (worstRank[a.urgency] ?? 0) ? b : a,
    );

    const nearest = active.reduce((best, cur) => {
        const diff = dueDaysDiff(cur.date);
        const bestDiff = dueDaysDiff(best.date);
        if (diff === null) return best;
        if (bestDiff === null) return cur;
        return diff < bestDiff ? cur : best;
    });

    const display = ['overdue', 'soon'].includes(worst.urgency) ? worst : nearest;

    return {
        show: true,
        urgency: worst.urgency,
        date: display.date,
        label: dueLabel(display.date),
        title: display.title,
        absoluteDate: formatDateNumeric(display.date),
        fromChecklist: display.source === 'checklist',
        hasChecklistDue: active.some((c) => c.source === 'checklist'),
    };
}

export function dueLabel(date) {
    if (!date) return '';
    return formatRelativeDate(date);
}

export function dueAlertClass(alert) {
    if (!alert?.show) return 'bg-slate-100 text-slate-600';
    if (alert.urgency === 'completed') return 'bg-emerald-100 text-emerald-800';
    if (alert.urgency === 'overdue') return 'bg-rose-100 text-rose-800';
    if (alert.urgency === 'soon') return 'bg-amber-100 text-amber-800';
    return 'bg-slate-100 text-slate-600';
}

export function calendarIconClass(alert) {
    if (!alert?.show) return 'text-slate-500';
    if (alert.urgency === 'completed') return 'text-emerald-700';
    if (alert.urgency === 'overdue') return 'text-rose-700';
    if (alert.urgency === 'soon') return 'text-amber-600';
    return 'text-slate-500';
}

/** @deprecated use dueAlertClass(cardDueAlert(card)) */
export function dueClass(card) {
    return dueAlertClass(cardDueAlert(card));
}

export function avatarInitials(name) {
    if (!name) return '?';
    const parts = String(name).trim().split(/\s+/);
    const first = parts[0]?.[0] ?? '';
    const last = parts.length > 1 ? parts[parts.length - 1][0] : '';
    return (first + last).toUpperCase().slice(0, 2);
}

const palette = [
    'bg-amber-500',
    'bg-rose-500',
    'bg-fuchsia-500',
    'bg-violet-500',
    'bg-indigo-500',
    'bg-sky-500',
    'bg-emerald-500',
    'bg-teal-500',
    'bg-orange-500',
];

export function avatarColor(seed) {
    if (seed === undefined || seed === null) return palette[0];
    const n = Number(seed);
    if (Number.isFinite(n)) return palette[Math.abs(Math.trunc(n)) % palette.length];
    let hash = 0;
    for (const ch of String(seed)) hash = (hash * 31 + ch.charCodeAt(0)) | 0;
    return palette[Math.abs(hash) % palette.length];
}
