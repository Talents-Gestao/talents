/**
 * Detalhe do feedback (só apresentação): progresso das respostas, ação principal por estado, jornada e aba pela URL.
 * Lê o que o servidor já manda (session, recording, recordingAi); não decide permissão nem regra de envio.
 */

export const SESSION_TABS = [
    { key: 'respostas', label: 'Respostas' },
    { key: 'gravacao', label: 'Gravação e relatório' },
    { key: 'assinaturas', label: 'Assinaturas' },
];

const TAB_KEYS = SESSION_TABS.map((t) => t.key);

/** Aba ativa a partir da URL (?aba=); sem o parâmetro ou com valor desconhecido, «Respostas». */
export function tabFromUrl(url) {
    const query = String(url ?? '').split('#')[0].split('?')[1] ?? '';
    const value = new URLSearchParams(query).get('aba');

    return TAB_KEYS.includes(value) ? value : 'respostas';
}

/** Mesma URL com a aba (?aba=); «Respostas» é o padrão e não vai para a URL. */
export function urlWithTab(url, tab) {
    const [base, hash] = String(url ?? '').split('#');
    const [path, query = ''] = base.split('?');
    const params = new URLSearchParams(query);
    if (tab && tab !== 'respostas') params.set('aba', tab);
    else params.delete('aba');
    const qs = params.toString();

    return `${path}${qs ? `?${qs}` : ''}${hash ? `#${hash}` : ''}`;
}

/** Resposta preenchida (mesmo critério do contador de antes). */
export function isAnswered(val) {
    if (val == null || val === '') return false;
    if (Array.isArray(val)) return val.some(Boolean);
    if (typeof val === 'object') return Object.values(val).some((v) => v != null && v !== '');

    return true;
}

export const isIntro = (section) => section?.section_type === 'intro';

/**
 * Estado de uma seção respondível: { answered, total, status: 'not_started' | 'in_progress' | 'done', label }.
 * Conta só as perguntas do modelo; a pergunta extra (opcional) não entra no total.
 */
export function sectionProgress(section, answers = {}) {
    const questions = section?.questions ?? [];
    const total = questions.length;
    const answered = questions.filter((q) => isAnswered(answers?.[q.id])).length;
    const status = total > 0 && answered >= total ? 'done' : answered > 0 ? 'in_progress' : 'not_started';
    const label = status === 'done' ? 'Concluída' : status === 'in_progress' ? `Em andamento ${answered}/${total}` : 'Não iniciada';

    return { answered, total, status, label };
}

/** Separa as seções: introdução antes da primeira respondível («Sobre este modelo»), respondíveis e notas finais. */
export function splitSections(sections = []) {
    const first = sections.findIndex((s) => !isIntro(s));
    if (first === -1) return { about: sections, body: [], closing: [] };
    let last = sections.length - 1;
    while (last > first && isIntro(sections[last])) last -= 1;

    return { about: sections.slice(0, first), body: sections.slice(first, last + 1), closing: sections.slice(last + 1) };
}

/** Progresso geral: soma das perguntas respondidas das seções respondíveis. */
export function overallProgress(sections = [], answers = {}) {
    return sections
        .filter((s) => !isIntro(s))
        .reduce(
            (acc, s) => {
                const p = sectionProgress(s, answers);
                return { answered: acc.answered + p.answered, total: acc.total + p.total };
            },
            { answered: 0, total: 0 },
        );
}

/** Primeira seção respondível ainda não concluída (null se todas concluídas). */
export function firstIncompleteSectionId(sections = [], answers = {}) {
    const section = sections.find((s) => !isIntro(s) && (s.questions?.length ?? 0) > 0 && sectionProgress(s, answers).status !== 'done');

    return section?.id ?? null;
}

const signaturesRequested = (session) => session?.status === 'awaiting_signatures' || (session?.signatures?.length ?? 0) > 0;

/**
 * Ação principal do cabeçalho (uma por estado):
 * - concluído ou com assinatura solicitada: «Ver assinaturas»;
 * - em preenchimento com perguntas sem resposta: «Continuar preenchimento»;
 * - em preenchimento com tudo respondido: «Enviar para assinatura» (a regra de envio não muda: a aba
 *   «Assinaturas» continua permitindo o envio a qualquer momento, como hoje);
 * - cancelado: nenhuma.
 * @returns {{ key: 'continue' | 'send' | 'signatures', label: string } | null}
 */
export function primaryAction(session, progress) {
    if (!session || session.status === 'cancelled') return null;
    if (session.status === 'completed' || signaturesRequested(session)) return { key: 'signatures', label: 'Ver assinaturas' };
    if (progress && progress.total > 0 && progress.answered < progress.total) return { key: 'continue', label: 'Continuar preenchimento' };

    return { key: 'send', label: 'Enviar para assinatura' };
}

/**
 * Jornada «Preenchimento X/Y › Assinaturas › Concluído».
 * @returns {{ steps: Array<{ key: string, label: string, detail: string, status: 'done' | 'current' | 'todo' }>, current: number, compact: string }}
 */
export function journey(session, progress) {
    const signed = (session?.signatures ?? []).filter((s) => s.signed_at).length;
    const invited = session?.signatures?.length ?? 0;
    const current = session?.status === 'completed' ? 2 : signaturesRequested(session) ? 1 : 0;
    const fill = progress?.total ? `${progress.answered}/${progress.total}` : '';
    const sign = invited ? `${signed} de ${invited} assinadas` : '';
    const steps = [
        { key: 'fill', label: 'Preenchimento', detail: fill },
        { key: 'sign', label: 'Assinaturas', detail: current >= 1 ? sign : '' },
        { key: 'done', label: 'Concluído', detail: '' },
    ].map((s, i) => ({ ...s, status: session?.status === 'completed' || i < current ? 'done' : i === current ? 'current' : 'todo' }));
    const now = steps[current];
    const compact = `${now.label}${now.detail ? ` ${now.detail}` : ''} · ${current + 1} de ${steps.length}`;

    return { steps, current, compact };
}

/**
 * Conectores da jornada (um entre cada par de etapas), só para apresentação.
 * - etapa anterior concluída: cheio (1) e tom «done»;
 * - etapa anterior é a atual e há percentual: preenchido na proporção (0–1) e tom «current»;
 * - senão: vazio (0) e tom «todo».
 * @param {Array<{ status: 'done' | 'current' | 'todo' }>} steps journey().steps
 * @param {number | null} currentPercent percentual (0–100) da etapa atual, ou null
 * @returns {Array<{ fill: number, tone: 'done' | 'current' | 'todo' }>}
 */
export function journeyConnectors(steps = [], currentPercent = null) {
    return steps.slice(0, -1).map((step) => {
        if (step.status === 'done') return { fill: 1, tone: 'done' };
        if (step.status === 'current' && Number.isFinite(currentPercent)) {
            return { fill: Math.min(1, Math.max(0, currentPercent / 100)), tone: 'current' };
        }
        return { fill: 0, tone: 'todo' };
    });
}
