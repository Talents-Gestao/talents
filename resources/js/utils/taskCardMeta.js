export function descriptionPresent(card) {
    const d = card?.description;
    return typeof d === 'string' ? d.trim().length > 0 : Boolean(d);
}

export function checklistTotals(card) {
    if (!card?.checklists?.length) return null;
    let total = 0;
    let done = 0;
    card.checklists.forEach((cl) => {
        cl.items?.forEach((it) => {
            total += 1;
            if (it.is_completed) done += 1;
        });
    });
    if (total === 0) return null;
    return { done, total, complete: done === total };
}

export function commentsCount(card) {
    if (Array.isArray(card?.comments)) return card.comments.length;
    return Number(card?.comments_count) || 0;
}

export function attachmentsCount(card) {
    if (Array.isArray(card?.attachments)) return card.attachments.length;
    return Number(card?.attachments_count) || 0;
}

export function dueLabel(date) {
    if (!date) return '';
    try {
        const [y, m, d] = String(date).split('-').map(Number);
        const dt = new Date(y, (m || 1) - 1, d || 1);
        return dt.toLocaleDateString('pt-BR', { day: 'numeric', month: 'short' });
    } catch (_e) {
        return date;
    }
}

export function dueClass(card) {
    if (card?.completed_at) return 'bg-emerald-100 text-emerald-800';
    if (!card?.due_date) return 'bg-slate-100 text-slate-600';
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const [y, m, d] = String(card.due_date).split('-').map(Number);
    const due = new Date(y, (m || 1) - 1, d || 1);
    const diff = (due - today) / 86_400_000;
    if (diff < 0) return 'bg-rose-100 text-rose-800';
    if (diff <= 2) return 'bg-amber-100 text-amber-800';
    return 'bg-slate-100 text-slate-600';
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
