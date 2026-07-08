const FEEDBACK_SECTION_ICONS = {
    objetivo: '🎯',
    inicio: '💬',
    termometro: '🚦',
    profiler: '🧩',
    conquistas: '🌟',
    dev_lider_colab: '👥',
    dev_colab_lider: '🗣️',
    dev_lider_self: '🎯',
    acoes: '📝',
    percepcoes: '📊',
    encerramento: '✅',
};

export function feedbackSectionIcon(sectionKey) {
    return FEEDBACK_SECTION_ICONS[sectionKey] ?? '';
}
