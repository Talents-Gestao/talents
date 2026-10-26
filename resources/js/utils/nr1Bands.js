const BADGE_CLASSES = {
    green: 'bg-emerald-50 text-emerald-800 border-emerald-200',
    yellow: 'bg-amber-50 text-amber-800 border-amber-200',
    red: 'bg-red-50 text-red-800 border-red-200',
};

const BAR_CLASSES = {
    green: 'bg-emerald-500',
    yellow: 'bg-amber-500',
    red: 'bg-red-500',
};

export const bandBadgeClass = (riskLevel) => BADGE_CLASSES[riskLevel] ?? 'bg-gray-50 text-gray-700 border-gray-200';

export const bandBarClass = (riskLevel) => BAR_CLASSES[riskLevel] ?? 'bg-gray-400';

/** Índice gravado ("2.5974") → texto de exibição com duas casas e vírgula ("2,60"). */
export const scoreText = (score) => {
    if (score === null || score === undefined || score === '') {
        return '—';
    }
    return (Math.round(Number(score) * 100) / 100).toFixed(2).replace('.', ',');
};
