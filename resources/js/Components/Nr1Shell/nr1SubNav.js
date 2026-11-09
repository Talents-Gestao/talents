/**
 * Sub-navegação do painel NR-1 (lógica pura, testada em tests/JavaScript/nr1SubNav.test.mjs).
 *
 * A lista vem só do servidor (`shell.menu`, montada por Nr1Shell::SECTIONS/MATRIX/allows): esta camada não decide o que
 * o perfil vê, apenas marca o item ativo. Uma seção nova entra no menu sem mexer no layout.
 */

/** Rota do painel NR-1 (qualquer /nr1/*), para o estado ativo da entrada na sidebar principal. */
export function isNr1Path(url) {
    const path = String(url ?? '').split('?')[0].split('#')[0];

    return path === '/nr1' || path.startsWith('/nr1/');
}

/** Itens na ordem do servidor, com o ativo marcado; o glifo do servidor fica como ícone de reserva. */
export function subNavItems(menu, current) {
    if (!Array.isArray(menu)) {
        return [];
    }

    return menu
        .filter((item) => item && typeof item.key === 'string' && typeof item.href === 'string')
        .map((item) => ({
            key: item.key,
            label: String(item.label ?? item.key),
            href: item.href,
            glyph: item.icon ?? '',
            active: item.key === current,
        }));
}

/** Seções que só existem nas campanhas do modelo anterior (espelha ShellController::ONLY_LEGACY). */
export const ONLY_LEGACY_SECTIONS = ['parecer'];

/** Fora do menu gerencial (espelha ShellController::HIDDEN_FROM_MENU). */
export const HIDDEN_FROM_MENU_SECTIONS = ['campanha'];

/**
 * Seção ao trocar de campanha no cabeçalho: fica na seção atual; se ela não existir para a nova campanha (fora do menu
 * do perfil, ou «Parecer e publicação» indo para uma V11), cai em «Visão geral» — nunca em «Campanha NR-1» por acaso.
 */
export function sectionAfterCampaignSwitch(current, menu, target) {
    const keys = (Array.isArray(menu) ? menu : []).map((item) => item?.key).filter(Boolean);
    const legacy = Boolean(target?.legacy);
    const fits = (key) =>
        keys.includes(key)
        && (legacy || !ONLY_LEGACY_SECTIONS.includes(key))
        && !HIDDEN_FROM_MENU_SECTIONS.includes(key);
    if (fits(current)) {
        return current;
    }
    if (fits('visao-geral')) {
        return 'visao-geral';
    }

    return keys.find(fits) ?? current;
}

/** Destino da troca de campanha: href da seção (do menu do servidor) e a query de empresa e campanha. */
export function campaignSwitchVisit(shell, context, target) {
    const section = sectionAfterCampaignSwitch(shell?.current, shell?.menu, target);
    const href = (shell?.menu ?? []).find((item) => item.key === section)?.href ?? null;
    const query = { campanha: target?.id };
    if (context?.company?.id) {
        query.empresa = context.company.id;
    }

    return { section, href, query };
}

/** Cor pastel do ícone de cada seção (acentos dos cartões do painel: lilás, verde, dourado, coral). */
export const SECTION_ACCENTS = {
    'visao-geral': 'lilac',
    campanha: 'green',
    'minha-jornada': 'coral',
    resultados: 'gold',
    'plano-de-acao': 'coral',
    documentos: 'lilac',
    parecer: 'gold',
    solucoes: 'green',
    oportunidades: 'coral',
};
const ACCENT_CYCLE = ['lilac', 'green', 'gold', 'coral'];

export function accentFor(key, index = 0) {
    return SECTION_ACCENTS[key] ?? ACCENT_CYCLE[Math.abs(index) % ACCENT_CYCLE.length];
}

/**
 * Selos da trilha, só com o que já vem no contexto do cabeçalho (nenhum cálculo novo): o estado de publicação vai em
 * Documentos (V11) ou em Parecer e publicação (modelo anterior). Ativa/Encerrada não ganha selo de seção após a
 * saída de Campanha NR-1 do menu gerencial.
 *
 * @returns {Record<string, {label: string, tone: string}>}
 */
export function subNavBadges(context, menu) {
    const badge = context?.survey?.statusBadge;
    if (!badge?.label || context?.collaborator) {
        return {};
    }
    const keys = (Array.isArray(menu) ? menu : []).map((item) => item?.key);
    const publicationLabels = ['Publicado', 'Em revisão', 'Em edição'];
    if (!publicationLabels.includes(badge.label)) {
        return {};
    }
    const target = keys.includes('parecer') ? 'parecer' : 'documentos';

    return keys.includes(target) ? { [target]: { label: badge.label, tone: badge.tone ?? 'gray' } } : {};
}
