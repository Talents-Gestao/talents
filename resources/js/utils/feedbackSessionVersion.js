/**
 * Textos puros de versão do modelo no feedback e de «Reabrir para edição» (sem Vue, para teste).
 */

const plural = (n, one, many) => (n === 1 ? `1 ${one}` : `${n} ${many}`);

/**
 * Resumo da prévia de «Atualizar para a versão X».
 *
 * @param {{ to: { number: number }, kept: Array, added: Array, removed: Array, answers_moved: number, answers_out_of_version: number } | null} preview
 * @returns {{ title: string, lines: string[], warning: string | null } | null}
 */
export function versionUpdateSummary(preview) {
    if (!preview) return null;
    const kept = preview.kept?.length ?? 0;
    const added = preview.added?.length ?? 0;
    const removed = preview.removed?.length ?? 0;
    const moved = Number(preview.answers_moved) || 0;
    const outside = Number(preview.answers_out_of_version) || 0;

    return {
        title: `Atualizar para a versão ${preview.to.number}`,
        lines: [
            `${plural(kept, 'pergunta mantida', 'perguntas mantidas')} (${plural(moved, 'resposta preservada', 'respostas preservadas')})`,
            `${plural(added, 'pergunta nova', 'perguntas novas')}`,
            `${plural(removed, 'pergunta sai da versão', 'perguntas saem da versão')}`,
        ],
        warning: outside
            ? `${plural(outside, 'resposta fica', 'respostas ficam')} fora da versão atual: continua${outside === 1 ? '' : 'm'} guardada${outside === 1 ? '' : 's'} e visíve${outside === 1 ? 'l' : 'is'} no feedback, no PDF e no relatório, mas não aparece${outside === 1 ? '' : 'm'} mais no preenchimento.`
            : null,
    };
}

/**
 * Aviso de «Reabrir para edição».
 *
 * @param {{ invites: number, signed: number } | null} impact
 */
export function reopenCopy(impact) {
    const invites = Number(impact?.invites) || 0;
    const signed = Number(impact?.signed) || 0;
    const discarded = signed === 0
        ? 'Nenhuma assinatura foi coletada ainda.'
        : `${plural(signed, 'assinatura já coletada será descartada', 'assinaturas já coletadas serão descartadas')}.`;

    return {
        message:
            `O feedback volta para «Em preenchimento» e ${plural(invites, 'convite de assinatura é cancelado', 'convites de assinatura são cancelados')}. `
            + `${discarded} Os links enviados deixam de funcionar; depois de editar, envie para assinatura de novo.`,
        checkLabel: signed === 0
            ? 'Entendo que os convites serão cancelados.'
            : `Entendo que os convites serão cancelados e ${plural(signed, 'assinatura será descartada', 'assinaturas serão descartadas')}.`,
    };
}
