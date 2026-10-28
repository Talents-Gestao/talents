/** Largura mínima (%) de uma faixa do funil, para manter a faixa visível em contagens pequenas. */
export const FUNNEL_MIN_WIDTH = 18;

export const FUNNEL_OVER_PREVIOUS_HINT =
    'Mais itens que na etapa anterior: propostas podem vir de leads de outros períodos ou sem lead.';

/**
 * Percentual de `count` sobre `base` no formato pt-BR.
 * Uma casa decimal abaixo de 10%, inteiro a partir de 10%; nunca "0%" quando há itens ("<0,1%");
 * sem base (0 ou ausente): "—".
 */
export function formatFunnelPercent(count, base) {
    const n = Number(count) || 0;
    const b = Number(base) || 0;

    if (b <= 0) {
        return '—';
    }
    if (n <= 0) {
        return '0%';
    }

    const ratio = (100 * n) / b;
    if (ratio < 0.1) {
        return '<0,1%';
    }

    const oneDecimal = Math.round(ratio * 10) / 10;
    if (oneDecimal < 10) {
        return `${oneDecimal.toFixed(1).replace('.', ',')}%`;
    }

    return `${Math.round(ratio)}%`;
}

/**
 * Métricas por etapa: contagens independentes (sem coorte).
 * - pctOfLeads: contagem ÷ leads (leitura principal, sem cadeia);
 * - pctOfPrevious: contagem ÷ etapa anterior (secundária); a 1ª etapa não tem anterior;
 * - overPrevious: etapa com mais itens que a anterior (dado de períodos/origens diferentes);
 * - topWidth/bottomWidth: largura proporcional à contagem, com mínimo e continuidade entre faixas.
 *
 * @param {Array<{key: string, label?: string, count: number, href?: string}>} stages
 */
export function buildFunnelRows(stages) {
    const list = Array.isArray(stages) ? stages : [];
    const counts = list.map((row) => Math.max(0, Number(row.count) || 0));
    const leads = counts[0] ?? 0;
    const widthBase = Math.max(0, ...counts);

    const widths = counts.map((count) =>
        widthBase > 0
            ? Math.max(FUNNEL_MIN_WIDTH, Math.min(100, (100 * count) / widthBase))
            : FUNNEL_MIN_WIDTH,
    );

    return list.map((row, index) => {
        const count = counts[index];
        const previous = index > 0 ? counts[index - 1] : null;

        return {
            key: row.key,
            label: row.label ?? row.key,
            href: row.href || null,
            count,
            pctOfLeads: formatFunnelPercent(count, leads),
            pctOfPrevious: previous === null ? null : formatFunnelPercent(count, previous),
            overPrevious: previous !== null && previous > 0 && count > previous,
            topWidth: widths[index],
            bottomWidth: widths[Math.min(index + 1, widths.length - 1)],
        };
    });
}
