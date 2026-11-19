/**
 * Leitura do relatório de IA do feedback (só apresentação, sobre o JSON que o servidor já valida):
 * - pontos fortes e de atenção sem o rótulo genérico repetido antes de cada citação;
 * - compromissos com a citação só quando ela difere da frase;
 * - trechos-chave sem repetir citações já exibidas;
 * - responsável pelo nome, quando a sessão traz o nome.
 */

/** Texto comparável: minúsculas, sem pontuação e espaços extras. */
export function comparable(text) {
    return String(text ?? '')
        .toLowerCase()
        .normalize('NFC')
        .replace(/[^\p{L}\p{N}]+/gu, ' ')
        .trim();
}

const same = (a, b) => comparable(a) !== '' && comparable(a) === comparable(b);

/**
 * O texto de um ponto é rótulo genérico quando se repete igual em mais de um item da lista, ou quando só repete a
 * citação. Nesses casos, mostra-se só a citação (ou o texto, se não houver citação).
 *
 * @returns {Array<{ texto: string|null, trecho: string|null }>}
 */
export function pointsView(points = []) {
    const counts = new Map();
    for (const p of points) {
        const key = comparable(p.texto);
        counts.set(key, (counts.get(key) ?? 0) + 1);
    }

    return points.map((p) => {
        const generic = (counts.get(comparable(p.texto)) ?? 0) > 1 || same(p.texto, p.trecho);
        const texto = p.texto && !(generic && p.trecho) ? p.texto : null;

        return { texto, trecho: p.trecho || null };
    });
}

/** Nome do responsável (líder, colaborador ou os dois); sem nome, o rótulo do papel. */
export function ownerLabel(commitment, people = {}) {
    const leader = people.leader?.trim();
    const employee = people.employee?.trim();
    switch (commitment.responsavel) {
        case 'lider':
            return leader || commitment.responsavel_label;
        case 'colaborador':
            return employee || commitment.responsavel_label;
        case 'ambos':
            return leader && employee ? `${leader} e ${employee}` : commitment.responsavel_label;
        default:
            return commitment.responsavel_label;
    }
}

/** @returns {Array<{ id: string, descricao: string, trecho: string|null, owner: string, prazo: string|null }>} */
export function commitmentsView(commitments = [], people = {}) {
    return commitments.map((c) => ({
        id: c.id,
        descricao: c.descricao,
        trecho: c.trecho && !same(c.trecho, c.descricao) ? c.trecho : null,
        owner: ownerLabel(c, people),
        prazo: c.prazo || null,
    }));
}

/** Trechos-chave que ainda não aparecem como citação nos pontos ou nos compromissos (nem repetidos entre si). */
export function keyQuotesView(report) {
    const shown = new Set(
        [
            ...(report?.pontos_fortes ?? []).map((p) => p.trecho),
            ...(report?.pontos_atencao ?? []).map((p) => p.trecho),
            ...(report?.compromissos ?? []).flatMap((c) => [c.trecho, c.descricao]),
        ]
            .map(comparable)
            .filter(Boolean),
    );

    const result = [];
    for (const t of report?.trechos_chave ?? []) {
        const key = comparable(t.texto);
        if (!key || shown.has(key)) continue;
        shown.add(key);
        result.push(t);
    }

    return result;
}
