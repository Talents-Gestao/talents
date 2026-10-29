/**
 * Textos dos quatro cartões da Visão geral (lógica pura, testada em tests/JavaScript/nr1OverviewCards.test.mjs).
 *
 * - Participação: os elegíveis vêm do cadastro técnico; sem cadastro, diz isso (nunca um número inventado).
 * - Dimensões em atenção: «Todas aparecem nas prioridades» só quando é verdade (a lista mostra todas).
 * - Plano de ação: sem medidas, estado honesto no lugar de «0 / 0».
 */
export function overviewCardsText(cards, prioritiesCount = null) {
    const participation = cards?.participation ?? {};
    const attention = cards?.attention ?? {};
    const plan = cards?.plan ?? null;

    const count = attention.count ?? 0;
    let attentionText = null;
    if (count === 0) attentionText = 'Nenhuma dimensão em atenção';
    else if (prioritiesCount !== null && prioritiesCount === count) attentionText = 'Todas aparecem nas prioridades';

    const hasPlan = plan !== null && (plan.total ?? 0) > 0;

    return {
        eligible: participation.eligible ? `${participation.eligible} elegíveis` : 'elegíveis não informados',
        attention: attentionText,
        plan: hasPlan ? { progress: Math.round((plan.confirmed / plan.total) * 100) } : null,
        planEmpty: cards?.planMessage ?? 'Plano de ação ainda não elaborado.',
    };
}
